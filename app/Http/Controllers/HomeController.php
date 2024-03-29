<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{


    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');

    }//end __construct()


    /**
     * Show the application dashboard.
     */
    public function index(): View
    {
        return view('home');

    }//end index()


}//end class
