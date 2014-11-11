<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/10/14
 * Time: 9:06 PM
 */

class DeployPermissions
{
    const ADMIN = 'admin';
    const WRITE = 'write';
    const PULL = 'pull';
    const DENY = 'deny';

    private static $level = array(
        self::ADMIN => 3,
        self::WRITE => 2,
        self::PULL  => 1,
        self::DENY  => -100000,
    );

    public static function havePermission($base, $compare)
    {
        return self::$level[$compare] >= self::$level[$base];
    }
}