<div class="mx-auto flex w-full max-w-md flex-col gap-5">
    @if ($enviado)
        <div class="ng-surface flex flex-col items-center gap-3 p-8 text-center">
            <span class="flex size-14 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-500/15">
                <flux:icon name="check-circle" class="size-8" />
            </span>
            <flux:heading size="lg" style="color: var(--cor-texto);">Obrigado pela avaliação!</flux:heading>
            <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Sua opinião ajuda muito o estabelecimento.</flux:text>
        </div>
    @elseif ($indisponivel)
        <div class="ng-surface flex flex-col items-center gap-3 p-8 text-center">
            <flux:icon name="information-circle" class="size-10" style="color: var(--cor-texto-suave);" />
            <flux:heading size="lg" style="color: var(--cor-texto);">Avaliação indisponível</flux:heading>
            <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Este atendimento já foi avaliado ou não está disponível para avaliação.</flux:text>
        </div>
    @else
        <div class="ng-surface flex flex-col gap-5 p-6">
            <div class="text-center">
                <flux:heading size="lg" style="color: var(--cor-texto);">Como foi seu atendimento?</flux:heading>
                <flux:text class="mt-1 text-sm" style="color: var(--cor-texto-suave);">
                    {{ $servico }}@if ($profissional) com {{ $profissional }}@endif@if ($quando) · {{ $quando }}@endif
                </flux:text>
            </div>

            {{-- Estrelas (1–5) --}}
            <div class="flex items-center justify-center gap-1">
                @for ($i = 1; $i <= 5; $i++)
                    <button type="button" wire:click="$set('nota', {{ $i }})" aria-label="{{ $i }} estrelas"
                        class="p-1 transition-transform hover:scale-110">
                        <flux:icon name="star" variant="{{ ($nota ?? 0) >= $i ? 'solid' : 'outline' }}" class="size-9"
                            style="color: {{ ($nota ?? 0) >= $i ? '#f59e0b' : 'color-mix(in srgb, var(--cor-texto) 30%, transparent)' }};" />
                    </button>
                @endfor
            </div>
            @error('nota') <flux:text class="text-center text-sm text-red-600">{{ $message }}</flux:text> @enderror

            <flux:textarea wire:model="comentario" rows="3" label="Comentário (opcional)"
                placeholder="Conte como foi…" />

            <flux:button wire:click="salvar" variant="primary" class="w-full"
                wire:loading.attr="disabled" wire:target="salvar">Enviar avaliação</flux:button>
        </div>
    @endif
</div>
