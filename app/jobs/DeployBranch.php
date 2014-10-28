<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-26
 * Time: 下午8:26
 */

use Symfony\Component\Process\Process;

class DeployBranch
{
    public function fire($job, $message)
    {
        $redis = app('redis')->connection();
        $id = $message['id'];
        $branch = $message['branch'];
        $root= $redis->get('deploy.root');
        $commitRoot = "{$root}/commit/";
        $branchPath = "{$commitRoot}/{$branch}";

        $result = json_decode($redis->hget('deploy.h.results', $id), true);
        $result['result'] = 'deploying';
        $result['last_time'] = date('Y-m-d H:i:s');
        $redis->hset('deploy.h.results', $id, json_encode($result));

        $build_command = $redis->get('deploy.build.command');
        $dist_command = $redis->get('deploy.dist.command');

        Log::info("job id : {$job->getJobId()} start \n---------------------------");
        Log::info("new branch deploy: $id, $branch");

//        if (File::exists($branchPath)) {
//            $job->delete();
//            Log::info("{$job->getJobId()} finish!\n---------------------------");
//            return;
//        }

        $defaultBranch = "develop";
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
            return;
        }

        $commit = trim($revParseProcess->getOutput());
        $commitPath = "{$commitRoot}/{$commit}";

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
            (new Process("git checkout {$commit}", $commitPath))->mustRun();

            Log::info("make build");
            (new Process($build_command, $commitPath))->setTimeout(600)->mustRun();

            Log::info("make dist");
            (new Process($dist_command, $commitPath))->mustRun();
        }

        $redis->zadd('deploy.Z.commit.version', time(), $commit);

        DeployFiles::deploy($commit, 'staging');

        $result['commit'] = $commit;
        $result['result'] = 'success';
        $result['last_time'] = date('Y-m-d H:i:s');
        $redis->hset('deploy.h.results', $id, json_encode($result));

        Log::info($job->getJobId()." finish\n---------------------------");
        $job->delete();
    }
}