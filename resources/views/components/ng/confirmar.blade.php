@props([
    'name',                         // nome do flux:modal (Flux::modal('...')->show())
    'titulo',
    'texto' => null,
    'icone' => 'exclamation-triangle',
    'tom' => 'red',                 // red (remover) | amber (inativar)
])

{{-- Modal de confirmação padrão (sem confirm nativo). O botão de confirmar vem no
     slot (com o wire:click da ação). Reutilizado nos cadastros. --}}
@php($circulo = $tom === 'amber'
    ? 'bg-amber-100 text-amber-600 dark:bg-amber-500/15'
    : 'bg-red-100 text-red-600 dark:bg-red-500/15')

<flux:modal :name="$name" class="max-w-sm">
    <div class="flex flex-col gap-4">
        <div class="flex items-center gap-3">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-full {{ $circulo }}">
                <flux:icon :name="$icone" class="size-6" />
            </span>
            <div>
                <flux:heading size="lg">{{ $titulo }}</flux:heading>
                @if ($texto)
                    <flux:text class="mt-1">{{ $texto }}</flux:text>
                @endif
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <flux:modal.close><flux:button variant="ghost">Voltar</flux:button></flux:modal.close>
            {{ $slot }}
        </div>
    </div>
</flux:modal>
