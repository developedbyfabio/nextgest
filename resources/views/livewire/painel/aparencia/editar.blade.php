<div class="flex flex-col gap-6 p-6 lg:p-8">
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
                            class="ng-card-interactive flex flex-col gap-2 p-3 text-left">
                            <div class="flex gap-1">
                                <span class="size-5 rounded-full border border-black/10" style="background-color: {{ $t['cor_principal'] }}"></span>
                                <span class="size-5 rounded-full border border-black/10" style="background-color: {{ $t['cor_secundaria'] }}"></span>
                                <span class="size-5 rounded-full border border-black/10" style="background-color: {{ $t['cor_fundo'] }}"></span>
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

            {{-- Cores --}}
            <div class="flex flex-col gap-3">
                <flux:heading size="sm">Cores</flux:heading>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <flux:input type="color" wire:model.live="cor_principal" label="Principal" />
                    <flux:input type="color" wire:model.live="cor_secundaria" label="Secundária" />
                    <flux:input type="color" wire:model.live="cor_fundo" label="Fundo" />
                    <flux:input type="color" wire:model.live="cor_superficie" label="Superfície" />
                    <flux:input type="color" wire:model.live="cor_texto" label="Texto" />
                    <flux:input type="color" wire:model.live="cor_texto_suave" label="Texto suave" />
                </div>
            </div>

            {{-- Tipografia --}}
            <div class="flex flex-col gap-3">
                <flux:heading size="sm">Tipografia</flux:heading>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="fonte" label="Fonte">
                        @foreach ($fontes as $valor => $rotulo)
                            <flux:select.option value="{{ $valor }}">{{ $rotulo }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="tamanho_base" label="Tamanho base">
                        @foreach (['14px', '15px', '16px', '17px', '18px'] as $tam)
                            <flux:select.option value="{{ $tam }}">{{ $tam }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            {{-- Layout --}}
            <div class="flex flex-col gap-3">
                <flux:heading size="sm">Layout</flux:heading>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="menu_posicao" label="Posição do menu (painel)">
                        <flux:select.option value="topo">Topo</flux:select.option>
                        <flux:select.option value="lateral">Lateral</flux:select.option>
                    </flux:select>
                    <flux:select wire:model.live="icone_estilo" label="Estilo de ícone">
                        <flux:select.option value="outline">Contorno</flux:select.option>
                        <flux:select.option value="solid">Sólido</flux:select.option>
                    </flux:select>
                </div>
            </div>
        </div>

        {{-- Prévia ao vivo --}}
        <div class="lg:col-span-1">
            <div class="sticky top-6 flex flex-col gap-3">
                <flux:heading size="sm">Prévia do portal</flux:heading>
                <x-ng.previa-portal :aparencia="$aparencia" :nome="tenant('nome')" />
                <flux:text class="text-center text-xs text-zinc-500">Atualiza enquanto você edita.</flux:text>
            </div>
        </div>
    </div>
</div>
