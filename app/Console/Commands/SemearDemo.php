<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\Unidade;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Popula um cenário de DEMONSTRAÇÃO no banco de um tenant (uso local de teste):
 * unidade, serviços, profissionais (com serviços e horários), gerente/recepção,
 * clientes e agendamentos em status variados.
 *
 * Idempotente: catálogo/usuários/clientes via firstOrCreate; agendamentos só são
 * criados se ainda não houver agendamentos de demo (marcados em `observacoes`).
 * Use --recriar para refazer apenas os agendamentos de demo.
 *
 * Não é para produção. A senha padrão é de demonstração (--senha para trocar).
 */
class SemearDemo extends Command
{
    protected $signature = 'nextgest:demo
                            {tenant : slug/id do tenant}
                            {--senha=password : senha de demo dos logins criados}
                            {--recriar : recria os agendamentos de demonstração}';

    protected $description = 'Popula um cenário de demonstração no tenant (local)';

    private const MARCA_DEMO = '[demo]';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));

        if (! $tenant) {
            $this->error("Tenant não encontrado: {$this->argument('tenant')}");

            return self::FAILURE;
        }

        $senha = (string) $this->option('senha');

        $tenant->run(function () use ($senha) {
            $unidade = $this->unidade();
            $servicos = $this->servicos($unidade);
            $profissionais = $this->profissionais($unidade, $servicos, $senha);
            $this->equipeDeApoio($unidade, $senha);
            $clientes = $this->clientes($senha);
            $this->agendamentos($unidade, $servicos, $profissionais, $clientes);
        });

        $this->info('Cenário de demonstração pronto no tenant '.$this->argument('tenant').'.');
        $this->line('Senha de demonstração dos logins: '.$senha);

        return self::SUCCESS;
    }

    private function unidade(): Unidade
    {
        return Unidade::firstOrCreate(
            ['nome' => 'Matriz Centro'],
            ['endereco' => 'Rua Principal, 100', 'telefone' => '(11) 3333-0000', 'ativo' => true],
        );
    }

    /** @return array<string, Servico> */
    private function servicos(Unidade $unidade): array
    {
        $catalogo = [
            'Corte masculino' => [30, 45.00],
            'Barba' => [20, 30.00],
            'Corte + Barba' => [50, 70.00],
            'Sobrancelha' => [15, 20.00],
            'Coloração' => [60, 90.00],
        ];

        $servicos = [];
        foreach ($catalogo as $nome => [$duracao, $preco]) {
            $servico = Servico::firstOrCreate(
                ['nome' => $nome],
                ['duracao_minutos' => $duracao, 'preco' => $preco, 'ativo' => true],
            );
            $servico->unidades()->syncWithoutDetaching([$unidade->id]);
            $servicos[$nome] = $servico;
        }

        return $servicos;
    }

    /**
     * @param  array<string, Servico>  $servicos
     * @return array<int, User>
     */
    private function profissionais(Unidade $unidade, array $servicos, string $senha): array
    {
        $definicoes = [
            ['Jorge Tesoura', 'jorge@demo.test', ['Corte masculino', 'Barba', 'Corte + Barba', 'Sobrancelha']],
            ['Ana Navalha', 'ana@demo.test', ['Corte masculino', 'Coloração', 'Sobrancelha']],
            ['Bruno Máquina', 'bruno@demo.test', ['Corte masculino', 'Barba', 'Corte + Barba']],
        ];

        $profissionais = [];
        foreach ($definicoes as [$nome, $email, $fazServicos]) {
            $prof = User::firstOrCreate(
                ['email' => $email],
                ['name' => $nome, 'password' => $senha, 'e_profissional' => true, 'ativo' => true],
            );
            $prof->syncRoles(['Profissional']);
            $prof->unidades()->syncWithoutDetaching([$unidade->id]);
            $prof->servicos()->syncWithoutDetaching(
                collect($fazServicos)->map(fn ($n) => $servicos[$n]->id)->all()
            );

            // Horários: seg–sex 09–12 e 13–18; sáb 09–13. Só se ainda não houver.
            if ($prof->horariosTrabalho()->count() === 0) {
                foreach ([1, 2, 3, 4, 5] as $dia) {
                    $prof->horariosTrabalho()->create(['unidade_id' => $unidade->id, 'dia_semana' => $dia, 'hora_inicio' => '09:00', 'hora_fim' => '12:00']);
                    $prof->horariosTrabalho()->create(['unidade_id' => $unidade->id, 'dia_semana' => $dia, 'hora_inicio' => '13:00', 'hora_fim' => '18:00']);
                }
                $prof->horariosTrabalho()->create(['unidade_id' => $unidade->id, 'dia_semana' => 6, 'hora_inicio' => '09:00', 'hora_fim' => '13:00']);
            }

            $profissionais[] = $prof;
        }

        return $profissionais;
    }

    private function equipeDeApoio(Unidade $unidade, string $senha): void
    {
        $apoio = [
            ['Gerente Demo', 'gerente@demo.test', 'Gerente'],
            ['Recepção Demo', 'recepcao@demo.test', 'Recepção'],
        ];

        foreach ($apoio as [$nome, $email, $papel]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $nome, 'password' => $senha, 'e_profissional' => false, 'ativo' => true],
            );
            $user->syncRoles([$papel]);
            $user->unidades()->syncWithoutDetaching([$unidade->id]);
        }
    }

    /** @return array<int, Cliente> */
    private function clientes(string $senha): array
    {
        $definicoes = [
            ['Maria Souza', 'maria@cliente.test', '(11) 90000-0001'],
            ['Carlos Lima', 'carlos@cliente.test', '(11) 90000-0002'],
            ['Paula Dias', 'paula@cliente.test', '(11) 90000-0003'],
        ];

        return collect($definicoes)->map(function ($def) use ($senha) {
            [$nome, $email, $tel] = $def;

            return Cliente::firstOrCreate(
                ['email' => $email],
                ['nome' => $nome, 'telefone' => $tel, 'password' => $senha],
            );
        })->all();
    }

    /**
     * @param  array<string, Servico>  $servicos
     * @param  array<int, User>  $profissionais
     * @param  array<int, Cliente>  $clientes
     */
    private function agendamentos(Unidade $unidade, array $servicos, array $profissionais, array $clientes): void
    {
        $existem = Agendamento::where('observacoes', self::MARCA_DEMO)->exists();

        if ($existem && ! $this->option('recriar')) {
            $this->line('Agendamentos de demo já existem (use --recriar para refazer).');

            return;
        }

        if ($existem && $this->option('recriar')) {
            // Remoção escopada aos agendamentos de demo (não toca em dados reais).
            Agendamento::where('observacoes', self::MARCA_DEMO)->each(function ($ag) {
                $ag->itens()->delete();
                $ag->delete();
            });
        }

        [$jorge, $ana, $bruno] = $profissionais;
        [$maria, $carlos, $paula] = $clientes;

        $amanha = Carbon::tomorrow()->setTime(0, 0);
        $ontem = Carbon::yesterday()->setTime(0, 0);
        $hoje = Carbon::today();

        // [cliente, profissional, [servicos], inicio, status]
        $plano = [
            [$maria, $jorge, ['Corte masculino', 'Barba'], $amanha->copy()->setTime(9, 0), 'confirmado'],
            [$carlos, $ana, ['Coloração'], $amanha->copy()->setTime(10, 0), 'pendente'],
            [$paula, $bruno, ['Corte masculino'], $amanha->copy()->setTime(14, 0), 'confirmado'],
            [$maria, $ana, ['Sobrancelha'], $hoje->copy()->setTime(16, 0), 'em_andamento'],
            [$carlos, $jorge, ['Corte + Barba'], $ontem->copy()->setTime(10, 0), 'concluido'],
            [$paula, $jorge, ['Corte masculino'], $ontem->copy()->setTime(11, 0), 'nao_compareceu'],
            [$maria, $bruno, ['Barba'], $amanha->copy()->setTime(15, 0), 'cancelado'],
        ];

        foreach ($plano as [$cliente, $prof, $nomesServicos, $inicio, $status]) {
            $itens = collect($nomesServicos)->map(fn ($n) => $servicos[$n]);
            $duracao = (int) $itens->sum('duracao_minutos');

            $agendamento = Agendamento::create([
                'unidade_id' => $unidade->id,
                'cliente_id' => $cliente->id,
                'profissional_id' => $prof->id,
                'data_hora_inicio' => $inicio,
                'data_hora_fim' => $inicio->copy()->addMinutes($duracao),
                'status' => $status,
                'origem' => 'equipe',
                'valor_total' => (float) $itens->sum('preco'),
                'observacoes' => self::MARCA_DEMO,
            ]);

            foreach ($itens as $servico) {
                $agendamento->itens()->create([
                    'servico_id' => $servico->id,
                    'preco' => $servico->preco,
                    'duracao_minutos' => $servico->duracao_minutos,
                ]);
            }
        }
    }
}
