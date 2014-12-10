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
        return $this->redis->zrevrange($this->key, 0, 20);
    }

    public function clearList()
    {
        $max = 1 << 31 - 1;
        $count = $this->redis->zcount($this->key, 0, $max);
        if ($count > 20) {
            $res = $this->redis->zrange($this->key, 0, $count - 20 - 1);
            $this->redis->zremrangebyrank($this->key, 0, $count - 20 - 1);
        } else {
            $res = array();
        }
        return $res;
    }
}
