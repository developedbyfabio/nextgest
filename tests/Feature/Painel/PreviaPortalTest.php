<?php

declare(strict_types=1);

use App\Livewire\Painel\Aparencia\Editar;
use App\Support\Aparencia;
use Livewire\Livewire;

/**
 * Prévia = portal real (mesmos componentes x-portal.*), carrossel das telas do
 * cliente, template aceso e salvar com reload.
 */

it('o portal REAL mostra a imagem de cabeçalho (capa) e o fundo quando configurados', function () {
    $t = criarTenant('lojacapa');
    tenancy()->initialize($t);
    Aparencia::salvar([
        'header_imagem' => 'aparencia/capa.png',
        'fundo_imagem' => 'aparencia/fundo.png',
    ]);

    // Visitante (sem login) → home com a capa (x-portal.capa) e o fundo no layout.
    $html = $this->get('/lojacapa')->assertOk()->content();

    expect($html)->toContain('/lojacapa/arquivo/aparencia/capa.png')   // capa/cabeçalho no hero
        ->toContain('/lojacapa/arquivo/aparencia/fundo.png');           // fundo no <body>
});

it('a prévia e o portal usam o MESMO componente de capa (imagem de cabeçalho aparece nos dois)', function () {
    $t = criarTenant('lojacompart');
    tenancy()->initialize($t);
    $this->actingAs(usuarioComPapel('Dono'), 'web');
    Aparencia::salvar(['header_imagem' => 'aparencia/capa.png']);

    // Prévia (no editor) referencia a mesma URL do arquivo de cabeçalho.
    $html = Livewire::test(Editar::class)->html();
    expect($html)->toContain('/lojacompart/arquivo/aparencia/capa.png');
});

it('a prévia expõe o carrossel com as 4 telas do cliente', function () {
    $t = criarTenant('lojacarrossel');
    tenancy()->initialize($t);
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    $html = Livewire::test(Editar::class)->html();

    // Estrutura do carrossel (4 telas).
    expect($html)->toContain("telas: ['Início', 'Login', 'Cliente', 'Agendar']")
        ->toContain('total: 4');

    // Cada tela renderiza seu conteúdo distintivo.
    expect($html)->toContain('Criar conta e agendar')      // 1) deslogado
        ->toContain('Acesse para agendar em')              // 2) login
        ->toContain('Olá, Ana')                            // 3) home logado
        ->toContain('Próximos agendamentos')               // 3) home logado
        ->toContain('Novo agendamento');                   // 4) fluxo de agendamento
});

it('a prévia tem o alternador claro/escuro aplicado à moldura (todas as telas)', function () {
    $t = criarTenant('lojatoggle');
    tenancy()->initialize($t);
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    $html = Livewire::test(Editar::class)->html();

    expect($html)->toContain('ng-previa')
        ->toContain("'is-dark': dark");
});

it('selecionar um template marca o estado ativo e persiste para reabrir', function () {
    $t = criarTenant('lojatpl');
    tenancy()->initialize($t);
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    // Ao aplicar, fica "aceso".
    Livewire::test(Editar::class)
        ->call('aplicarTemplate', 'barbearia')
        ->assertSet('template', 'barbearia')
        ->assertSeeHtml('aria-pressed="true"');

    // Persistido e refletido ao reabrir a tela.
    Aparencia::salvar(['template' => 'premium']);
    Livewire::test(Editar::class)->assertSet('template', 'premium');
});

it('salvar persiste e RECARREGA a página do painel (reload p/ aplicar o tema)', function () {
    $t = criarTenant('lojasalvar');
    tenancy()->initialize($t);
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Editar::class)
        ->set('cor_principal', '#123456')
        ->set('tamanho_base', '18px')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertRedirect(route('painel.aparencia', ['tenant' => 'lojasalvar']));

    $a = Aparencia::doTenant();
    expect($a['cor_principal'])->toBe('#123456');
    expect($a['tamanho_base'])->toBe('18px');
});
