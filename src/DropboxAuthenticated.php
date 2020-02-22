<?php

namespace Dcblogdev\Dropbox;

use Closure;
use Dcblogdev\Dropbox\Facades\Dropbox;

class DropboxAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (Dropbox::getTokenData() === null) {
            return Dropbox::connect();
        }

        return $next($request);
    }
}
