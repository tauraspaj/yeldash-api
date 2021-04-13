<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    //root
});

$router->post('user/auth', 'AuthController@login');

$router->group(['middleware' => 'auth'], function () use ($router) {
    $router->post('user/logout', 'AuthController@logout');
    
    $router->get('users', 'UserController@index');
    $router->get('user/myProfile', 'UserController@myProfile');
    $router->post('user/update', 'UserController@update');
    $router->post('users', 'UserController@create');
    // $router->delete('users/{userId}', 'UserController@index');

    $router->get('devices/summary', 'DeviceController@deviceList');
    
    $router->group(['middleware' => 'deviceMiddleware'], function () use ($router) {
        $router->get('devices/{deviceId}', 'DeviceController@show');
        $router->put('devices/{deviceId}/customise', 'DeviceController@updateCustomise');
    });
});
