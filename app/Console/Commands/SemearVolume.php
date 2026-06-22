<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\HorarioTrabalho;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Popula um tenant de VOLUME para auditoria de performance (contagem de queries em
 * escala). SOB DEMANDA — não roda no boot, não toca os tenants de demo.
 *
 * Uso: php artisan nextgest:semear-volume volumeteste --clientes=2000 --agendamentos=10000 --profissionais=8
 *
 * Inserts em lote (DB::table) para ser rápido. Idempotência leve: some aos dados
 * existentes (use um tenant dedicado e descartável).
 */
class SemearVolume extends Command
{
    protected $signature = 'nextgest:semear-volume {tenant} {--clientes=2000} {--agendamentos=10000} {--profissionais=8} {--vendas=4000}';

    protected $description = 'Popula um tenant de volume para medir performance (não usar em demo/produção).';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if (! $tenant) {
            $this->error('Tenant não encontrado. Crie-o antes (Tenant::create).');

            return self::FAILURE;
        }

        $nCli = (int) $this->option('clientes');
        $nAg = (int) $this->option('agendamentos');
        $nProf = (int) $this->option('profissionais');
        $nVendas = (int) $this->option('vendas');

        $tenant->run(function () use ($nCli, $nAg, $nProf, $nVendas) {
            $unidade = Unidade::firstOrCreate(['nome' => 'Matriz Volume'], ['ativo' => true]);

            // Serviços
            $servicoIds = [];
            foreach (['Corte', 'Barba', 'Coloração'] as $i => $nome) {
                $s = Servico::firstOrCreate(['nome' => $nome], ['duracao_minutos' => 30, 'preco' => 40 + $i * 10, 'ativo' => true]);
                $s->unidades()->syncWithoutDetaching([$unidade->id]);
                $servicoIds[] = $s->id;
            }

            // Profissionais (com unidade, serviços e horário seg–sáb 09–18)
            $profIds = [];
            $senha = Hash::make('volume-123');
            for ($i = 0; $i < $nProf; $i++) {
                $u = User::create([
                    'name' => "Prof Volume {$i}", 'email' => "profvol{$i}@volume.test",
                    'password' => $senha, 'e_profissional' => true, 'ativo' => true,
                ]);
                $u->assignRole('Profissional');
                $u->unidades()->sync([$unidade->id]);
                $u->servicos()->sync($servicoIds);
                foreach (range(1, 6) as $dia) {
                    HorarioTrabalho::create([
                        'user_id' => $u->id, 'unidade_id' => $unidade->id,
                        'dia_semana' => $dia, 'hora_inicio' => '09:00', 'hora_fim' => '18:00',
                    ]);
                }
                $profIds[] = $u->id;
            }

            // Clientes (lote)
            $this->info("Inserindo {$nCli} clientes...");
            $agora = now();
            collect(range(1, $nCli))->chunk(1000)->each(function ($chunk) use ($agora) {
                DB::table('clientes')->insert($chunk->map(fn ($n) => [
                    'nome' => "Cliente Volume {$n}", 'email' => "clivol{$n}@volume.test",
                    'telefone' => '11'.str_pad((string) $n, 9, '0', STR_PAD_LEFT),
                    'created_at' => $agora, 'updated_at' => $agora,
                ])->all());
            });
            $cliIds = DB::table('clientes')->pluck('id')->all();

            // Agendamentos (lote) — espalhados no último ano, status variados
            $this->info("Inserindo {$nAg} agendamentos...");
            $status = ['concluido', 'confirmado', 'pendente', 'cancelado', 'nao_compareceu'];
            collect(range(1, $nAg))->chunk(2000)->each(function ($chunk) use ($agora, $profIds, $cliIds, $unidade, $status) {
                DB::table('agendamentos')->insert($chunk->map(function ($n) use ($agora, $profIds, $cliIds, $unidade, $status) {
                    $ini = Carbon::now()->subDays(random_int(-30, 365))->setTime(random_int(9, 17), [0, 15, 30, 45][random_int(0, 3)]);

                    return [
                        'unidade_id' => $unidade->id,
                        'cliente_id' => $cliIds[array_rand($cliIds)],
                        'profissional_id' => $profIds[array_rand($profIds)],
                        'data_hora_inicio' => $ini,
                        'data_hora_fim' => $ini->copy()->addMinutes(30),
                        'status' => $status[array_rand($status)],
                        'origem' => 'cliente',
                        'valor_total' => 40,
                        'created_at' => $agora, 'updated_at' => $agora,
                    ];
                })->all());
            });

            // Vendas (lote)
            $this->info("Inserindo {$nVendas} vendas...");
            $stV = ['paga', 'aberta', 'cancelada'];
            collect(range(1, $nVendas))->chunk(2000)->each(function ($chunk) use ($agora, $cliIds, $unidade, $stV) {
                DB::table('vendas')->insert($chunk->map(function ($n) use ($agora, $cliIds, $unidade, $stV) {
                    $data = Carbon::now()->subDays(random_int(0, 365));

                    return [
                        'unidade_id' => $unidade->id,
                        'cliente_id' => $cliIds[array_rand($cliIds)],
                        'status' => $stV[array_rand($stV)],
                        'valor_bruto' => 60, 'desconto' => 0, 'valor_total' => 60,
                        'data' => $data, 'created_at' => $agora, 'updated_at' => $agora,
                    ];
                })->all());
            });
        });

        $this->info('Volume pronto.');

        return self::SUCCESS;
    }
}
