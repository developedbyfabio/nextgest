@props(['size' => 'sm'])

{{-- Alternância Claro / Escuro / Sistema (sistema de aparência do Flux).
     "Sistema" segue o SO do usuário/visitante. Persiste em localStorage (padrão
     do Flux). Reutilizado no portal do cliente e no painel. --}}
<flux:dropdown position="bottom" align="end">
    <flux:button :size="$size" variant="ghost" icon="moon" aria-label="Tema: claro, escuro ou sistema" />

    <flux:menu>
        <flux:menu.radio.group x-data="{ appearance: $flux.appearance }" x-model="appearance" heading="Tema">
            <flux:menu.radio value="light" icon="sun">Claro</flux:menu.radio>
            <flux:menu.radio value="dark" icon="moon">Escuro</flux:menu.radio>
            <flux:menu.radio value="system" icon="computer-desktop">Sistema</flux:menu.radio>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
