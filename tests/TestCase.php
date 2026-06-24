<?php

namespace BitDreamIT\MikoPBX\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use BitDreamIT\MikoPBX\MikoPBXServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        Factory::guessFactoryNamesUsing(
            fn(string $modelName) => 'BitDreamIT\\MikoPBX\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [MikoPBXServiceProvider::class];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('mikopbx.url',           'http://localhost');
        config()->set('mikopbx.api_key',        'test-key');
        config()->set('mikopbx.ami.host',       'localhost');
        config()->set('mikopbx.ami.secret',     'test');
        config()->set('mikopbx.table_prefix',   'mikopbx_');

        // Run migrations
        $migration = include __DIR__.'/../database/migrations/2026_01_01_000001_create_mikopbx_tables.php';
        $migration->up();
    }
}
