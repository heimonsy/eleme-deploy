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

        return View::make('config');
    }

    public function hostConfig()
    {
        return View::make('hostconfig');
    }
}