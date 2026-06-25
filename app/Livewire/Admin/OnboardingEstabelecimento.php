<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Painel\Equipe\Horarios;
use App\Models\Configuracao;
use App\Models\Estabelecimento;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\CelularBr;
use App\Rules\Cnpj;
use App\Rules\Cpf;
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

    public const TOTAL_ETAPAS = 7;

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

    // Etapa 2 — responsável (Dono). O LOGIN (nome/email/senha) vai para o tenant; o
    // contato completo do dono (sobrenome/celular/CPF) é fonte de verdade no central (D56).
    public string $donoNome = '';

    public string $donoSobrenome = '';

    public string $donoEmail = '';

    public string $donoCelular = '';

    public string $donoCpf = '';

    public string $donoSenha = '';

    // Etapa 3 — estabelecimento (cadastro central; D56). Endereço/documento/faturamento
    // são opcionais aqui (completáveis depois na tela "Dados"); nome fantasia é exigido.
    public string $nomeFantasia = '';

    public string $cep = '';

    public string $logradouro = '';

    public string $numero = '';

    public string $complemento = '';

    public string $bairro = '';

    public string $cidade = '';

    public string $uf = '';

    public ?string $faturamentoMensal = null;

    public string $documentoTipo = '';

    public string $documento = '';

    // Etapa 4 — horário de funcionamento. Lista ordenada (seg..dom).
    /** @var array<int, array{dia:int, rotulo:string, aberto:bool, inicio:string, fim:string}> */
    public array $funcionamento = [];

    // Etapa 5 — aparência.
    public string $template = '';

    public string $cor_principal = '';

    public string $cor_secundaria = '';

    public string $fonte = '';

    public string $tamanho_base = '';

    public $logoUpload = null;

    public $headerUpload = null;

    public $fundoUpload = null;

    // Etapa 6 — plano (slug do catálogo config/planos.php). Sem default: escolha obrigatória.
    public string $plano = '';

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
        $this->normalizarOpcionais();

        if ($this->etapa === 4) {
            // Funcionamento (validação própria — não é regra de form).
            if (! $this->validarFuncionamento()) {
                return;
            }
        } else {
            $this->validate($this->regrasEtapa($this->etapa), $this->mensagens(), $this->atributos());
        }

        if ($this->etapa < self::TOTAL_ETAPAS) {
            $this->etapa++;
        }

        // Ao entrar na etapa Estabelecimento, pré-preenche o nome fantasia com o nome
        // do negócio (Identidade), se ainda estiver vazio — o operador pode ajustar.
        if ($this->etapa === 3 && $this->nomeFantasia === '') {
            $this->nomeFantasia = $this->nome;
        }
    }

    /** Campo de faturamento vazio ('') vira null (evita falhar a regra `numeric`). */
    protected function normalizarOpcionais(): void
    {
        if ($this->faturamentoMensal === '') {
            $this->faturamentoMensal = null;
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
                'donoSobrenome' => ['required', 'string', 'max:255'],
                'donoEmail' => ['required', 'string', 'email', 'max:255'],
                'donoCelular' => ['required', 'string', new CelularBr],
                'donoCpf' => ['required', 'string', new Cpf],
                'donoSenha' => ['required', 'string', 'min:8'],
            ],
            3 => [
                // Estabelecimento (central). Nome fantasia exigido; endereço/documento/
                // faturamento opcionais (completáveis depois na tela "Dados").
                'nomeFantasia' => ['required', 'string', 'max:255'],
                'cep' => ['nullable', 'string', 'max:9'],
                'logradouro' => ['nullable', 'string', 'max:255'],
                'numero' => ['nullable', 'string', 'max:20'],
                'complemento' => ['nullable', 'string', 'max:255'],
                'bairro' => ['nullable', 'string', 'max:255'],
                'cidade' => ['nullable', 'string', 'max:255'],
                'uf' => ['nullable', 'string', 'max:2'],
                'faturamentoMensal' => ['nullable', 'numeric', 'min:0'],
                'documentoTipo' => ['nullable', Rule::in(['cpf', 'cnpj']), 'required_with:documento'],
                'documento' => $this->regraDocumento(),
            ],
            5 => [
                'cor_principal' => $hex,
                'cor_secundaria' => $hex,
                'fonte' => ['required', 'string', Rule::in(array_keys(Aparencia::FONTES))],
                'tamanho_base' => ['required', 'string', 'regex:/^\d{2}px$/'],
                'logoUpload' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
                'headerUpload' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
                'fundoUpload' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            ],
            6 => [
                // Plano obrigatório, só chaves do catálogo (config/planos.php).
                'plano' => ['required', 'string', Rule::in(array_keys(config('planos', [])))],
            ],
            default => [],
        };
    }

    /** Regra do documento conforme o tipo escolhido (validado só se preenchido). */
    protected function regraDocumento(): array
    {
        return match ($this->documentoTipo) {
            'cpf' => ['nullable', 'string', new Cpf],
            'cnpj' => ['nullable', 'string', new Cnpj],
            default => ['nullable', 'string'],
        };
    }

    protected function mensagens(): array
    {
        return [
            'slug.regex' => 'O slug deve ter apenas letras minúsculas, números e hífen, começando por letra ou número.',
            'slug.not_in' => 'Este slug é reservado e não pode ser usado.',
            'slug.unique' => 'Já existe um estabelecimento com este slug.',
            'plano.required' => 'Selecione um plano.',
            'plano.in' => 'Selecione um plano.',
            'documentoTipo.required_with' => 'Escolha o tipo (CPF ou CNPJ) do documento informado.',
        ];
    }

    protected function atributos(): array
    {
        return [
            'donoNome' => 'nome',
            'donoSobrenome' => 'sobrenome',
            'donoEmail' => 'e-mail',
            'donoCelular' => 'celular',
            'donoCpf' => 'CPF',
            'donoSenha' => 'senha',
            'nomeFantasia' => 'nome fantasia',
            'faturamentoMensal' => 'faturamento mensal',
            'documento' => 'documento',
            'documentoTipo' => 'tipo de documento',
            'uf' => 'UF',
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

    /** Aparência para a prévia ao vivo (com as imagens temporárias, se houver). */
    public function aparenciaParaPrevia(): array
    {
        return array_merge(Aparencia::PADRAO, $this->aparenciaForm(), [
            'logo_url' => $this->urlPrevia($this->logoUpload),
            'header_url' => $this->urlPrevia($this->headerUpload),
            'fundo_url' => $this->urlPrevia($this->fundoUpload),
        ]);
    }

    /** URL temporária de um upload, só se for imagem previewável (senão null). */
    private function urlPrevia($upload): ?string
    {
        return $upload && method_exists($upload, 'isPreviewable') && $upload->isPreviewable()
            ? $upload->temporaryUrl()
            : null;
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

        $this->normalizarOpcionais();

        // Revalida tudo (defesa: o cliente pode ter pulado etapas) — identidade,
        // responsável, estabelecimento, aparência e plano. Funcionamento é à parte.
        $this->validate(
            array_merge(
                $this->regrasEtapa(1),
                $this->regrasEtapa(2),
                $this->regrasEtapa(3),
                $this->regrasEtapa(5),
                $this->regrasEtapa(6),
            ),
            $this->mensagens(),
            $this->atributos(),
        );

        if (! $this->validarFuncionamento()) {
            $this->etapa = 4;

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

        // 1b) Aplica o plano escolhido: seta `plano` + os `recursos` do catálogo (D55).
        //     Só atributos virtuais → preserva o `segmento` recém-gravado (regra de ouro).
        $tenant->aplicarPlano($this->plano);

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
            $header = $this->headerUpload ? $this->headerUpload->store('aparencia', 'public') : null;
            $fundo = $this->fundoUpload ? $this->fundoUpload->store('aparencia', 'public') : null;

            Aparencia::salvar(array_merge($this->aparenciaForm(), [
                'logo' => $logo,
                'header_imagem' => $header,
                'fundo_imagem' => $fundo,
            ]));

            Configuracao::updateOrCreate(['chave' => 'descricao'], ['valor' => $this->descricao]);
            Configuracao::updateOrCreate(
                ['chave' => 'horario_funcionamento'],
                ['valor' => json_encode($this->funcionamentoParaSalvar())],
            );
        });

        // 3) Cadastro CENTRAL (1:1) — fonte de verdade do admin/cobrança (D56).
        //    Documentos/celular/CPF/CEP gravados normalizados (só dígitos).
        Estabelecimento::create([
            'tenant_id' => $tenant->getKey(),
            'nome_fantasia' => $this->nomeFantasia !== '' ? $this->nomeFantasia : $this->nome,
            'cep' => Estabelecimento::soDigitos($this->cep),
            'logradouro' => $this->logradouro ?: null,
            'numero' => $this->numero ?: null,
            'complemento' => $this->complemento ?: null,
            'bairro' => $this->bairro ?: null,
            'cidade' => $this->cidade ?: null,
            'uf' => $this->uf !== '' ? mb_strtoupper($this->uf) : null,
            'faturamento_mensal' => $this->faturamentoMensal !== null && $this->faturamentoMensal !== '' ? $this->faturamentoMensal : null,
            'documento_tipo' => $this->documento !== '' ? ($this->documentoTipo ?: null) : null,
            'documento' => $this->documento !== '' ? Estabelecimento::soDigitos($this->documento) : null,
            'dono_nome' => $this->donoNome,
            'dono_sobrenome' => $this->donoSobrenome,
            'dono_email' => $this->donoEmail,
            'dono_celular' => Estabelecimento::soDigitos($this->donoCelular),
            'dono_cpf' => Estabelecimento::soDigitos($this->donoCpf),
        ]);

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
            'planos' => config('planos', []),
        ]);
    }
}
