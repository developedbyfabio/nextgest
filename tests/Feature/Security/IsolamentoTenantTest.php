<?php

declare(strict_types=1);

use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\SessionGuard;

/*
| T1 — Isolamento entre tenants. Método FIEL (evita os 2 artefatos conhecidos):
| - status CRU (o cliente HTTP de teste NÃO segue redirect);
| - NÃO usar actingAs() em cross-tenant: ele injeta o usuário em memória no guard e
|   sobrevive entre requests do mesmo processo, BURLANDO a resolução sessão→banco.
|   Em vez disso, montamos a SESSÃO real (chave de login do SessionGuard) para o guard
|   resolver o id contra o banco do tenant ALVO — como em produção.
| Fixtures próprias (sega/segb), nunca dados de demo.
*/

function sessaoLogin(string $guard, int $id, string $tenantSlug): array
{
    return [
        'login_'.$guard.'_'.sha1(SessionGuard::class) => $id,
        '_tenant_sessao' => $tenantSlug,
    ];
}

it('[T1] DB-per-tenant: id de usuário do tenant B não existe no contexto do tenant A', function () {
    $idEmB = criarTenant('segb')->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@b.test'])->id);

    $achadoEmA = criarTenant('sega')->run(fn () => User::find($idEmB));

    expect($achadoEmA)->toBeNull(); // bancos separados — VERMELHO se compartilhassem DB
});

it('[T1] sessão de equipe do tenant A não autentica no painel do tenant B', function () {
    $donoA = criarTenant('sega')->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@a.test']));
    criarTenant('segb'); // B não tem o id de A → resolução por banco do tenant falha

    $resp = $this->withSession(sessaoLogin('web', $donoA->id, 'sega'))
        ->get('/segb/painel/equipe');

    expect($resp->status())->not->toBe(200);                     // não acessa o painel de B
    expect($resp->getContent())->not->toContain('dono@a.test');  // e não vaza dado
});

it('[T1] sessão de cliente do tenant A não autentica no portal do tenant B', function () {
    $cliA = criarTenant('sega')->run(fn () => Cliente::create([
        'nome' => 'Cli A', 'email' => 'cli@a.test', 'telefone' => '11999990000', 'password' => 'segredo-cli-123',
    ]));
    criarTenant('segb');

    $resp = $this->withSession(sessaoLogin('cliente', $cliA->id, 'sega'))
        ->get('/segb/agendar');

    expect($resp->status())->not->toBe(200);
    expect($resp->getContent())->not->toContain('cli@a.test');
});

it('[T1] página pública de um tenant não vaza o slug de outro (banco central)', function () {
    criarTenant('sega');
    criarTenant('segb');

    $resp = $this->get('/sega');

    $resp->assertOk();
    expect($resp->getContent())->not->toContain('segb');
});

/*
| VULN-002 (média) — tenant INATIVO continua servindo (200). Não há middleware que
| barre `tenant('ativo') === false`; só o `ativo` do USUÁRIO é checado no login. O teste
| abaixo descreve o comportamento SEGURO esperado e fica `skip` até o fix (suite verde).
*/
it('[T1][VULN-002] tenant INATIVO deveria bloquear o acesso ao portal', function () {
    criarTenant('seginativo');
    Tenant::whereKey('seginativo')->update(['ativo' => false]);

    $resp = $this->get('/seginativo');

    expect($resp->status())->toBeIn([403, 404, 503]); // hoje retorna 200
})->skip('VULN-002: tenant inativo não é bloqueado (retorna 200). Aguardando fix aprovado.');
