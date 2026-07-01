<?php

declare(strict_types=1);

use App\Livewire\Painel\Aparencia\Editar;
use App\Support\Aparencia;
use App\Support\Favicon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Favicon por tenant (D90): upload PROCESSADO no servidor (reduz p/ 32x32 e
 * converte PNG, via GD), guardado scoped no disco do tenant (como o logo) e
 * injetado no `<head>` (portal/painel/auth) com fallback pro padrão do Nextgest
 * e cache-busting (nome único por upload). Testes de EFEITO.
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojafav');
    tenancy()->initialize($this->tenant);
});

it('processa a imagem enviada num PNG 32x32 (contain) gravado no disco do tenant', function () {
    Storage::fake('public');
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    // Imagem GRANDE e NÃO-quadrada → deve virar um PNG 32x32.
    Livewire::test(Editar::class)
        ->set('faviconUpload', UploadedFile::fake()->image('marca.jpg', 800, 400))
        ->call('salvar')
        ->assertHasNoErrors();

    $path = Aparencia::doTenant()['favicon'];
    expect($path)->toStartWith('aparencia/favicon-')->toEndWith('.png');
    Storage::disk('public')->assertExists($path);

    // Efeito do processamento: 32x32 e PNG (não a imagem crua 800x400/jpeg).
    $info = getimagesizefromstring(Storage::disk('public')->get($path));
    expect($info[0])->toBe(Favicon::TAMANHO);
    expect($info[1])->toBe(Favicon::TAMANHO);
    expect($info['mime'])->toBe('image/png');
});

it('processa também WebP de entrada (GD do servidor decodifica WebP)', function () {
    Storage::fake('public');
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Editar::class)
        ->set('faviconUpload', UploadedFile::fake()->image('icone.webp', 256, 256))
        ->call('salvar')
        ->assertHasNoErrors();

    $path = Aparencia::doTenant()['favicon'];
    $info = getimagesizefromstring(Storage::disk('public')->get($path));
    expect($info['mime'])->toBe('image/png');
    expect($info[0])->toBe(Favicon::TAMANHO);
});

it('injeta o <link rel=icon> do favicon do tenant, servido pela rota do tenant', function () {
    Storage::fake('public');

    // Sem favicon → fallback pro padrão do Nextgest (head não quebra).
    $semFavicon = Aparencia::linkFavicon(Aparencia::doTenant());
    expect($semFavicon)->toContain('rel="icon"')
        ->toContain('nextgest-logo.png');

    // Com favicon → aponta pro arquivo do tenant (rota /{slug}/arquivo/...).
    Aparencia::salvar(['favicon' => 'aparencia/favicon-abc.png']);
    $comFavicon = Aparencia::linkFavicon(Aparencia::doTenant());
    expect($comFavicon)->toContain('rel="icon"')
        ->toContain('type="image/png"')
        ->toContain('/lojafav/arquivo/aparencia/favicon-abc.png')
        ->not->toContain('nextgest-logo.png');
});

it('troca de favicon gera caminho novo (cache-busting por nome único)', function () {
    Storage::fake('public');

    $p1 = Favicon::processar(UploadedFile::fake()->image('a.png', 120, 120));
    $p2 = Favicon::processar(UploadedFile::fake()->image('a.png', 120, 120));

    expect($p1)->not->toBe($p2); // URL nova a cada upload → força a atualização
    Storage::disk('public')->assertExists($p1);
    Storage::disk('public')->assertExists($p2);
});

it('remove o favicon do tenant sem apagar o resto da aparência', function () {
    Storage::fake('public');
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Aparencia::salvar(['favicon' => 'aparencia/favicon-abc.png', 'cor_principal' => '#123456']);

    Livewire::test(Editar::class)
        ->call('removerImagem', 'favicon')
        ->call('salvar')
        ->assertHasNoErrors();

    $a = Aparencia::doTenant();
    expect($a['favicon'])->toBeNull();
    expect($a['cor_principal'])->toBe('#123456'); // não vazou pro resto
});

it('rejeita formato inválido (PDF) já no upload temporário', function () {
    Storage::fake('public');
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Editar::class)
        ->set('faviconUpload', UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
        ->assertHasErrors('faviconUpload');
});

it('rejeita favicon acima de 5 MB já no upload temporário', function () {
    Storage::fake('public');
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Editar::class)
        ->set('faviconUpload', UploadedFile::fake()->create('grande.png', 6000, 'image/png'))
        ->assertHasErrors('faviconUpload');
});

it('injeta linkFavicon no <head> dos layouts do tenant (portal, painel, auth)', function () {
    // Guarda a exigência central da feature: o `<link rel=icon>` entra no head no
    // MESMO ponto onde cores/fonte já entram (contexto path-based do tenant).
    foreach ([
        'portal.blade.php',
        'painel.blade.php',
        'portal-auth.blade.php',
        'auth.blade.php',
    ] as $layout) {
        $html = file_get_contents(resource_path("views/components/layouts/{$layout}"));
        expect(str_contains($html, 'Aparencia::linkFavicon'))->toBeTrue("Favicon não injetado em {$layout}");
    }
});
