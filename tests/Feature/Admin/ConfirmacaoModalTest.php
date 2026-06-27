<?php

declare(strict_types=1);

use App\Livewire\Admin\Faturamento;
use App\Livewire\Admin\TenantDetalhe;
use App\Livewire\Admin\Tenants;
use Livewire\Livewire;

/*
| D65 — confirmações do /admin usam o modal padrão (x-ng.confirmar), nunca o confirm
| nativo do navegador. Regressão: as telas NÃO podem conter `wire:confirm`; o modal de
| confirmação (título) tem de estar renderizado.
*/

// admin() / criarTenant() — helpers globais (tests/Pest.php).

it('lista de estabelecimentos: sem confirm nativo, com modal de inativar', function () {
    criarTenant('lojaum');
    $this->actingAs(admin(), 'admin');

    Livewire::test(Tenants::class)
        ->assertDontSeeHtml('wire:confirm')
        ->assertSeeHtml('wire:click="pedirInativar')
        ->assertSee('Inativar estabelecimento?'); // título do x-ng.confirmar
});

it('detalhe do tenant: aplicar plano via modal, sem confirm nativo', function () {
    criarTenant('lojaum');
    $this->actingAs(admin(), 'admin');

    Livewire::test(TenantDetalhe::class, ['tenantId' => 'lojaum'])
        ->assertDontSeeHtml('wire:confirm')
        ->assertSeeHtml('wire:click="pedirTrocarPlano"')
        ->assertSee('Aplicar este plano?');
});

it('faturamento: reverter/cancelar via modal, sem confirm nativo', function () {
    criarTenant('lojaum');
    $this->actingAs(admin(), 'admin');

    Livewire::test(Faturamento::class, ['tenantId' => 'lojaum'])
        ->assertDontSeeHtml('wire:confirm')
        ->assertSee('Reverter o pagamento?')
        ->assertSee('Cancelar esta fatura?');
});
