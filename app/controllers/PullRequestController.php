<?php

use Eleme\Worker\Supervisor;
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/12/14
 * Time: 11:02 AM
 */

class PullRequestController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function info($siteId)
    {

        return View::make('pullrequest.info', array(
            'siteId' => $siteId,
            'pullRequests' => (new PullRequest($siteId))->getList(),
            'leftNavActive' => 'pullRequestInfo',
        ));
    }

    public function deploy($siteId)
    {
        $pr = new PullRequest($siteId);
        $commits = $pr->getList();
        $commitList = array();
        foreach ($commits as $m) {
            if ($m->testStatus == 'Success' && $m->status == 'open') {
                $commitList[] = array(
                    'title' => $m->title,
                    'commit' => $m->commit,
                );
            }
        }
        $ht = new HostType();
        $hostTypes = $ht->getList();

        $hostList = array();
        foreach ($hostTypes as $hostType) {
            $hostList = array_merge($hostList, (new SiteHost($siteId, $hostType, SiteHost::STATIC_HOST))->getList());
            $hostList = array_merge($hostList, (new SiteHost($siteId, $hostType, SiteHost::WEB_HOST))->getList());
        }
        $existHostTypes = array();
        $user = GithubLogin::getLoginUser();
        $hp = (new HostType())->permissionList();

        $deployPermissions = array();
        foreach ($_ENV as $key => $value) {
            if (strpos($key, 'DEPLOY_PERMISSIONS') === 0) {
                $deployPermissions[substr($key, strlen('DEPLOY_PERMISSIONS') + 1)] = $value;
            }
        }

        $userTeams = array();
        foreach ($user->teams as $team) {
            $userTeams[] = $team->name;
        }

        foreach ($hostList as $host) {
            //Debugbar::info($user->permissions[$siteId] . ' ' . $hp[$host['hosttype']]);
            $canDeploy = true;
            if (!empty($deployPermissions[$host['hosttype']])) {
                $teamName = $deployPermissions[$host['hosttype']];
                if (!in_array($teamName, $userTeams)) {
                    $canDeploy = false;
                }
            }
            if ($canDeploy && !empty($user->permissions[$siteId]) &&
                DeployPermissions::havePermission($hp[$host['hosttype']], $user->permissions[$siteId])) {
                $existHostTypes[$host['hosttype']] = $host['hosttype'];
            }
        }

        $pr = new PullRequest($siteId);
        $list = $pr->getList();
        $prList = array();
        foreach ($list as &$m) {
            $prList[$m->commit] = array(
                'url' => $m->url,
                'repo' => $m->repo,
                'branch' => $m->branch,
            );
        }

        $prd = new PullRequestDeploy($siteId);
        $prdList = $prd->getList();

        return View::make('pullrequest.deploy', array(
            'siteId' => $siteId,
            'leftNavActive' => 'pullRequestDeploy',
            'commitList' =>  $commitList,
            'hostTypeList' => $existHostTypes,
            'toDeploy' => Input::get('toDeploy'),
            'prdList' => $prdList,
            'prList' => $prList,
        ));
    }


    public function buildStatus($siteId)
    {
        $pr = new PullRequest($siteId);
        $commits = Input::get('commits');
        $prCommits = $pr->get($commits);
//        $prCommits[0]->buildStatus = 'Success';
//        $prCommits[0]->testStatus = 'Error';
//        $prCommits[0]->errorMsg = 'test';

        return Response::json(array(
            'res' => 0,
            'commits' => $prCommits,
        ));
    }

    public function rebuild($siteId)
    {
        $commit = Input::get('commit');
        $pr = new PullRequest($siteId);
        $prCommit = $pr->get($commit);
        if ($prCommit->testStatus == 'Error' ||  $prCommit->buildStatus == 'Error') {
            $prCommit->testStatus = 'Waiting';
            $prCommit->errorMsg = NULL;
            $prCommit->buildStatus = 'Waiting';
            $res = 0;
            $info = '';
            //Queue::push('PullRequestBuild', array('siteId' => $siteId, 'commit' => $commit), DeployInfo::PR_BUILD_QUEUE);
            $class = Config::get('worker.queue.prbuild');
            Supervisor::push($class, array('siteId' => $siteId, 'commit' => $commit, 'pullNumber' => $prCommit->pullNumber), 'prbuild');
            $pr->save($prCommit);
        } else {
            $res = 1;
            $info = '当前Commit的状态不可Rebuild';
        }
        return Response::json(array(
            'res' => $res,
            'info' => $info
        ));
    }

    public function toDeploy($siteId)
    {
        $commit = Input::get('commit');
        $hostType = Input::get('remote');
        $date = date('Y-m-d H:i:s');

        $user = GithubLogin::getLoginUser();

        $pr = new PullRequest($siteId);
        $prCommit = $pr->get($commit);
        $prd = new PullRequestDeploy($siteId);
        $pri = $prd->add($prCommit->prId, $prCommit->title, $commit, $prCommit->user, $user->login, $hostType, $date, $date, 'Waiting');

        //Queue::push('DeployCommit', array('id' => $pri->id, 'type' => DeployCommit::TYPE_PULL_REQUEST, 'hostType' => $hostType, 'siteId' => $siteId, 'commit' => $commit), DeployInfo::DEPLOY_QUEUE);
        $class = Config::get('worker.queue.deploy');
        Supervisor::push($class, array(
            'siteId' => $siteId,
            'commit' => $commit,
            'type' => DeployCommit::TYPE_PULL_REQUEST,
            'hostType' => $hostType,
            'id' => $pri->id
        ), 'deploy');

        return Response::json(array('res' => 0));
    }

    public function deployStatus($siteId)
    {
        $ids = Input::get('ids');
        $prd = new PullRequestDeploy($siteId);
        $priList = $prd->get($ids);

        return Response::json(array(
            'res' => 0,
            'infos' => $priList,
        ));
    }

}
