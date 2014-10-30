<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-30
 * Time: 上午10:31
 */


class CommitVersion
{
    private $key = 'DEPLOY:Z:COMMIT:VERSION:';
    private $redis;
    public function __construct($siteId)
    {
        $this->redis = app('redis')->connection();
        $this->key = $this->key.$siteId;
    }

    public function add($commit)
    {
        return $this->redis->zadd($this->key, time(), $commit);
    }

    public function getList()
    {
        return $this->redis->zrevrange($this->key, 0, 30);
    }
}