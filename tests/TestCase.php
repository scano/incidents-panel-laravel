<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $settings = $app['config']->get('database.connections.mysql_testing');

        if ($connection !== 'mysql_testing'
            || ($settings['driver'] ?? null) !== 'mysql'
            || ($settings['database'] ?? null) !== 'incidencias_testing'
            || ($settings['username'] ?? null) !== 'incidencias_test') {
            throw new LogicException('Las pruebas solo pueden ejecutarse con la conexión MySQL exclusiva mysql_testing.');
        }

        return $app;
    }
}
