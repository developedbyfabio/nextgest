<div class="flex flex-col gap-6 p-6 lg:p-8">
    {{-- Carrega TODAS as fontes do catálogo para a prévia ao vivo refletir
         qualquer seleção na hora (antes de salvar/recarregar). Nas páginas reais
         (portal/painel) carrega-se só a fonte escolhida. --}}
    {!! \App\Support\Aparencia::linksFontesGoogle() !!}

    <x-ng.page-header title="Aparência" subtitle="Identidade visual do seu portal de agendamento">
        <x-slot:actions>
            <flux:button wire:click="salvar" variant="primary" icon="check">Salvar</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    <div class="grid gap-8 lg:grid-cols-3">
        {{-- Formulário --}}
        <div class="flex flex-col gap-6 lg:col-span-2">
            {{-- Templates --}}
            <div class="flex flex-col gap-3">
                <flux:heading size="sm">Comece por um template</flux:heading>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($templates as $chave => $t)
                        <button type="button" wire:click="aplicarTemplate('{{ $chave }}')"
                            @class([
                                'ng-card-interactive relative flex flex-col gap-2 p-3 text-left',
                                'ring-2 ring-[var(--color-accent)] ring-offset-2 dark:ring-offset-zinc-900' => $template === $chave,
                            ])
                            aria-pressed="{{ $template === $chave ? 'true' : 'false' }}">
                            @if ($template === $chave)
                                <span class="absolute right-2 top-2 flex size-5 items-center justify-center rounded-full text-white" style="background-color: var(--color-accent);">
                                    <flux:icon name="check" class="size-3.5" />
                                </span>
                            @endif
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
            </div>

            <flux:separator />

            {{-- Cores da marca (acento). As superfícies seguem claro/escuro (D36). --}}
            <div class="flex flex-col gap-3">
                <flux:heading size="sm">Cores da marca</flux:heading>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <flux:input type="color" wire:model.live="cor_principal" label="Principal (acento)" />
                    <flux:input type="color" wire:model.live="cor_secundaria" label="Secundária (realces)" />
                </div>
                <flux:text class="text-xs text-zinc-500">
                    O fundo, as superfícies e o texto seguem o modo <strong>claro/escuro</strong> escolhido por
                    quem acessa — não são cores fixas. A marca entra como acento (botões, links, destaques).
                </flux:text>
            </div>

            {{-- Tipografia --}}
            <div class="flex flex-col gap-3">
                <flux:heading size="sm">Tipografia</flux:heading>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="fonte" label="Fonte">
                        {{-- :value/:style (bound) para o Flux escapar UMA vez. Passar via
                             value="..." faz o Blade pré-escapar e o Flux escapar de novo
                             (escape duplo) → a opção enviava um valor que o Rule::in
                             rejeitava. Ver a nota "Bug - Fonte rejeitada na Aparencia". --}}
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
                <flux:text class="text-xs text-zinc-500">A fonte e o tamanho são aplicados no portal e no painel.</flux:text>
            </div>

            {{-- Marca do portal (D92): ícone predefinido OU logo enviado. --}}
            <div class="flex flex-col gap-3">
                <flux:heading size="sm">Marca do portal</flux:heading>
                <flux:text class="text-xs text-zinc-500">O símbolo que aparece no topo e no destaque do portal.</flux:text>

                {{-- Segmented control: Ícone | Logo (wire:click = atualiza a prévia ao vivo). --}}
                <div class="inline-flex w-fit rounded-lg border border-zinc-200 p-0.5 dark:border-zinc-700">
                    @foreach (['icone' => 'Ícone', 'logo' => 'Logo'] as $valor => $rotulo)
                        <button type="button" wire:click="$set('marca_tipo', '{{ $valor }}')"
                            @class([
                                'rounded-md px-3 py-1.5 text-sm font-medium transition',
                                'text-white' => $marca_tipo === $valor,
                                'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $marca_tipo !== $valor,
                            ])
                            @style(['background-color: var(--color-accent)' => $marca_tipo === $valor])
                            aria-pressed="{{ $marca_tipo === $valor ? 'true' : 'false' }}">
                            {{ $rotulo }}
                        </button>
                    @endforeach
                </div>

                @if ($marca_tipo === 'icone')
                    {{-- Seletor visual: grade de ícones RENDERIZADOS (prévia), não só nomes.
                         Valor via wire:click (string literal do catálogo) — sem value="{{ }}",
                         então imune ao escape duplo; o Rule::in valida no salvar. --}}
                    <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                        @foreach ($icones as $chave => $rotulo)
                            <button type="button" wire:click="$set('marca_icone', '{{ $chave }}')" title="{{ $rotulo }}"
                                @class([
                                    'flex flex-col items-center gap-1 rounded-lg border p-2 text-center transition',
                                    'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800' => $marca_icone !== $chave,
                                    'ring-2 ring-[var(--color-accent)] ring-offset-2 dark:ring-offset-zinc-900' => $marca_icone === $chave,
                                ])
                                aria-pressed="{{ $marca_icone === $chave ? 'true' : 'false' }}">
                                <span class="flex size-9 items-center justify-center rounded-md" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">
                                    <flux:icon :name="$chave" class="size-5" />
                                </span>
                                <span class="w-full truncate text-[0.7rem] text-zinc-500">{{ $rotulo }}</span>
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="flex items-center gap-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-zinc-50 dark:bg-zinc-800">
                            @if ($aparencia['logo_url'] ?? null)
                                <img src="{{ $aparencia['logo_url'] }}" alt="Logo" class="size-full object-contain" />
                            @else
                                <flux:icon name="photo" class="size-6 text-zinc-400" />
                            @endif
                        </div>
                        <flux:text class="text-xs text-zinc-500">
                            Usa a <strong>logo</strong> enviada em <strong>Imagens</strong> (abaixo). Pode ser
                            <strong>transparente</strong> — não colocamos fundo atrás dela.
                            @unless ($aparencia['logo_url'] ?? null)
                                Enquanto não houver logo, o portal mostra o ícone padrão.
                            @endunless
                        </flux:text>
                    </div>
                @endif
            </div>

            {{-- Imagens --}}
            <div class="flex flex-col gap-3">
                <flux:heading size="sm">Imagens</flux:heading>
                <flux:text class="text-xs text-zinc-500">PNG, JPG ou WebP, até 5 MB cada.</flux:text>

                @foreach ([
                    ['campo' => 'logo', 'upload' => 'logoUpload', 'url' => 'logo_url', 'rotulo' => 'Logo'],
                    ['campo' => 'header_imagem', 'upload' => 'headerUpload', 'url' => 'header_url', 'rotulo' => 'Imagem de cabeçalho'],
                    ['campo' => 'fundo_imagem', 'upload' => 'fundoUpload', 'url' => 'fundo_url', 'rotulo' => 'Imagem de fundo'],
                    ['campo' => 'favicon', 'upload' => 'faviconUpload', 'url' => 'favicon_url', 'rotulo' => 'Favicon (ícone da aba)'],
                ] as $img)
                    <div class="flex items-center gap-4">
                        <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                            @if ($aparencia[$img['url']] ?? null)
                                <img src="{{ $aparencia[$img['url']] }}" alt="{{ $img['rotulo'] }}" class="size-full object-contain" />
                            @else
                                <flux:icon name="photo" class="size-6 text-zinc-400" />
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col gap-1">
                            <flux:label>{{ $img['rotulo'] }}</flux:label>
                            <input type="file" wire:model="{{ $img['upload'] }}" accept="image/png,image/jpeg,image/webp"
                                class="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:file:bg-zinc-700 dark:hover:file:bg-zinc-600" />
                            <div wire:loading wire:target="{{ $img['upload'] }}" class="text-xs text-zinc-500">Enviando…</div>
                            <flux:error name="{{ $img['upload'] }}" />
                        </div>
                        @if ($aparencia[$img['campo']] ?? null)
                            <flux:button size="sm" variant="ghost" wire:click="removerImagem('{{ $img['campo'] }}')">Remover</flux:button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Prévia ao vivo --}}
        <div class="lg:col-span-1">
            <div class="sticky top-6 flex flex-col gap-3">
                <flux:heading size="sm">Prévia do portal</flux:heading>
                <flux:text class="text-xs text-zinc-500">As telas que o cliente vê — deslize entre elas.</flux:text>
                <x-ng.previa-portal :aparencia="$aparencia" :nome="tenant('nome')" />
            </div>
        </div>
    </div>
</div>
