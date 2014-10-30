<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-29
 * Time: 下午5:39
 */

class SiteHost
{
    // host的两种类型
    const STATIC_HOST = 'STATIC';
    const WEB_HOST = 'WEB';

    private $prefix = array(
        self::STATIC_HOST => 'DEPLOY:L:STATIC:HOST:',
        self::WEB_HOST    => 'DEPLOY:L:WEB:HOST:'
    );

    private $redis;
    private $type;
    private $hostType;
    private $siteId;

    public function key()
    {
        return $this->prefix[$this->type].$this->hostType.':'.$this->siteId;
    }

    /**
     * @param $siteId
     * @param $hostType
     * @param $type string
     */
    public function __construct($siteId, $hostType, $type)
    {
        $this->hostType = $hostType;
        $this->siteId = $siteId;
        $this->type =  $type;
        $this->redis = app('redis')->connection();
    }

    public function getList()
    {
        $jsonArray = $this->redis->lrange($this->key(), 0, -1);
        $res = array();
        foreach ($jsonArray as $m) {
            $res[] = json_decode($m, true);
        }
        return $res;
    }

    public function add($host)
    {
        return $this->redis->lpush($this->key(), $this->jsonValue($host));
    }

    public function remove($host)
    {
        return $this->redis->lrem($this->key(), 1, $this->jsonValue($host));
    }

    private function jsonValue($value) {
        if (is_string($value)) {
            $site = json_decode($value);
        }
        ksort($value);
        return json_encode($value);
    }
}