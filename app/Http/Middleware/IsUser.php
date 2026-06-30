<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsUser
{
  public function handle(Request $request, Closure $next): Response
  {
    if (auth()->check() && auth()->user()->user_type == '0') {
      return $next($request);
    }

    return redirect()->route('login');
  }
}
