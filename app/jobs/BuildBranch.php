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
        $root = $dc->get(DC::ROOT);
        $commitRoot = "{$root}/commit/";
        $branchPath = "{$commitRoot}/{$branch}";

        $build = $df->get($buildId);
        $build['result'] = 'Fetch Origin';
        $build['last_time'] = date('Y-m-d H:i:s');
        $df->save($build);

        $buildCommand = 'make deploy';

        Log::info("job id : {$job->getJobId()} start");
        Log::info("Build {$siteId} branch:  {$branch}");

        $defaultBranch = $dc->get(DC::DEFAULT_BRANCH);
        $developRoot = "{$root}/branch/{$defaultBranch}";

        Log::info("git fetch origin");
        (new Process("git fetch origin", $developRoot))->setTimeout(600)->mustRun();
        (new Process("cp -r {$developRoot} {$branchPath}", $commitRoot))->mustRun();

        $revParseProcess = new Process("git rev-parse origin/{$branch}", $branchPath);
        $revParseProcess->run();
        if (!$revParseProcess->isSuccessful()) {
            Log::info("{$branch} do not exists!");
            File::deleteDirectory($branchPath);
            $job->delete();
            Log::info("{$job->getJobId()} finish!\n---------------------------");
            $build['result'] = 'ERROR 1';
            $build['last_time'] = date('Y-m-d H:i:s');
            $df->save($build);
            return;
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
                File::move($branchPath, $commitPath);
            }
        }
        if ($needBuild) {
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

        Log::info($job->getJobId()." finish\n---------------------------");
        $job->delete();
    }
}