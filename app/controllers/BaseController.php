<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/11/14
 * Time: 1:15 PM
 */


class BaseController extends Controller
{
    public function __construct()
    {
        $sites = (new WebSite())->getList();
        $user = GithubLogin::getLoginUser();
        $validSites = array();
        foreach ($sites as $m) {
            if (!empty($user->permissions[$m['siteId']])) {
                $validSites[] = $m;
            }
        }
        View::share('sites', $validSites);
    }
}