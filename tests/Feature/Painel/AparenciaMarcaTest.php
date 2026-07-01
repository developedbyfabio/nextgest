<?php

declare(strict_types=1);

use App\Livewire\Painel\Aparencia\Editar;
use App\Support\Aparencia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Marca do portal (D92): o dono escolhe a fonte do brand-mark do portal — um ÍCONE
 * predefinido (catálogo curado) ou o LOGO enviado (respeitando transparência),
 * persistido em configuracoes.aparencia (sem migração) e renderizado pelo PARTIAL
 * ÚNICO x-portal.marca (mesmo no portal e na prévia). Testes de EFEITO.
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojamarca');
    tenancy()->initialize($this->tenant);
});

/** Renderiza o brand-mark isolado (mesmo partial do portal/prévia). */
function renderMarca(array $aparencia, ?string $logoUrl = null, string $contexto = 'hero'): string
{
    return Blade::render(
        '<x-portal.marca :aparencia="$aparencia" :logo-url="$logoUrl" :contexto="$contexto" nome="Loja" />',
        compact('aparencia', 'logoUrl', 'contexto')
    );
}

it('salva marca_tipo=icone + marca_icone válido e o hero renderiza o ícone (no quadrado de acento)', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Editar::class)
        ->set('marca_tipo', 'icone')
        ->set('marca_icone', 'sparkles')
        ->call('salvar')
        ->assertHasNoErrors();

    $a = Aparencia::doTenant();
    expect($a['marca_tipo'])->toBe('icone');
    expect($a['marca_icone'])->toBe('sparkles');

    // Ícone → quadrado de acento com um SVG (flux:icon), SEM <img de logo.
    $html = renderMarca($a);
    expect($html)->toContain('background-color: var(--cor-principal)')
        ->toContain('data-flux-icon')
        ->not->toContain('<img');

    // O ícone escolhido realmente flui: 'sparkles' e 'scissors' renderizam SVGs distintos.
    $scissors = renderMarca(array_merge($a, ['marca_icone' => 'scissors']));
    expect($html)->not->toBe($scissors);
});

it('rejeita marca_icone fora do catálogo (Rule::in) e marca_tipo inválido', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Editar::class)
        ->set('marca_tipo', 'icone')
        ->set('marca_icone', 'nao-existe')
        ->call('salvar')
        ->assertHasErrors('marca_icone');

    Livewire::test(Editar::class)
        ->set('marca_tipo', 'banner')
        ->call('salvar')
        ->assertHasErrors('marca_tipo');
});

it('TODO ícone do catálogo passa na validação e persiste', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    foreach (array_keys(Aparencia::ICONES_MARCA) as $chave) {
        Livewire::test(Editar::class)
            ->set('marca_tipo', 'icone')
            ->set('marca_icone', $chave)
            ->call('salvar')
            ->assertHasNoErrors();

        expect(Aparencia::doTenant()['marca_icone'])->toBe($chave);
    }
});

it('salva marca_tipo=logo com logo enviado e o mark renderiza o LOGO (transparência preservada)', function () {
    Storage::fake('public');
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Editar::class)
        ->set('marca_tipo', 'logo')
        ->set('logoUpload', UploadedFile::fake()->image('logo.png', 300, 120))
        ->call('salvar')
        ->assertHasNoErrors();

    $a = Aparencia::doTenant();
    expect($a['marca_tipo'])->toBe('logo');
    expect($a['logo'])->toStartWith('aparencia/');

    $logoUrl = Aparencia::urlArquivo($a['logo']);
    $html = renderMarca($a, $logoUrl);

    // Logo → <img com a URL, SEM quadrado de acento atrás (não anula transparência).
    expect($html)->toContain('<img')
        ->toContain($logoUrl)
        ->not->toContain('background-color: var(--cor-principal)');
});

it('marca_tipo=logo SEM logo enviado cai no fallback do ícone padrão', function () {
    // Config diz "logo", mas não há imagem → o partial cai no ícone (quadrado de acento).
    $a = array_merge(Aparencia::doTenant(), ['marca_tipo' => 'logo', 'marca_icone' => 'scissors']);

    $html = renderMarca($a, null); // logoUrl null = sem logo
    expect($html)->toContain('background-color: var(--cor-principal)')
        ->toContain('data-flux-icon')
        ->not->toContain('<img');
});

it('marcaIcone valida: chave do catálogo é mantida; ausente/inválida vira o padrão', function () {
    expect(Aparencia::marcaIcone(['marca_icone' => 'gift']))->toBe('gift');
    expect(Aparencia::marcaIcone(['marca_icone' => 'inexistente']))->toBe('scissors');
    expect(Aparencia::marcaIcone([]))->toBe('scissors');
});

it('o brand-mark é UM partial só, reusado no hero e no topo (sem markup paralelo)', function () {
    // Portal e prévia compartilham x-portal.capa (hero) e x-portal.cabecalho (topo);
    // ambos delegam ao MESMO x-portal.marca — nenhuma renderização de ícone/logo duplicada.
    $capa = file_get_contents(resource_path('views/components/portal/capa.blade.php'));
    $cabecalho = file_get_contents(resource_path('views/components/portal/cabecalho.blade.php'));

    expect(str_contains($capa, 'x-portal.marca'))->toBeTrue('capa não usa o partial x-portal.marca');
    expect(str_contains($cabecalho, 'x-portal.marca'))->toBeTrue('cabecalho não usa o partial x-portal.marca');

    // O partial existe.
    expect(file_exists(resource_path('views/components/portal/marca.blade.php')))->toBeTrue();
});
