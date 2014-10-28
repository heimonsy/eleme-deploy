<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-24
 * Time: 下午8:17
 */


class ConfigController extends Controller
{

    public function config()
    {
        $redis = app('redis')->connection();
        $deploy_root = $redis->get('deploy.root');
        $static_dir = $redis->get('deploy.static.dir');
        $default_branch = $redis->get('deploy.default.branch');
        $remote_user = $redis->get('deploy.remote.user');
//        $ssh_key = $redis->get('deploy.ssh.key');
//        $ssh_key_phrase = $redis->get('deploy.ssh.key.phrase');
        $service_name = $redis->get('deploy.service.name');
        $remote_app_dir = $redis->get('deploy.remote.app.dir');
        $remote_static_dir = $redis->get('deploy.remote.static.dir');
        $build_command = $redis->get('deploy.build.command');
        $dist_command = $redis->get('deploy.dist.command');
        $rsync_exclude = $redis->get('deploy.rsync.exclude');
        $remote_owner = $redis->get('deploy.remote.owner');


        if ($ok = Session::get('save_ok', false)) {
            Session::forget('save_ok');
        }

        return View::make('config', array(
            'deploy_root' => $deploy_root,
            'static_dir' => $static_dir,
            'default_branch' => $default_branch,
            'remote_user' => $remote_user,
//            'ssh_key' => $ssh_key,
//            'key_phrase' => $ssh_key_phrase,
            'service_name' => $service_name,
            'remote_app_dir' => $remote_app_dir,
            'remote_static_dir' => $remote_static_dir,
            'build_command' => $build_command,
            'dist_command' => $dist_command,
            'ok' => $ok,
            'rsync_exclude' => $rsync_exclude,
            'remote_owner' => $remote_owner,
        ));
    }

    public function saveConfig() {
        $redis = app('redis')->connection();
        $redis->set('deploy.root', Input::get('deployRoot'));
        $redis->set('deploy.static.dir', Input::get('staticDir'));
        $redis->set('deploy.default.branch', Input::get('defaultBranch'));
        $redis->set('deploy.remote.user', Input::get('remoteUser'));
//        $redis->set('deploy.ssh.key', Input::get('sshKey'));
//        $redis->set('deploy.ssh.key.phrase', Input::get('keyPhrase'));
        $redis->set('deploy.service.name', Input::get('serviceName'));
        $redis->set('deploy.remote.app.dir', Input::get('remoteAppDir'));
        $redis->set('deploy.remote.static.dir', Input::get('remoteStaticDir'));
        $redis->set('deploy.build.command', Input::get('buildCommand'));
        $redis->set('deploy.dist.command', Input::get('distributeCommand'));
        $redis->set('deploy.rsync.exclude', Input::get('rsyncExclude'));
        $redis->set('deploy.remote.owner', Input::get('remoteOwner'));

        Session::put('save_ok', true);

        return Redirect::to('/config');
    }

    public function hostConfig()
    {
        $redis = app('redis')->connection();
        $static_staging = $redis->lrange('deploy.L.static.hosts.staging', 0, -1);
        $static_production = $redis->lrange('deploy.L.static.hosts.production', 0, -1);

        $web_staging = $redis->lrange('deploy.L.web.hosts.staging', 0, -1);
        $web_production = $redis->lrange('deploy.L.web.hosts.production', 0, -1);

        $solve = function(&$arr, &$brr) {
            foreach($brr as $m) {
                $arr[] = json_decode($m, true);
            }
        };

        $static_hosts = array();
        $solve($static_hosts, $static_staging);
        $solve($static_hosts, $static_production);

        $web_hosts = array();
        $solve($web_hosts, $web_staging);
        $solve($web_hosts, $web_production);

        Debugbar::info($static_hosts);
        return View::make('hostconfig', array(
            'static_hosts' => $static_hosts,
            'web_hosts' => $web_hosts,
        ));
    }

    public function hostAdd()
    {
        $hostname = Input::get('hostname');
        $hostip = Input::get('hostip');
        $hostport = Input::get('hostport');
        $hosttype = Input::get('hosttype');
        $time = date('Y-m-d H:i:s');

        $type = Input::get('type') ;

        $host = array(
            'hostname' => $hostname,
            'hostip' => $hostip,
            'hostport' => intval($hostport),
            'hosttype' => $hosttype,
            'type' => $type,
            'time' => $time
        );

        $redis = app('redis')->connection();
        $jstr = json_encode($host);
        $redis->lpush('deploy.L.'.$type.'.hosts.'.$hosttype, $jstr);

        return Response::json(array_merge($host, array('jstr' => $jstr)));
    }

    public function hostDel() {
        // lrem deploy.L.static.hosts.production 1
        $redis = app('redis')->connection();
        $jstr = Input::get('jstr');
        $host = json_decode($jstr, true);
        $res = $redis->lrem('deploy.L.'.$host['type'].'.hosts.'.$host['hosttype'], 1, $jstr);

        return Response::json(array('res' => $res));
    }
}
