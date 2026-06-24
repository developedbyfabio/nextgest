<?php

declare(strict_types=1);

use App\Models\Servico;
use App\Models\Unidade;

/**
 * Comando nextgest:reconciliar-unidades (D50): liga órfãos de unidade em tenants
 * de 1 unidade; 2+ só relata; nunca inventa horário; idempotente; dry-run padrão.
 */
it('tenant de 1 unidade: --apply liga serviço e profissional órfãos à única unidade', function () {
    $t = criarTenant('umaunidade');
    tenancy()->initialize($t);
    $u = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 40, 'ativo' => true]); // órfão
    $prof = usuarioComPapel('Profissional', ['email' => 'prof@uma.test', 'e_profissional' => true]);          // órfão
    tenancy()->end();

    $this->artisan('nextgest:reconciliar-unidades', ['--apply' => true])->assertExitCode(0);

    $t->run(function () use ($u, $servico, $prof) {
        expect($servico->fresh()->unidades->pluck('id')->all())->toBe([$u->id]);
        expect($prof->fresh()->unidades->pluck('id')->all())->toBe([$u->id]);
        expect(Servico::where('ativo', true)->whereDoesntHave('unidades')->count())->toBe(0);
    });
});

it('tenant de 2+ unidades: NÃO adivinha — mantém o órfão (só relataria)', function () {
    $t = criarTenant('duasunidades');
    tenancy()->initialize($t);
    Unidade::create(['nome' => 'Filial A', 'ativo' => true]);
    Unidade::create(['nome' => 'Filial B', 'ativo' => true]);
    $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 40, 'ativo' => true]); // órfão
    $prof = usuarioComPapel('Profissional', ['email' => 'prof@duas.test', 'e_profissional' => true]);          // órfão
    tenancy()->end();

    $this->artisan('nextgest:reconciliar-unidades', ['--apply' => true])->assertExitCode(0);

    $t->run(function () use ($servico, $prof) {
        expect($servico->fresh()->unidades()->count())->toBe(0); // continua órfão
        expect($prof->fresh()->unidades()->count())->toBe(0);    // continua órfão
    });
});

it('é idempotente: rodar --apply duas vezes não duplica nem altera', function () {
    $t = criarTenant('idempotente');
    tenancy()->initialize($t);
    $u = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 40, 'ativo' => true]);
    tenancy()->end();

    $this->artisan('nextgest:reconciliar-unidades', ['--apply' => true])->assertExitCode(0);
    $this->artisan('nextgest:reconciliar-unidades', ['--apply' => true])->assertExitCode(0);

    $t->run(function () use ($u, $servico) {
        // Exatamente UM vínculo (não duplicou) e na unidade certa.
        expect($servico->fresh()->unidades->pluck('id')->all())->toBe([$u->id]);
        expect(DB::table('servico_unidade')->where('servico_id', $servico->id)->count())->toBe(1);
    });
});

it('dry-run (sem --apply) não escreve nada', function () {
    $t = criarTenant('semescrita');
    tenancy()->initialize($t);
    Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 40, 'ativo' => true]);
    tenancy()->end();

    $this->artisan('nextgest:reconciliar-unidades')->assertExitCode(0); // sem --apply

    $t->run(function () use ($servico) {
        expect($servico->fresh()->unidades()->count())->toBe(0); // segue órfão
    });
});

it('nunca inventa horário — só sinaliza o profissional sem horários', function () {
    $t = criarTenant('semhorario');
    tenancy()->initialize($t);
    Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $prof = usuarioComPapel('Profissional', ['email' => 'prof@sh.test', 'e_profissional' => true]); // órfão, sem horário
    tenancy()->end();

    $this->artisan('nextgest:reconciliar-unidades', ['--apply' => true])->assertExitCode(0);

    $t->run(function () use ($prof) {
        // Foi ligado à unidade (1 unidade), mas NENHUM horário foi criado.
        expect($prof->fresh()->unidades()->count())->toBe(1);
        expect($prof->horariosTrabalho()->count())->toBe(0);
    });
});
