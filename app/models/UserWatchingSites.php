<?php

class UserWatchingSites
{
    const KEY_PREFIX = 'DEPLOY:S:USER:WATCHING:SITES:';
    private $key;
    private $redis;

    public function __construct($login)
    {
        $this->key = self::KEY_PREFIX . $login;
        $this->redis = app('redis')->connection();
    }

    public function add($siteId)
    {
        return $this->redis->sadd($this->key, $siteId);
    }

    public function remove($siteId)
    {
        return $this->redis->srem($this->key, $siteId);
    }

    public function all()
    {
        return $this->redis->smembers($this->key);
    }
}
