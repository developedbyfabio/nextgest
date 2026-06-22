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

it('[T1][VULN-001] sessão de equipe do A no painel do B → 302 LIMPO (não 500), sem vazar', function () {
    $donoA = criarTenant('sega')->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@a.test']));
    criarTenant('segb');

    $resp = $this->withSession(sessaoLogin('web', $donoA->id, 'sega'))
        ->get('/segb/painel');

    $resp->assertStatus(302);                                    // redirect LIMPO ao login (não 500)
    expect($resp->getContent())->not->toContain('dono@a.test');  // e não vaza dado de A nem de B
});

it('[T1] sessão de cliente do A no portal autenticado do B → 302 limpo', function () {
    $cliA = criarTenant('sega')->run(fn () => Cliente::create([
        'nome' => 'Cli A', 'email' => 'cli@a.test', 'telefone' => '11999990000', 'password' => 'segredo-cli-123',
    ]));
    criarTenant('segb');

    $resp = $this->withSession(sessaoLogin('cliente', $cliA->id, 'sega'))
        ->get('/segb/agendar');

    $resp->assertStatus(302);
    expect($resp->getContent())->not->toContain('cli@a.test');
});

it('[T1] sessão VÁLIDA do PRÓPRIO tenant acessa o painel (200 — não-regressão do fix)', function () {
    $dono = criarTenant('segown')->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@own.test']));

    $resp = $this->withSession(sessaoLogin('web', $dono->id, 'segown'))
        ->get('/segown/painel');

    $resp->assertOk(); // mesmo-tenant segue funcionando com o EscoparAutenticacaoPorTenant reordenado
});

it('[T1] página pública de um tenant não vaza o slug de outro (banco central)', function () {
    criarTenant('sega');
    criarTenant('segb');

    $resp = $this->get('/sega');

    $resp->assertOk();
    expect($resp->getContent())->not->toContain('segb');
});

/*
| VULN-002 (CORRIGIDA) — tenant INATIVO é bloqueado (404) em todo o grupo de tenant,
| via App\Http\Middleware\GarantirTenantAtivo (após o init da tenancy). Painel e portal.
*/
it('[T1][VULN-002] tenant INATIVO bloqueia (404) painel e portal', function () {
    criarTenant('seginativo');
    Tenant::whereKey('seginativo')->update(['ativo' => false]);

    $this->get('/seginativo')->assertNotFound();               // portal (home pública)
    $this->get('/seginativo/painel/login')->assertNotFound();  // login do tenant (não se loga em salão suspenso)
    $this->get('/seginativo/agendar')->assertNotFound();       // portal autenticado
});

it('[T1] tenant ATIVO segue acessível (não-regressão da VULN-002)', function () {
    criarTenant('segativo');

    $this->get('/segativo')->assertOk();
    $this->get('/segativo/painel/login')->assertOk();
});

it('[T1] inativar bloqueia mas NÃO apaga dado (reversível)', function () {
    $t = criarTenant('segreativa');
    $t->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@r.test']));

    Tenant::whereKey('segreativa')->update(['ativo' => false]); // inativar (soft)
    $this->get('/segreativa')->assertNotFound();                // acesso barrado

    // O dado continua lá (inativar é reversível; reativar volta a funcionar — ver teste
    // de "tenant ATIVO → 200"). Cada request é um processo: o re-GET pós-reativação no
    // MESMO teste cairia no early-return de Tenancy::initialize (instância stale).
    expect($t->run(fn () => User::where('email', 'dono@r.test')->exists()))->toBeTrue();
    expect(Tenant::find('segreativa'))->not->toBeNull(); // tenant não foi apagado
});

it('[T1] o admin/central NÃO é afetado pelo bloqueio de tenant (login do admin abre)', function () {
    // Rotas centrais não passam pelo grupo de tenant → GarantirTenantAtivo não as alcança.
    $this->get('/admin/login')->assertOk();
});
