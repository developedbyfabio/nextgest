{{-- Rodapé compartilhado do portal (D93): links legais do PRÓPRIO tenant + assinatura.
     Usado na home (layout do portal) e nas telas de auth (layout portal-auth) — um só
     partial, sem paralelo. Links apontam para as rotas do tenant resolvido. --}}
@php($tenantId = tenant('id'))

<footer class="border-t px-4 py-4 text-center text-xs"
    style="border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent); color: var(--cor-texto-suave);">
    <nav class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1" aria-label="Links legais">
        <a href="{{ route('tenant.politica-privacidade', ['tenant' => $tenantId]) }}"
            class="hover:underline" style="color: var(--cor-texto-suave);">Política de Privacidade</a>
        <span aria-hidden="true">·</span>
        <a href="{{ route('tenant.termos-uso', ['tenant' => $tenantId]) }}"
            class="hover:underline" style="color: var(--cor-texto-suave);">Termos de Uso</a>
    </nav>
    <div class="mt-2">Powered by Nextgest</div>
</footer>
