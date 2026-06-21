<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Painel\Equipe\Horarios;
use App\Models\Configuracao;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Aparencia;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Onboarding guiado de um estabelecimento (wizard, guard admin). Substitui o
 * modal "nome + slug": cria o tenant de ponta a ponta — identidade, Dono,
 * horário de funcionamento e aparência (com prévia ao vivo) — e só provisiona
 * tudo na confirmação final.
 *
 * Reaproveita Aparencia::TEMPLATES (presets) e o componente de prévia
 * x-ng.previa-portal (Etapa 2). O segmento sugere o template de partida.
 *
 * Onde ficam segmento/descrição:
 * - segmento: metadado central do negócio (consultável no /admin sem entrar no
 *   tenant) → coluna JSON `data` do tenant central (via VirtualColumn do stancl).
 * - descrição: conteúdo exibido no portal do cliente → `configuracoes` do tenant
 *   (mesmo lugar de `aparencia`; leitura local pelo portal).
 */
#[Layout('components.layouts.admin')]
#[Title('Novo estabelecimento')]
class OnboardingEstabelecimento extends Component
{
    use WithFileUploads;

    public const TOTAL_ETAPAS = 5;

    public int $etapa = 1;

    /** Segmentos do negócio (rotulados). A chave é persistida. */
    public const SEGMENTOS = [
        'barbearia' => 'Barbearia',
        'salao_feminino' => 'Salão feminino',
        'salao_masculino' => 'Salão masculino',
        'estetica' => 'Estética',
        'outro' => 'Outro',
    ];

    /** Template de aparência sugerido por segmento (chave de Aparencia::TEMPLATES). */
    public const SUGESTAO_TEMPLATE = [
        'barbearia' => 'barbearia',
        'salao_feminino' => 'salao_feminino',
        'salao_masculino' => 'salao_masculino',
        'estetica' => 'premium',
        'outro' => 'neutro',
    ];

    // Etapa 1 — identidade.
    public string $nome = '';

    public string $descricao = '';

    public string $segmento = '';

    public string $slug = '';

    public bool $slugManual = false;

    // Etapa 2 — responsável (Dono).
    public string $donoNome = '';

    public string $donoEmail = '';

    public string $donoSenha = '';

    // Etapa 3 — horário de funcionamento. Lista ordenada (seg..dom).
    /** @var array<int, array{dia:int, rotulo:string, aberto:bool, inicio:string, fim:string}> */
    public array $funcionamento = [];

    // Etapa 4 — aparência.
    public string $template = '';

    public string $cor_principal = '';

    public string $cor_secundaria = '';

    public string $fonte = '';

    public string $tamanho_base = '';

    public $logoUpload = null;

    public function mount(): void
    {
        abort_unless(auth('admin')->check(), 403);

        $this->preencherAparencia(Aparencia::PADRAO);
        $this->funcionamento = $this->funcionamentoPadrao();
    }

    /** Seg–sáb 09:00; sáb fecha 13:00, demais 18:00; domingo fechado. */
    protected function funcionamentoPadrao(): array
    {
        $padrao = [];

        foreach (Horarios::DIAS as $dia => $rotulo) {
            $padrao[] = [
                'dia' => $dia,
                'rotulo' => $rotulo,
                'aberto' => $dia !== 0,
                'inicio' => '09:00',
                'fim' => $dia === 6 ? '13:00' : '18:00',
            ];
        }

        return $padrao;
    }

    /** @param array<string,mixed> $a */
    protected function preencherAparencia(array $a): void
    {
        $a = array_merge(Aparencia::PADRAO, $a);

        $this->cor_principal = $a['cor_principal'];
        $this->cor_secundaria = $a['cor_secundaria'];
        $this->fonte = $a['fonte'];
        $this->tamanho_base = $a['tamanho_base'];
    }

    // Slug sugerido a partir do nome, até o operador editá-lo manualmente.
    public function updatedNome(string $value): void
    {
        if (! $this->slugManual) {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugManual = true;
    }

    // Ao escolher o segmento, sugere o template (sem travar a escolha do operador).
    public function updatedSegmento(string $value): void
    {
        $this->aplicarTemplate(self::SUGESTAO_TEMPLATE[$value] ?? 'neutro');
    }

    public function aplicarTemplate(string $chave): void
    {
        $t = Aparencia::template($chave);

        if ($t === null) {
            return;
        }

        $this->template = $chave;
        $this->preencherAparencia($t);
    }

    public function proximo(): void
    {
        if ($this->etapa === 3) {
            if (! $this->validarFuncionamento()) {
                return;
            }
        } else {
            $this->validate($this->regrasEtapa($this->etapa), $this->mensagens(), $this->atributos());
        }

        if ($this->etapa < self::TOTAL_ETAPAS) {
            $this->etapa++;
        }
    }

    public function voltar(): void
    {
        if ($this->etapa > 1) {
            $this->etapa--;
        }
    }

    /** Navegação pelo stepper: só permite voltar a etapas já preenchidas. */
    public function irPara(int $etapa): void
    {
        if ($etapa >= 1 && $etapa < $this->etapa) {
            $this->etapa = $etapa;
        }
    }

    protected function regrasEtapa(int $etapa): array
    {
        $hex = ['required', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'];

        return match ($etapa) {
            1 => [
                'nome' => ['required', 'string', 'max:255'],
                'descricao' => ['nullable', 'string', 'max:1000'],
                'segmento' => ['required', Rule::in(array_keys(self::SEGMENTOS))],
                'slug' => [
                    'required', 'string', 'max:255',
                    'regex:/^[a-z0-9][a-z0-9-]*$/',
                    Rule::notIn(config('nextgest.reserved_slugs', [])),
                    Rule::unique('tenants', 'id'),
                    Rule::unique('tenants', 'slug'),
                ],
            ],
            2 => [
                'donoNome' => ['required', 'string', 'max:255'],
                'donoEmail' => ['required', 'string', 'email', 'max:255'],
                'donoSenha' => ['required', 'string', 'min:8'],
            ],
            4 => [
                'cor_principal' => $hex,
                'cor_secundaria' => $hex,
                'fonte' => ['required', 'string', Rule::in(array_keys(Aparencia::FONTES))],
                'tamanho_base' => ['required', 'string', 'regex:/^\d{2}px$/'],
                'logoUpload' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            ],
            default => [],
        };
    }

    protected function mensagens(): array
    {
        return [
            'slug.regex' => 'O slug deve ter apenas letras minúsculas, números e hífen, começando por letra ou número.',
            'slug.not_in' => 'Este slug é reservado e não pode ser usado.',
            'slug.unique' => 'Já existe um estabelecimento com este slug.',
        ];
    }

    protected function atributos(): array
    {
        return [
            'donoNome' => 'nome',
            'donoEmail' => 'e-mail',
            'donoSenha' => 'senha',
        ];
    }

    /** Valida o horário: ao menos um dia aberto e fim após início nos abertos. */
    protected function validarFuncionamento(): bool
    {
        $this->resetErrorBag();

        $algumAberto = false;

        foreach ($this->funcionamento as $i => $f) {
            if (! ($f['aberto'] ?? false)) {
                continue;
            }

            $algumAberto = true;

            $inicioOk = (bool) preg_match('/^\d{2}:\d{2}$/', (string) $f['inicio']);
            $fimOk = (bool) preg_match('/^\d{2}:\d{2}$/', (string) $f['fim']);

            if (! $inicioOk || ! $fimOk || $f['inicio'] >= $f['fim']) {
                $this->addError("funcionamento.$i.fim", 'O fim deve ser após o início.');
            }
        }

        if (! $algumAberto) {
            $this->addError('funcionamento', 'Defina ao menos um dia de funcionamento.');
        }

        return $this->getErrorBag()->isEmpty();
    }

    /** @return array<string,string> */
    protected function aparenciaForm(): array
    {
        return [
            'cor_principal' => $this->cor_principal,
            'cor_secundaria' => $this->cor_secundaria,
            'fonte' => $this->fonte,
            'tamanho_base' => $this->tamanho_base,
        ];
    }

    /** Aparência para a prévia ao vivo (com a logo temporária, se houver). */
    public function aparenciaParaPrevia(): array
    {
        $logoPreviewavel = $this->logoUpload
            && method_exists($this->logoUpload, 'isPreviewable')
            && $this->logoUpload->isPreviewable();

        return array_merge(Aparencia::PADRAO, $this->aparenciaForm(), [
            'logo_url' => $logoPreviewavel ? $this->logoUpload->temporaryUrl() : null,
        ]);
    }

    /** Horário pronto para persistir (sem o rótulo). */
    protected function funcionamentoParaSalvar(): array
    {
        return collect($this->funcionamento)
            ->map(fn ($f) => [
                'dia' => (int) $f['dia'],
                'aberto' => (bool) $f['aberto'],
                'inicio' => $f['inicio'],
                'fim' => $f['fim'],
            ])
            ->all();
    }

    public function confirmar()
    {
        abort_unless(auth('admin')->check(), 403);

        // Revalida tudo (defesa: o cliente pode ter pulado etapas).
        $this->validate(
            array_merge($this->regrasEtapa(1), $this->regrasEtapa(2), $this->regrasEtapa(4)),
            $this->mensagens(),
            $this->atributos(),
        );

        if (! $this->validarFuncionamento()) {
            $this->etapa = 3;

            return null;
        }

        // 1) Cria o tenant — dispara CreateDatabase + Migrate + Seed (síncrono).
        //    segmento vai para a coluna JSON `data` (não é coluna customizada).
        $tenant = Tenant::create([
            'id' => $this->slug,
            'nome' => $this->nome,
            'slug' => $this->slug,
            'ativo' => true,
            'segmento' => $this->segmento,
        ]);

        // 2) No contexto do tenant: Dono + aparência (+ logo) + descrição + horário.
        $tenant->run(function () {
            if (! User::where('email', $this->donoEmail)->exists()) {
                User::create([
                    'name' => $this->donoNome,
                    'email' => $this->donoEmail,
                    'password' => $this->donoSenha, // cast 'hashed'
                    'deve_trocar_senha' => true,    // troca obrigatória no 1º login
                    'e_profissional' => false,
                    'ativo' => true,
                ])->assignRole('Dono');
            }

            $logo = $this->logoUpload ? $this->logoUpload->store('aparencia', 'public') : null;

            Aparencia::salvar(array_merge($this->aparenciaForm(), ['logo' => $logo]));

            Configuracao::updateOrCreate(['chave' => 'descricao'], ['valor' => $this->descricao]);
            Configuracao::updateOrCreate(
                ['chave' => 'horario_funcionamento'],
                ['valor' => json_encode($this->funcionamentoParaSalvar())],
            );
        });

        session()->flash('onboarding_sucesso', "Estabelecimento \"{$this->nome}\" criado com sucesso.");

        return redirect()->route('admin.tenant.detalhe', ['tenantId' => $this->slug]);
    }

    public function render(): View
    {
        return view('livewire.admin.onboarding-estabelecimento', [
            'totalEtapas' => self::TOTAL_ETAPAS,
            'segmentos' => self::SEGMENTOS,
            'templates' => Aparencia::TEMPLATES,
            'templateSugerido' => self::SUGESTAO_TEMPLATE[$this->segmento] ?? null,
            'fontes' => Aparencia::FONTES,
            'aparencia' => $this->aparenciaParaPrevia(),
        ]);
    }
}
