<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-30
 * Time: 下午2:11
 */

class JobLock
{
    private static $BUILD_LOCK_PREFIX = "DEPLOY:LOCK:BUILD:";
    private static $DEPLOY_LOCK_PREFIX = "DEPLOY:LOCK:BUILD:";

    /**
     * 不同的项目，使用$commitPath目录作为build锁
     * @param $commitPath
     * @return string
     */
    public static function buildLock($commitPath)
    {
        return  self::$BUILD_LOCK_PREFIX . $commitPath;
    }
    
}