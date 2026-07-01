@php($aparencia = \App\Support\Aparencia::doTenant())
<x-portal.auth
    :nome="tenant('nome')"
    :logo-url="\App\Support\Aparencia::urlArquivo($aparencia['logo'])"
    :fundo-url="\App\Support\Aparencia::urlArquivo($aparencia['fundo_imagem'])"
    titulo="Criar conta"
    :subtitulo="'Cadastre-se para agendar em ' . tenant('nome')"
    class="flex-1"
>
    <form wire:submit="registrar" class="flex flex-col gap-4">
        <flux:input
            wire:model="nome"
            label="Nome"
            placeholder="Seu nome completo"
            autocomplete="name"
            required
        />

        <flux:input
            wire:model="telefone"
            type="tel"
            label="Telefone"
            placeholder="(00) 00000-0000"
            autocomplete="tel"
            required
        />

        <flux:input
            wire:model="email"
            type="email"
            label="E-mail"
            placeholder="voce@exemplo.com"
            autocomplete="username"
            required
        />

        <flux:input
            wire:model="cpf"
            label="CPF"
            mask="999.999.999-99"
            placeholder="000.000.000-00"
            inputmode="numeric"
            required
        />

        <flux:input
            wire:model="password"
            type="password"
            label="Senha"
            placeholder="Mínimo de 8 caracteres"
            autocomplete="new-password"
            viewable
            required
        />

        <flux:input
            wire:model="password_confirmation"
            type="password"
            label="Confirmar senha"
            placeholder="Repita a senha"
            autocomplete="new-password"
            viewable
            required
        />

        <flux:button type="submit" variant="primary" class="w-full">
            Criar conta
        </flux:button>
    </form>

    <flux:separator class="my-6" text="ou" />

    <div class="flex flex-col gap-3">
        <x-portal.botao-google />

        <flux:button :href="route('cliente.login', ['tenant' => tenant('id')])" variant="ghost" class="w-full" wire:navigate>
            Já tenho conta
        </flux:button>
    </div>

    <x-portal.consentimento />
</x-portal.auth>
