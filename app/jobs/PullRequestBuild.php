 <?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/12/14
 * Time: 10:56 AM
 */

use Symfony\Component\Process\Process;

class PullRequestBuild
{
    public function fire($job, $message)
    {
        Log::info("\n---------------------------\njob id : {$job->getJobId()} start");
        $commit = $message['commit'];
        $siteId = $message['siteId'];
        $pr = new PullRequest($siteId);
        $commitInfo = $pr->get($commit);
        $repoName = $commitInfo->repo;
        $gitOrigin = "git@github.com:{$repoName}.git";
        $dc = new DC($siteId);
        $branch = $commitInfo->branch;

        $root = (new SystemConfig())->get(SystemConfig::WORK_ROOT_FIELD) . '/' . $siteId;

        $branchRoot = "{$root}/pull_requests/repo/$repoName";
        $commitPath = "{$root}/pull_requests/commit/{$commit}";
        $progress = 0;
        $cmd = '';

        $lock = new Eleme\Rlock\Lock(app('redis')->connection(), JobLock::pullRequestBuildLock($repoName), array('blocking' => false));
        if (!$lock->acquire()) {
            Log::info("Job : {$job->getJobId()} Release");
            $job->release(30);
            return;
        }

        try{

            if (!File::exists($branchRoot)) {
                Log::info('Git clone');
                $cmd = 'mkdir -p ' . $branchRoot;
                (new Process($cmd))->mustRun();
                $cmd = "mkdir -p {$root}/pull_requests/commit/";
                (new Process($cmd))->mustRun();
                $progress = 1;
                $cmd = "git clone $gitOrigin $branchRoot --depth 10";
                (new Process($cmd))->setTimeout(600)->mustRun();
                $progress = 2;
            }

            Log::info("git fetch origin");
            $cmd = "git fetch origin $branch:$branch --depth 20";
            (new Process($cmd, $branchRoot))->setTimeout(600)->mustRun();

            if (File::exists($commitPath)) {
                $cmd = "rm -rf $commitPath";
                (new Process($cmd))->setTimeout(600)->mustRun();
            }
            $progress = 3;
            $cmd = "cp -r $branchRoot $commitPath";
            Log::info($cmd);
            (new Process($cmd))->setTimeout(600)->mustRun();

            $cmd = "git checkout {$branch}";
            Log::info($cmd);
            (new Process($cmd, $commitPath))->mustRun();

            $cmd = "git checkout {$commit}";
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
            switch($progress) {
                case 5 :
                    $commitInfo->buildStatus = 'Success';
                    break;
                case 4 :
                    //(new Process("rm -rf $commitPath"))->run();
                case 3 :
                case 2:
                    $commitInfo->testStatus = 'Abort';
                    break;
                case 1 :
                    (new Process("rm -rf $branchRoot"))->run();
                    $commitInfo->testStatus = 'Abort';
                    break;
            }
            $pr->save($commitInfo);
        }
        $lock->release();

        Log::info("progress : $progress");
        Log::info("job id : {$job->getJobId()} finish");
        $job->delete();
    }

}