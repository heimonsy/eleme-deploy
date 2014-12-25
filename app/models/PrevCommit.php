<?php

class PrevCommit
{
     const KEY_PREFIX = 'DEPLOY:K:PREV:COMMMIT:';
     private $key;
     private $redis;

     public function __construct($siteId, $hostType)
     {
         $this->key = self::KEY_PREFIX . $siteId . ':' . $hostType;
         $this->redis = app('redis')->connection();
     }

     public function set($commit)
     {
         return $this->redis->set($this->key, $commit);
     }

     public function get()
     {
         return $this->redis->get($this->key);
     }
}
