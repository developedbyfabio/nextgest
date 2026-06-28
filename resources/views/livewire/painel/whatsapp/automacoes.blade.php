<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="WhatsApp" subtitle="Automações de mensagens">
        <x-slot:actions>
            <flux:button wire:click="salvar" variant="primary" icon="check">Salvar</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    @include('livewire.painel.whatsapp._abas')

    {{-- TERMO DE RISCO (D80): trava a ativação até o aceite (também no servidor). --}}
    @unless ($termoAceito)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
            <div class="flex items-start gap-3">
                <flux:icon name="shield-exclamation" class="mt-0.5 size-6 shrink-0 text-amber-600 dark:text-amber-400" />
                <div class="flex flex-col gap-3">
                    <div>
                        <flux:heading size="sm" class="text-amber-900 dark:text-amber-200">Antes de ligar qualquer automação, leia e aceite</flux:heading>
                        <flux:text class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                            O envio automático pelo WhatsApp <strong>não-oficial</strong> pode levar ao
                            <strong>bloqueio/banimento do número</strong>. Use um <strong>número dedicado</strong>
                            (não o principal do salão), só envie a clientes que esperam o contato (consentimento/LGPD)
                            e mantenha volume baixo. Você é responsável pelo uso. As automações ficam
                            <strong>bloqueadas</strong> até o aceite.
                        </flux:text>
                    </div>
                    <div>
                        <flux:button wire:click="aceitarTermo" variant="primary" icon="check-badge">Li e aceito o risco</flux:button>
                    </div>
                </div>
            </div>
        </div>
    @endunless

    {{-- Número usado só pelo botão "testar" (dados de exemplo; não toca a base de clientes). --}}
    <div class="ng-surface flex flex-col gap-2 p-4 sm:flex-row sm:items-end sm:gap-4">
        <flux:input wire:model="numeroTeste" label="Número para teste" placeholder="(41) 99999-9999"
            description="O teste envia a mensagem com dados de exemplo para este número." class="sm:max-w-xs" />
    </div>

    {{-- TRANSACIONAIS --}}
    <div class="flex flex-col gap-3">
        <div>
            <flux:heading size="lg" style="color: var(--cor-texto);">Transacionais</flux:heading>
            <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Disparadas por um evento, para um cliente.</flux:text>
        </div>
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($transacionais as $a)
                @include('livewire.painel.whatsapp._card-automacao', ['a' => $a])
            @endforeach
        </div>
    </div>

    {{-- BROADCAST / INFORMATIVO (sensível) --}}
    <div class="flex flex-col gap-3">
        <div>
            <flux:heading size="lg" style="color: var(--cor-texto);">Broadcast / informativo</flux:heading>
            <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Envio em massa para a base de clientes.</flux:text>
        </div>

        <div class="flex items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
            <flux:icon name="exclamation-triangle" class="mt-0.5 size-5 shrink-0" />
            <span><strong>Uso cuidadoso · requer opt-in · risco de bloqueio.</strong> Envio em massa pelo
                WhatsApp não-oficial pode levar ao <strong>banimento do número</strong>. Comece desligado e
                só use com consentimento (LGPD). O disparo em massa real (com limites) virá numa etapa própria.</span>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($broadcasts as $a)
                @include('livewire.painel.whatsapp._card-automacao', ['a' => $a])
            @endforeach
        </div>
    </div>

    <div class="flex justify-end">
        <flux:button wire:click="salvar" variant="primary" icon="check">Salvar automações</flux:button>
    </div>
</div>
