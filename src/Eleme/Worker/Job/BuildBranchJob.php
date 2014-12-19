<?php
namespace Eleme\Worker\Job;

use Eleme\Worker\ElemeJob;
use Eleme\Worker\Worker;
use Symfony\Component\Process\Process;
use Log;
use Exception;
use DC;
use SystemConfig;
use DeployInfo;
use JobLock;
use File;
use CommitVersion;
use Eleme\Worker\GitProcess;

class BuildBranchJob implements ElemeJob
{
    private $infoManage;
    private $deployInfo;

    public function descriptYourself($message)
    {
        return "Build {$message['siteId']}, branch {$message['branch']}";
    }

    public function fire(Worker $worker, $message)
    {
        Log::info("--- BuildBranchJob Start ---");
        $siteId = $message['siteId'];
        $buildId = $message['id'];
        $branch = $message['branch'];

        $dc = new DC($siteId);
        $this->infoManage = new DeployInfo($siteId);

        $root = (new SystemConfig())->get(SystemConfig::WORK_ROOT_FIELD) . '/' . $siteId;
        $commitRoot = "{$root}/commit/";
        $branchPath = "{$commitRoot}/{$branch}";
        $gitOrigin = $dc->get(DC::GIT_ORIGIN);

        $buildCommand = $dc->get(DC::BUILD_COMMAND) ?: 'make deploy';
        $defaultBranch = 'default';
        $developRoot = "{$root}/branch/{$defaultBranch}";

        $progress = 0;
        $redis = app('redis')->connection();
        $lock = new \Eleme\Rlock\Lock($redis, JobLock::buildLock($developRoot), array('timeout' => 600000, 'blocking' => false));

        if (!$lock->acquire()) {
            Log::info("Job locked, now {$worker->getJobId()} Release");
            $worker->release(30);
            return;
        }

        try {
            $this->deployInfo = $this->infoManage->get($buildId);

            $ifContent = $dc->get(DC::IDENTIFYFILE);
            if (!empty($ifContent)) {
                $passphrase = $dc->get(DC::PASSPHRASE);
                $sitePath = (new SystemConfig())->get(SystemConfig::WORK_ROOT_FIELD) . '/' . $siteId;
                if (!File::exists($sitePath)) {
                    $this->process('mkdir -p ' . $sitePath);
                }
                $identifyfile = $sitePath . '/identify.key';
                file_put_contents($identifyfile, $ifContent);
                chmod($identifyfile, 0600);
            } else {
                $passphrase = null;
                $identifyfile = null;
            }

            if (!File::exists($developRoot)) {
                $this->refreshStatus('Clone Repo');
                $worker->report('');
                $createWait = 1;
                $this->process('mkdir -p ' . $commitRoot);
                $this->process('mkdir -p ' . $developRoot);
                $this->gitProcess('git clone -q ' . $gitOrigin . ' ' . $developRoot, $developRoot, $identifyfile, $passphrase);
                unset($createWait);
            }

            $this->refreshStatus('Fetch Origin');
            $worker->report('');
            $this->gitProcess("git fetch origin", $developRoot, $identifyfile, $passphrase);
            $progress = 1;
            $this->process("cp -r {$developRoot} {$branchPath}", $commitRoot);

            $revParseProcess = $this->process("git rev-parse origin/{$branch}", $branchPath);
            $commit = trim($revParseProcess->getOutput());
            $commitPath = "{$commitRoot}/{$commit}";

            $needBuild = true;
            if ($commit !== $branch) {
                if (File::exists($commitPath)) {
                    File::deleteDirectory($branchPath);
                    $needBuild = false;
                } else {
                    $progress = 2;
                    File::move($branchPath, $commitPath);
                }
            }
            if ($needBuild) {
                $this->refreshStatus('Building', false);
                Log::info("Build {$siteId} branch:  {$branch}");

                $this->process("git checkout {$commit}", $commitPath);
                $worker->report('');
                $this->process($buildCommand, $commitPath);
            }

            (new CommitVersion($siteId))->add($commit);

            $this->deployInfo['commit'] = $commit;
            $this->refreshStatus('Build Success');

            Log::info('--- '. $worker->getJobId() . " finish ---");

        } catch (Exception $e) {
            Log::error($e);
            Log::info('--- '. $worker->getJobId() . " ERROR ---");

            //$this->deployInfo['errMsg'] = $e->getFile() . '  '. $e->getLine() . ' : ' . $e->getMessage();
            $this->deployInfo['errOut'] = $worker->getJobId() .  " error : " . $e->getMessage() . "\n";
            $this->refreshStatus('Error', false);

            switch($progress) {
                case 2 :
                    $this->process('rm -rf ' . $commitPath);
                case 1 :
                    $this->process('rm -rf ' . $branchPath);
            }
            if (isset($createWait) && $createWait == 1) {
                $this->process('rm -rf ' . $developRoot);
                $this->process('rm -rf ' . $commitRoot);
            }
        }

        $lock->release();
        Log::info("--- BuildBranchJob End ---");
        if (!empty($identifyfile)) $this->process('rm -f ' . $identifyfile, false);
    }

    public function process($command, $cwd = null, $must = true)
    {
        $process = new Process($command, $cwd);

        return $this->run($process, $command, $must);
    }

    public function gitProcess($command, $cwd = null, $identifyfile = null, $passphrase = null, $must = true)
    {
        $process = new GitProcess($command, $cwd, $identifyfile, $passphrase, 600);
        $this->run($process, $command, $must);
    }

    public function run(Process $process, $originCommand, $must = true)
    {
        $str = "<span class='text-info'>{$originCommand}</span>\n";
        $this->deployInfo['standOut'] .= $str;
        $this->deployInfo['errOut'] .= $str;
        $this->infoManage->save($this->deployInfo);

        $must ? $process->setTimeout(600)->mustRun() : $process->run();

        $this->deployInfo['standOut'] .= $process->getOutput();
        $this->deployInfo['errOut'] .= $process->getErrorOutput();
        $this->infoManage->save($this->deployInfo);

        return $process;
    }

    public function refreshStatus($status, $log = true)
    {
        $this->deployInfo['result'] = $status;
        $this->deployInfo['last_time'] = date('Y-m-d H:i:s');
        $this->infoManage->save($this->deployInfo);
        if ($log) {
            Log::info($status);
        }
    }

}
