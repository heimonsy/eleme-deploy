<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-26
 * Time: 下午7:47
 */

class DeployController extends Controller
{

    public function index()
    {
        $redis = app('redis')->connection();
        $default_branch = $redis->get('deploy.default.branch');

        $commit_version = $redis->zrevrange('deploy.Z.commit.version', 0, 30, 'WITHSCORES');

        $deploy_ids = $redis->lrange('deploy.L.result.ids', 0, 30);
        if (count($deploy_ids) == 0) {
            $deploy_ids = array(0);
        }
        $res = $redis->hmget('deploy.h.results', $deploy_ids);

        $results = array();
        foreach($res as $m) {
            $results[] = json_decode($m, true);
        }
        return View::make('deploy', array(
            'default_branch' => $default_branch,
            'commit_version' => $commit_version,
            'results' => $results,
        ));
    }

    // deploy branch
    public function branch() {
        $redis = app('redis')->connection();

        $branch = Input::get('branch');
        $id = $redis->incr('deploy.id');
        $deploy = array(
            'id' => $id,
            'branch' => $branch,
            'commit' => 'unknow',
            'type'   => 'branch',
            'hosttype' => 'staging',
            'time'   => date('Y-m-d H:i:s'),
            'last_time' => '0000-00-00 00:00:00',
            'result' => 'waiting',
        );

        $redis->lpush('deploy.L.result.ids', $id);
        $redis->hset('deploy.h.results', $id, json_encode($deploy));


        Queue::push('DeployBranch', array('branch' => $branch, 'id' => $id));

        return Redirect::to('/deploy');
    }

    //deploy commit
    public function commit() {
        $redis = app('redis')->connection();

        $commit = Input::get('commit');
        $hosttype = Input::get('remote');
        $id = $redis->incr('deploy.id');
        $deploy = array(
            'id' => $id,
            'branch' => 'unknow',
            'commit' => $commit,
            'hosttype'   => $hosttype,
            'type'   => 'commit',
            'time'   => date('Y-m-d H:i:s'),
            'last_time' => '0000-00-00 00:00:00',
            'result' => 'waiting',
        );

        $redis->lpush('deploy.L.result.ids', $id);
        $redis->hset('deploy.h.results', $id, json_encode($deploy));

        Queue::push('DeployCommit', array('commit' => $commit, 'hosttype' => $hosttype, 'id' => $id));

        return Redirect::to('/deploy');
    }

    //deploy status
    public function status() {
        $id = Input::get('id');
        $redis = app('redis')->connection();
        $jstr = $redis->hget('deploy.h.results', $id);
        if ($jstr == NULL) {
            return Response::json(array('res' => 1));
        }
        $post = json_decode($jstr, true);

        return Response::json(array(
            'res' => 0,
            'result' => $post['result'],
            'last_time' => $post['last_time'],
            'commit' => $post['commit'],
        ));
    }
} 