<div class="flex flex-col gap-6">
    <x-ng.page-header title="Novo estabelecimento" subtitle="Onboarding guiado">
        <x-slot:actions>
            <flux:button :href="route('admin.tenants')" variant="ghost" icon="arrow-left" wire:navigate>Cancelar</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    {{-- Stepper --}}
    @php($passos = [1 => 'Identidade', 2 => 'Responsável', 3 => 'Funcionamento', 4 => 'Aparência', 5 => 'Revisão'])
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
            <flux:text class="text-sm text-zinc-500">Cria o usuário com papel Dono — acesso total ao painel do estabelecimento.</flux:text>
            <flux:input wire:model="donoNome" label="Nome" required />
            <flux:input wire:model="donoEmail" type="email" label="E-mail (login)" required />
            <flux:input wire:model="donoSenha" type="password" label="Senha inicial" viewable required>
                <x-slot:description>Mínimo de 8 caracteres. O Dono poderá trocar depois.</x-slot:description>
            </flux:input>
        </div>
    @endif

    {{-- ETAPA 3 — Horário de funcionamento --}}
    @if ($etapa === 3)
        <div class="flex max-w-xl flex-col gap-4">
            <flux:heading size="lg">Horário de funcionamento</flux:heading>
            <flux:text class="text-sm text-zinc-500">Faixas padrão do estabelecimento. Servem de base ao cadastrar profissionais e à exibição no portal.</flux:text>

            @error('funcionamento')
                <flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>
            @enderror

            <div class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-700">
                @foreach ($funcionamento as $i => $f)
                    <div class="flex flex-wrap items-center gap-4 py-3">
                        <div class="w-28">
                            <flux:switch wire:model.live="funcionamento.{{ $i }}.aberto" label="{{ $f['rotulo'] }}" />
                        </div>
                        @if ($f['aberto'])
                            <div class="flex items-center gap-2">
                                <flux:input type="time" wire:model="funcionamento.{{ $i }}.inicio" class="w-32" />
                                <span class="text-zinc-400">até</span>
                                <flux:input type="time" wire:model="funcionamento.{{ $i }}.fim" class="w-32" />
                            </div>
                        @else
                            <flux:text class="text-sm text-zinc-400">Fechado</flux:text>
                        @endif
                        @error("funcionamento.$i.fim")
                            <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                        @enderror
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ETAPA 4 — Aparência (com prévia ao vivo) --}}
    @if ($etapa === 4)
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

                {{-- Logo --}}
                <div class="flex items-center gap-4">
                    <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                        @if ($logoUpload)
                            <img src="{{ $logoUpload->temporaryUrl() }}" alt="Logo" class="size-full object-contain" />
                        @else
                            <flux:icon name="photo" class="size-6 text-zinc-400" />
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col gap-1">
                        <flux:label>Logo (opcional) — PNG, JPG ou WebP, até 2 MB</flux:label>
                        <input type="file" wire:model="logoUpload" accept="image/png,image/jpeg,image/webp"
                            class="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:file:bg-zinc-700 dark:hover:file:bg-zinc-600" />
                        <div wire:loading wire:target="logoUpload" class="text-xs text-zinc-500">Enviando…</div>
                        <flux:error name="logoUpload" />
                    </div>
                </div>
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

    {{-- ETAPA 5 — Revisão e confirmação --}}
    @if ($etapa === 5)
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

                <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">Responsável</flux:heading>
                    <flux:text>{{ $donoNome }}</flux:text>
                    <flux:text class="text-sm text-zinc-500">{{ $donoEmail }}</flux:text>
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
