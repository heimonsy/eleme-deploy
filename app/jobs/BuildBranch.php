<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-26
 * Time: 下午8:26
 */

use Symfony\Component\Process\Process;

class BuildBranch
{
    public function fire($job, $message)
    {
        $siteId = $message['siteId'];
        $buildId = $message['id'];
        $branch = $message['branch'];

        $dc = new DC($siteId);
        $df = new DeployInfo($siteId);

        $root = (new SystemConfig())->get(SystemConfig::WORK_ROOT_FIELD) . '/' . $siteId;
        $commitRoot = "{$root}/commit/";
        $branchPath = "{$commitRoot}/{$branch}";
        $gitOrigin = $dc->get(DC::GIT_ORIGIN);

        $build = $df->get($buildId);
        $build['result'] = 'Fetch Origin';
        $build['last_time'] = date('Y-m-d H:i:s');
        $df->save($build);

        $buildCommand = 'make deploy';

        Log::info("job id : {$job->getJobId()} start");
        $progress = 0;
        try {

            $defaultBranch = 'default';
            $developRoot = "{$root}/branch/{$defaultBranch}";

            if (!File::exists($developRoot)) {
                Log::info('Git clone');
                (new Process('mkdir -p ' . $commitRoot))->mustRun();
                (new Process('mkdir -p ' . $developRoot))->mustRun();
                (new Process('git clone ' . $gitOrigin . ' ' . $developRoot))->setTimeout(600)->mustRun();
            }

            Log::info("git fetch origin");
            (new Process("git fetch origin", $developRoot))->setTimeout(600)->mustRun();
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
                $redis = app('redis')->connection();
                $buildLock = new \Eleme\Rlock\Lock($redis, JobLock::buildLock($commitPath));
                $buildLock->acquire();
                (new Process("git checkout {$commit}", $commitPath))->mustRun();

                Log::info("make deploy");
                (new Process($buildCommand, $commitPath))->setTimeout(600)->mustRun();
                $buildLock->release();
            }

            (new CommitVersion($siteId))->add($commit);

            $build['commit'] = $commit;
            $build['result'] = 'Build Success';
            $build['last_time'] = date('Y-m-d H:i:s');
            $df->save($build);

            Log::info($job->getJobId() . " finish\n---------------------------");

        } catch (Exception $e) {
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

            Log::error($e->getMessage());
            Log::info($job->getJobId() . " Error Finish\n---------------------------");
        }
        $job->delete();
    }
}