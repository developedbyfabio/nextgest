<div class="flex flex-col gap-6">
    <x-ng.page-header title="Novo estabelecimento" subtitle="Onboarding guiado">
        <x-slot:actions>
            <flux:button :href="route('admin.tenants')" variant="ghost" icon="arrow-left" wire:navigate>Cancelar</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    {{-- Stepper --}}
    @php($passos = [1 => 'Identidade', 2 => 'Responsável', 3 => 'Estabelecimento', 4 => 'Funcionamento', 5 => 'Aparência', 6 => 'Plano', 7 => 'Revisão'])
    <div class="flex flex-wrap items-center gap-x-2 gap-y-3">
        @foreach ($passos as $num => $rotulo)
            <button type="button" wire:click="irPara({{ $num }})" @disabled($num >= $etapa)
                class="flex items-center gap-2 rounded-full px-3 py-1.5 text-sm transition
                    @if ($num === $etapa) bg-[var(--color-accent)] text-white
                    @elseif ($num < $etapa) text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700
                    @else text-zinc-400 dark:text-zinc-600 @endif">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-semibold
                    @if ($num === $etapa) border-white/40
                    @elseif ($num < $etapa) border-green-500 bg-green-500 text-white
                    @else border-zinc-300 dark:border-zinc-600 @endif">
                    @if ($num < $etapa) ✓ @else {{ $num }} @endif
                </span>
                <span class="max-sm:hidden">{{ $rotulo }}</span>
            </button>
            @if (! $loop->last)
                <flux:icon name="chevron-right" class="size-4 text-zinc-300 dark:text-zinc-600 max-sm:hidden" />
            @endif
        @endforeach
    </div>

    <flux:separator />

    {{-- ETAPA 1 — Identidade --}}
    @if ($etapa === 1)
        <div class="flex max-w-xl flex-col gap-4">
            <flux:heading size="lg">Identidade do negócio</flux:heading>
            <flux:input wire:model.live.debounce.400ms="nome" label="Nome" placeholder="Ex.: Barbearia do Jorge" required />
            <flux:input wire:model.live="slug" label="Slug (URL)" placeholder="ex.: barbeariadojorge" required>
                <x-slot:description>Acesso em /{slug}. Apenas minúsculas, números e hífen. Sugerido pelo nome, editável.</x-slot:description>
            </flux:input>
            <flux:select wire:model.live="segmento" label="Segmento" placeholder="Selecione…" required>
                @foreach ($segmentos as $valor => $rotulo)
                    <flux:select.option value="{{ $valor }}">{{ $rotulo }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="descricao" label="Descrição" rows="3"
                placeholder="Aparece no portal do cliente. Ex.: cortes clássicos e barba, atendimento sob agendamento." />
        </div>
    @endif

    {{-- ETAPA 2 — Responsável (Dono) --}}
    @if ($etapa === 2)
        <div class="flex max-w-xl flex-col gap-4">
            <flux:heading size="lg">Responsável (Dono)</flux:heading>
            <flux:text class="text-sm text-zinc-500">Cria o usuário com papel Dono (login no painel) e guarda o contato dele no cadastro central.</flux:text>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="donoNome" label="Nome" required />
                <flux:input wire:model="donoSobrenome" label="Sobrenome" required />
            </div>
            <flux:input wire:model="donoEmail" type="email" label="E-mail (login)" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="donoCelular" label="Celular" mask="(99) 99999-9999" placeholder="(41) 99999-9999" required />
                <flux:input wire:model="donoCpf" label="CPF" mask="999.999.999-99" placeholder="000.000.000-00" required />
            </div>
            <flux:input wire:model="donoSenha" type="password" label="Senha inicial" viewable required>
                <x-slot:description>Mínimo de 8 caracteres. O Dono poderá trocar depois.</x-slot:description>
            </flux:input>
        </div>
    @endif

    {{-- ETAPA 3 — Estabelecimento (cadastro central; D56) --}}
    @if ($etapa === 3)
        <div class="flex max-w-2xl flex-col gap-4">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg">Estabelecimento</flux:heading>
                <flux:text class="text-sm text-zinc-500">Dados cadastrais (admin/cobrança). Só o nome fantasia é obrigatório; o resto pode ser completado depois.</flux:text>
            </div>

            <flux:input wire:model="nomeFantasia" label="Nome fantasia" required />

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="cep" label="CEP" mask="99999-999" placeholder="00000-000" />
                <div class="sm:col-span-2">
                    <flux:input wire:model="logradouro" label="Logradouro" placeholder="Rua / Av." />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="numero" label="Número" />
                <flux:input wire:model="complemento" label="Complemento" />
                <flux:input wire:model="bairro" label="Bairro" />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <flux:input wire:model="cidade" label="Cidade" />
                </div>
                <flux:input wire:model="uf" label="UF" maxlength="2" placeholder="PR" />
            </div>

            <flux:separator text="Opcional" />

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="faturamentoMensal" type="number" step="0.01" min="0" label="Faturamento mensal (R$)" placeholder="0,00" />
                <flux:select wire:model.live="documentoTipo" label="Documento">
                    <flux:select.option value="">— nenhum —</flux:select.option>
                    <flux:select.option value="cpf">CPF</flux:select.option>
                    <flux:select.option value="cnpj">CNPJ</flux:select.option>
                </flux:select>
                <flux:input wire:model="documento" label="Número do documento"
                    :mask="$documentoTipo === 'cnpj' ? '99.999.999/9999-99' : ($documentoTipo === 'cpf' ? '999.999.999-99' : null)"
                    :disabled="$documentoTipo === ''" placeholder="{{ $documentoTipo === '' ? 'Escolha o tipo' : '' }}" />
            </div>
        </div>
    @endif

    {{-- ETAPA 4 — Horário de funcionamento --}}
    @if ($etapa === 4)
        <div class="flex max-w-xl flex-col gap-4">
            <flux:heading size="lg">Horário de funcionamento</flux:heading>
            <flux:text class="text-sm text-zinc-500">Faixas padrão do estabelecimento. Servem de base ao cadastrar profissionais e à exibição no portal.</flux:text>

            @error('funcionamento')
                <flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>
            @enderror

            {{-- Editor compartilhado (mesmo da tela de Funcionamento no painel). --}}
            <x-funcionamento-editor :funcionamento="$funcionamento" />
        </div>
    @endif

    {{-- ETAPA 5 — Aparência (com prévia ao vivo) --}}
    @if ($etapa === 5)
        <div class="grid gap-8 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">Aparência</flux:heading>
                    @if ($templateSugerido)
                        <flux:text class="text-sm text-zinc-500">Sugerimos o template <strong>{{ $templates[$templateSugerido]['rotulo'] }}</strong> para este segmento. Ajuste à vontade.</flux:text>
                    @endif
                </div>

                {{-- Templates --}}
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($templates as $chave => $t)
                        <button type="button" wire:click="aplicarTemplate('{{ $chave }}')"
                            @class([
                                'ng-card-interactive flex flex-col gap-2 p-3 text-left',
                                'ring-2 ring-[var(--color-accent)]' => $template === $chave,
                            ])>
                            <div class="flex gap-1">
                                <span class="size-5 rounded-full border border-black/10" style="background-color: {{ $t['cor_principal'] }}"></span>
                                <span class="size-5 rounded-full border border-black/10" style="background-color: {{ $t['cor_secundaria'] }}"></span>
                            </div>
                            <div>
                                <div class="text-sm font-medium">{{ $t['rotulo'] }}</div>
                                <div class="text-xs text-zinc-500">{{ $t['descricao'] }}</div>
                            </div>
                        </button>
                    @endforeach
                </div>

                {{-- Carrega as fontes do catálogo p/ a prévia ao vivo refletir a seleção. --}}
                {!! \App\Support\Aparencia::linksFontesGoogle() !!}

                {{-- Cores da marca (acento). Superfícies seguem claro/escuro (D36). --}}
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <flux:input type="color" wire:model.live="cor_principal" label="Principal (acento)" />
                    <flux:input type="color" wire:model.live="cor_secundaria" label="Secundária (realces)" />
                </div>

                {{-- Tipografia --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="fonte" label="Fonte">
                        {{-- :value/:style (bound) p/ o Flux escapar uma vez (evita escape
                             duplo do value, que fazia o Rule::in rejeitar a fonte). --}}
                        @foreach ($fontes as $valor => $meta)
                            <flux:select.option :value="$valor" :style="'font-family: ' . $valor">{{ $meta['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="tamanho_base" label="Tamanho base">
                        @foreach (['14px', '15px', '16px', '17px', '18px'] as $tam)
                            <flux:select.option value="{{ $tam }}">{{ $tam }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                {{-- Imagens (logo + cabeçalho + fundo) — mesmo tratamento da aba de Aparência. --}}
                <flux:text class="text-xs text-zinc-500">PNG, JPG ou WebP, até 5 MB cada (opcionais).</flux:text>
                @foreach ([
                    ['upload' => 'logoUpload', 'preview' => $logoUpload, 'rotulo' => 'Logo'],
                    ['upload' => 'headerUpload', 'preview' => $headerUpload, 'rotulo' => 'Imagem de cabeçalho'],
                    ['upload' => 'fundoUpload', 'preview' => $fundoUpload, 'rotulo' => 'Imagem de fundo'],
                ] as $img)
                    <div class="flex items-center gap-4">
                        <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                            @if ($img['preview'] && method_exists($img['preview'], 'isPreviewable') && $img['preview']->isPreviewable())
                                <img src="{{ $img['preview']->temporaryUrl() }}" alt="{{ $img['rotulo'] }}" class="size-full object-contain" />
                            @else
                                <flux:icon name="photo" class="size-6 text-zinc-400" />
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col gap-1">
                            <flux:label>{{ $img['rotulo'] }} (opcional)</flux:label>
                            <input type="file" wire:model="{{ $img['upload'] }}" accept="image/png,image/jpeg,image/webp"
                                class="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:file:bg-zinc-700 dark:hover:file:bg-zinc-600" />
                            <div wire:loading wire:target="{{ $img['upload'] }}" class="text-xs text-zinc-500">Enviando…</div>
                            <flux:error name="{{ $img['upload'] }}" />
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Prévia ao vivo (reuso da Etapa 2) --}}
            <div class="lg:col-span-1">
                <div class="sticky top-6 flex flex-col gap-3">
                    <flux:heading size="sm">Prévia do portal</flux:heading>
                    <x-ng.previa-portal :aparencia="$aparencia" :nome="$nome ?: 'Seu estabelecimento'" />
                    <flux:text class="text-center text-xs text-zinc-500">Atualiza enquanto você edita.</flux:text>
                </div>
            </div>
        </div>
    @endif

    {{-- ETAPA 6 — Plano (define os recursos liberados; D55) --}}
    @if ($etapa === 6)
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg">Plano</flux:heading>
                <flux:text class="text-sm text-zinc-500">Define os módulos liberados para o estabelecimento. Pode ser trocado depois, no detalhe.</flux:text>
            </div>

            @error('plano')
                <flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>
            @enderror

            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ($planos as $chave => $p)
                    <button type="button" wire:click="$set('plano', '{{ $chave }}')"
                        @class([
                            'ng-card-interactive flex flex-col gap-3 p-4 text-left',
                            'ring-2 ring-[var(--color-accent)]' => $plano === $chave,
                        ])>
                        <div class="flex items-center justify-between gap-2">
                            <flux:heading size="sm">{{ $p['nome'] }}</flux:heading>
                            @if ($plano === $chave)
                                <flux:icon name="check-circle" class="size-5 shrink-0 text-[var(--color-accent)]" />
                            @endif
                        </div>
                        <div class="text-2xl font-bold tracking-tight">
                            R$ {{ number_format($p['preco_mes'], 2, ',', '.') }}<span class="text-sm font-normal text-zinc-500">/mês</span>
                        </div>
                        <flux:separator />
                        <ul class="flex flex-col gap-1.5 text-sm">
                            @forelse ($p['recursos'] as $slug)
                                <li class="flex items-center gap-2">
                                    <flux:icon name="check" class="size-4 shrink-0 text-green-600" />
                                    {{ \App\Enums\Recurso::from($slug)->rotulo() }}
                                </li>
                            @empty
                                <li class="text-zinc-500">Recursos essenciais (sem módulos extras)</li>
                            @endforelse
                        </ul>
                    </button>
                @endforeach
            </div>
            <flux:text class="text-xs text-zinc-500">Preço de referência interna do admin. Trocar o plano depois redefine os recursos para o padrão do plano.</flux:text>
        </div>
    @endif

    {{-- ETAPA 7 — Revisão e confirmação --}}
    @if ($etapa === 7)
        <div class="grid gap-8 lg:grid-cols-3">
            <div class="flex flex-col gap-5 lg:col-span-2">
                <flux:heading size="lg">Revisão</flux:heading>

                <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">Negócio</flux:heading>
                    <flux:text><strong>{{ $nome }}</strong> · <span class="font-mono text-sm">/{{ $slug }}</span></flux:text>
                    <flux:text class="text-sm text-zinc-500">Segmento: {{ $segmentos[$segmento] ?? '—' }}</flux:text>
                    @if ($descricao)
                        <flux:text class="text-sm text-zinc-500">{{ $descricao }}</flux:text>
                    @endif
                </div>

                {{-- Plano escolhido + recursos inclusos. --}}
                @php($planoSel = $planos[$plano] ?? null)
                <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">Plano</flux:heading>
                    @if ($planoSel)
                        <flux:text><strong>{{ $planoSel['nome'] }}</strong> · R$ {{ number_format($planoSel['preco_mes'], 2, ',', '.') }}/mês</flux:text>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            @forelse ($planoSel['recursos'] as $slug)
                                <flux:badge color="green" size="sm">{{ \App\Enums\Recurso::from($slug)->rotulo() }}</flux:badge>
                            @empty
                                <flux:badge color="zinc" size="sm">Sem módulos extras</flux:badge>
                            @endforelse
                        </div>
                    @else
                        <flux:text class="text-sm text-zinc-400">Nenhum plano selecionado.</flux:text>
                    @endif
                </div>

                <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">Responsável</flux:heading>
                    <flux:text>{{ trim($donoNome.' '.$donoSobrenome) }}</flux:text>
                    <flux:text class="text-sm text-zinc-500">{{ $donoEmail }}</flux:text>
                    <flux:text class="text-sm text-zinc-500">{{ $donoCelular }} · CPF {{ $donoCpf }}</flux:text>
                </div>

                {{-- Estabelecimento (cadastro central). --}}
                <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">Estabelecimento</flux:heading>
                    <flux:text>{{ $nomeFantasia ?: $nome }}</flux:text>
                    @php($linhaEndereco = trim(collect([$logradouro, $numero, $bairro, $cidade, $uf])->filter()->implode(', ')))
                    @if ($linhaEndereco !== '')
                        <flux:text class="text-sm text-zinc-500">{{ $linhaEndereco }}{{ $cep ? ' · CEP '.$cep : '' }}</flux:text>
                    @endif
                    @if ($faturamentoMensal)
                        <flux:text class="text-sm text-zinc-500">Faturamento: R$ {{ number_format((float) $faturamentoMensal, 2, ',', '.') }}/mês</flux:text>
                    @endif
                    @if ($documento !== '')
                        <flux:text class="text-sm text-zinc-500">{{ strtoupper($documentoTipo) }}: {{ $documento }}</flux:text>
                    @endif
                </div>

                <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">Funcionamento</flux:heading>
                    @foreach ($funcionamento as $f)
                        <flux:text class="text-sm">
                            <span class="inline-block w-20">{{ $f['rotulo'] }}</span>
                            @if ($f['aberto'])
                                <span class="text-zinc-600 dark:text-zinc-300">{{ $f['inicio'] }} – {{ $f['fim'] }}</span>
                            @else
                                <span class="text-zinc-400">Fechado</span>
                            @endif
                        </flux:text>
                    @endforeach
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="sticky top-6 flex flex-col gap-3">
                    <flux:heading size="sm">Prévia do portal</flux:heading>
                    <x-ng.previa-portal :aparencia="$aparencia" :nome="$nome ?: 'Seu estabelecimento'" />
                </div>
            </div>
        </div>
    @endif

    {{-- Navegação --}}
    <flux:separator />
    <div class="flex items-center justify-between">
        <flux:button wire:click="voltar" variant="ghost" icon="arrow-left" :disabled="$etapa === 1">Voltar</flux:button>

        @if ($etapa < $totalEtapas)
            <flux:button wire:click="proximo" variant="primary" icon:trailing="arrow-right">Próximo</flux:button>
        @else
            <flux:button wire:click="confirmar" variant="primary" icon="check">
                <span wire:loading.remove wire:target="confirmar">Criar estabelecimento</span>
                <span wire:loading wire:target="confirmar">Criando…</span>
            </flux:button>
        @endif
    </div>
</div>
