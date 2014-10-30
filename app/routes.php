<?php

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

Route::get('/', 'SystemController@index');

Route::post('/hostType/add', 'SystemController@addHostType');
Route::post('/hostType/del', 'SystemController@delHostType');

Route::post('/site/add', 'SystemController@addSite');
Route::post('/site/del', 'SystemController@delSite');

Route::post('/config/save', 'ConfigController@saveConfig');

Route::get('/test', function(){

//    $revParseProcess = new \Symfony\Component\Process\Process('cd /home/vagrant/heimonsy-develop/web-deploy2.eleme.local/ && git branch');
//    $revParseProcess->mustRun();
//    if ($revParseProcess->isSuccessful()) {
//        echo $revParseProcess->getOutput() . '  hehe';
//    }

    var_dump(unserialize('siteId=web1&deployRoot=%2Fhome%2Fvagrant%2Fdeploy%2F&staticDir=&rsyncExclude=&defaultBranch=&remoteUser=&remoteOwner=&serviceName=php5-fpm&remoteAppDir=&remoteStaticDir=&buildCommand=build+deploy&staticHostScript=%40after%3Aremote%0D%0A%40after%3Alocal&webHostScript='));
    exit();
    $str = <<<EOT

@before:remote
ls {{root}}
git branch
sudo service {{serviceName}} start

@after:remote
sudo service {{serviceName}} stop
ll

@remote

@after:remote
dir
date

@before:remote
EOT;
    try {
        $list = ScriptCommand::complie($str, 'web1');
        Debugbar::info($list);

    } catch(Exception $e){

        echo $e->getMessage();
    }

    return "---";
});



Route::get('/site/config/{siteId}', 'ConfigController@config');

Route::get('/deploy/{siteId}', 'DeployController@index');

Route::get('/host/config/{siteId}', 'ConfigController@hostConfig');
Route::post('/host/add', 'ConfigController@hostAdd');
Route::post('/host/del', 'ConfigController@hostDel');






Route::post('/deploy/branch', 'DeployController@branch');
Route::post('/deploy/commit', 'DeployController@commit');
Route::get('/deploy/status', 'DeployController@status');