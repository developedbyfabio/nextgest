<?php

declare(strict_types=1);

use App\Livewire\Painel\Aparencia\Editar;
use App\Support\Aparencia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Cobre o que o Fabio reportou como quebrado/"não funciona" na aba Aparência:
 * tipografia (fonte + tamanho), uploads (logo/cabeçalho/fundo) e a coerência com
 * o D36 (sem campos de cor de superfície nem de layout que não aplicam). Testes
 * de EFEITO (não só "o campo existe").
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojafonte');
    tenancy()->initialize($this->tenant);
});

it('oferece pelo menos 10 fontes e inclui as do padrão/templates', function () {
    expect(count(Aparencia::FONTES))->toBeGreaterThanOrEqual(10);

    // Todo valor de fonte usado em PADRAO e nos TEMPLATES precisa ser uma opção
    // válida (senão a tela rejeitaria o salvamento desse tenant).
    $usadas = collect(Aparencia::TEMPLATES)->pluck('fonte')
        ->push(Aparencia::PADRAO['fonte'])->unique();

    foreach ($usadas as $fonte) {
        expect(array_key_exists($fonte, Aparencia::FONTES))->toBeTrue("Fonte fora do catálogo: {$fonte}");
    }
});

it('TODA fonte do catálogo passa na validação, salva e aplica', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    foreach (array_keys(Aparencia::FONTES) as $fonte) {
        Livewire::test(Editar::class)
            ->set('fonte', $fonte)
            ->call('salvar')
            ->assertHasNoErrors();

        expect(Aparencia::doTenant()['fonte'])->toBe($fonte);
        expect(Aparencia::cssVarsAcento(Aparencia::doTenant()))->toContain("font-family: {$fonte}");
    }
});

it('TODO tamanho base oferecido passa na validação e salva', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    foreach (['14px', '15px', '16px', '17px', '18px'] as $tam) {
        Livewire::test(Editar::class)
            ->set('tamanho_base', $tam)
            ->call('salvar')
            ->assertHasNoErrors();

        expect(Aparencia::doTenant()['tamanho_base'])->toBe($tam);
    }
});

it('cada <option> de fonte oferece um value ACEITO pela validação (sem escape duplo)', function () {
    // Regressão direta do bug: o value renderizado pelo <option>, depois de
    // decodificado pelo navegador, tem de ser EXATAMENTE uma chave do catálogo —
    // senão o Rule::in rejeita o que a própria tela oferece ("teste verde × navegador").
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    $html = Livewire::test(Editar::class)->html();

    // Isola o <select> de fonte (entre o seu wire:model e o do tamanho base).
    $ini = strpos($html, 'wire:model.live="fonte"');
    $fim = strpos($html, 'wire:model.live="tamanho_base"');
    $bloco = substr($html, $ini, $fim - $ini);

    preg_match_all('/value="([^"]*)"/', $bloco, $m);
    $valores = collect($m[1])
        ->map(fn ($v) => html_entity_decode($v, ENT_QUOTES))
        ->unique()->values();

    expect($valores)->not->toBeEmpty();
    expect($valores->count())->toBe(count(Aparencia::FONTES)); // todas as fontes viram opção

    foreach ($valores as $v) {
        expect(array_key_exists($v, Aparencia::FONTES))->toBeTrue(
            "Option de fonte com value fora do catálogo (escape duplo?): {$v}"
        );
    }
});

it('salva a fonte e o tamanho, persiste e aplica no acento (body)', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    $poppins = "'Poppins', ui-sans-serif, sans-serif";

    Livewire::test(Editar::class)
        ->set('fonte', $poppins)
        ->set('tamanho_base', '18px')
        ->call('salvar')
        ->assertHasNoErrors();

    $a = Aparencia::doTenant();
    expect($a['fonte'])->toBe($poppins);
    expect($a['tamanho_base'])->toBe('18px');

    // cssVarsAcento (usado no style do <body> de portal/painel) aplica fonte+tamanho.
    $css = Aparencia::cssVarsAcento($a);
    expect($css)->toContain("font-family: {$poppins}");
    expect($css)->toContain('font-size: 18px');
});

it('rejeita fonte fora do catálogo', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Editar::class)
        ->set('fonte', 'Comic Sans MS')
        ->call('salvar')
        ->assertHasErrors('fonte');
});

it('emite o <link> da fonte Google escolhida e nada para fonte do sistema', function () {
    // Fonte Google → gera o link da família correspondente.
    Aparencia::salvar(['fonte' => "'Poppins', ui-sans-serif, sans-serif"]);
    $link = Aparencia::linkFonteGoogle();
    expect($link)->toContain('fonts.googleapis.com/css2')
        ->toContain('family=Poppins')
        ->toContain('rel="stylesheet"');

    // Fonte do sistema (padrão Instrument Sans) → sem link externo.
    Aparencia::salvar(['fonte' => 'ui-sans-serif, system-ui, sans-serif']);
    expect(Aparencia::linkFonteGoogle())->toBe('');
});

it('o link "todas as fontes" cobre cada família Google do catálogo', function () {
    $todas = Aparencia::linksFontesGoogle();

    foreach (Aparencia::FONTES as $meta) {
        if ($meta['google'] !== null) {
            $familia = explode(':', $meta['google'])[0]; // ex.: "Open+Sans"
            expect($todas)->toContain('family='.$familia);
        }
    }
});

it('faz upload de cabeçalho válido, grava no disco do tenant e passa a referenciar', function () {
    Storage::fake('public');
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Editar::class)
        ->set('headerUpload', UploadedFile::fake()->image('capa.jpg', 800, 300))
        ->call('salvar')
        ->assertHasNoErrors();

    $header = Aparencia::doTenant()['header_imagem'];
    expect($header)->toStartWith('aparencia/');
    Storage::disk('public')->assertExists($header);
    expect(Aparencia::urlArquivo($header))->toContain('/lojafonte/arquivo/aparencia/');
});

it('rejeita upload de tipo não permitido (ex.: PDF) com erro', function () {
    Storage::fake('public');
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Editar::class)
        ->set('fundoUpload', UploadedFile::fake()->create('arquivo.pdf', 100, 'application/pdf'))
        ->call('salvar')
        ->assertHasErrors('fundoUpload');
});

it('rejeita upload acima de 2 MB com erro', function () {
    Storage::fake('public');
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    // imagem "real" de ~3 MB (acima do limite de 2048 KB e do PHP padrão).
    Livewire::test(Editar::class)
        ->set('headerUpload', UploadedFile::fake()->create('grande.jpg', 3000, 'image/jpeg'))
        ->call('salvar')
        ->assertHasErrors('headerUpload');
});

it('não expõe campos de cor de superfície nem de layout (D36)', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    $html = Livewire::test(Editar::class)->html();

    // Removidos da tela (seguem claro/escuro): fundo/superfície/texto/texto-suave.
    expect($html)->not->toContain('wire:model.live="cor_fundo"')
        ->not->toContain('wire:model.live="cor_superficie"')
        ->not->toContain('wire:model.live="cor_texto"')
        ->not->toContain('wire:model.live="cor_texto_suave"');

    // Removidos os controles de layout sem efeito.
    expect($html)->not->toContain('wire:model.live="menu_posicao"')
        ->not->toContain('wire:model.live="icone_estilo"');

    // Mantém o que aplica de verdade.
    expect($html)->toContain('wire:model.live="cor_principal"')
        ->toContain('wire:model.live="fonte"');
});
