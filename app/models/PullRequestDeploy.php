<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/13/14
 * Time: 5:16 PM
 */

class PullRequestDeploy
{
    private $storeKeyPrefix = "DEPLOY:H:PR:DEPLOY:";
    private $listKeyPrefix = "DEPLOY:L:PR:DEPLOY:";
    private $idKeyPrefix = 'DEPLOY:INT:PR:DEPLOY:ID:';
    private $siteId;
    private $redis;

    public function __construct($siteId)
    {
        $this->siteId = $siteId;
        $this->redis = app('redis')->connection();
    }

    public function get($ids)
    {
        if (is_array($ids)) {
            if (count($ids) == 0) {
                $ids = array(0);
            }

            $res = $this->redis->hmget($this->storeKey(), $ids);
            $list = array();
            foreach ($res as $m) {
                if ($m == NULL) continue;
                $list[] = PullRequestDeployInfo::createFromJson($m);
            }
            return $list;
        }
        $res = $this->redis->hget($this->storeKey(), $ids);
        return $res == NULL ? NULL : PullRequestDeployInfo::createFromJson($res);
    }

    public function getList()
    {
        $ids = $this->redis->lrange($this->listKey(), 0, 30);
        return $this->get($ids);
    }

    public function add($prId, $prTitle, $commit, $prUser, $operateUser, $hostType, $time, $updateTime, $status)
    {
        $id = $this->redis->incr($this->idKey());
        $pr = new PullRequestDeployInfo($id, $prId, $prTitle, $commit, $prUser, $operateUser, $hostType, $time, $updateTime, $status);
        $this->redis->hset($this->storeKey(), $id, $pr->json());
        $this->redis->lpush($this->listKey(), $id);
        return $pr;
    }

    public function save($pr)
    {
        return $this->redis->hset($this->storeKey(), $pr->id, $pr->json());
    }

    public function storeKey()
    {
        return $this->storeKeyPrefix . $this->siteId;
    }

    public function listKey()
    {
        return $this->listKeyPrefix . $this->siteId;
    }

    public function idKey()
    {
        return $this->idKeyPrefix . $this->siteId;
    }
}
