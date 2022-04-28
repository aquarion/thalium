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

Route::get('/', 'LibrisController@home')->name("home");

Route::get('/system/{system}', 'LibrisController@allBySystem')->name("system.index");
Route::get('/systemList/{system}', 'LibrisController@allBySystemList')->name("system.list");

Route::get('/search', 'LibrisController@search')->name("search");


if (App::environment(['local', 'staging'])) {
    Route::get('/debug/thumbnail', 'DebugController@thumbnail')->name("debug.thumbnail");
    Route::get('/everything', 'DebugController@everything');
}
Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
