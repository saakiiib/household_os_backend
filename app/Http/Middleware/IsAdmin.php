<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class IsAdmin
{
  public function handle(Request $request, Closure $next): Response
  {
    if (auth()->check() && auth()->user()->user_type == '1') {
      return $next($request);
    }

    return redirect()->route('login');
  }
}
