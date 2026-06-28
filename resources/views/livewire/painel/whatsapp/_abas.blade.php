{{-- Abas da área WhatsApp — mesma rota/gating (recurso:whatsapp + can:gerenciar_whatsapp).
     A aba ativa vem por LITERAL ($ativa), passado no @include de cada tela — NÃO de
     request()->routeIs() (que some no /livewire/update e fazia o indicador desaparecer no
     erro/re-render, D84). Assim o destaque PERSISTE em erro, re-render e wire:navigate. --}}
@props(['ativa' => ''])
<flux:navbar>
    <flux:navbar.item icon="qr-code" :href="route('painel.whatsapp', ['tenant' => tenant('id')])" :current="$ativa === 'conexao'" wire:navigate>Conexão</flux:navbar.item>
    <flux:navbar.item icon="chat-bubble-bottom-center-text" :href="route('painel.whatsapp.automacoes', ['tenant' => tenant('id')])" :current="$ativa === 'automacoes'" wire:navigate>Automações</flux:navbar.item>
    <flux:navbar.item icon="fire" :href="route('painel.whatsapp.aquecimento', ['tenant' => tenant('id')])" :current="$ativa === 'aquecimento'" wire:navigate>Aquecimento</flux:navbar.item>
    <flux:navbar.item icon="clock" :href="route('painel.whatsapp.janela', ['tenant' => tenant('id')])" :current="$ativa === 'janela'" wire:navigate>Janela</flux:navbar.item>
    <flux:navbar.item icon="list-bullet" :href="route('painel.whatsapp.historico', ['tenant' => tenant('id')])" :current="$ativa === 'historico'" wire:navigate>Histórico</flux:navbar.item>
    <flux:navbar.item icon="no-symbol" :href="route('painel.whatsapp.optout', ['tenant' => tenant('id')])" :current="$ativa === 'optout'" wire:navigate>Opt-out</flux:navbar.item>
</flux:navbar>
