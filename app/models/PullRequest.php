<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/12/14
 * Time: 11:43 AM
 */

class PullRequestObject
{
    public $id;
    public $title;
    public $user;
    public $repo;
    public $branch;
    public $lastCommit;
    public $lastUpdateAt;
    public $commitBuild;
    public $statusUpdateAt;
    public $status;
    public $url;
    public $mergedBy;

    public function __construct($id, $title, $user, $repo, $branch, $lastCommit, $createAt, $lastUpdateAt, $commitBuild, $statusUpdateAt, $status, $url, $mergedBy = '')
    {
        $this->id = $id;
        $this->title = $title;
        $this->user = $user;
        $this->repo = $repo;
        $this->createAt = $createAt;
        $this->lastCommit = $lastCommit;
        $this->lastUpdateAt = $lastUpdateAt;
        $this->commitBuild = $commitBuild;
        $this->statusUpdateAt = $statusUpdateAt;
        $this->status = $status;
        $this->url = $url;
        $this->branch = $branch;
        $this->mergedBy = $mergedBy;
    }

    public function json()
    {
        return json_encode($this);
    }

    public static function createFromJson($json)
    {
        $o = json_decode($json);
        return new PullRequestObject(
            $o->id, $o->title, $o->user, $o->repo, $o->branch, $o->lastCommit, $o->createAt,$o->lastUpdateAt, $o->commitBuild, $o->statusUpdateAt,
            $o->status, $o->url, $o->mergedBy
        );
    }
}


class PullRequest
{
    private $redis;
    private $storeKeyPrefix = 'DEPLOY:H:PULL:REQUEST:';
    private $listKeyPrefix = 'DEPLOY:L:PULL:REQUEST:';
    private $siteId;

    public function __construct($siteId)
    {
        $this->redis = app('redis')->connection();
        $this->siteId = $siteId;

    }

    public function add(&$jsonObject)
    {
        $pr = $jsonObject->pull_request;

        $mergedBy = empty($pr->merged_by) ? '' : $pr->merged_by->login;

        $date = date('Y-m-d H:i:s');
        $pro = new PullRequestObject($pr->id, $pr->title, $pr->user->login, $pr->head->repo->full_name, $pr->head->ref,
            $pr->head->sha, $date, $date, 'Waiting', $date, $pr->state, $pr->html_url, $mergedBy);
        $this->redis->hset($this->storeKey(), $pr->id, $pro->json());
        $this->redis->lpush($this->listKey(), $pr->id);
        return $pro;
    }

    public function getList()
    {
        $ids = $this->redis->lrange($this->listKey(), 0, 30);
        return $this->get($ids);
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
                $list[] = PullRequestObject::createFromJson($m);
            }
            return $list;
        }

        $res = $this->redis->hget($this->storeKey(), $ids);
        return $res == NULL ? NULL : PullRequestObject::createFromJson($res);
    }

    public function save(PullRequestObject $o)
    {
        return $this->redis->hset($this->storeKey(), $o->id, $o->json());
    }

    private function storeKey()
    {
        return $this->storeKeyPrefix . $this->siteId;
    }

    private function listKey()
    {
        return $this->listKeyPrefix . $this->siteId;
    }
}