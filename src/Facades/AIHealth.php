<?php

namespace AIHealth\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class AIHealth extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \AIHealth\Laravel\Client::class;
    }
}
