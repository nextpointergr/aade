<?php

namespace NextPointer\Aade;

use Illuminate\Support\ServiceProvider;
use NextPointer\Aade\Services\Aade;

class AadeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/aade.php',
            'aade'
        );

        $this->app->singleton(Aade::class, function () {
            return new Aade();
        });

        $this->app->alias(Aade::class, 'aade');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/aade.php' => config_path('aade.php'),
        ], 'aade-config');
    }
}