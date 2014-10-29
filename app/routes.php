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
    $array = array('test', 'haha', 'hehe'); 
    Debugbar::info($array);
    return View::make('test', array('hello' => 'hello i\'m twig'));
});



Route::get('/site/config/{siteId}', 'ConfigController@config');

Route::get('/deploy/{siteId}', 'DeployController@index');

Route::get('/host/config/{siteId}', 'ConfigController@hostConfig');
Route::post('/host/add', 'ConfigController@hostAdd');
Route::post('/host/del', 'ConfigController@hostDel');






Route::post('/deploy/branch', 'DeployController@branch');
Route::post('/deploy/commit', 'DeployController@commit');
Route::get('/deploy/status', 'DeployController@status');