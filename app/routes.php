<?php

App::before(function($request) {
    $preg = '/^\/github\/oauth|\/logout|\/playload/';
    $check =  GithubLogin::check();
    if (!$check && preg_match($preg, $request->getRequestUri()) == 0) {
        return Redirect::to('/github/oauth/confirm');
    } elseif ($check) {
        $user = GithubLogin::getLoginUser();
        View::share('login', $user->login);
    }
});

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::post('/playload', function() {
    if (Request::header('X-GitHub-Event') == 'pull_request') {
        $content = file_get_contents('php://input');
        $notifyObject = json_decode($content);
        $repoFullName = $notifyObject->pull_request->base->repo->full_name;
        $siteId = WebSite::getSiteIdByFullName($repoFullName);
        $commit = $notifyObject->pull_request->head->sha;
        $pullNumber = $notifyObject->pull_request->number;
        if ($siteId !== NULL) {
            $pr = new PullRequest($siteId);
            $have = $pr->get($commit);
            if ($have !== NULL) {
                if ($notifyObject->action == 'closed') {
                    $mgb = $notifyObject->pull_request->merged_by;
                    $have->mergedBy = empty($mgb) ? '' : $mgb->login;
                    $commitList = $pr->getListByPRId($notifyObject->pull_request->id);
                    foreach ($commitList as $m) {
                        $m->status = 'closed';
                        $pr->save($m);
                    }
                } elseif ($notifyObject->action == 'reopened') {
                    $commitList = $pr->getListByPRId($notifyObject->pull_request->id);
                    foreach ($commitList as $m) {
                        $m->status = 'open';
                        $pr->save($m);
                    }
                }
                $have->status = $notifyObject->pull_request->state;
                $pr->save($have);
            } else {
                //Queue::push('PullRequestBuild', array('siteId' => $siteId, 'commit' => $commit), DeployInfo::PR_BUILD_QUEUE);
                $class = Config::get('worker.queue.prbuild');
                \Eleme\Worker\Supervisor::push($class, array('siteId' => $siteId, 'commit' => $commit, 'pullNumber' => $pullNumber), 'prbuild');
                $pr->add($notifyObject);
            }
        }
    }

    return  "";
});

Route::get('/github/oauth/confirm', function() {
    if (GithubLogin::check()) {
        return Redirect::to('/');
    }

    return Response::view('oauth-confirm');
});

Route::get('/github/oauth', function() {
    if (GithubLogin::check()) {
        return Redirect::to('/');
    }

    return Redirect::to(GithubLogin::authorizeUrl());
});

Route::get('/logout', function() {
    $cookie = GithubLogin::logout();

    return Redirect::to('/github/oauth/confirm')->withCookie($cookie);
});

Route::get('/github/oauth/callback', function() {
    if (GithubLogin::check()) {
        return Redirect::to('/');
    }

    $code = Input::get('code');
    if ($code == '') {
        return 'CODE ERROR';
    }

    $accessToken = \Eleme\Github\GithubAuthorize::accessToken($code);
    if ($accessToken == NULL) {
        echo "CODE ERROR";
    }
    $client = new \Eleme\Github\GithubClient($accessToken);
    $teams = $client->request('user/teams');
    $haveEleme = false;
    $orgTeams = array();
    foreach ($teams as $team) {
        if ($team->organization->login == Config::get('github.organization')) {
            $haveEleme = true;
            $orgTeams[] = $gt = new GithubTeam($team);
            TeamRepos::delByTeamId($gt->id);
        }
    }

    if ($haveEleme) {
        $user = $client->request('user');
        $email = isset($user->email) ? $user->email : '';
        $cookie = GithubLogin::login($user->login, $email, $accessToken, $orgTeams);

        return Redirect::to('/')->withCookie($cookie);
    } else {
        return "ORG ERROR";
    }
});

Route::get('/user/permissions/refresh', function () {
    $user = GithubLogin::getLoginUser();
    $user->sitePermission();
    GithubLogin::sessionUser($user);

    return Redirect::to('/');
});

Route::get('/user/team/repos', function() {
    $repos = array();
    $user = GithubLogin::getLoginUser();
    foreach ($user->teams as $team) {
        $repos = array_merge($repos, (new TeamRepos($team->id, $user->token))->repos());
    }
    //var_dump($repos);
    return Response::json(array('res' => 0, 'data' => $repos));
});

Route::get('/', 'SystemController@index');

Route::post('/system/config/save', 'SystemController@systemConfig');

Route::post('/hostType/add', 'SystemController@addHostType');
Route::post('/hostType/del', 'SystemController@delHostType');

Route::post('/site/add', 'SystemController@addSite');
Route::post('/site/del', 'SystemController@delSite');

Route::post('/config/save', 'ConfigController@saveConfig');

Route::get('/test', function() {
    //Mail::send('emails.deploy', array('siteId' => 'web2', 'status' => 'Success', 'hostType' => 'testing', 'commit' => 'f548a32fd929500d28966d60c432d833c0391167', 'repoName' => 'heimonsy/eleme-deploy'), function($message)
    //{
        //$message->to('heimonsy@gmail.com', 'Heimonsy')->subject('[TEST] Deploy Success!');
        //$message->cc('250661062@qq.com')->cc('hongbo.tang@ele.me');
    //});
    //\Eleme\Worker\Report\WorkerReport::clearPids();
    //return 'hehe';
    //$class = Config::get('worker.queue.build');
    //\Eleme\Worker\Supervisor::push($class, array('test' => 'test'), 'build');
    //$class = Config::get('worker.queue.prbuild');
    //\Eleme\Worker\Supervisor::push($class, array('test' => 'test'), 'prbuild');
    //$class = Config::get('worker.queue.deploy');
    //\Eleme\Worker\Supervisor::push($class, array('test' => 'test'), 'deploy');
    return '<hr>hehe';
});

Route::get('/clear', function() {
    //$client = new \GuzzleHttp\Client();
    //$res = $client->get('');
    //$client = new Eleme\Github\GithubClient('ad9ea7efa56f8cb8c780058622058e43f48a39f2');
    //$content = $client->request('teams/991232/repos?page=1');
    //$response = $client->getResponse();
    //$header = $response->getHeader('Link');
    //preg_match('/<(.+)>; rel="next"/', $header, $matchs);
    //var_dump($matchs);
    return '<hr>hehe';
});

Route::get('/process', function() {
    //(new Symfony\Component\Process\Process('ssh-keygen -R github.com'))->mustRun();
    //(new Symfony\Component\Process\Process('git clone root@arch:~/hehe /home/vagrant/deploy/deploy/branch/default --depth 20'))->mustRun();
    //
    //$cmd = 'clone git@github.com:heimonsy/deploy-test-develop.git /home/vagrant/deploy/deploy/branch/default --depth 20';
    //$gp = new Eleme\Worker\GitProcess($cmd, '/tmp/', '/var/www/.ssh/github.bak');
    //$gp->mustRun();

    return 'hehe';
});

Route::get('/site/config/{siteId}', 'ConfigController@config');

Route::get('/deploy/{siteId}', 'DeployController@index');
Route::get('/deploy/info/logs', 'DeployController@logs');

Route::get('/host/config/{siteId}', 'ConfigController@hostConfig');
Route::post('/host/add', 'ConfigController@hostAdd');
Route::post('/host/del', 'ConfigController@hostDel');

Route::post('/branch/deploy', 'DeployController@branch');
Route::post('/commit/deploy', 'DeployController@commit');
Route::get('/status/deploy', 'DeployController@status');

// PR
Route::get('/{siteId}/pull_request/info', 'PullRequestController@info');
Route::get('/{siteId}/pull_request/deploy', 'PullRequestController@deploy');
Route::get('/{siteId}/status/pull_request/build', 'PullRequestController@buildStatus');
Route::post('/{siteId}/pull_request/rebuild', 'PullRequestController@rebuild');
Route::post('/{siteId}/pull_request/deploy', 'PullRequestController@toDeploy');
Route::get('/{siteId}/status/pull_request/deploy', 'PullRequestController@deployStatus');

// job
Route::get('/workers', 'JobController@index');
Route::get('/worker/process.json', 'JobController@process');
Route::post('/worker/clear-no-response', 'JobController@clearNoResponse');
Route::post('/worker/new', 'JobController@newWorker');
Route::post('/worker/shutdown', 'JobController@shutdownProcess');

// watch
Route::post('/sites/{siteId}/watch', 'SystemController@watch');
Route::post('/sites/{siteId}/notwatch', 'SystemController@notWatch');
