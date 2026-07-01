@php($aparencia = \App\Support\Aparencia::doTenant())
@php($faltas = collect([$precisaTelefone ? 'telefone' : null, $precisaCpf ? 'CPF' : null])->filter()->implode(' e '))
<x-portal.auth
    :nome="tenant('nome')"
    :logo-url="\App\Support\Aparencia::urlArquivo($aparencia['logo'])"
    :fundo-url="\App\Support\Aparencia::urlArquivo($aparencia['fundo_imagem'])"
    titulo="Complete seu cadastro"
    :subtitulo="'Falta seu ' . $faltas . ' para continuar em ' . tenant('nome')"
    class="flex-1"
>
    <form wire:submit="salvar" class="flex flex-col gap-4">
        <flux:text class="text-sm" style="color: var(--cor-texto-suave);">
            Precisamos completar seu cadastro para confirmar sua identidade, entrar em contato e
            evitar duplicidades. Seus dados ficam protegidos conforme nossa
            <a href="{{ route('tenant.politica-privacidade', ['tenant' => tenant('id')]) }}"
                class="underline" style="color: var(--cor-principal);" wire:navigate>Política de Privacidade</a>.
        </flux:text>

        <flux:input wire:model="nome" label="Nome" placeholder="Seu nome completo" autocomplete="name" required />

        @if ($precisaTelefone)
            <flux:input
                wire:model="telefone"
                label="Telefone (WhatsApp)"
                mask="(99) 99999-9999"
                placeholder="(00) 00000-0000"
                inputmode="numeric"
                autocomplete="tel"
                required
            />
        @endif

        @if ($precisaCpf)
            <flux:input
                wire:model="cpf"
                label="CPF"
                mask="999.999.999-99"
                placeholder="000.000.000-00"
                inputmode="numeric"
                required
            />
        @endif

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
