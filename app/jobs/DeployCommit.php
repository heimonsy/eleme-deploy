<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-26
 * Time: 下午8:26
 */


class DeployCommit
{
    public function fire($job, $message)
    {
        $redis = app('redis')->connection();
        $id = $message['id'];
        $commit = $message['commit'];
        $hosttype = $message['hosttype'];

        $result = json_decode($redis->hget('deploy.h.results', $id), true);
        $result['result'] = 'deploying';
        $result['last_time'] = date('Y-m-d H:i:s');
        $redis->hset('deploy.h.results', $id, json_encode($result));

        Log::info("job id : {$job->getJobId()} start \n---------------------------");
        Log::info("new commit deploy: {$id}, {$commit}");

        DeployFiles::deploy($commit, $hosttype);

        $result['commit'] = $commit;
        $result['result'] = 'success';
        $result['last_time'] = date('Y-m-d H:i:s');
        $redis->hset('deploy.h.results', $id, json_encode($result));

        Log::info($job->getJobId()." finish");
        $job->delete();
    }
}