<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use App\Models\User;
use Illuminate\Http\Request;

class LoginController extends Controller
{
  use AuthenticatesUsers;

  protected $redirectTo = '/home';

  public function __construct()
  {
    $this->middleware('guest')->except('logout');
  }

  public function login(Request $request)
  {
    $input = $request->all();

    $this->validate($request, [
      'email' => 'required|email',
      'password' => 'required',
    ]);

    $chksts = User::where('email', $input['email'])->first();
    if ($chksts) {
      if ($chksts->status == 1) {
        if (auth()->attempt(['email' => $input['email'], 'password' => $input['password']])) {
          if (auth()->user()->user_type == '1') {
            return redirect()->route('admin.dashboard');
          } elseif (auth()->user()->user_type == '0') {
            return redirect()->route('user.dashboard');
          }
        } else {
          return redirect()->back()
            ->withInput($request->only('email'))
            ->withErrors(['password' => 'Wrong password.']);
        }
      } else {
        return redirect()->back()
          ->withInput($request->only('email'))
          ->withErrors(['email' => 'Your account is inactive.']);
      }
    } else {
      return redirect()->back()
        ->withInput($request->only('email'))
        ->withErrors(['email' => 'Please input correct email and password.']);
    }
  }
}
