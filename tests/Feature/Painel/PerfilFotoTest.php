<?php

declare(strict_types=1);

use App\Livewire\Painel\Perfil\Foto;
use App\Support\Aparencia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Foto de perfil (Item 5): upload + recorte quadrado, self-service por usuário.
 * O recorte roda no cliente (Cropper.js); o servidor valida e persiste reaproveitando
 * o MESMO caminho da Aparência (store('aparencia','public') → disco do tenant, servido
 * por TenantArquivoController). Testes de EFEITO + os marcadores de menu no layout.
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojafoto');
    tenancy()->initialize($this->tenant);
});

it('faz upload da foto, grava na pasta do tenant (aparencia) e referencia no usuário', function () {
    Storage::fake('public');
    $dono = usuarioComPapel('Dono');
    $this->actingAs($dono, 'web');

    Livewire::test(Foto::class)
        ->set('foto', UploadedFile::fake()->image('foto.png', 512, 512))
        ->call('salvar')
        ->assertHasNoErrors();

    $dono->refresh();
    expect($dono->foto_perfil)->toStartWith('aparencia/');
    Storage::disk('public')->assertExists($dono->foto_perfil);
    // Servida pela rota do tenant (não tenant_asset) — isolada por tenant.
    expect(Aparencia::urlArquivo($dono->foto_perfil))->toContain('/lojafoto/arquivo/aparencia/');
});

it('remover zera a foto do usuário (volta às iniciais)', function () {
    Storage::fake('public');
    $dono = usuarioComPapel('Dono', ['foto_perfil' => 'aparencia/antiga.png']);
    $this->actingAs($dono, 'web');

    Livewire::test(Foto::class)->call('remover');

    expect($dono->refresh()->foto_perfil)->toBeNull();
});

it('rejeita arquivo não-imagem (PDF)', function () {
    Storage::fake('public');
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Foto::class)
        ->set('foto', UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
        ->assertHasErrors('foto');
});

it('rejeita SVG (mimes:png,jpg,jpeg,webp — sem SVG, que pode carregar script)', function () {
    Storage::fake('public');
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Foto::class)
        ->set('foto', UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'))
        ->assertHasErrors('foto');
});

it('rejeita upload acima de 5 MB', function () {
    Storage::fake('public');
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Foto::class)
        ->set('foto', UploadedFile::fake()->create('grande.png', 6000, 'image/png'))
        ->assertHasErrors('foto');
});

it('a foto fica isolada por tenant: um usuário de outro tenant não a herda', function () {
    Storage::fake('public');
    // Tenant A grava uma foto.
    $donoA = usuarioComPapel('Dono', ['foto_perfil' => 'aparencia/a.png']);
    expect($donoA->foto_perfil)->toBe('aparencia/a.png');

    // Tenant B nasce sem foto para seus usuários (colunas/bancos separados).
    $tenantB = criarTenant('lojafotob');
    tenancy()->initialize($tenantB);
    expect(usuarioComPapel('Dono')->foto_perfil)->toBeNull();
});

it('o painel monta o menu novo: Início como sidebar.item, dropdown nome+email e o modal de foto', function () {
    $dono = usuarioComPapel('Dono', ['name' => 'Dona Demo', 'email' => 'dona@demo.test']);
    $html = $this->actingAs($dono, 'web')->get('/lojafoto/painel')->assertOk()->content();

    // Tarefa 1: "Início" virou flux:sidebar.item — tem as classes do estado RECOLHIDO
    // (que o flux:navlist.item não tinha → causa do "espremido").
    expect($html)->toContain('data-flux-sidebar-item')
        ->toContain('in-data-flux-sidebar-collapsed-desktop:w-10');

    // Item 4: nome como cabeçalho (font-semibold) + e-mail menor (text-xs) no dropdown.
    expect($html)->toContain('Dona Demo')
        ->toContain('dona@demo.test')
        ->toContain('text-xs text-zinc-500');

    // Item 5: item de menu + modal + seletor de arquivo SEM SVG; gatilho do Cropper.
    expect($html)->toContain('Foto de perfil')
        ->toContain('data-modal="foto-perfil"')   // Flux nomeia o modal via data-modal
        ->toContain('accept="image/png,image/jpeg,image/webp"')
        ->toContain('cropperFoto(');
});

it('sem foto, o avatar do rodapé cai para as iniciais (DD)', function () {
    $dono = usuarioComPapel('Dono', ['name' => 'Dona Demo']);
    $html = $this->actingAs($dono, 'web')->get('/lojafoto/painel')->content();

    // Sem foto_perfil → o flux:avatar não recebe src de arquivo do tenant.
    expect($html)->not->toContain('/lojafoto/arquivo/aparencia/');
    expect($html)->toContain('DD'); // iniciais do nome
});

it('com foto, o avatar do rodapé usa a URL do arquivo do tenant', function () {
    $dono = usuarioComPapel('Dono', ['name' => 'Dona Demo', 'foto_perfil' => 'aparencia/minha.png']);
    $html = $this->actingAs($dono, 'web')->get('/lojafoto/painel')->content();

    expect($html)->toContain('/lojafoto/arquivo/aparencia/minha.png');
});

it('o menu segue respeitando os gates: Recepção não vê Financeiro; Dono vê (guard-rail)', function () {
    // Dono (todas as permissões) enxerga o item de Visão financeira.
    $dono = usuarioComPapel('Dono');
    $htmlDono = $this->actingAs($dono, 'web')->get('/lojafoto/painel')->content();
    expect($htmlDono)->toContain('Visão financeira');

    // Recepção (sem ver_financeiro) NÃO vê Financeiro/Comissões no menu — o ajuste do
    // menu (Início/dropdown/foto) não afrouxou os @can/@recurso.
    auth('web')->logout();
    $recepcao = usuarioComPapel('Recepção', ['email' => 'rec@loja.test']);
    $htmlRec = $this->actingAs($recepcao, 'web')->get('/lojafoto/painel')->content();
    expect($htmlRec)->not->toContain('Visão financeira')
        ->not->toContain('Comissões');
});
