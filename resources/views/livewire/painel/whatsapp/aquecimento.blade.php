<div class="flex flex-col gap-6 p-6 lg:p-8" x-data
    @wa-erro-validacao.window="requestAnimationFrame(() => { const el = $root.querySelector('[data-invalid]'); if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.focus({ preventScroll: true }); } })">
    <x-ng.page-header title="WhatsApp" subtitle="Aquecimento do número (curva de volume)">
        <x-slot:actions>
            <flux:button wire:click="salvar" variant="primary" icon="check">Salvar</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    @include('livewire.painel.whatsapp._abas', ['ativa' => 'aquecimento'])

    <div class="ng-surface flex flex-col gap-2 p-4 text-sm" style="color: var(--cor-texto-suave);">
        <p>Número novo no WhatsApp <strong>não-oficial</strong> é frágil: começar com volume alto leva a
            <strong>bloqueio</strong>. O aquecimento sobe o volume aos poucos. O teto do dia é o
            <strong>menor</strong> entre o teto normal e o da curva. O envio em massa (broadcast) só libera
            na fase madura.</p>
        @if ($conectado)
            <p style="color: var(--cor-texto);">Hoje: <strong>dia {{ $diaAtual }}</strong> da curva ·
                teto efetivo <strong>{{ $tetoEfetivo }}/dia</strong> ·
                broadcast {{ $broadcastLiberado ? 'liberado' : 'ainda bloqueado' }}.</p>
        @else
            <p>Conecte o WhatsApp para a curva começar a contar (o dia 1 é o dia da conexão).</p>
        @endif
    </div>

    <div class="ng-surface flex flex-col gap-4 p-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <flux:heading size="sm" style="color: var(--cor-texto);">Aquecimento ligado</flux:heading>
                <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Desligar remove o limite extra (não recomendado para número novo).</flux:text>
            </div>
            <flux:switch wire:model="ativo" />
        </div>

        <flux:separator />

        {{-- Fases da curva --}}
        <div class="flex flex-col gap-2">
            <flux:text class="text-sm font-medium" style="color: var(--cor-texto);">Fases da curva</flux:text>
            @foreach ($fases as $i => $f)
                <div class="flex items-end gap-3">
                    <flux:input type="number" wire:model="fases.{{ $i }}.ate_dia" min="1" max="90"
                        label="Até o dia" class="w-32" />
                    <flux:input type="number" wire:model="fases.{{ $i }}.limite_dia" min="1"
                        label="Teto por dia" class="w-32" />
                </div>
            @endforeach
            <flux:text class="text-xs" style="color: var(--cor-texto-suave);">
                Depois da última fase, vale o teto normal (aquecimento concluído).
            </flux:text>
        </div>

        <flux:separator />

        <flux:input type="number" wire:model="broadcastDia" min="1" max="90"
            label="Liberar broadcast a partir do dia" class="w-48"
            description="Envio em massa (notícias/avisos) só a partir deste dia da curva." />
    </div>

    <div class="flex justify-end">
        <flux:button wire:click="salvar" variant="primary" icon="check">Salvar aquecimento</flux:button>
    </div>
</div>
