<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-28
 * Time: 下午9:15
 */


class HostType
{
    private $redis;
    private $key = 'deploy:S:host:types';
    private $permissionPrefix = 'DEPLOY:HOST:TYPE:PERMISSION:';

    public function __construct()
    {
        $this->redis = app('redis')->connection();
    }

    public function getList()
    {
        return $this->redis->smembers($this->key);
    }

    public function permissionList()
    {
        $list = $this->getList();
        $res = array();
        foreach ($list as $hostType) {
            $permission = $this->redis->get($this->permissionKey($hostType));
            $res[$hostType] = empty($permission) ? DeployPermissions::PULL : $permission;
        }
        return $res;
    }

    public function add($value, $permission=NULL)
    {
        if ($permission == NULL) $permission = DeployPermissions::PULL;
        $this->redis->sadd($this->key, $value);
        $this->redis->set($this->permissionKey($value), $permission);

        return true;
    }

    private function permissionKey($hostType)
    {
        return $this->permissionPrefix . $hostType;
    }

    public function remove($value)
    {
        $this->redis->del($this->permissionKey($value));
        return $this->redis->srem($this->key, $value);
    }

}