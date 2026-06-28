{{-- Controle de UM consentimento de um cliente (D86). Recebe $c (cliente, com os 2 flags)
     e $tipo ('geral'|'marketing'). Bloquear é imediato; liberar (re-consentir) confirma. --}}
@php($bloqueado = $tipo === 'geral' ? $c->whatsapp_optout : $c->whatsapp_marketing_optout)
@if ($bloqueado)
    <span class="inline-flex items-center gap-2">
        <flux:badge :color="$tipo === 'geral' ? 'red' : 'amber'" size="sm">Bloqueado</flux:badge>
        <flux:button wire:click="confirmarLiberacao({{ $c->id }}, '{{ $tipo }}')" size="sm" variant="ghost" icon="arrow-uturn-left">Liberar</flux:button>
    </span>
@else
    <flux:button wire:click="bloquear({{ $c->id }}, '{{ $tipo }}')" size="sm" variant="subtle" icon="no-symbol">
        {{ $tipo === 'geral' ? 'Bloquear tudo' : 'Sair do marketing' }}
    </flux:button>
@endif
