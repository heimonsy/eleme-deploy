<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-30
 * Time: 下午6:38
 */


class SystemConfig
{
    const WORK_ROOT_FIELD = 'ROOT';
    private $key = 'DEPLOY:H:SYSTEM:CONFIG';
    private $redis;

    public function __construct()
    {
        $this->redis = app('redis')->connection();
    }

    public function get($field)
    {
        return $this->redis->hget($this->key, $field);
    }

    public function set($field, $value)
    {
        return $this->redis->hset($this->key, $field, $value);
    }

}