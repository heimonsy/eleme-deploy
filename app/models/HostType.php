<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-28
 * Time: 下午9:15
 */


class HostType
{
    private $redis;
    private $key = 'deploy:S:host:types';

    public function __construct()
    {
        $this->redis = app('redis')->connection();
    }

    public function getList()
    {
        return $this->redis->smembers($this->key);
    }

    public function add($value)
    {
        return $this->redis->sadd($this->key, $value);
    }

    public function remove($value)
    {
        return $this->redis->srem($this->key, $value);
    }
}