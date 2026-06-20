<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckLoginController extends Controller
{
    public function checkLogin(Request $request)
    {
        if (Auth::check()) {
            return 'Yes';
        } else {
            abort(401);
        }

    }// end checkLogin()

}// end class
