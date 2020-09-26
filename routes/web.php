<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/index','IndexController@index');
Route::get('/addOpt','IndexController@addMyOpt');
Route::get('/getCodeList','IndexController@getCodeList');
Route::get('/addBuys','IndexController@addBuys');
Route::get('/deleteOpt','IndexController@deleteOpt');

