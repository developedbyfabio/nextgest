<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder executado no contexto de CADA tenant (logo após as migrations do
 * tenant — ver pipeline em App\Providers\TenancyServiceProvider).
 *
 * Semeia os papéis e permissões padrão (Apêndice D) e a configuração inicial
 * `confirmacao_automatica = true`. ver_financeiro é permissão separada, padrão
 * só do Dono.
 */
class TenantDatabaseSeeder extends Seeder
{
    /**
     * Todas as permissões (ação_módulo). guard `web` (equipe).
     */
    private const PERMISSOES = [
        'ver_agenda',
        'ver_agenda_propria',
        'criar_agendamento',
        'editar_agendamento',
        'ver_clientes',
        'criar_servico',
        'editar_servico',
        'criar_produto',
        'editar_produto',
        'criar_usuario',
        'editar_permissoes',
        'ver_financeiro',
        'criar_venda',
        'gerenciar_clube',
        'gerenciar_pagamentos',
        'gerenciar_whatsapp',
        'usar_kanban',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSOES as $nome) {
            Permission::findOrCreate($nome, 'web');
        }

        // Dono: todas.
        $dono = Role::findOrCreate('Dono', 'web');
        $dono->syncPermissions(self::PERMISSOES);

        // Gerente: tudo menos financeiro e edição de permissões (config sensível).
        $gerente = Role::findOrCreate('Gerente', 'web');
        $gerente->syncPermissions(array_diff(self::PERMISSOES, [
            'ver_financeiro',
            'editar_permissoes',
        ]));

        // Recepção: agenda, clientes, vendas e kanban.
        $recepcao = Role::findOrCreate('Recepção', 'web');
        $recepcao->syncPermissions([
            'ver_agenda',
            'criar_agendamento',
            'editar_agendamento',
            'ver_clientes',
            'criar_venda',
            'usar_kanban',
        ]);

        // Profissional: vê apenas a própria agenda.
        $profissional = Role::findOrCreate('Profissional', 'web');
        $profissional->syncPermissions([
            'ver_agenda_propria',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Configuração inicial do estabelecimento.
        DB::table('configuracoes')->updateOrInsert(
            ['chave' => 'confirmacao_automatica'],
            ['valor' => '1', 'created_at' => now(), 'updated_at' => now()]
        );
    }
}
