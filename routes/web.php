<?php

use Illuminate\Support\Facades\Route;

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

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/reindex', 'LibrisController@reindex');
Route::get('/everything', 'LibrisController@everything');
Route::get('/delete', 'LibrisController@deleteIndex');
Route::get('/update', 'LibrisController@updateIndex');
Route::get('/system/{system}', 'LibrisController@allBySystem')->name("system.index");
Route::get('/systemGrid/{system}', 'LibrisController@allBySystemGrid')->name("system.index");

Route::get('/', 'LibrisController@home')->name("home");

Route::get('/search', 'LibrisController@search')->name("search");
