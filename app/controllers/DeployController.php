<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-26
 * Time: 下午7:47
 */

class DeployController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index($siteId)
    {
        $defaultBranch = (new DC($siteId))->get(DC::DEFAULT_BRANCH);
        $commitVersion = (new CommitVersion($siteId))->getList();
        $infoList = (new DeployInfo($siteId))->getList();
        $hostTypes = (new HostType())->getList();

        $hostList = array();
        foreach ($hostTypes as $hostType) {
            $hostList = array_merge($hostList, (new SiteHost($siteId, $hostType, SiteHost::STATIC_HOST))->getList());
            $hostList = array_merge($hostList, (new SiteHost($siteId, $hostType, SiteHost::WEB_HOST))->getList());
        }
        $existHostTypes = array();
        $user = GithubLogin::getLoginUser();
        $hp = (new HostType())->permissionList();
        foreach ($hostList as $host) {
            //Debugbar::info($user->permissions[$siteId] . ' ' . $hp[$host['hosttype']]);
            if (!empty($user->permissions[$siteId]) &&
                DeployPermissions::havePermission($hp[$host['hosttype']], $user->permissions[$siteId])) {
                $existHostTypes[$host['hosttype']] = $host['hosttype'];
            }
        }

        return View::make('deploy.deploy', array(
            'defaultBranch' => $defaultBranch,
            'commit_version' => $commitVersion,
            'results' => $infoList,
            'hostTypes' => $existHostTypes,
            'siteId' => $siteId,
        ));
    }

    // deploy branch
    public function branch() {
        $siteId = Input::get('siteId');
        $branch = Input::get('branch');

        $deploy = array(
            'branch' => $branch,
            'commit' => '---',
            'type'   => 'Build',
            'hostType' => '---',
            'user' => GithubLogin::getLoginUser()->login,
            'time'   => date('Y-m-d H:i:s'),
            'last_time' => '0000-00-00 00:00:00',
            'result' => 'Build Waiting',
        );
        $id = (new DeployInfo($siteId))->add($deploy);

        Queue::push('BuildBranch', array('siteId' => $siteId, 'branch' => $branch, 'id' => $id), DeployInfo::BUILD_QUEUE);
        return Response::json(array('res' => 0));
    }

    //deploy commit
    public function commit() {
        $siteId = Input::get('siteId');
        $commit = Input::get('commit');
        $hostType = Input::get('remote');
        $deploy = array(
            'branch' => '---',
            'commit' => $commit,
            'hosttype'   => $hostType,
            'user' => GithubLogin::getLoginUser()->login,
            'type'   => 'Deploy To ' . $hostType,
            'time'   => date('Y-m-d H:i:s'),
            'last_time' => '0000-00-00 00:00:00',
            'result' => 'Deploy Waiting',
        );
        $id = (new DeployInfo($siteId))->add($deploy);

        Queue::push('DeployCommit', array(
            'siteId' => $siteId,
            'commit' => $commit,
            'hostType' => $hostType,
            'id' => $id
        ), DeployInfo::DEPLOY_QUEUE);

        return Response::json(array('res' => 0));
    }

    //deploy status
    public function status() {
        $ids = Input::get('ids');
        $siteId = Input::get('siteId');
        $hosts = (new DeployInfo($siteId))->get($ids);

        return Response::json(array(
            'res' => 0,
            'hosts' => $hosts,
        ));
    }
} 