<?php

namespace Dcblogdev\Dropbox\Facades;

use Illuminate\Support\Facades\Facade;

class Dropbox extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'dropbox';
    }
}
