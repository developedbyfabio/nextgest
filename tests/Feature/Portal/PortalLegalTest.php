<?php

declare(strict_types=1);

use App\Support\Legal;

/**
 * Documentos legais do portal (D93): Política de Privacidade e Termos de Uso —
 * páginas PÚBLICAS (sem login), renderizadas no layout do portal, com conteúdo
 * ÚNICO compartilhado por todos os tenants (só o slug da URL muda). HTTP real
 * (passa pela rota + tenancy por caminho).
 */
beforeEach(function () {
    criarTenant('lojaum');
});

it('serve a Política de Privacidade publicamente (200, sem login) no layout do portal', function () {
    $r = $this->get('/lojaum/politica-de-privacidade');

    $r->assertOk(); // público: 200, não redireciona para login
    $r->assertSee('Política de Privacidade');
    $r->assertSee('Lei Geral de Proteção de Dados Pessoais'); // conteúdo LGPD real
    $r->assertSee('Direitos do titular');
    $r->assertSee('Versão '.Legal::VERSAO);
    $r->assertSee('Powered by Nextgest'); // rodapé do layout do portal (tema aplicado)
});

it('serve os Termos de Uso publicamente (200, sem login) no layout do portal', function () {
    $r = $this->get('/lojaum/termos-de-uso');

    $r->assertOk();
    $r->assertSee('Termos de Uso');
    $r->assertSee('Legislação aplicável e foro');
    $r->assertSee('Powered by Nextgest');
    $r->assertSee('Versão '.Legal::VERSAO);
});

it('a página traz o nome do estabelecimento, versão e link de voltar para o portal', function () {
    $r = $this->get('/lojaum/politica-de-privacidade');

    $r->assertOk();
    $r->assertSee('Lojaum');                 // nome do estabelecimento no cabeçalho
    $r->assertSee('Voltar para');
    // Link de voltar aponta para o portal do tenant (URL segue o APP_URL do ambiente).
    $r->assertSee('href="'.route('tenant.home', ['tenant' => 'lojaum']).'"', false);
});

it('dois tenants servem o MESMO conteúdo em suas próprias URLs (sem vazar)', function () {
    criarTenant('lojadois');

    $trecho = 'Confirmação da existência de tratamento'; // trecho distintivo do conteúdo único

    $this->get('/lojaum/politica-de-privacidade')->assertOk()->assertSee($trecho);
    $this->get('/lojadois/politica-de-privacidade')->assertOk()->assertSee($trecho);

    // Cada página aponta para os documentos do PRÓPRIO slug.
    $this->get('/lojaum/politica-de-privacidade')->assertSee('/lojaum/termos-de-uso');
    $this->get('/lojadois/politica-de-privacidade')->assertSee('/lojadois/termos-de-uso');
});

it('o rodapé com os links legais aparece na home, no login e no registro', function () {
    foreach (['/lojaum', '/lojaum/login', '/lojaum/registrar'] as $url) {
        $r = $this->get($url);
        $r->assertOk();
        $r->assertSee('/lojaum/politica-de-privacidade');
        $r->assertSee('/lojaum/termos-de-uso');
        $r->assertSee('Powered by Nextgest');
    }
});

it('login e registro mostram a linha de consentimento com os links legais', function () {
    foreach (['/lojaum/login', '/lojaum/registrar'] as $url) {
        $r = $this->get($url);
        $r->assertOk();
        $r->assertSee('Ao continuar, você concorda com');
        $r->assertSee('/lojaum/politica-de-privacidade');
        $r->assertSee('/lojaum/termos-de-uso');
    }
});
