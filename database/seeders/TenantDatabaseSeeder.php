<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\KanbanColuna;
use App\Models\KanbanQuadro;
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
 *
 * ADITIVO/IDEMPOTENTE (seguro para re-seed em tenant real customizado): GARANTE o
 * piso (papéis + permissões base + configs default) SEM impor o teto — concede
 * permissões aditivamente (`givePermissionTo`, nunca `syncPermissions` que revoga) e
 * cria configs só se não existirem (`insertOrIgnore`, nunca sobrescreve). Rodar 2×
 * não muda nada; customização do Dono (permissão extra / config ajustada) é preservada.
 */
class TenantDatabaseSeeder extends Seeder
{
    /**
     * Todas as permissões (ação_módulo). guard `web` (equipe).
     */
    private const PERMISSOES = [
        'ver_agenda',
        'ver_agenda_propria',
        'ver_avaliacoes',          // aba "Últimos serviços": TODOS os atendimentos+avaliações, com nome do cliente (Dono/Gerente)
        'ver_avaliacoes_proprias', // Profissional: só os próprios atendimentos, cliente ANÔNIMO
        'criar_agendamento',
        'editar_agendamento',
        'gerir_agenda',
        'ver_clientes',
        'ver_cpf_cliente',         // ver o CPF COMPLETO do cliente (D94) — Dono/Gerente; Recepção só mascarado
        'gerir_unidades',
        'criar_servico',
        'editar_servico',
        'criar_produto',
        'editar_produto',
        'gerir_estoque',
        'criar_usuario',
        'editar_usuario',
        'editar_permissoes',
        'ver_financeiro',
        'criar_venda',
        'finalizar_atendimento_proprio', // Profissional: finalizar o PRÓPRIO atendimento e gerir a comanda dele
        'gerenciar_clube',
        'gerenciar_pagamentos',
        'gerenciar_whatsapp',
        'usar_kanban',
        'gerir_aparencia',
        'ver_dashboard',
        'ver_indicadores',         // aba Indicadores (retenção/frequência) — Dono+Gerente
        'gerir_kanban',            // quadros/colunas + CRM (Dono/Gerente)
        'ver_kanban_atendimento',  // quadro de atendimento/balcão (inclui Recepção)
        'gerenciar_2fa_proprio',   // ativar/gerir o PRÓPRIO 2FA (TOTP) — só Dono
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSOES as $nome) {
            Permission::findOrCreate($nome, 'web');
        }

        // Concessão ADITIVA por papel (givePermissionTo = syncWithoutDetaching):
        // GARANTE as permissões base, NUNCA revoga extras que o Dono tenha concedido.
        // Dono: todas.
        $dono = Role::findOrCreate('Dono', 'web');
        $dono->givePermissionTo(self::PERMISSOES);

        // Gerente: tudo menos financeiro, edição de permissões e credenciais de
        // PAGAMENTO (config sensível). `gerenciar_pagamentos` é exclusivo do Dono;
        // `gerenciar_whatsapp` o Gerente mantém. (Decisão do Fabio — ver Dxx.)
        $gerente = Role::findOrCreate('Gerente', 'web');
        $gerente->givePermissionTo(array_diff(self::PERMISSOES, [
            'ver_financeiro',
            'editar_permissoes',
            'gerenciar_pagamentos',
            'gerenciar_2fa_proprio', // 2FA é do Dono (decisão fixa: opcional e só Dono)
        ]));

        // Recepção: agenda, clientes, vendas, estoque e kanban de atendimento (balcão).
        $recepcao = Role::findOrCreate('Recepção', 'web');
        $recepcao->givePermissionTo([
            'ver_agenda',
            'criar_agendamento',
            'editar_agendamento',
            'gerir_agenda',
            'ver_clientes',
            'criar_venda',
            'gerir_estoque',
            'usar_kanban',
            'ver_kanban_atendimento',
        ]);

        // Profissional: vê a própria agenda e finaliza os próprios atendimentos
        // (gera/gere a comanda daquele atendimento — cliente e profissional travados).
        $profissional = Role::findOrCreate('Profissional', 'web');
        $profissional->givePermissionTo([
            'ver_agenda_propria',
            'finalizar_atendimento_proprio',
            'ver_avaliacoes_proprias',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Configurações iniciais do estabelecimento — criar SÓ se não existir
        // (insertOrIgnore pela chave única): tenant novo recebe os defaults; tenant
        // existente MANTÉM o valor que o Dono ajustou (nunca reseta).
        $configs = [
            'confirmacao_automatica' => '1',
            'intervalo_slots_minutos' => '15',
            'cancelamento_antecedencia_horas' => '2',
        ];

        foreach ($configs as $chave => $valor) {
            DB::table('configuracoes')->insertOrIgnore([
                'chave' => $chave,
                'valor' => $valor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->semearKanban();
    }

    /**
     * Quadros Kanban padrão (D22): Atendimento e CRM, com colunas iniciais.
     * Idempotente (firstOrCreate), para re-seed seguro de tenants existentes.
     */
    private function semearKanban(): void
    {
        $padrao = [
            'atendimento' => ['nome' => 'Atendimento', 'colunas' => ['Aguardando', 'Em atendimento', 'Concluído', 'Pago']],
            'crm' => ['nome' => 'CRM', 'colunas' => ['Novo contato', 'Em conversa', 'Agendado', 'Fidelizado']],
        ];

        foreach ($padrao as $tipo => $def) {
            $quadro = KanbanQuadro::firstOrCreate(
                ['tipo' => $tipo],
                ['nome' => $def['nome'], 'ativo' => true],
            );

            foreach ($def['colunas'] as $ordem => $nome) {
                KanbanColuna::firstOrCreate(
                    ['quadro_id' => $quadro->id, 'nome' => $nome],
                    ['ordem' => $ordem],
                );
            }
        }
    }
}
