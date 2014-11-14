<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/13/14
 * Time: 5:17 PM
 */

class PullRequestDeployInfo
{
    public $id;
    public $prId;
    public $prTitle;
    public $commit;
    public $prUser;
    public $operateUser;
    public $hostType;
    public $time;
    public $updateTime;
    public $status;
    public $errorMsg;

    public function __construct($id, $prId, $prTitle, $commit, $prUser, $operateUser, $hostType, $time, $updateTime, $status, $errorMsg = NULL)
    {
        $this->id = $id;
        $this->prId = $prId;
        $this->prTitle = $prTitle;
        $this->commit = $commit;
        $this->prUser = $prUser;
        $this->operateUser = $operateUser;
        $this->time = $time;
        $this->updateTime = $updateTime;
        $this->status = $status;
        $this->hostType = $hostType;
        $this->errorMsg = $errorMsg;
    }

    public static function createFromJson($json)
    {
        if (is_string($json)) {
            $json = json_decode($json);
        }

        return new PullRequestDeployInfo($json->id, $json->prId, $json->prTitle, $json->commit, $json->prUser, $json->operateUser,
            $json->hostType, $json->time, $json->updateTime, $json->status, $json->errorMsg);
    }

    public function json()
    {
        return json_encode($this);
    }
}
