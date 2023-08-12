<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CheckLoginController extends Controller
{
    public function checkLogin(Request $request)
    {
        if (Auth::check()) {
            return "Yes";
        } else {
            abort(401);
        }
    }
}
