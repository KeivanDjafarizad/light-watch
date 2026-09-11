<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\IngestionServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    IngestionServiceProvider::class,
];
