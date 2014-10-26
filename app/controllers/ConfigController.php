<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-24
 * Time: 下午8:17
 */


class ConfigController extends Controller{

    public function config() {
        return View::make('config');
    }

}