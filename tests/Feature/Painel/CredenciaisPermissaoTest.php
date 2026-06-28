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

it('Dono acessa o gateway de pagamento (200) e whatsapp (200)', function () {
    $dono = $this->tenant->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']));

    $this->actingAs($dono, 'web')->get('/lojaum/painel/pagamentos')->assertOk();
    $this->actingAs($dono, 'web')->get('/lojaum/painel/whatsapp')->assertOk();
});

it('Gerente: whatsapp 200, gateway de pagamento 403 (sem gerenciar_pagamentos)', function () {
    $ger = $this->tenant->run(fn () => usuarioComPapel('Gerente', ['email' => 'ger@lojaum.com']));

    $this->actingAs($ger, 'web')->get('/lojaum/painel/whatsapp')->assertOk();
    $this->actingAs($ger, 'web')->get('/lojaum/painel/pagamentos')->assertForbidden();
});

it('Recepção: 403 em pagamento e whatsapp', function () {
    $rec = $this->tenant->run(fn () => usuarioComPapel('Recepção', ['email' => 'rec@lojaum.com']));

    $this->actingAs($rec, 'web')->get('/lojaum/painel/pagamentos')->assertForbidden();
    $this->actingAs($rec, 'web')->get('/lojaum/painel/whatsapp')->assertForbidden();
});

it('Profissional puro: 403 em pagamento e whatsapp', function () {
    $prof = $this->tenant->run(fn () => usuarioComPapel('Profissional', ['email' => 'prof@lojaum.com', 'e_profissional' => true]));

    $this->actingAs($prof, 'web')->get('/lojaum/painel/pagamentos')->assertForbidden();
    $this->actingAs($prof, 'web')->get('/lojaum/painel/whatsapp')->assertForbidden();
});

it('Dono + Profissional (mesma pessoa): acessa pagamento e whatsapp E é agendável', function () {
    $u = $this->tenant->run(function () {
        $u = usuarioComPapel('Dono', ['email' => 'solo@lojaum.com', 'e_profissional' => true]);
        $u->assignRole('Profissional'); // segundo papel, sem remover o Dono

        return $u;
    });

    // Acesso de Dono (superset) mantém-se com o papel extra de Profissional.
    $this->actingAs($u, 'web')->get('/lojaum/painel/pagamentos')->assertOk();
    $this->actingAs($u, 'web')->get('/lojaum/painel/whatsapp')->assertOk();

    // Agendável independe do papel: depende de e_profissional (query padrão de agendáveis).
    $agendaveis = $this->tenant->run(
        fn () => User::where('e_profissional', true)->where('ativo', true)->pluck('email')->all()
    );
    expect($agendaveis)->toContain('solo@lojaum.com');
});
