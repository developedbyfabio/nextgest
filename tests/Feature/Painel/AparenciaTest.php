<?php

declare(strict_types=1);

use App\Livewire\Painel\Aparencia\Editar;
use App\Support\Aparencia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojavisual');
    tenancy()->initialize($this->tenant);
});

it('aplica um template copiando seus valores para o formulário', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Editar::class)
        ->call('aplicarTemplate', 'barbearia')
        ->assertSet('cor_principal', '#b45309')
        ->assertSet('icone_estilo', 'solid')
        ->assertHasNoErrors();
});

it('salva a aparência e persiste em configuracoes', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Editar::class)
        ->set('cor_principal', '#123456')
        ->set('tamanho_base', '18px')
        ->call('salvar')
        ->assertHasNoErrors();

    $a = Aparencia::doTenant();
    expect($a['cor_principal'])->toBe('#123456');
    expect($a['tamanho_base'])->toBe('18px');
    expect(Aparencia::cssVars($a))->toContain('--cor-principal: #123456');
    expect(Aparencia::cssVars($a))->toContain('--color-accent: #123456');
});

it('valida cor inválida e tamanho fora do formato', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Editar::class)
        ->set('cor_principal', 'azul')
        ->set('tamanho_base', 'grande')
        ->call('salvar')
        ->assertHasErrors(['cor_principal', 'tamanho_base']);
});

it('reflete as escolhas na prévia ao vivo (CSS vars)', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Editar::class)
        ->set('cor_principal', '#abcdef')
        ->assertSee('--cor-principal: #abcdef', false);
});

it('renderiza o componente reutilizável de prévia com a aparência recebida', function () {
    $html = Blade::render(
        '<x-ng.previa-portal :aparencia="$a" nome="Estúdio Teste" />',
        ['a' => ['cor_principal' => '#ff0099', 'cor_texto' => '#101010']]
    );

    expect($html)->toContain('--cor-principal: #ff0099');
    expect($html)->toContain('--cor-texto: #101010');
    expect($html)->toContain('Estúdio Teste');
});

it('bloqueia quem não tem gerir_aparencia (Profissional, 403)', function () {
    $this->actingAs(usuarioComPapel('Profissional'), 'web')
        ->get('/lojavisual/painel/aparencia')
        ->assertForbidden();
});

it('permite acesso ao Gerente', function () {
    $this->actingAs(usuarioComPapel('Gerente'), 'web')
        ->get('/lojavisual/painel/aparencia')
        ->assertOk();
});

it('faz upload de logo, persiste o caminho e gera URL por caminho', function () {
    Storage::fake('public');
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Editar::class)
        ->set('logoUpload', UploadedFile::fake()->image('logo.png', 64, 64))
        ->call('salvar')
        ->assertHasNoErrors();

    $logo = Aparencia::doTenant()['logo'];
    expect($logo)->toStartWith('aparencia/');
    Storage::disk('public')->assertExists($logo);
    expect(Aparencia::urlArquivo($logo))->toContain('/lojavisual/arquivo/aparencia/');
});

it('remove a imagem do estado e persiste a remoção ao salvar', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');
    Aparencia::salvar(['logo' => 'aparencia/x.png']);

    Livewire::test(Editar::class)
        ->assertSet('logo', 'aparencia/x.png')
        ->call('removerImagem', 'logo')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(Aparencia::doTenant()['logo'])->toBeNull();
});

it('serve arquivo do tenant por caminho e responde 404 para inexistente', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    $this->get('/lojavisual/arquivo/aparencia/naoexiste.png')->assertNotFound();

    $base = storage_path('app/public');
    File::ensureDirectoryExists($base.'/aparencia');
    // PNG 1x1 mínimo.
    file_put_contents(
        $base.'/aparencia/logo.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
    );

    try {
        $this->get('/lojavisual/arquivo/aparencia/logo.png')->assertOk();
        // Path traversal não escapa da raiz pública do tenant.
        $this->get('/lojavisual/arquivo/'.urlencode('../').'.env')->assertNotFound();
    } finally {
        File::deleteDirectory($base);
    }
});
