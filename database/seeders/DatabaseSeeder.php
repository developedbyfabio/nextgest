<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder do banco CENTRAL. Hoje não há dados a semear no central por padrão
 * (super-admins são criados manualmente / por comando). O seed por TENANT fica
 * em Database\Seeders\TenantDatabaseSeeder, disparado na criação do tenant.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sem seed central por padrão.
    }
}
