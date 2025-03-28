<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Providers\AppServiceProvider;
use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller implements HasMiddleware
{
    /*
        |--------------------------------------------------------------------------
        | Register Controller
        |--------------------------------------------------------------------------
        |
        | This controller handles the registration of new users as well as their
        | validation and creation. By default this controller uses a trait to
        | provide this functionality without requiring any additional code.
        |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = AppServiceProvider::HOME;


    public static function middleware(): array
    {
        return ['guest'];

    }//end middleware()


    /**
     * Get a validator for an incoming registration request.
     */
    protected function validator(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make(
            $data,
            [
                'name'     => [
                    'required',
                    'string',
                    'max:255',
                ],
                'email'    => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    'unique:users',
                ],
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                ],
            ]
        );

    }//end validator()


    /**
     * Create a new user instance after a valid registration.
     */
    protected function create(array $data): User
    {
        return User::create(
            [
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
            ]
        );

    }//end create()


}//end class
