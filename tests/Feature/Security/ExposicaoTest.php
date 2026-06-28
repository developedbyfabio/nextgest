<?php

declare(strict_types=1);

use App\Livewire\Painel\Aparencia\Editar as AparenciaEditar;
use App\Livewire\Painel\Equipe\Index as EquipeIndex;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

/*
| T7 (XSS/upload) + T8 (segredos). Single-tenant; status/HTML conferidos crus.
*/

beforeEach(function () {
    $this->tenant = criarTenant('segexp');
    tenancy()->initialize($this->tenant);
});

it('[T7] XSS armazenado: nome com <script> é ESCAPADO ao renderizar a equipe', function () {
    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@x.test']), 'web');
    usuarioComPapel('Profissional', ['name' => '<script>alert(1)</script>', 'email' => 'xss@x.test', 'e_profissional' => true]);

    Livewire::test(EquipeIndex::class)
        ->assertDontSee('<script>alert(1)</script>', false) // cru não aparece (Blade escapa com {{ }})
        ->assertSee('&lt;script&gt;', false);               // aparece escapado
});

it('[T7] regras de upload da aparência rejeitam não-imagem e capam tamanho (anti-SVG/script)', function () {
    // Usa as REGRAS REAIS do componente (rules() protegido) — não a config, e roda um
    // Validator de verdade contra um .php. (set() de upload no Livewire::test não
    // exercita a validação fielmente — daí o Validator direto.)
    $comp = new AparenciaEditar;
    $regras = (fn () => $this->rules())->call($comp);

    $regraLogo = $regras['logoUpload'];

    // .php é rejeitado (image + mimes:png,jpg,jpeg,webp — sem SVG).
    $v = Validator::make(
        ['logoUpload' => UploadedFile::fake()->create('payload.php', 10)],
        ['logoUpload' => $regraLogo],
    );
    expect($v->fails())->toBeTrue();

    // O cap de 5 MB e a allowlist de mimes estão de fato nas regras aplicadas.
    expect($regraLogo)->toContain('image')
        ->and($regraLogo)->toContain('mimes:png,jpg,jpeg,webp')
        ->and($regraLogo)->toContain('max:5120');
});

// [T8] O segredo cifrado do WhatsApp agora é o `instancia_token` (Evolution), coberto em
// tests/Feature/WhatsApp/WhatsAppFatia1Test.php (cifrado no banco + $hidden, sem vazar).
// O antigo editor Cloud da Meta foi aposentado na Fatia 2 (D76).
