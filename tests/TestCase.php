<?php

namespace BitDreamIT\MikoPBX\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use BitDreamIT\MikoPBX\MikoPBXServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [MikoPBXServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'MikoPBX' => \BitDreamIT\MikoPBX\Facades\MikoPBX::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('mikopbx.url',          'https://127.0.0.1');
        $app['config']->set('mikopbx.api_key',       'test-api-key');
        $app['config']->set('mikopbx.ami_host',      '127.0.0.1');
        $app['config']->set('mikopbx.ami_port',      5038);
        $app['config']->set('mikopbx.ami_username',  'test');
        $app['config']->set('mikopbx.ami_secret',    'test');
        $app['config']->set('database.default',      'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
