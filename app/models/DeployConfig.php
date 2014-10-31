<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-28
 * Time: 下午8:39
 */



class DeployConfig
{
    const ROOT = 'ROOT';
    const STATIC_DIR = 'STATIC:DIR';
    const DEFAULT_BRANCH = 'DEFUALT:BRANCH';
    const REMOTE_USER = 'REMOTE:USER';
    const SERVICE_NAME = 'SERVICE:NAME';
    const REMOTE_APP_DIR = 'REMOTE:APP:DIR';
    const REMOTE_STATIC_DIR = 'REMOTE:STATIC:DIR';
    const BUILD_COMMAND = 'BUILD:COMMAND';
    const RSYNC_EXCLUDE = 'DEPLOY:RSYNC:EXCLUDE';
    const REMOTE_OWNER = 'REMOTE_OWNER';
    const DEPLOY_STATIC_SCRIPT = 'DEPLOY:STATIC:SCRIPT';
    const DEPLOY_WEB_SCRIPT = 'DEPLOY:WEB:SCRIPT';
    const GIT_ORIGIN = 'GIT:ORIGIN';

    private $prefix = 'deploy:H:config:';
    private $key;
    private $siteId;
    private $redis;


    public function __construct($siteId)
    {
        $this->redis = app('redis')->connection();
        $this->siteId = $siteId;
        $this->key = $this->prefix . $siteId;
    }

    public function get($field)
    {
        return $this->redis->hget($this->key, $field);
    }

    public function set($field, $value)
    {
        return $this->redis->hset($this->key, $field, $value);
    }

    public function getList()
    {
        $root = $this->get(self::ROOT);
        return array(
            'root' => $root,
            'staticDir' => $this->get(self::STATIC_DIR),
            'gitOrigin' => $this->get(self::GIT_ORIGIN),
            'rsyncExclude' => $this->get(self::RSYNC_EXCLUDE),
            'defaultBranch' => $this->get(self::DEFAULT_BRANCH),
            'remoteUser' => $this->get(self::REMOTE_USER),
            'remoteOwner' => $this->get(self::REMOTE_OWNER),
            'serviceName' => $this->get(self::SERVICE_NAME),
            'remoteAppDir' => $this->get(self::REMOTE_APP_DIR),
            'remoteStaticDir' => $this->get(self::REMOTE_STATIC_DIR)
        );
    }

} 