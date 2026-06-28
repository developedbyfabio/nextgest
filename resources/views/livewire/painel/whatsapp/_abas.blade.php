{{-- Abas da área WhatsApp — mesma rota/gating (recurso:whatsapp + can:gerenciar_whatsapp). --}}
<flux:navbar>
    <flux:navbar.item :href="route('painel.whatsapp', ['tenant' => tenant('id')])" :current="request()->routeIs('painel.whatsapp')" wire:navigate>Conexão</flux:navbar.item>
    <flux:navbar.item :href="route('painel.whatsapp.automacoes', ['tenant' => tenant('id')])" :current="request()->routeIs('painel.whatsapp.automacoes')" wire:navigate>Automações</flux:navbar.item>
    <flux:navbar.item :href="route('painel.whatsapp.aquecimento', ['tenant' => tenant('id')])" :current="request()->routeIs('painel.whatsapp.aquecimento')" wire:navigate>Aquecimento</flux:navbar.item>
    <flux:navbar.item :href="route('painel.whatsapp.janela', ['tenant' => tenant('id')])" :current="request()->routeIs('painel.whatsapp.janela')" wire:navigate>Janela</flux:navbar.item>
    <flux:navbar.item :href="route('painel.whatsapp.historico', ['tenant' => tenant('id')])" :current="request()->routeIs('painel.whatsapp.historico')" wire:navigate>Histórico</flux:navbar.item>
    <flux:navbar.item :href="route('painel.whatsapp.optout', ['tenant' => tenant('id')])" :current="request()->routeIs('painel.whatsapp.optout')" wire:navigate>Opt-out</flux:navbar.item>
</flux:navbar>
