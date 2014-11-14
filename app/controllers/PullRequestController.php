<?php
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
        $hostTypes = $ht->permissionList();
        $hostTypeList = array();
        foreach ($hostTypes as $hostType => $permission) {
            if ($permission == DeployPermissions::PULL) {
                $hostTypeList[] = $hostType;
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
            'hostTypeList' => $hostTypeList,
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
            $pr->save($prCommit);
            $res = 0;
            $info = '';
            Queue::push('PullRequestBuild', array('siteId' => $siteId, 'commit' => $commit), DeployInfo::BUILD_QUEUE);
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

        Queue::push('DeployCommit', array('id' => $pri->id, 'type' => DeployCommit::TYPE_PULL_REQUEST, 'hostType' => $hostType, 'siteId' => $siteId, 'commit' => $commit), DeployInfo::DEPLOY_QUEUE);
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