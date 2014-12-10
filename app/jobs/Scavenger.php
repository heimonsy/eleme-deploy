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
            $siteRoot = $root . '/' . $site['siteId'];
            $commits = (new CommitVersion($site['siteId']))->clearList();
            foreach ($commits as $commit) {
                $commitPath = $siteRoot . '/commit/' . $commit;
                (new Process("rm -rf $commitPath"))->setTimeout(600)->run();
            }

            $commits = (new PullRequest($site['siteId']))->clearList();
            foreach ($commits as $commit) {
                $commitPath = $siteRoot . '/pull_requests/commit/' . $commit;
                (new Process("rm -rf $commitPath"))->setTimeout(600)->run();
            }

            (new DeployInfo($site['siteId']))->clearList();
            (new PullRequestDeploy($site['siteId']))->clearList();
        }
    }
}
