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
use Eleme\Worker\GitProcess;
use PullRequest;

class PullRequestBuildJob implements ElemeJob
{
    public function descriptYourself($message)
    {
        return "Pull Request Build, site id {$message['siteId']}\n";
    }

    public function fire(Worker $worker, $message)
    {
        Log::info("--- Pull Request Build Start ---");
        $commit = $message['commit'];
        $pullNumber = $message['pullNumber'];
        $siteId = $message['siteId'];
        $pr = new PullRequest($siteId);
        $commitInfo = $pr->get($commit);
        $repoName = $commitInfo->repo;
        $gitOrigin = "git@github.com:{$repoName}.git";
        $dc = new DC($siteId);
        $branch = $commitInfo->branch;

        $root = (new SystemConfig())->get(SystemConfig::WORK_ROOT_FIELD) . '/' . $siteId;

        $defaultBranch = "{$root}/branch/default";
        $prDefaultBranch = "{$root}/branch/pruse";
        $branchRoot = "{$root}/pull_requests/repo/$repoName";
        $commitPath = "{$root}/pull_requests/commit/{$commit}";
        $progress = 0;
        $cmd = '';

        $lock = new \Eleme\Rlock\Lock(app('redis')->connection(), JobLock::pullRequestBuildLock($repoName), array('timeout' => 600000, 'blocking' => false));
        if (!$lock->acquire()) {
            Log::info("Job locked, now {$worker->getJobId()} Release");
            $worker->release(30);

            return;
        }

        try {
            if (!File::exists($prDefaultBranch)) {
                Log::info('init pull request branch');
                $mcd = 'mkdir -p ' . $prDefaultBranch ;
                (new Process($cmd))->mustRun();
                $cmd = "mkdir -p {$root}/pull_requests/commit/";
                (new Process($cmd))->mustRun();

                $progress = 1;
                $cmd = "cp -r $defaultBranch $prDefaultBranch";
                (new Process($cmd))->mustRun();
                $progress = 2;
            }

            Log::info("git fetch origin");
            $cmd = "git fetch -f origin +refs/pull/{$pullNumber}/head";
            (new GitProcess($cmd, $prDefaultBranch))->setTimeout(600)->mustRun();

            if (File::exists($commitPath)) {
                $cmd = "rm -rf $commitPath";
                (new Process($cmd))->setTimeout(600)->mustRun();
            }

            $progress = 3;
            $cmd = "cp -r $prDefaultBranch $commitPath";
            (new Process($cmd))->mustRun();


            $cmd = "git checkout -qf FETCH_HEAD";
            Log::info($cmd);
            (new Process($cmd, $commitPath))->mustRun();

            $commitInfo->buildStatus = 'Building';
            $pr->save($commitInfo);
            $progress = 4;
            $cmd = $dc->get(DC::BUILD_COMMAND);
            Log::info($cmd);
            (new Process($cmd, $commitPath))->setTimeout(600)->mustRun();
            $commitInfo->buildStatus = 'Success';
            $pr->save($commitInfo);

            $progress = 5;
            $commitInfo->testStatus = 'Testing';
            $pr->save($commitInfo);
            $cmd = $dc->get(DC::TEST_COMMAND);
            if (!empty($cmd)) {
                Log::info($cmd);
                (new Process($cmd, $commitPath))->setTimeout(600)->mustRun();
            }

            $commitInfo->testStatus = 'Success';
            $pr->save($commitInfo);
        } catch (Exception $e) {
            Log::info("ERROR!!! : " . $e->getMessage());

            $commitInfo->buildStatus = 'Error';
            $commitInfo->testStatus = 'Error';
            $commitInfo->errorMsg = "Command : {$cmd}\nError Message : {$e->getMessage()}";
            switch ($progress) {
                case 5 :
                    $commitInfo->buildStatus = 'Success';
                    break;
                case 4 :
                    (new Process("rm -rf $commitPath"))->run();
                case 3 :
                case 2:
                    $commitInfo->testStatus = 'Abort';
                    break;
                case 1 :
                    $cmd = "rm -rf {$root}/pull_requests/commit/";
                    (new Process($cmd))->run();
                    (new Process("rm -rf $prDefaultBranch"))->run();
                    $commitInfo->testStatus = 'Abort';
                    break;
            }
            $pr->save($commitInfo);
        }
        $lock->release();

        Log::info("progress : $progress");
        Log::info("worker id : {$worker->getJobId()} finish");
    }
}
