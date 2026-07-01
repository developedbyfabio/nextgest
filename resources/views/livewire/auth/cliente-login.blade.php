@php($aparencia = \App\Support\Aparencia::doTenant())
<x-portal.auth
    :nome="tenant('nome')"
    :logo-url="\App\Support\Aparencia::urlArquivo($aparencia['logo'])"
    :fundo-url="\App\Support\Aparencia::urlArquivo($aparencia['fundo_imagem'])"
    titulo="Entrar"
    :subtitulo="'Acesse para agendar em ' . tenant('nome')"
    class="flex-1"
>
    <form wire:submit="login" class="flex flex-col gap-4">
        <flux:input
            wire:model="email"
            type="email"
            label="E-mail"
            placeholder="voce@exemplo.com"
            autocomplete="username"
            required
        />

        <flux:input
            wire:model="password"
            type="password"
            label="Senha"
            placeholder="Sua senha"
            autocomplete="current-password"
            viewable
            required
        />

        <flux:checkbox wire:model="remember" label="Manter conectado" />

        <flux:button type="submit" variant="primary" class="w-full">
            Entrar
        </flux:button>
    </form>

    <flux:separator class="my-6" text="ou" />

    <flux:button :href="route('cliente.registrar', ['tenant' => tenant('id')])" variant="ghost" class="w-full" wire:navigate>
        Criar uma conta
    </flux:button>

    <x-portal.consentimento />
</x-portal.auth>
