<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp(); // la app bootea aquí con el config original

        // Reemplazar AMBAS conexiones por SQLite en memoria
        $sqlite = [
            'driver'                  => 'sqlite',
            'database'                => ':memory:',
            'prefix'                  => '',
            'foreign_key_constraints' => true,
        ];

        config([
            'database.connections.landlord'        => $sqlite,
            'database.connections.tenant'          => $sqlite,
            'database.default'                     => 'landlord',
            // No cambiar la DB al hacer makeCurrent() — ya es el SQLite correcto
            'multitenancy.switch_tenant_tasks'     => [
                \Spatie\Multitenancy\Tasks\PrefixCacheTask::class,
            ],
        ]);

        // Descartar cualquier conexión MySQL cacheada antes del override
        DB::purge('landlord');
        DB::purge('tenant');
    }
}
