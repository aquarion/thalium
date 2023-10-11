<?php

use App\Http\Controllers\Auth;
use App\Http\Controllers\DebugController;
use App\Http\Controllers\LibrisController;
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

Auth::routes();

Route::get('/auth/checkLogin', [Auth\CheckLoginController::class, 'checkLogin']);

Route::get('/', [LibrisController::class, 'home'])->name("home")->middleware('auth');

Route::get('/system/{system}', [LibrisController::class, 'docsBySystem'])->name("system.index")->middleware('auth');
Route::get('/document', [LibrisController::class, 'showDocument'])->name("document.iframe")->middleware('auth');
Route::get('/systemList/{system}', [LibrisController::class, 'docsBySystemList'])->name("system.list")->middleware('auth');

Route::get('/search', [LibrisController::class, 'search'])->name("search")->middleware('auth');


if (App::environment(['local', 'staging'])) {
    Route::get('/debug/thumbnail', [DebugController::class, 'thumbnail'])->name("debug.thumbnail")->middleware('auth:admin');
    Route::get('/debug/system', [DebugController::class, 'system'])->name("debug.system")->middleware('auth:admin');
}

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home')->middleware('auth');
