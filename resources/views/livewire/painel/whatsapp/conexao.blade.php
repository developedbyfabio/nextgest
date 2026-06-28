{{-- wire:init confirma o estado REAL na Evolution ao abrir (sem bloquear o 1º render). --}}
<div class="flex flex-col gap-6 p-6 lg:p-8" wire:init="sincronizar">
    <x-ng.page-header title="WhatsApp" subtitle="Conecte o WhatsApp do seu estabelecimento" />

    @include('livewire.painel.whatsapp._abas', ['ativa' => 'conexao'])

    {{-- Aviso de número dedicado (D80): reduz o risco de bloqueio. --}}
    <div class="mx-auto flex w-full max-w-xl items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
        <flux:icon name="exclamation-triangle" class="mt-0.5 size-5 shrink-0" />
        <span>Use um <strong>número dedicado/secundário</strong> para o WhatsApp do sistema — <strong>não o
            número principal</strong> do salão. O envio automático pelo WhatsApp não-oficial tem risco de
            <strong>bloqueio</strong>; um número à parte protege o seu contato principal.</span>
    </div>

    <div class="ng-surface mx-auto flex w-full max-w-xl flex-col items-center gap-5 p-6 text-center">

        {{-- CONECTADO (poll lento detecta queda → "caiu") --}}
        @if ($estado === 'conectado')
            <div wire:poll.20s="monitorar" class="flex flex-col items-center gap-5">
                <span class="flex size-14 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-500/15">
                    <flux:icon name="check-circle" class="size-8" />
                </span>
                <div>
                    <flux:heading size="lg" style="color: var(--cor-texto);">WhatsApp conectado</flux:heading>
                    <flux:text class="mt-1 text-sm" style="color: var(--cor-texto-suave);">
                        As mensagens do estabelecimento sairão por este número.
                    </flux:text>
                </div>
                <flux:modal.trigger name="desconectar-whatsapp">
                    <flux:button variant="danger" icon="arrow-right-start-on-rectangle">Desconectar</flux:button>
                </flux:modal.trigger>
            </div>

        {{-- AGUARDANDO LEITURA DO QR (poll curto que PARA ao conectar) --}}
        @elseif ($estado === 'aguardando')
            <div wire:poll.3s="verificarStatus" class="flex flex-col items-center gap-4">
                <div>
                    <flux:heading size="lg" style="color: var(--cor-texto);">Escaneie o QR no WhatsApp</flux:heading>
                    <flux:text class="mt-1 text-sm" style="color: var(--cor-texto-suave);">
                        WhatsApp → Configurações → <strong>Aparelhos conectados</strong> → Conectar aparelho.
                    </flux:text>
                </div>

                @if ($qr)
                    <img src="{{ $qr }}" alt="QR de conexão do WhatsApp"
                        class="size-64 rounded-xl border bg-white p-2" style="border-color: color-mix(in srgb, var(--cor-texto) 12%, transparent);" />
                @else
                    <div class="flex size-64 items-center justify-center rounded-xl border" style="border-color: color-mix(in srgb, var(--cor-texto) 12%, transparent);">
                        <flux:icon name="qr-code" class="size-10" style="color: var(--cor-texto-suave);" />
                    </div>
                @endif

                <div class="flex items-center gap-2 text-sm" style="color: var(--cor-texto-suave);">
                    <flux:icon name="arrow-path" class="size-4 animate-spin" />
                    Aguardando leitura… (o QR expira em ~1 min)
                </div>

                <flux:button wire:click="renovarQr" variant="outline" icon="arrow-path" size="sm">Gerar novo QR</flux:button>
            </div>

        {{-- CAIU (sessão derrubada) --}}
        @elseif ($estado === 'caiu')
            <span class="flex size-14 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-500/15">
                <flux:icon name="exclamation-triangle" class="size-8" />
            </span>
            <div>
                <flux:heading size="lg" style="color: var(--cor-texto);">A conexão caiu</flux:heading>
                <flux:text class="mt-1 text-sm" style="color: var(--cor-texto-suave);">
                    O WhatsApp deste estabelecimento desconectou. Reconecte para voltar a enviar.
                </flux:text>
            </div>
            <flux:button wire:click="conectar" variant="primary" icon="arrow-path">Reconectar</flux:button>

        {{-- ERRO --}}
        @elseif ($estado === 'erro')
            <span class="flex size-14 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-500/15">
                <flux:icon name="exclamation-triangle" class="size-8" />
            </span>
            <div>
                <flux:heading size="lg" style="color: var(--cor-texto);">Não foi possível conectar</flux:heading>
                <flux:text class="mt-1 text-sm" style="color: var(--cor-texto-suave);">{{ $erro ?: 'Tente novamente em instantes.' }}</flux:text>
            </div>
            <flux:button wire:click="conectar" variant="primary" icon="arrow-path">Tentar de novo</flux:button>

        {{-- DESCONECTADO --}}
        @else
            <span class="flex size-14 items-center justify-center rounded-full"
                style="background-color: color-mix(in srgb, var(--cor-principal) 14%, transparent); color: var(--cor-principal);">
                <flux:icon name="chat-bubble-left-right" class="size-8" />
            </span>
            <div>
                <flux:heading size="lg" style="color: var(--cor-texto);">WhatsApp desconectado</flux:heading>
                <flux:text class="mt-1 text-sm" style="color: var(--cor-texto-suave);">
                    Conecte para gerar o QR e parear um número.
                </flux:text>
            </div>
            <flux:button wire:click="conectar" variant="primary" icon="qr-code"
                wire:loading.attr="disabled" wire:target="conectar">
                <span wire:loading.remove wire:target="conectar">Conectar</span>
                <span wire:loading wire:target="conectar">Gerando QR…</span>
            </flux:button>
        @endif
    </div>

    {{-- Confirmação de risco (D65) no lugar do confirm() nativo (D84). --}}
    <x-ng.confirmar name="desconectar-whatsapp" tom="red" icone="arrow-right-start-on-rectangle"
        titulo="Desconectar o WhatsApp?"
        texto="As mensagens automáticas param até você reconectar um número.">
        <flux:button wire:click="desconectar" variant="danger" icon="arrow-right-start-on-rectangle">Desconectar</flux:button>
    </x-ng.confirmar>
</div>
