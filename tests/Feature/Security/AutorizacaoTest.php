<?php

declare(strict_types=1);

use App\Livewire\Painel\Pagamentos\Gateway;
use App\Models\GatewayPagamento;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venda;
use App\Policies\VendaPolicy;
use Livewire\Livewire;

/*
| T2/T3/T6 — Autorização interna (permissão + IDOR), reavaliação no servidor (Livewire)
| e mass assignment. Tudo no MESMO tenant (sem artefato cross-tenant). Status CRU.
*/

beforeEach(function () {
    $this->tenant = criarTenant('segaut');
    tenancy()->initialize($this->tenant);
});

// ---- T2: gate por permissão (status cru) ----

it('[T2] Recepção não vê comissões/financeiro (ver_financeiro) → 403', function () {
    $this->actingAs(usuarioComPapel('Recepção'), 'web')
        ->get('/segaut/painel/comissoes')->assertForbidden();
});

it('[T2] Profissional não acessa equipe (editar_usuario) → 403', function () {
    $this->actingAs(usuarioComPapel('Profissional', ['e_profissional' => true]), 'web')
        ->get('/segaut/painel/equipe')->assertForbidden();
});

it('[T2] Gerente não acessa papéis (editar_permissoes) → 403', function () {
    $this->actingAs(usuarioComPapel('Gerente'), 'web')
        ->get('/segaut/painel/papeis')->assertForbidden();
});

// ---- T2: IDOR de comanda (VendaPolicy) ----

it('[T2] VendaPolicy: Profissional só gere a PRÓPRIA comanda de atendimento', function () {
    $p1 = usuarioComPapel('Profissional', ['email' => 'p1@x.test', 'e_profissional' => true]);
    $p2 = usuarioComPapel('Profissional', ['email' => 'p2@x.test', 'e_profissional' => true]);
    $dono = usuarioComPapel('Dono', ['email' => 'd@x.test']);
    $policy = new VendaPolicy;

    $vendaDeP2 = new Venda(['profissional_id' => $p2->id, 'agendamento_id' => 1]);
    expect($policy->gerir($p1, $vendaDeP2))->toBeFalse()      // de OUTRO profissional → bloqueado
        ->and($policy->gerir($p2, $vendaDeP2))->toBeTrue()    // a própria → ok
        ->and($policy->gerir($dono, $vendaDeP2))->toBeTrue(); // criar_venda → qualquer comanda

    $avulsa = new Venda(['profissional_id' => $p2->id, 'agendamento_id' => null]);
    expect($policy->gerir($p2, $avulsa))->toBeFalse();        // avulsa (sem atendimento) → bloqueado
});

// ---- T3: Livewire reavalia no servidor (não só esconde botão) ----

it('[T3] Gerente é barrado no gateway de pagamento mesmo com a flag ligada (403 no servidor)', function () {
    $t = Tenant::find('segaut');
    $t->recursos = ['gateway'];
    $t->save(); // isola a checagem de PERMISSÃO (não a flag)

    $this->actingAs(usuarioComPapel('Gerente'), 'web')
        ->get('/segaut/painel/pagamentos')->assertForbidden();
});

it('[T3] a tela do gateway nunca expõe o token (só a conta pública)', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    GatewayPagamento::create([
        'provedor' => 'mercadopago', 'ativo' => true, 'padrao' => true,
        'conta_externa_id' => '99', 'conta_externa_nome' => 'Salão Conectado',
        'credenciais' => ['access_token' => 'SEGREDO-SNAP-9876'],
    ]);

    Livewire::test(Gateway::class)
        ->assertSee('Salão Conectado')        // conta pública aparece
        ->assertDontSee('SEGREDO-SNAP-9876');  // token nunca
});

// ---- T6: mass assignment ----

it('[T6] papel não é mass-assignable via create (não é coluna; só via syncRoles em tela gated)', function () {
    $u = User::create([
        'name' => 'Tamper', 'email' => 'tamper@x.test', 'password' => 'senha-123-ok',
        'role' => 'Dono', 'is_admin' => true, // campos injetados não existem/relacionam
    ]);

    expect($u->hasRole('Dono'))->toBeFalse()        // 'role' ignorado
        ->and($u->getAttribute('is_admin'))->toBeNull(); // coluna inexistente, não persiste
});

it('[T6] Profissional não consegue montar a tela de equipe para mexer em papel/e_profissional', function () {
    // Sem editar_usuario, a rota da equipe é 403 — não há caminho para setar e_profissional/papel.
    $this->actingAs(usuarioComPapel('Profissional', ['e_profissional' => false]), 'web')
        ->get('/segaut/painel/equipe')->assertForbidden();
});
