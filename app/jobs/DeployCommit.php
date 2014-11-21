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
    const TYPE_PULL_REQUEST = 'pull_request';
    CONST TYPE_NORMAL_DEPLOY = 'normal';

    private $deployInfo;
    private $prDeployInfo;
    private $pr;
    private $type;
    private $dfManger;
    private $warnings;

    public function updateStatus($status, $errorMsg = NULL)
    {
        $date = date('Y-m-d H:i:s');
        if ($this->type == self::TYPE_PULL_REQUEST) {
            $this->prDeployInfo->status = $status;
            $this->prDeployInfo->updateTime = $date;
            $this->prDeployInfo->errorMsg = $errorMsg;
            $this->pr->save($this->prDeployInfo);
        } else {
            $this->deployInfo['result'] = $status;
            $this->deployInfo['last_time'] = $date;
            $this->deployInfo['errMsg'] = $errorMsg;
            $this->dfManger->save($this->deployInfo);
        }
    }

    public function fire($job, $message)
    {
        $this->warnings = array();
        $commit = $message['commit'];
        $hostType = $message['hostType'];
        $siteId = $message['siteId'];

        $dc = new DC($siteId);
        $staticDir = $dc->get(DC::STATIC_DIR);

        if (isset($message['type']) && $message['type'] == self::TYPE_PULL_REQUEST) {
            $id = $message['id'];
            $this->type = self::TYPE_PULL_REQUEST;
            $this->pr = new PullRequestDeploy($siteId);
            $this->prDeployInfo = $this->pr->get($id);

            $root = (new SystemConfig())->get(SystemConfig::WORK_ROOT_FIELD) . '/' . $siteId . '/pull_requests';
            $commitPath = "$root/commit/$commit";

        } else {
            $this->type = self::TYPE_NORMAL_DEPLOY;
            $this->dfManger = new DeployInfo($siteId);
            $id = $message['id'];
            $this->deployInfo = $this->dfManger->get($id);

            $root = (new SystemConfig())->get(SystemConfig::WORK_ROOT_FIELD) . '/' . $siteId . '/commit';
            $commitPath = "{$root}/{$commit}";
        }

        $LOCAL_STATIC_DIR = "{$commitPath}/{$staticDir}";
        $LOCAL_DIR = $commitPath;


        $remoteUser = $dc->get(DC::REMOTE_USER);
        $remoteOwner = $dc->get(DC::REMOTE_OWNER);

        $RSYNC_EXCLUDE = "{$commitPath}/" . $dc->get(DC::RSYNC_EXCLUDE);
        $REMOTE_STATIC_DIR = $dc->get(DC::REMOTE_STATIC_DIR);
        $REMOTE_DIR = $dc->get(DC::REMOTE_APP_DIR);

        $staticScript = ScriptCommand::complie($dc->get(DC::DEPLOY_STATIC_SCRIPT), $siteId);
        $webScript    = ScriptCommand::complie($dc->get(DC::DEPLOY_WEB_SCRIPT), $siteId);

        $this->updateStatus('Deploying');

        Log::info("\n---------------------------\njob id : {$job->getJobId()} start ");
        Log::info("commit deploy: {$commit}");

        //本地同步锁，不能在同一个commit下同步
        $redis = app('redis')->connection();
        $commitLock = new \Eleme\Rlock\Lock($redis, JobLock::buildLock($commitPath), array('timeout' => 600000, 'blocking' => false));
        if (!$commitLock->acquire()) {
            Log::info("Job : {$job->getJobId()} Release");
            $job->release(30);
        }
        $rsyLock = NULL;
        try {
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

            /*****************************************
             *
             *  执行静态文件同步
             *
             *****************************************/
            //执行同步前本地命令

            $this->processCommands($staticScript['before']['handle']);
            while (!$staticHosts->isEmpty()) {
                $host = $staticHosts->shift();
                $rsyLock = new \Eleme\Rlock\Lock($redis, JobLock::rsyLock($host['hostip']), array('timeout' => 600000, 'blocking' => false));
                if ($rsyLock->acquire()) {
                    try{
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
                    } catch (Exception $e) {
                        $rsyLock->release();
                        throw $e;
                    }
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
                $rsyLock = new \Eleme\Rlock\Lock($redis, JobLock::rsyLock($host['hostip']), array('timeout' => 600000, 'blocking' => false));
                if ($rsyLock->acquire()) {
                    try {
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
                    } catch (Exception $e) {
                        $rsyLock->release();
                        throw $e;
                    }
                } else {
                    $webHosts->push($host);
                }
            }
            //执行同步后本地命令
            $this->processCommands($webScript['after']['handle']);

            $errMsg = '';
            foreach ($this->warnings as $w) {
                $errMsg .= "{$w}\n";
            }
            $errMsg = empty($errMsg) ? null : $errMsg;
            $this->updateStatus('Deploy Success', $errMsg);

            Log::info($job->getJobId()." finish");

        } catch (Exception $e) {
            //if ($rsyLock != null) $rsyLock->release();
            $this->updateStatus('Error', "file : " . $e->getFile() . "\nline : " . $e->getLine() . "\n Error Msg : " . $e->getMessage());

            Log::error($e->getMessage());
            Log::info($job->getJobId() . " Error Finish\n---------------------------");
        }
        $commitLock->release();
        $job->delete();
    }

    private function processCommands($CMDS, $remoteHostName = NULL)
    {
        foreach ($CMDS as $command) {
            if ($remoteHostName === NULL) {
                $process = new Process($command);
            } else {
                $process = new Process($this->remoteProcess($remoteHostName, $command));
            }
            $process->run();
            if (!$process->isSuccessful()) {
                $info = "Command Warning : $command  \nWarning Info : {$process->getErrorOutput()}";
                Log::info($info);
                $this->warnings[] = $info;
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
