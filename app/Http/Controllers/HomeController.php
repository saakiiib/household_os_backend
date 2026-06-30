<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{

    public function dashboard()
    {
        if (Auth::check()) {
            $user = auth()->user();

            if ($user->user_type == '1') {
                return redirect()->route('admin.dashboard');
            } else if ($user->user_type == '0') {
                return redirect()->route('user.dashboard');
            }
        } else {
            return redirect()->route('login');
        }
    }

    public function adminHome()
    {
        return spa('admin.pages.dashboard');
    }

    public function userHome()
    {
        return 'user';
    }
}