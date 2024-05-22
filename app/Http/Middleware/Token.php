<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Token
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request['api_key'] != 'Rsxw_q25jhk92345/624087Mnhi.oxcv') {
            return response(['message' => 'access denied!'], 500);
        }else{
            return $next($request);;
        }
    }
}
