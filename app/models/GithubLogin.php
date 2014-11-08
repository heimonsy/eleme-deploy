<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/8/14
 * Time: 2:07 PM
 */

use Eleme\Github\GithubAuthorize;

class GithubLogin
{
    private static $loginUser;
    private static $sessionKey = 'LOGIN_USER';
    private static $cookieKey  = 'ELEME_DEPLOY_LOGIN';

    public static function check()
    {
        if (Session::get(self::$sessionKey) == NULL) {
            // login from cookie
            $login = Cookie::get(self::$cookieKey);
            if ($login != NULL) {
                $user = GithubUser::loadFromRedis($login);
                //var_dump($user);
                if ($user != NULL) {
                    self::sessionUser($user);
                    return true;
                }
            }
            return false;
        }
        return true;
    }

    private static function sessionUser(GithubUser $user)
    {
        Session::set(self::$sessionKey, $user->json());
    }


    /**
     * login from auth
     * @param $login
     * @param $email
     * @param $token
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public static function login($login, $email, $token, $teams)
    {
        $expire = 60*60*24*5;

        $user = new GithubUser($login, $email, $token, $teams);
        self::sessionUser($user);

        $user->set();

        return Cookie::make(self::$cookieKey, $login, $expire / 60);
    }

    public static function logout()
    {
        Session::flush();
        return Cookie::forget(self::$cookieKey);
    }


    public static function getLoginUser()
    {
        if (empty(self::$loginUser)) {
            $jstr = Session::get(self::$sessionKey);
            self::$loginUser = GithubUser::loadFromJson($jstr);
        }
        return self::$loginUser;
    }

    public static function authorizeUrl()
    {
        return GithubAuthorize::authorizeUrl(Config::get('github.scope'));
    }
}