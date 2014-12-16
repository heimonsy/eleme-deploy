<?php
namespace Eleme\Worker\Job;

use Eleme\Worker\ElemeJob;
use Eleme\Worker\Worker;
use Symfony\Component\Process\Process;
use Log;
use Exception;
use DC;
use SystemConfig;
use JobLock;
use File;
use CommitVersion;
use Eleme\Worker\GitProcess;
use PullRequestDeploy;
use DeployInfo;
use SiteHost;
use SplQueue;
use SSHProcess\SSHProcess;
use SSHProcess\RsyncProcess;
use ScriptCommand;


class DeployCommitJob implements ElemeJob
{
    const TYPE_PULL_REQUEST = 'pull_request';
    const TYPE_NORMAL_DEPLOY = 'normal';

    private $deployInfo;
    private $prDeployInfo;
    private $pr;
    private $type;
    private $dfManger;
    private $warnings;

    public function descriptYourself($message)
    {
        $commit = substr($message['commit'], 0, 7);
        $siteId = $message['siteId'];
        return "site $siteId, Deploy Commit : {$commit}\n";
    }

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

    public function fire(Worker $worker, $message)
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
        $LOCAL_STATIC_DIR = "{$commitPath}/{$staticDir}/";
        $LOCAL_DIR = $commitPath . '/';

        $remoteUser = $dc->get(DC::REMOTE_USER);
        $remoteOwner = $dc->get(DC::REMOTE_OWNER);
        $ifContent = $dc->get(DC::IDENTIFYFILE);
        if (!empty($ifContent)) {
            $passphrase = $dc->get(DC::PASSPHRASE);
            $identifyfile = (new SystemConfig())->get(SystemConfig::WORK_ROOT_FIELD) . '/' . $siteId . '/identify.key';
            file_put_contents($identifyfile, $ifContent);
            chmod($identifyfile, 0600);
        } else {
            $passphrase = null;
            $identifyfile = null;
        }

        $RSYNC_EXCLUDE = "{$commitPath}/" . $dc->get(DC::RSYNC_EXCLUDE);
        $REMOTE_STATIC_DIR = $dc->get(DC::REMOTE_STATIC_DIR) . '/';
        $REMOTE_DIR = $dc->get(DC::REMOTE_APP_DIR) . '/';

        $staticScript = ScriptCommand::complie($dc->get(DC::DEPLOY_STATIC_SCRIPT), $siteId);
        $webScript    = ScriptCommand::complie($dc->get(DC::DEPLOY_WEB_SCRIPT), $siteId);

        $this->updateStatus('Deploying');

        Log::info("--- {$worker->getJobId()} ---");
        Log::info("Commit deploy: {$commit}");

        //本地同步锁，不能在同一个commit下同步
        $redis = app('redis')->connection();
        $commitLock = new \Eleme\Rlock\Lock($redis, JobLock::buildLock($commitPath), array('timeout' => 600000, 'blocking' => false));
        if (!$commitLock->acquire()) {
            Log::info("worker : {$worker->getJobId()} Release");
            $worker->release(30);
            return ;
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

            $worker->report('');
            //执行同步前本地命令
            $this->processCommands($staticScript['before']['handle']);

            while (!$staticHosts->isEmpty()) {
                $host = $staticHosts->shift();
                $rsyLock = new \Eleme\Rlock\Lock($redis, JobLock::rsyLock($host['hostip']), array('timeout' => 180000, 'blocking' => false));
                $worker->report('');
                if ($rsyLock->acquire()) {
                    try{
                        $HOST_NAME = $host['hostname'];
                        $PORT = $host['hostport'];
                        //执行同步前每次都执行的本地命令
                        $this->processCommands($staticScript['before']['local']);
                        //执行同步前每次都执行的远端命令
                        $this->processCommands($staticScript['before']['remote'], $HOST_NAME, $host['hostip'], $remoteUser, $identifyfile, $passphrase, $PORT);

                        Log::info("deploying static files to {$HOST_NAME}.");
                        (new SSHProcess($HOST_NAME, $host['hostip'], $remoteUser, "sudo mkdir -p {$REMOTE_STATIC_DIR}", $identifyfile, $passphrase, null, $PORT))->mustRun();
                        (new SSHProcess($HOST_NAME, $host['hostip'], $remoteUser, "sudo chown {$remoteUser} -R {$REMOTE_STATIC_DIR}", $identifyfile, $passphrase, null, $PORT))->mustRun();
                        (new RsyncProcess($HOST_NAME, $host['hostip'], $remoteUser, $RSYNC_EXCLUDE, $LOCAL_STATIC_DIR, $REMOTE_STATIC_DIR, RsyncProcess::KEEP_FILES, $identifyfile, $passphrase, $commitPath, $PORT))->setTimeout(600)->mustRun();
                        (new SSHProcess($HOST_NAME, $host['hostip'], $remoteUser, "sudo chown {$remoteOwner} -R {$REMOTE_STATIC_DIR}", $identifyfile, $passphrase, null, $PORT))->mustRun();

                        //执行同步后每次都执行的本地命令
                        $this->processCommands($staticScript['after']['local']);
                        //执行同步后每次都执行的远端命令
                        $this->processCommands($staticScript['after']['remote'], $HOST_NAME, $host['hostip'], $remoteUser, $identifyfile, $passphrase, $PORT);

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
                $worker->report('');
                $rsyLock = new \Eleme\Rlock\Lock($redis, JobLock::rsyLock($host['hostip']), array('timeout' => 180000, 'blocking' => false));
                if ($rsyLock->acquire()) {
                    try {
                        $HOST_NAME = $host['hostname'];
                        $PORT = $host['hostport'];
                        //执行同步前每次都执行的本地命令
                        $this->processCommands($webScript['before']['local']);
                        //执行同步前每次都执行的远端命令
                        $this->processCommands($webScript['before']['remote'], $HOST_NAME, $host['hostip'], $remoteUser, $identifyfile, $passphrase, $PORT);

                        Log::info("deploying web apps to {$HOST_NAME}.");
                        (new SSHProcess($HOST_NAME, $host['hostip'], $remoteUser, "sudo mkdir -p {$REMOTE_DIR}", $identifyfile, $passphrase, null, $PORT))->mustRun();
                        (new SSHProcess($HOST_NAME, $host['hostip'], $remoteUser, "sudo chown {$remoteUser} -R {$REMOTE_DIR}", $identifyfile, $passphrase, null, $PORT))->mustRun();
                        (new RsyncProcess($HOST_NAME, $host['hostip'], $remoteUser, $RSYNC_EXCLUDE, $LOCAL_DIR, $REMOTE_DIR, RsyncProcess::FORCE_DELETE, $identifyfile, $passphrase, $commitPath, $PORT))->setTimeout(600)->mustRun();
                        (new SSHProcess($HOST_NAME, $host['hostip'], $remoteUser, "sudo chown {$remoteOwner} -R {$REMOTE_DIR}", $identifyfile, $passphrase, null, $PORT))->mustRun();

                        //执行同步后每次都执行的本地命令
                        $this->processCommands($webScript['after']['local']);
                        //执行同步后每次都执行的远端命令
                        $this->processCommands($webScript['after']['remote'], $HOST_NAME, $host['hostip'], $remoteUser, $identifyfile, $passphrase, $PORT);

                        $rsyLock->release();
                    } catch (Exception $e) {
                        $rsyLock->release();
                        throw $e;
                    }
                } else {
                    $webHosts->push($host);
                }
            }

            $worker->report('');
            //执行同步后本地命令
            $this->processCommands($webScript['after']['handle']);

            $errMsg = '';
            foreach ($this->warnings as $w) {
                $errMsg .= "{$w}\n";
            }
            $errMsg = empty($errMsg) ? null : $errMsg;
            $this->updateStatus('Deploy Success', $errMsg);

            Log::info($worker->getJobId()." finish");

        } catch (Exception $e) {
            //if ($rsyLock != null) $rsyLock->release();
            $this->updateStatus('Error', "file : " . $e->getFile() . "\nline : " . $e->getLine() . "\n Error Msg : " . $e->getMessage());

            Log::error($e->getMessage());
            Log::info($worker->getJobId() . " Error Finish\n---------------------------");
        }
        $commitLock->release();

        (new Process('rm -f' . $identifyfile))->run();
    }

    private function processCommands($CMDS, $remoteHostName = NULL, $address = null, $username = null, $identifyfile = null, $passphrase = null, $port = 22)
    {
        foreach ($CMDS as $command) {
            if ($remoteHostName === NULL) {
                $process = new Process($command);
            } else {
                $process = new SSHProcess($remoteHostName, $address, $username, $command, $identifyfile, $passphrase, null, $port);
            }
            $process->run();
            if (!$process->isSuccessful()) {
                $info = "Command Warning : $command  \nWarning Info : {$process->getErrorOutput()}";
                Log::info($info);
                $this->warnings[] = $info;
            }
        }
    }
}
