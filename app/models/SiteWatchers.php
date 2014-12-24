<?php

class SiteWatchers
{

    const KEY_PREFIX = 'DEPLOY:S:SITE:WATCHERS:';
    private $key;
    private $redis;

    public function __construct($siteId)
    {
        $this->key = self::KEY_PREFIX . $siteId;
        $this->redis = app('redis')->connection();
    }

    public function add($login)
    {
        return $this->redis->sadd($this->key, $login);
    }

    public function remove($login)
    {
        return $this->redis->srem($this->key, $login);
    }

    public function all()
    {
        return $this->redis->smembers($this->key);
    }
}
