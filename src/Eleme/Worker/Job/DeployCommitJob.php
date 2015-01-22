<?php
namespace Eleme\Worker\Job;

use Mail;
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
use Heimonsy\HipChat;
use Watch;
use GithubUser;


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

    public function updateStatus($status = null, $errorMsg = NULL, $standOut = null, $errOut = null)
    {
        $date = date('Y-m-d H:i:s');
        if ($this->type == self::TYPE_PULL_REQUEST) {
            $this->prDeployInfo->status = $status ?: $this->prDeployInfo->status;
            $this->prDeployInfo->updateTime = $date;
            $this->prDeployInfo->errorMsg = $errorMsg ?: $this->prDeployInfo->errorMsg;
            $this->pr->save($this->prDeployInfo);
        } else {
            $this->deployInfo['result'] = $status ?: $this->deployInfo['result'];
            $this->deployInfo['last_time'] = $date;
            $this->deployInfo['errMsg'] = $errorMsg ?: $this->deployInfo['errMsg'];
            $this->deployInfo['standOut'] .= $standOut ?: '';
            $this->deployInfo['errOut'] .= $errOut ?: '';
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
        $gitOrigin = $dc->get(DC::GIT_ORIGIN);

        if (isset($message['type']) && $message['type'] == self::TYPE_PULL_REQUEST) {
            $id = $message['id'];
            $this->type = self::TYPE_PULL_REQUEST;
            $this->pr = new PullRequestDeploy($siteId);
            $this->prDeployInfo = $this->pr->get($id);
            $operateUser = $this->prDeployInfo->operateUser;

            $root = (new SystemConfig())->get(SystemConfig::WORK_ROOT_FIELD) . '/' . $siteId . '/pull_requests';
            $commitPath = "$root/commit/$commit";

        } else {
            $this->type = self::TYPE_NORMAL_DEPLOY;
            $this->dfManger = new DeployInfo($siteId);
            $id = $message['id'];
            $this->deployInfo = $this->dfManger->get($id);
            $operateUser = $this->deployInfo['user'];

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


        Log::info("--- {$worker->getJobId()} ---");
        Log::info("Commit deploy: {$commit}");

        //本地同步锁
        $redis = app('redis')->connection();
        $commitLock = new \Eleme\Rlock\Lock($redis, JobLock::buildLock($siteId . $hostType), array('timeout' => 600000, 'blocking' => false));
        if (!$commitLock->acquire()) {
            Log::info("worker : {$worker->getJobId()} Release");
            $worker->release(30);
            return ;
        }
        $this->updateStatus('Deploying');
        $rsyLock = NULL;
        try {
            $REMOTE_DIR = $this->clearDirString($REMOTE_DIR);
            $REMOTE_STATIC_DIR = $this->clearDirString($REMOTE_STATIC_DIR);

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
                        $this->sshProcess($HOST_NAME, $host['hostip'], $remoteUser, "sudo mkdir -p {$REMOTE_STATIC_DIR}", $identifyfile, $passphrase, null, $PORT);
                        $this->sshProcess($HOST_NAME, $host['hostip'], $remoteUser, "sudo chown {$remoteUser} -R {$REMOTE_STATIC_DIR}", $identifyfile, $passphrase, null, $PORT);
                        $this->rsyncProcess($HOST_NAME, $host['hostip'], $remoteUser, $RSYNC_EXCLUDE, $LOCAL_STATIC_DIR, $REMOTE_STATIC_DIR, RsyncProcess::KEEP_FILES, $identifyfile, $passphrase, $commitPath, $PORT);
                        $this->sshProcess($HOST_NAME, $host['hostip'], $remoteUser, "sudo chown {$remoteOwner} -R {$REMOTE_STATIC_DIR}", $identifyfile, $passphrase, null, $PORT);

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
                        $this->sshProcess($HOST_NAME, $host['hostip'], $remoteUser, "sudo mkdir -p {$REMOTE_DIR}", $identifyfile, $passphrase, null, $PORT);
                        $this->sshProcess($HOST_NAME, $host['hostip'], $remoteUser, "sudo chown {$remoteUser} -R {$REMOTE_DIR}", $identifyfile, $passphrase, null, $PORT);
                        $this->rsyncProcess($HOST_NAME, $host['hostip'], $remoteUser, $RSYNC_EXCLUDE, $LOCAL_DIR, $REMOTE_DIR, RsyncProcess::FORCE_DELETE, $identifyfile, $passphrase, $commitPath, $PORT);
                        $this->sshProcess($HOST_NAME, $host['hostip'], $remoteUser, "sudo chown {$remoteOwner} -R {$REMOTE_DIR}", $identifyfile, $passphrase, null, $PORT);

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
            $pc = new \PrevCommit($siteId, $hostType);
            $prevCommit = $pc->get();

            try {
                preg_match('/github\.com:(.+?)\.git$/i', $gitOrigin, $matchs);
                $repoName = $matchs[1];
                $token = $dc->get(DC::HIPCHAT_TOKEN);
                $room = $dc->get(DC::HIPCHAT_ROOM);
                if (!empty($token) && !empty($room)) {
                    $client = new HipChat($token, $room);
                    $diffUrl = '';
                    if (!empty($prevCommit) && $prevCommit != $commit ) {
                        $diffUrl = "Diff: https://github.com/{$repoName}/compare/{$prevCommit}...{$commit}";
                    }
                    $notify = <<<EOT
Message: Deploy {$siteId} to {$hostType} Success
Deploy Id: {$id}
Operater: {$operateUser}
Status: Success
Host Type: {$hostType}
Commit: {$commit}
{$diffUrl}
EOT;
                    $client->notify($notify);
                    Log::info('Hipchat Notify Send Sucess');
                }
                $logins = Watch::allUserWatching($siteId);
                $emails = array();
                foreach ($logins as $login) {
                    $user = GithubUser::loadFromRedis($login);
                    $emails[] = $user->email;
                }
                if (count($emails) > 0) {
                    $transport = \App::make('swift.transport');
                    $transport->stop();
                    $transport->start();
                    Mail::send('emails.deploy', array('siteId' => $siteId, 'status' => 'Success', 'hostType' => $hostType, 'commit' => $commit, 'repoName' => $matchs[1], 'user' => $operateUser, 'id' => $id, 'prevCommit' => $prevCommit), function($message) use ($emails) {
                        $email = array_pop($emails);
                        $message->to($email)->subject('Deploy Success!');

                        foreach ($emails as $email) {
                            $message->cc($email);
                        }
                    });
                    Log::info('Email Notify Send Success');
                }
            } catch (Exception $e) {
                Log::error("Notify Error:\n" . $e);
            }
            $rmpath = app_path() . '/storage/views/*';
            $this->process('sudo rm -rf ' . $rmpath, null, false);
            $rmpath = app_path() . '/storage/cache/*';
            $this->process('sudo rm -rf ' . $rmpath, null, false);
            $rmpath = app_path() . '/storage/meta/*';
            $this->process('sudo rm -rf ' . $rmpath, null, false);
            $pc->set($commit);

        } catch (Exception $e) {
            //if ($rsyLock != null) $rsyLock->release();
            $errMsg = $worker->getJobId() .  " error : " . $e->getMessage() . "\n";
            $this->updateStatus('Error', $errMsg, null, $errMsg);

            Log::error($e);
            Log::info($worker->getJobId() . " Error Finish\n---------------------------");
        }
        $commitLock->release();

        //if (!empty($identifyfile)) $this->process('rm -f ' . $identifyfile, false);
    }

    private function processCommands($CMDS, $remoteHostName = NULL, $address = null, $username = null, $identifyfile = null, $passphrase = null, $port = 22)
    {
        foreach ($CMDS as $command) {
            if ($remoteHostName === NULL) {
                $this->process($command);
            } else {
                $this->sshProcess($remoteHostName, $address, $username, $command, $identifyfile, $passphrase, null, $port, false);
            }
        }
    }

    public function process($command, $cwd = null, $must = true)
    {
        $process = new Process($command, $cwd);

        return $this->run($process, $command, $must);
    }

    public function sshProcess($host, $address, $username, $command, $identifyfile, $passphrase, $cwd = null, $port = 22, $must = true)
    {
        $process = new SSHProcess($host, $address, $username, $command, $identifyfile, $passphrase, null, $port, 600);

        return $this->run($process, $command, $must);
    }

    public function rsyncProcess($hostname, $address, $username, $exclude, $localDir, $remoteDir, $forceDelete, $identityfile = null, $passphrase = null, $cwd = null, $port = 22, $must = true)
    {
        $process = new RsyncProcess($hostname, $address, $username, $exclude, $localDir, $remoteDir, $forceDelete, $identityfile, $passphrase, $cwd, $port, 600);
        return $this->run($process, 'RSYNC', $must);
    }

    public function gitProcess($command, $cwd = null, $identifyfile = null, $passphrase = null, $must = true)
    {
        $process = new GitProcess($command, $cwd, $identifyfile, $passphrase, 600);
        return $this->run($process, $command, $must);
    }

    public function run(Process $process, $originCommand, $must = true)
    {
        $str = "<span class='text-info'>{$originCommand}</span>\n";
        $this->updateStatus(null, null, $str, $str);

        $must ? $process->setTimeout(600)->mustRun() : $process->setTimeout(600)->run();

        $this->updateStatus(null, null, $process->getOutput(), preg_replace('/"Enter passphrase" \{ send ".+/', '--------', $process->getErrorOutput()));

        return $process;
    }

    public function clearDirString($str)
    {
        $dirArray = explode('/', $str);
        $realPath = '';
        foreach ($dirArray as $dir) {
            if (!empty($dir)) {
                $realPath .= '/' . $dir;
            }
        }

        if (!empty($_ENV['REMOTE_DIR_PREFIX']) && strpos($realPath, $_ENV['REMOTE_DIR_PREFIX']) !== 0) {
            throw new \Exception('REMOTE DIR PREFIX ERROR : ' . $realPath);
        }

        $danger_path = array('', '/', '/root', '/boot', '/etc', '/dev'. '/lib');

        if (in_array($realPath, $danger_path)) {
            throw new \Exception('Remote Dir si Danger Path : ' . $realPath);
        }

        return $realPath .  '/';
    }
}
