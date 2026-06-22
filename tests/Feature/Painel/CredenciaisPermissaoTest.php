<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

/*
| Permissão de credenciais (decisão Fabio): gerenciar_pagamentos = só Dono;
| gerenciar_whatsapp = Dono + Gerente. Gate por PERMISSÃO (can), nunca por papel.
| Tenants criados no teste já nascem com o seeder atualizado. Fluxo HTTP por tenant.
*/

beforeEach(function () {
    $this->tenant = criarTenant('lojaum');

    // Liga as flags (0a) no registro central para os editores passarem no middleware recurso:.
    $t = Tenant::find('lojaum');
    $t->recursos = ['gateway', 'whatsapp'];
    $t->save();
});

it('Dono acessa credenciais de pagamento (200) e whatsapp (200)', function () {
    $dono = $this->tenant->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']));

    $this->actingAs($dono, 'web')->get('/lojaum/painel/integracoes/mercadopago')->assertOk();
    $this->actingAs($dono, 'web')->get('/lojaum/painel/integracoes/whatsapp')->assertOk();
});

it('Gerente: whatsapp 200, pagamento 403 e índice sem o card de pagamento', function () {
    $ger = $this->tenant->run(fn () => usuarioComPapel('Gerente', ['email' => 'ger@lojaum.com']));

    $this->actingAs($ger, 'web')->get('/lojaum/painel/integracoes/whatsapp')->assertOk();
    $this->actingAs($ger, 'web')->get('/lojaum/painel/integracoes/mercadopago')->assertForbidden();

    $this->actingAs($ger, 'web')->get('/lojaum/painel/integracoes')
        ->assertOk()
        ->assertSee('WhatsApp')
        ->assertDontSee('Mercado Pago');
});

it('Recepção: 403 em pagamento, whatsapp e no índice', function () {
    $rec = $this->tenant->run(fn () => usuarioComPapel('Recepção', ['email' => 'rec@lojaum.com']));

    $this->actingAs($rec, 'web')->get('/lojaum/painel/integracoes/mercadopago')->assertForbidden();
    $this->actingAs($rec, 'web')->get('/lojaum/painel/integracoes/whatsapp')->assertForbidden();
    $this->actingAs($rec, 'web')->get('/lojaum/painel/integracoes')->assertForbidden();
});

it('Profissional puro: 403 em pagamento e whatsapp', function () {
    $prof = $this->tenant->run(fn () => usuarioComPapel('Profissional', ['email' => 'prof@lojaum.com', 'e_profissional' => true]));

    $this->actingAs($prof, 'web')->get('/lojaum/painel/integracoes/mercadopago')->assertForbidden();
    $this->actingAs($prof, 'web')->get('/lojaum/painel/integracoes/whatsapp')->assertForbidden();
});

it('Dono + Profissional (mesma pessoa): acessa pagamento e whatsapp E é agendável', function () {
    $u = $this->tenant->run(function () {
        $u = usuarioComPapel('Dono', ['email' => 'solo@lojaum.com', 'e_profissional' => true]);
        $u->assignRole('Profissional'); // segundo papel, sem remover o Dono

        return $u;
    });

    // Acesso de Dono (superset) mantém-se com o papel extra de Profissional.
    $this->actingAs($u, 'web')->get('/lojaum/painel/integracoes/mercadopago')->assertOk();
    $this->actingAs($u, 'web')->get('/lojaum/painel/integracoes/whatsapp')->assertOk();

    // Agendável independe do papel: depende de e_profissional (query padrão de agendáveis).
    $agendaveis = $this->tenant->run(
        fn () => User::where('e_profissional', true)->where('ativo', true)->pluck('email')->all()
    );
    expect($agendaveis)->toContain('solo@lojaum.com');
});
