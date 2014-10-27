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
        $ssh_key = $redis->get('deploy.ssh.key');
        $ssh_key_phrase = $redis->get('deploy.ssh.key.phrase');
        $service_name = $redis->get('deploy.service.name');
        $remote_app_dir = $redis->get('deploy.remote.app.dir');
        $remote_static_dir = $redis->get('deploy.remote.static.dir');
        $build_command = $redis->get('deploy.build.command');
        $dist_command = $redis->get('deploy.dist.command');

        if ($ok = Session::get('save_ok', false)) {
            Session::forget('save_ok');
        }

        return View::make('config', array(
            'deploy_root' => $deploy_root,
            'static_dir' => $static_dir,
            'default_branch' => $default_branch,
            'remote_user' => $remote_user,
            'ssh_key' => $ssh_key,
            'key_phrase' => $ssh_key_phrase,
            'service_name' => $service_name,
            'remote_app_dir' => $remote_app_dir,
            'remote_static_dir' => $remote_static_dir,
            'build_command' => $build_command,
            'dist_command' => $dist_command,
            'ok' => $ok,
        ));
    }

    public function saveConfig() {
        $redis = app('redis')->connection();
        $redis->set('deploy.root', Input::get('deployRoot'));
        $redis->set('deploy.static.dir', Input::get('staticDir'));
        $redis->set('deploy.default.branch', Input::get('defaultBranch'));
        $redis->set('deploy.remote.user', Input::get('remoteUser'));
        $redis->set('deploy.ssh.key', Input::get('sshKey'));
        $redis->set('deploy.ssh.key.phrase', Input::get('keyPhrase'));
        $redis->set('deploy.service.name', Input::get('serviceName'));
        $redis->set('deploy.remote.app.dir', Input::get('remoteAppDir'));
        $redis->set('deploy.remote.static.dir', Input::get('remoteStaticDir'));
        $redis->set('deploy.build.command', Input::get('buildCommand'));
        $redis->set('deploy.dist.command', Input::get('distributeCommand'));
        Session::put('save_ok', true);

        return Redirect::to('/config');
    }

    public function hostConfig()
    {
        return View::make('hostconfig');
    }
}
