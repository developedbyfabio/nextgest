<div class="flex flex-col gap-6 p-6 lg:p-8" x-data
    @wa-erro-validacao.window="requestAnimationFrame(() => { const el = $root.querySelector('[data-invalid]'); if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.focus({ preventScroll: true }); } })">
    <x-ng.page-header title="WhatsApp" subtitle="Janela de horário permitido">
        <x-slot:actions>
            <flux:button wire:click="salvar" variant="primary" icon="check">Salvar</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    @include('livewire.painel.whatsapp._abas', ['ativa' => 'janela'])

    <div class="ng-surface flex flex-col gap-2 p-4 text-sm" style="color: var(--cor-texto-suave);">
        <p>As mensagens automáticas só saem dentro da janela (fuso do sistema). Fora dela, o envio é
            <strong>adiado</strong> para o próximo horário válido; se o evento já tiver passado (ex.: um
            lembrete cujo atendimento já começaria), é <strong>descartado</strong>. A decisão é tomada no
            servidor, na hora do envio.</p>
        <p style="color: var(--cor-texto);">
            Janela global agora: <strong>{{ $abertaAgora ? 'aberta' : 'fechada' }}</strong>.
        </p>
    </div>

    {{-- Janela global --}}
    <div class="ng-surface flex flex-col gap-4 p-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <flux:heading size="sm" style="color: var(--cor-texto);">Janela global</flux:heading>
                <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Desligar = enviar a qualquer hora (sem restrição).</flux:text>
            </div>
            <flux:switch wire:model.live="globalAtiva" />
        </div>

        @if ($globalAtiva)
            <div class="flex items-end gap-3">
                <flux:input type="time" wire:model="globalInicio" label="Início" class="w-36" />
                <flux:input type="time" wire:model="globalFim" label="Fim" class="w-36" />
            </div>
        @endif
    </div>

    {{-- Override por automação --}}
    <div class="ng-surface flex flex-col gap-4 p-5">
        <div>
            <flux:heading size="sm" style="color: var(--cor-texto);">Janela própria por automação</flux:heading>
            <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Cada automação nasce com a janela global; ligue para definir uma só dela.</flux:text>
        </div>

        @foreach ($automacoes as $a)
            <flux:separator />
            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between gap-3">
                    <flux:text class="font-medium" style="color: var(--cor-texto);">{{ $a->rotulo() }}</flux:text>
                    <flux:switch wire:model.live="overrides.{{ $a->value }}.usar" />
                </div>
                @if ($overrides[$a->value]['usar'] ?? false)
                    <div class="flex items-end gap-3">
                        <flux:input type="time" wire:model="overrides.{{ $a->value }}.inicio" label="Início" class="w-36" />
                        <flux:input type="time" wire:model="overrides.{{ $a->value }}.fim" label="Fim" class="w-36" />
                    </div>
                @else
                    <flux:text class="text-xs" style="color: var(--cor-texto-suave);">Usando a janela global.</flux:text>
                @endif
            </div>
        @endforeach
    </div>

    <div class="flex justify-end">
        <flux:button wire:click="salvar" variant="primary" icon="check">Salvar janela</flux:button>
    </div>
</div>
