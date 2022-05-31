<?php

namespace OnTheFlyConfigurator\LaravelPlugin;

use Illuminate\Support\ServiceProvider;

class LaravelPluginServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->make('OnTheFlyConfigurator\LaravelPlugin\Classes\Cart');
        $this->mergeConfigFrom(
            __DIR__.'/config/config.php', 'ontheflyconfigurator'
        );
    }

    public function boot()
    {

    }
}
