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
        $df = new DeployInfo($siteId);

        $root = (new SystemConfig())->get(SystemConfig::WORK_ROOT_FIELD) . '/' . $siteId;
        $commitRoot = "{$root}/commit/";
        $branchPath = "{$commitRoot}/{$branch}";
        $gitOrigin = $dc->get(DC::GIT_ORIGIN);

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
            $build = $df->get($buildId);

            if (!File::exists($developRoot)) {
                Log::info('Git clone');
                $createWait = 1;
                (new Process('mkdir -p ' . $commitRoot))->mustRun();
                (new Process('mkdir -p ' . $developRoot))->mustRun();
                $worker->report('');
                (new GitProcess('git clone ' . $gitOrigin . ' ' . $developRoot, $developRoot, $identifyfile, $passphrase))->setTimeout(600)->mustRun();
                unset($createWait);
            }
            $build['result'] = 'Fetch Origin';
            $build['last_time'] = date('Y-m-d H:i:s');
            $df->save($build);

            Log::info("git fetch origin");
            $worker->report('');
            (new GitProcess("git fetch origin", $developRoot, $identifyfile, $passphrase))->setTimeout(600)->mustRun();
            $progress = 1;
            (new Process("cp -r {$developRoot} {$branchPath}", $commitRoot))->mustRun();


            $revParseProcess = new Process("git rev-parse origin/{$branch}", $branchPath);
            $revParseProcess->run();
            if (!$revParseProcess->isSuccessful()) {
                throw new Exception('Error Message : ' . $revParseProcess->getErrorOutput());
            }

            $commit = trim($revParseProcess->getOutput());
            $commitPath = "{$commitRoot}/{$commit}";

            $build['result'] = 'Building';
            $build['last_time'] = date('Y-m-d H:i:s');
            $df->save($build);
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
                Log::info("Build {$siteId} branch:  {$branch}");

                (new Process("git checkout {$commit}", $commitPath))->mustRun();

                Log::info($buildCommand);
                $worker->report('');
                (new Process($buildCommand, $commitPath))->setTimeout(600)->mustRun();
            }

            (new CommitVersion($siteId))->add($commit);

            $build['commit'] = $commit;
            $build['result'] = 'Build Success';
            $build['last_time'] = date('Y-m-d H:i:s');
            $df->save($build);

            Log::info($worker->getJobId() . " finish\n---------------------------");

        } catch (Exception $e) {
            Log::error($e->getMessage());
            Log::info($worker->getJobId() . " Error Finish\n---------------------------");

            $build['errMsg'] = $e->getMessage();
            $build['result'] = 'Error';
            $build['last_time'] = date('Y-m-d H:i:s');
            $df->save($build);

            switch($progress) {
                case 2 :
                    (new Process('rm -rf ' . $commitPath))->run();
                case 1 :
                    (new Process('rm -rf ' . $branchPath))->run();

            }
            if (isset($createWait) && $createWait == 1) {
                (new Process('rm -rf ' . $developRoot))->run();
                (new Process('rm -rf ' . $commitRoot))->run();
            }

        }

        Log::info("--- BuildBranchJob End ---");
    }
}
