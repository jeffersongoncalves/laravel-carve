<?php

namespace JeffersonGoncalves\Carve\Tests;

use JeffersonGoncalves\Carve\CarveServiceProvider;
use JeffersonGoncalves\Carve\Facades\Carve;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            CarveServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Carve' => Carve::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('view.paths', [__DIR__.'/Fixtures/views']);
    }
}
