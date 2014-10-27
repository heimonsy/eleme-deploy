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

Route::get('/', function()
{
	return View::make('hello');
});

Route::get('/test', function(){
    $array = array('test', 'haha', 'hehe'); 
    Debugbar::info($array);
    return View::make('test', array('hello' => 'hello i\'m twig'));
});

Route::get('/config', 'ConfigController@config');

Route::get('/deploy', 'DeployController@index');

Route::get('/host/config', 'ConfigController@hostConfig');

Route::post('/config/save', 'ConfigController@saveConfig');

Route::get('/host/add', 'ConfigController@hostAdd');

Route::post('/host/del', 'ConfigController@hostDel');


Route::post('/deploy/branch', 'DeployController@branch');
Route::post('/deploy/commit', 'DeployController@commit');
Route::get('/deploy/status', 'DeployController@status');