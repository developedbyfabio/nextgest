{{-- Linha de consentimento (D93) das telas de login/registro do cliente: aponta para
     os documentos legais do PRÓPRIO tenant. Estilo pelo tema (--cor-texto-suave/
     --cor-principal); sobre imagem de fundo herda a superfície de leitura da coluna
     do formulário (.ng-com-fundo), então não "some" na foto. --}}
@php($tenantId = tenant('id'))

<p class="mt-6 text-center text-xs leading-relaxed" style="color: var(--cor-texto-suave);">
    Ao continuar, você concorda com a
    <a href="{{ route('tenant.politica-privacidade', ['tenant' => $tenantId]) }}"
        class="underline" style="color: var(--cor-principal);" wire:navigate>Política de Privacidade</a>
    e os
    <a href="{{ route('tenant.termos-uso', ['tenant' => $tenantId]) }}"
        class="underline" style="color: var(--cor-principal);" wire:navigate>Termos de Uso</a>.
</p>
