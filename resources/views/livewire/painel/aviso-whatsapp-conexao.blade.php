{{-- wire:init confere o estado real (D80). Banner só quando a sessão do WhatsApp caiu. --}}
<div wire:init="verificar">
    @if ($caiu)
        <div class="sticky top-0 z-40 flex flex-wrap items-center gap-2 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950">
            <flux:icon name="exclamation-triangle" class="size-4 shrink-0" />
            <span>O WhatsApp do estabelecimento <strong>desconectou</strong>. Os lembretes não serão enviados até reconectar.</span>
            <a href="{{ route('painel.whatsapp', ['tenant' => tenant('id')]) }}" wire:navigate class="underline underline-offset-2">Reconectar</a>
        </div>
    @endif
</div>
