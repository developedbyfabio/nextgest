@php($aparencia = \App\Support\Aparencia::doTenant())
<x-portal.auth
    :nome="tenant('nome')"
    :logo-url="\App\Support\Aparencia::urlArquivo($aparencia['logo'])"
    :fundo-url="\App\Support\Aparencia::urlArquivo($aparencia['fundo_imagem'])"
    titulo="Complete seu cadastro"
    :subtitulo="'Falta o CPF para continuar em ' . tenant('nome')"
    class="flex-1"
>
    <form wire:submit="salvar" class="flex flex-col gap-4">
        <flux:text class="text-sm" style="color: var(--cor-texto-suave);">
            Informe seu <strong>CPF</strong> para confirmarmos sua identidade e evitar cadastros
            duplicados. Ele fica protegido conforme nossa
            <a href="{{ route('tenant.politica-privacidade', ['tenant' => tenant('id')]) }}"
                class="underline" style="color: var(--cor-principal);" wire:navigate>Política de Privacidade</a>.
        </flux:text>

        <flux:input
            wire:model="cpf"
            label="CPF"
            mask="999.999.999-99"
            placeholder="000.000.000-00"
            inputmode="numeric"
            required
        />

        <flux:button type="submit" variant="primary" class="w-full">
            Salvar e continuar
        </flux:button>
    </form>

    {{-- Sair: o gate isenta a rota de logout, então não há loop de redirecionamento. --}}
    <form method="POST" action="{{ route('cliente.logout', ['tenant' => tenant('id')]) }}" class="mt-4">
        @csrf
        <flux:button type="submit" variant="ghost" class="w-full">Sair</flux:button>
    </form>
</x-portal.auth>
