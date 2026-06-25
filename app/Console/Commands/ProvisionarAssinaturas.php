<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Assinatura;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Provisiona uma assinatura SaaS padrão (em_teste) para tenants que ainda não têm —
 * backfill da Fase 4a (D58). Dry-run por padrão; só grava com --apply.
 *
 * IDEMPOTENTE e NÃO-DESTRUTIVO: pula quem já tem assinatura; nunca atualiza/apaga.
 * Roda 100% no central (não inicializa tenancy nem toca banco de tenant).
 */
class ProvisionarAssinaturas extends Command
{
    protected $signature = 'nextgest:provisionar-assinaturas
                            {--apply : grava de fato (sem esta flag é dry-run)}';

    protected $description = 'Cria assinatura SaaS padrão (em_teste) para tenants sem assinatura (idempotente)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $trial = (int) config('cobranca.trial_padrao_dias', 30);

        $criados = 0;
        $pulados = 0;
        $linhas = [];

        foreach (Tenant::all() as $tenant) {
            if (Assinatura::where('tenant_id', $tenant->getKey())->exists()) {
                $pulados++;

                continue;
            }

            $plano = $tenant->planoAtual();                       // slug ou null
            $valor = (float) (config("planos.{$plano}.preco_mes") ?? 0);

            $linhas[] = [$tenant->getKey(), $plano ?? '—', number_format($valor, 2, ',', '.'), Assinatura::EM_TESTE];

            if ($apply) {
                Assinatura::create([
                    'tenant_id' => $tenant->getKey(),
                    'plano' => $plano,
                    'valor_mensal' => $valor,
                    'data_inicio' => $tenant->created_at ?? now(),
                    'trial_dias' => $trial,
                    'status' => Assinatura::EM_TESTE,
                ]);
            }

            $criados++;
        }

        if ($linhas !== []) {
            $this->table(['Tenant', 'Plano', 'Valor/mês', 'Situação'], $linhas);
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d a provisionar, %d já com assinatura (pulados).',
            $apply ? 'APLICADO' : 'DRY-RUN (use --apply para gravar)',
            $criados,
            $pulados,
        ));

        return self::SUCCESS;
    }
}
