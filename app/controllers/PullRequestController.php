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

}