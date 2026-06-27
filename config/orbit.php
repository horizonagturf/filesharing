<?php

use Orbit\Drivers\Json;
use Orbit\Drivers\Markdown;
use Orbit\Drivers\Yaml;

return [

    'default' => env('ORBIT_DEFAULT_DRIVER', 'json'),

    'drivers' => [
        'md' => Markdown::class,
        'json' => Json::class,
        'yaml' => Yaml::class,
    ],

    'paths' => [
        'content' => storage_path('content'),
        'cache' => storage_path('content/orbit'),
    ],

];
