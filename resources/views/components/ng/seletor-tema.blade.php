@props(['size' => 'sm'])

{{-- Alternância Claro / Escuro / Sistema (sistema de aparência do Flux).
     "Sistema" segue o SO do usuário/visitante. Persiste em localStorage (padrão
     do Flux). Reutilizado no portal do cliente e no painel.
     IMPORTANTE: o x-model liga DIRETO em `$flux.appearance` (objeto reativo global
     do Flux). Uma cópia local (`x-data="{ appearance: $flux.appearance }"`) só leria
     o valor inicial e NÃO chamaria o effect do Flux que aplica `.dark` → seletor
     inerte. Ver [[Bug - Seletor de tema nao alterna (x-model local)]]. --}}
<flux:dropdown position="bottom" align="end">
    <flux:button :size="$size" variant="ghost" icon="moon" aria-label="Tema: claro, escuro ou sistema" />

    <flux:menu>
        <flux:menu.radio.group x-data x-model="$flux.appearance" heading="Tema">
            <flux:menu.radio value="light" icon="sun">Claro</flux:menu.radio>
            <flux:menu.radio value="dark" icon="moon">Escuro</flux:menu.radio>
            <flux:menu.radio value="system" icon="computer-desktop">Sistema</flux:menu.radio>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
