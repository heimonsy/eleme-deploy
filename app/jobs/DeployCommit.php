<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-26
 * Time: 下午8:26
 */

use Symfony\Component\Process\Process;

class DeployCommit
{
    public function fire($job, $message)
    {
        $id = $message['id'];
        $commit = $message['commit'];
        $hostType = $message['hostType'];
        $siteId = $message['siteId'];

        $dc = new DC($siteId);
        $df = new DeployInfo($siteId);
        $deploy = $df->get($id);

        $remoteUser = $dc->get(DC::REMOTE_USER);
        $remoteOwner = $dc->get(DC::REMOTE_OWNER);
        $staticDir = $dc->get(DC::STATIC_DIR);
        $root = $dc->get(DC::ROOT) . '/commit';
        $commitPath = "{$root}/{$commit}";

        $RSYNC_EXCLUDE = "{$commitPath}/" . $dc->get(DC::RSYNC_EXCLUDE);
        $REMOTE_STATIC_DIR = $dc->get(DC::REMOTE_STATIC_DIR);
        $LOCAL_STATIC_DIR = "{$commitPath}/{$staticDir}";

        $REMOTE_DIR = $dc->get(DC::REMOTE_APP_DIR);
        $LOCAL_DIR = $commitPath;

        $staticScript = ScriptCommand::complie($dc->get(DC::DEPLOY_STATIC_SCRIPT), $siteId);
        $webScript    = ScriptCommand::complie($dc->get(DC::DEPLOY_WEB_SCRIPT), $siteId);

        $deploy['result'] = 'Deploying';
        $deploy['last_time'] = date('Y-m-d H:i:s');
        $df->save($deploy);

        Log::info("job id : {$job->getJobId()} start \n---------------------------");
        Log::info("commit deploy: {$id}, {$commit}");

        $hosts = (new SiteHost($siteId, $hostType, SiteHost::STATIC_HOST))->getList();
        $staticHosts = new SplQueue();
        foreach ($hosts as $h) {
            $staticHosts->push($h);
        }
        $hosts    = (new SiteHost($siteId, $hostType, SiteHost::WEB_HOST))->getList();
        $webHosts = new SplQueue();
        foreach ($hosts as $h) {
            $webHosts->push($h);
        }

        //本地同步锁，不能在同一个commit下同步
        $redis = app('redis')->connection();
        $commitLock = new \Eleme\Rlock\Lock($redis, JobLock::buildLock($commitPath));
        $commitLock->acquire();

        /*****************************************
         *
         *  执行静态文件同步
         *
         *****************************************/
        //执行同步前本地命令

        $this->processCommands($staticScript['before']['handle']);
        while (!$staticHosts->isEmpty()) {
            $host = $staticHosts->shift();
            $rsyLock = new \Eleme\Rlock\Lock($redis, JobLock::rsyLock($host['hostip']), array('blocking' => false));
            if ($rsyLock->acquire()) {
                $HOST_NAME = $host['hostname'];
                //执行同步前每次都执行的本地命令
                $this->processCommands($staticScript['before']['local']);
                //执行同步前每次都执行的远端命令
                $this->processCommands($staticScript['before']['remote'], $HOST_NAME);

                Log::info("deploying static files to {$HOST_NAME}.");
                (new Process($this->remoteProcess($HOST_NAME, "sudo mkdir -p {$REMOTE_STATIC_DIR}")))->mustRun();
                (new Process($this->remoteProcess($HOST_NAME, "sudo chown {$remoteUser} -R {$REMOTE_STATIC_DIR}")))->mustRun();
                (new Process("rsync -az --progress --force --delay-updates --exclude-from={$RSYNC_EXCLUDE} {$LOCAL_STATIC_DIR}/ {$HOST_NAME}:{$REMOTE_STATIC_DIR}/", $commitPath))->setTimeout(600)->mustRun();
                (new Process($this->remoteProcess($HOST_NAME, "sudo chown {$remoteOwner} -R {$REMOTE_STATIC_DIR}")))->mustRun();

                //执行同步后每次都执行的本地命令
                $this->processCommands($staticScript['after']['local']);
                //执行同步后每次都执行的远端命令
                $this->processCommands($staticScript['after']['remote'], $HOST_NAME);

                $rsyLock->release();
            } else {
                // 正在同步，重新放回队列
                $staticHosts->push($host);
            }
        }
        //执行同步后本地命令
        $this->processCommands($staticScript['after']['handle']);


        /*****************************************
         *
         *  执行WEB应用同步
         *
         *****************************************/
        //执行同步前本地命令
        $this->processCommands($webScript['before']['handle']);
        while (!$webHosts->isEmpty()) {
            $host = $webHosts->shift();
            $rsyLock = new \Eleme\Rlock\Lock($redis, JobLock::rsyLock($host['hostip']), array('blocking' => false));
            if ($rsyLock->acquire()) {
                $HOST_NAME = $host['hostname'];
                //执行同步前每次都执行的本地命令
                $this->processCommands($webScript['before']['local']);
                //执行同步前每次都执行的远端命令
                $this->processCommands($webScript['before']['remote'], $HOST_NAME);

                Log::info("deploying web apps to {$HOST_NAME}.");
                (new Process($this->remoteProcess($HOST_NAME, "sudo mkdir -p {$REMOTE_DIR}")))->mustRun();
                (new Process($this->remoteProcess($HOST_NAME, "sudo chown {$remoteUser} -R {$REMOTE_DIR}")))->mustRun();
                (new Process("rsync -azq --progress --force --delete --delay-updates --exclude-from={$RSYNC_EXCLUDE} {$LOCAL_DIR}/ {$HOST_NAME}:{$REMOTE_DIR}/", $commitPath))->setTimeout(600)->mustRun();
                (new Process($this->remoteProcess($HOST_NAME, "sudo chown {$remoteOwner} -R {$REMOTE_DIR}")))->mustRun();

                //执行同步后每次都执行的本地命令
                $this->processCommands($webScript['after']['local']);
                //执行同步后每次都执行的远端命令
                $this->processCommands($webScript['after']['remote'], $HOST_NAME);

                $rsyLock->release();
            } else {
                $webHosts->push($host);
            }
        }
        //执行同步后本地命令
        $this->processCommands($webScript['after']['handle']);

        $commitLock->release();

        $deploy['result'] = 'Deploy Success';
        $deploy['last_time'] = date('Y-m-d H:i:s');
        $df->save($deploy);

        Log::info($job->getJobId()." finish");
        $job->delete();
    }

    private function processCommands($CMDS, $remoteHostName = NULL)
    {
        foreach ($CMDS as $command) {
            if ($remoteHostName === NULL) {
                (new Process($command))->mustRun();
            } else {
                (new Process($this->remoteProcess($remoteHostName, $command)))->mustRun();
            }
        }
    }

    private function remoteProcess($target, $script)
    {
        $script = 'set -e'.PHP_EOL.$script;
        return 'ssh '.$target.' \'bash -s\' << EOF
'.$script.'
EOF';
    }
}