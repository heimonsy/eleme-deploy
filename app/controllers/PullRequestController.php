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
        return View::make('pullrequest.deploy', array(
            'siteId' => $siteId,
            'leftNavActive' => 'pullRequestDeploy',
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

}