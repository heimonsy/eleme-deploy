<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/12/14
 * Time: 11:43 AM
 */

class PullRequestCommit
{
    public $prId;
    public $title;
    public $user;
    public $repo;
    public $branch;
    public $commit;
    public $pullNumber;
    public $createAt;
    public $lastUpdateAt;
    public $buildStatus;
    public $status;
    public $url;
    public $mergedBy;
    public $testStatus;
    public $errorMsg;

    public function __construct($prId, $title, $user, $repo, $branch, $commit, $pullNumber, $createAt, $lastUpdateAt,
        $buildStatus, $status, $url, $testStatus, $errorMsg, $mergedBy = '')
    {
        $this->prId = $prId;
        $this->title = $title;
        $this->user = $user;
        $this->repo = $repo;
        $this->branch = $branch;
        $this->commit = $commit;
        $this->pullNumber = $pullNumber;
        $this->createAt = $createAt;
        $this->lastUpdateAt = $lastUpdateAt;
        $this->buildStatus = $buildStatus;
        $this->status = $status;
        $this->url = $url;
        $this->mergedBy = $mergedBy;
        $this->testStatus = $testStatus;
        $this->errorMsg = $errorMsg;
    }

    public function json()
    {
        return json_encode($this);
    }

    public static function createFromJson($json)
    {
        $o = json_decode($json);
        return new PullRequestCommit(
            $o->prId, $o->title, $o->user, $o->repo, $o->branch, $o->commit, $o->pullNumber, $o->createAt,$o->lastUpdateAt, $o->buildStatus,
            $o->status, $o->url, $o->testStatus, $o->errorMsg,$o->mergedBy
        );
    }
}


class PullRequest
{
    private $redis;
    private $storeKeyPrefix = 'DEPLOY:H:PULL:REQUEST:COMMITS:';
    private $listKeyPrefix = 'DEPLOY:L:PULL:REQUEST:COMMITS:';
    private $relationKeyPrefix = 'DEPLOY:S:PULL:REQUEST:TO:COMMITS:';
    private $prListKeyPrefix = 'DEPLOY:L:PR:';
    private $siteId;

    public function __construct($siteId)
    {
        $this->redis = app('redis')->connection();
        $this->siteId = $siteId;
    }

    public function getListByPRId($id)
    {
        $commits = $this->redis->smembers($this->relationKey($id));
        return $this->get($commits);
    }

    public function add(&$jsonObject)
    {
        $pr = $jsonObject->pull_request;

        $mergedBy = empty($pr->merged_by) ? '' : $pr->merged_by->login;

        $date = date('Y-m-d H:i:s');
        $pro = new PullRequestCommit($pr->id, $pr->title, $pr->user->login, $pr->head->repo->full_name, $pr->head->ref,
            $pr->head->sha, $pr->number, $date, $date, 'Waiting', $pr->state, $pr->html_url, 'Waiting', NULL, $mergedBy);

        $this->redis->hset($this->storeKey(), $pr->head->sha, $pro->json());
        $this->redis->lpush($this->listKey(), $pr->head->sha);
        $this->redis->sadd($this->relationKey($pr->id), $pr->head->sha);
        $score = $this->redis->zscore($this->prListKey(), $pr->id);
        if ($score === null) {
            $this->redis->zadd($this->prListKey(), time(), $pr->id);
        }
        return $pro;
    }

    public function getList()
    {
        $commit = $this->redis->lrange($this->listKey(), 0, 30);
        return $this->get($commit);
    }

    public function get($commits)
    {
        if (is_array($commits)) {
            if (count($commits) == 0) {
                $commits = array(0);
            }
            $res = $this->redis->hmget($this->storeKey(), $commits);
            $list = array();
            foreach ($res as $m) {
                if ($m == NULL) continue;
                $list[] = PullRequestCommit::createFromJson($m);
            }
            return $list;
        }

        $res = $this->redis->hget($this->storeKey(), $commits);
        return $res == NULL ? NULL : PullRequestCommit::createFromJson($res);
    }

    public function save(PullRequestCommit $o)
    {
        return $this->redis->hset($this->storeKey(), $o->commit, $o->json());
    }

    private function storeKey()
    {
        return $this->storeKeyPrefix . $this->siteId;
    }
    private function listKey()
    {
        return $this->listKeyPrefix . $this->siteId;
    }

    private function relationKey($prId)
    {
        return $this->relationKeyPrefix . $this->siteId . ':' . $prId;
    }

    private function prListKey()
    {
        return $this->prListKeyPrefix . $this->siteId;
    }

    public function clearList()
    {
        $max = (1 << 31) - 1;
        $count = $this->redis->zcount($this->prListKey(), 0, $max);
        if ($count > 20) {
            $res = $this->redis->zrange($this->prListKey(), 0, $count - 20 - 1);
            foreach ($res as $pr) {
                $this->redis->del($this->relationKey($pr));
            }
            $this->redis->zremrangebyrank($this->prListKey(), 0, $count - 20 - 1);
        } elseif ($count === 0) {
            // 兼容以前的情况，将所有的pr relation全部删除
            $res = $this->redis->keys($this->relationKey('*'));
            foreach ($res as $key) {
                $this->redis->del($key);
            }
        }

        $len = $this->redis->llen($this->listKey());
        if ($len > 30) {
            $cut = $len - 30;
            $ids = $this->redis->lrange($this->listKey(), -$cut, -1);
            $this->redis->ltrim($this->listKey(), 0, 29);
            if (empty($ids)) {
                $ids = array(-1);
            }
            $this->redis->hdel($this->storeKey(), $ids);
            return $ids;
        }
        return array();
    }
}
