<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-30
 * Time: 上午10:31
 */

class DeployInfo
{
    const BUILD_QUEUE  = 'DEPLOY:QUEUE:BUILD';
    const DEPLOY_QUEUE = 'DEPLOY:QUEUE:DEPLOY';
    const PR_BUILD_QUEUE = 'DEPLOY:QUEUE:PR:BUILD';

    private $listKey = 'DEPLOY:L:INFO:IDS:';
    private $infoKey = 'DEPLOY:H:INFO:';
    private $incrKey = 'DEPLOY:INFO:ID:';
    private $redis;

    public function __construct($siteId)
    {
        $this->redis = app('redis')->connection();
        $this->listKey = $this->listKey . $siteId;
        $this->infoKey = $this->infoKey . $siteId;
        $this->incrKey = $this->incrKey . $siteId;
    }

    public function add($deploy)
    {
        $deploy['id'] = $this->newId();
        $this->redis->lpush($this->listKey, $deploy['id']);
        $this->redis->hset($this->infoKey, $deploy['id'], $this->jsonValue($deploy));
        return $deploy['id'];
    }

    /**
     * @param array|int $ids<p>
     * @return mixed
     */
    public function get($ids)
    {
        if (is_array($ids)) {
            if (count($ids) == 0) {
                $ids = array(0);
            }
            $res = $this->redis->hmget($this->infoKey, $ids);
            $list = array();
            foreach ($res as $m) {
                if ($m == NULL) continue;
                $list[] = json_decode($m, true);
            }
            return $list;
        }

        $res = $this->redis->hget($this->infoKey, $ids);
        return $res == NULL ? NULL : json_decode($res, true);
    }

    public function save($deploy)
    {
        return $this->redis->hset($this->infoKey, $deploy['id'], $this->jsonValue($deploy));
    }

    public function getList()
    {
        $ids = $this->redis->lrange($this->listKey, 0, 30);
        return $this->get($ids);
    }

    private function jsonValue($value) {
        if (is_string($value)) {
            $site = json_decode($value);
        }
        ksort($value);
        return json_encode($value);
    }

    private function newId()
    {
        return $this->redis->incr($this->incrKey);
    }


    public function clearList()
    {
        $len = (int) $this->redis->llen($this->listKey);
        if ($len > 30) {
            $cut = $len - 30;
            $ids = $this->redis->lrange($this->listKey, -$cut, -1);
            $this->redis->ltrim($this->listKey, 0, 29);
            if (empty($ids)) {
                $ids = array(-1);
            }
            $this->redis->hdel($this->infoKey, $ids);
        }
    }
}
