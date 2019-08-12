<?php

namespace Zhengwhizz\DBDic;

use Barryvdh\Snappy\ServiceProvider;

class DBDicServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // 发布配置文件
        $this->publishes([
            __DIR__ . '/../config/laravel-dbdic.php' => config_path('laravel-dbdic.php'),
        ]);
        // 发布视图文件
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dbdic');
        $this->publishes([
            __DIR__ . '/../resources/views' => base_path('resources/views/vendor/laravel-dbdic'),
        ]);
        // 发布资源文件
        $this->publishes([
            __DIR__ . '/../public/' => public_path(''),
        ]);
        // 注册路由
        if ((method_exists($this->app, 'routesAreCached') && !$this->app->routesAreCached())
            || $this->isLumen()) {
            require __DIR__ . '/routes.php';
        }
		$this->commands([
            Commands\WriteModelCommentCommand::class,
        ]);
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register('Barryvdh\Snappy\ServiceProvider');
    }

    protected function isLumen()
    {
        return strpos($this->app->version(), 'Lumen') !== false;
    }
}
