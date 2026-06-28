{{-- Abas da área WhatsApp (Conexão | Automações) — mesma rota/gating (D76/D77). --}}
<flux:navbar>
    <flux:navbar.item :href="route('painel.whatsapp', ['tenant' => tenant('id')])" :current="request()->routeIs('painel.whatsapp')" wire:navigate>Conexão</flux:navbar.item>
    <flux:navbar.item :href="route('painel.whatsapp.automacoes', ['tenant' => tenant('id')])" :current="request()->routeIs('painel.whatsapp.automacoes')" wire:navigate>Automações</flux:navbar.item>
</flux:navbar>
