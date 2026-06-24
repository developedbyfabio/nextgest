<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Servico;
use App\Models\Tenant;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Reconcilia ÓRFÃOS de unidade (D49/D50): serviços ativos sem `servico_unidade` e
 * profissionais ativos sem `user_unidade`. A UI (D49) já faz novos cadastros
 * nascerem vinculados; este comando cuida do legado.
 *
 * Regra (decisão do Fabio):
 *  - Tenant com 1 unidade → liga os órfãos àquela única unidade (sem ambiguidade).
 *  - Tenant com 2+ unidades → NÃO adivinha; só relata para decisão manual no modal
 *    "Gerir" da unidade (serviços) / na tela Equipe (profissionais).
 *  - Horários (`horarios_trabalho`) → NUNCA inventa; só sinaliza quem está sem.
 *
 * Seguro por construção: SÓ LIGA (syncWithoutDetaching), nunca desliga/remove;
 * IDEMPOTENTE (rodar 2× não duplica nem altera o que já está certo). Dry-run é o
 * padrão — escreve apenas com --apply. Não toca o MotorDisponibilidade.
 *
 * Uso:
 *   php artisan nextgest:reconciliar-unidades            # dry-run (não escreve)
 *   php artisan nextgest:reconciliar-unidades --apply    # aplica os vínculos
 */
class ReconciliarUnidades extends Command
{
    protected $signature = 'nextgest:reconciliar-unidades
                            {--apply : Aplica os vínculos faltantes. SEM esta flag roda em dry-run (não escreve nada).}';

    protected $description = 'Liga serviços/profissionais órfãos à unidade (tenants de 1 unidade); 2+ unidades só relata. Idempotente, não-destrutivo.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $this->info($apply
            ? 'MODO APLICAR — liga vínculos faltantes (nunca remove). Idempotente.'
            : 'MODO DRY-RUN — apenas relata, nada é escrito. Use --apply para aplicar.');
        $this->newLine();

        $tot = ['tenants' => 0, 'servLig' => 0, 'profLig' => 0, 'servManual' => 0, 'profManual' => 0, 'semHorario' => 0];

        foreach (Tenant::all() as $tenant) {
            $tenant->run(function () use ($tenant, $apply, &$tot) {
                $tot['tenants']++;

                $unidades = Unidade::where('ativo', true)->orderBy('nome')->get();
                $n = $unidades->count();

                $servicosOrfaos = Servico::where('ativo', true)
                    ->whereDoesntHave('unidades')->orderBy('nome')->get();
                $profsOrfaos = User::where('e_profissional', true)->where('ativo', true)
                    ->whereDoesntHave('unidades')->orderBy('name')->get();

                $cabecalho = $n === 1
                    ? "{$n} unidade: ".$unidades->first()->nome
                    : "{$n} unidade(s)";
                $this->line("Tenant: <fg=cyan>{$tenant->id}</> ({$cabecalho})");

                if ($n === 1) {
                    $u = $unidades->first();

                    if ($servicosOrfaos->isNotEmpty()) {
                        if ($apply) {
                            foreach ($servicosOrfaos as $s) {
                                $s->unidades()->syncWithoutDetaching([$u->id]);
                            }
                        }
                        $tot['servLig'] += $servicosOrfaos->count();
                        $verbo = $apply ? 'LIGADOS' : 'seriam ligados';
                        $this->line("  Serviços órfãos {$verbo} a {$u->nome} ({$servicosOrfaos->count()}): ".$servicosOrfaos->pluck('nome')->implode(', '));
                    } else {
                        $this->line('  Serviços órfãos: 0');
                    }

                    if ($profsOrfaos->isNotEmpty()) {
                        if ($apply) {
                            foreach ($profsOrfaos as $p) {
                                $p->unidades()->syncWithoutDetaching([$u->id]);
                            }
                        }
                        $tot['profLig'] += $profsOrfaos->count();
                        $verbo = $apply ? 'LIGADOS' : 'seriam ligados';
                        $this->line("  Profissionais órfãos {$verbo} a {$u->nome} ({$profsOrfaos->count()}): ".$profsOrfaos->pluck('name')->implode(', '));
                    } else {
                        $this->line('  Profissionais órfãos: 0');
                    }
                } elseif ($n >= 2) {
                    if ($servicosOrfaos->isNotEmpty()) {
                        $tot['servManual'] += $servicosOrfaos->count();
                        $this->warn("  Serviços órfãos — DECISÃO MANUAL (modal \"Gerir\" da unidade) ({$servicosOrfaos->count()}): ".$servicosOrfaos->pluck('nome')->implode(', '));
                    } else {
                        $this->line('  Serviços órfãos: 0');
                    }
                    if ($profsOrfaos->isNotEmpty()) {
                        $tot['profManual'] += $profsOrfaos->count();
                        $this->warn("  Profissionais órfãos — DECISÃO MANUAL (tela Equipe) ({$profsOrfaos->count()}): ".$profsOrfaos->pluck('name')->implode(', '));
                    } else {
                        $this->line('  Profissionais órfãos: 0');
                    }
                } else { // 0 unidades ativas
                    if ($servicosOrfaos->isNotEmpty() || $profsOrfaos->isNotEmpty()) {
                        $this->warn("  Sem unidade ATIVA — cadastre uma unidade antes de reconciliar (órfãos: {$servicosOrfaos->count()} serviço(s), {$profsOrfaos->count()} profissional(is)).");
                    } else {
                        $this->line('  Sem unidade ativa e sem órfãos.');
                    }
                }

                // Sinalização de horários (NUNCA cria). Profissional ativo com unidade
                // (efetiva: pós-reconciliação no caso 1-unidade) mas SEM horarios_trabalho
                // nela = não agenda. A jornada não dá para adivinhar → só sinaliza.
                $semHorario = [];
                foreach (User::where('e_profissional', true)->where('ativo', true)->with('unidades')->orderBy('name')->get() as $p) {
                    $unitIds = $p->unidades->pluck('id')->all();
                    if (empty($unitIds) && $n === 1) {
                        $unitIds = [$unidades->first()->id]; // seria ligado a esta unidade
                    }
                    if (empty($unitIds)) {
                        continue; // sem unidade definível (2+ órfão) → já está em "manual"
                    }
                    if (! $p->horariosTrabalho()->whereIn('unidade_id', $unitIds)->exists()) {
                        $semHorario[] = $p->name;
                    }
                }
                if (! empty($semHorario)) {
                    $tot['semHorario'] += count($semHorario);
                    $this->line('  <fg=yellow>Sem horário na unidade</> ('.count($semHorario).') — cadastre na tela Horários: '.implode(', ', $semHorario));
                }
            });
        }

        $this->newLine();
        $this->info("Resumo ({$tot['tenants']} tenant(s)) — ".($apply ? 'APLICADO' : 'DRY-RUN'));
        $this->line('  Vínculos '.($apply ? 'ligados' : 'a ligar')." (tenants de 1 unidade): {$tot['servLig']} serviço(s), {$tot['profLig']} profissional(is).");
        $this->line("  Decisão manual (tenants de 2+ unidades): {$tot['servManual']} serviço(s), {$tot['profManual']} profissional(is).");
        $this->line("  Profissionais sem horário (apenas sinalizado, nada criado): {$tot['semHorario']}.");

        if (! $apply && ($tot['servLig'] + $tot['profLig']) > 0) {
            $this->newLine();
            $this->comment('Rode novamente com --apply para aplicar os vínculos automáticos.');
        }

        return self::SUCCESS;
    }
}
