<?php
use Symfony\Component\Process\Process;

class Scavenger
{
    public function fire($job, $message)
    {
        $this->log("start scavenger");
        $this->clear();
        $this->log("finish scavenger");
        $job->delete();
    }

    public function clear()
    {
        $sites = (new WebSite)->getList();
        $root = (new SystemConfig())->get(SystemConfig::WORK_ROOT_FIELD);
        foreach ($sites as $site) {
            $this->info("开始清理 " . $site['siteId']);
            $siteRoot = $root . '/' . $site['siteId'];
            $commits = (new CommitVersion($site['siteId']))->clearList();
            $this->info("开始清理 commit, 总数" . count($commits));
            foreach ($commits as $commit) {
                $this->info("开始清理 commit: $commit");
                $commitPath = $siteRoot . '/commit/' . $commit;
                (new Process("rm -rf $commitPath"))->setTimeout(600)->run();
            }

            $commits = (new PullRequest($site['siteId']))->clearList();
            $this->info("开始清理 pr commit, 总数" . count($commits));
            foreach ($commits as $commit) {
                $this->info("开始清理 pr commit: $commit");
                $commitPath = $siteRoot . '/pull_requests/commit/' . $commit;
                (new Process("rm -rf $commitPath"))->setTimeout(600)->run();
            }

            $this->info("开始清理 deploy info");
            (new DeployInfo($site['siteId']))->clearList();
            $this->info("开始清理 pr deploy info");
            (new PullRequestDeploy($site['siteId']))->clearList();
            $this->info("清理完毕 " . $site['siteId']);
        }
    }

    public function info($info)
    {
        echo $info . "\n";
        Log::info($info);
    }
}
