<?php

namespace Decoweb\Panelpack\Providers;

use Illuminate\Support\ServiceProvider;
use Decoweb\Panelpack\Helpers\Pictures;
class PicturesServiceProvider extends ServiceProvider
{
    protected $defer = true;
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('Decoweb\Panelpack\Helpers\Contracts\PicturesContract',function(){
            return new Pictures();
        });
    }

    public function provides()
    {
        return ['Decoweb\Panelpack\Helpers\Contracts\PicturesContract'];
    }
}
