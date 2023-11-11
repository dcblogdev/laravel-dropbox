<?php

namespace Dcblogdev\Dropbox\Models;

use Illuminate\Database\Eloquent\Model;

class DropboxToken extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_in' => 'datetime',
    ];
}
