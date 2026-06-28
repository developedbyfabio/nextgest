{{-- Card de uma automação: toggle + template editável + variáveis + testar. Recebe $a
     (App\Enums\AutomacaoWhatsapp); usa $ativo/$template/$numeroTeste do componente. --}}
<div class="ng-surface flex flex-col gap-3 p-4">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <flux:heading size="sm" style="color: var(--cor-texto);">{{ $a->rotulo() }}</flux:heading>
            <flux:text class="text-sm" style="color: var(--cor-texto-suave);">{{ $a->descricao() }}</flux:text>
        </div>
        {{-- Sem o termo de risco aceito (D80), o toggle fica bloqueado (trava também no servidor). --}}
        <flux:switch wire:model="ativo.{{ $a->value }}" :disabled="! $termoAceito" />
    </div>

    <flux:textarea wire:model="template.{{ $a->value }}" rows="3"
        label="Mensagem" placeholder="Escreva a mensagem usando as variáveis abaixo…" />

    {{-- Antecedência: só o lembrete de serviço (D79). --}}
    @if ($a->value === 'lembrete_servico')
        <flux:input type="number" wire:model="antecedenciaLembrete" min="5" max="1440"
            label="Enviar quantos minutos antes" class="max-w-xs" />
    @endif

    {{-- Variáveis disponíveis nesta automação (placeholders). --}}
    <div class="flex flex-wrap items-center gap-1.5">
        <flux:text class="text-xs" style="color: var(--cor-texto-suave);">Variáveis:</flux:text>
        @foreach ($a->variaveis() as $v)
            <code class="rounded px-1.5 py-0.5 text-xs" style="background-color: color-mix(in srgb, var(--cor-texto) 8%, transparent); color: var(--cor-texto);">{{ '{'.$v.'}' }}</code>
        @endforeach
    </div>

    <div class="flex justify-end">
        <flux:button wire:click="testar('{{ $a->value }}')" size="sm" variant="outline" icon="paper-airplane"
            wire:loading.attr="disabled" wire:target="testar('{{ $a->value }}')">Testar</flux:button>
    </div>
</div>
