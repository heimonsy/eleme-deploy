<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/11/14
 * Time: 1:15 PM
 */


class BaseController extends Controller
{
    protected $validSites;

    public function __construct()
    {
        $sites = (new WebSite())->getList();
        $user = GithubLogin::getLoginUser();
        $validSites = array();
        $adminSites = array();
        foreach ($sites as $m) {
            if (!empty($user->permissions[$m['siteId']])) {
                $validSites[] = $m;
                if (DeployPermissions::havePermission(DeployPermissions::WRITE, $user->permissions[$m['siteId']])) {
                    $adminSites[] = $m['siteId'];
                }
            }
        }

        $isSuperUser = false;
        $i = 0;
        while (!empty($_ENV["SUPER_USERS.{$i}"])) {
            if ($_ENV["SUPER_USERS.{$i}"] == $user->login) {
                $isSuperUser = true;
                break;
            }
            $i++;
        }

        $this->validSites = $validSites;
        View::share('sites', $validSites);
        View::share('isSuperUser', $isSuperUser);
        View::share('adminSites', $adminSites);
    }
}
