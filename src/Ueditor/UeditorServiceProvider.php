<?php namespace Ueditor;

use Illuminate\Support\ServiceProvider;

class UeditorServiceProvider extends ServiceProvider {

    public function register()
    {
        $this->app->bind('ueditor', function() {
            return new Ueditor();
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/ueditor.php' => config_path('ueditor.php'),
            __DIR__.'/../../resources/assets' => public_path('leona/ueditor'),
        ]);
    }
} 