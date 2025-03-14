<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller implements HasMiddleware
{


    public static function middleware(): array
    {
        return [
            'auth',
        ];
    }//end __construct()


    /**
     * Show the application dashboard.
     */
    public function index(): View
    {
        return view('home');

    }//end index()


}//end class
