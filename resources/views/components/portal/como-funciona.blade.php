{{-- Passos "Como funciona" do portal. FONTE DE VERDADE única: home do visitante
     (portal real) e prévia. --}}
<div class="flex flex-col gap-3">
    <flux:text class="text-center text-sm font-medium" style="color: var(--cor-texto-suave);">Como funciona</flux:text>
    <div class="grid grid-cols-3 gap-2 text-center">
        @php($passos = [['user-plus', 'Crie sua conta'], ['scissors', 'Escolha o serviço'], ['calendar-days', 'Marque o horário']])
        @foreach ($passos as $i => [$icone, $texto])
            <div class="flex flex-col items-center gap-2 rounded-xl border p-3" style="border-color: color-mix(in srgb, var(--cor-texto) 8%, transparent);">
                <span class="flex size-9 items-center justify-center rounded-full text-sm font-bold" style="background-color: color-mix(in srgb, var(--cor-principal) 12%, transparent); color: var(--cor-principal);">{{ $i + 1 }}</span>
                <span class="text-xs" style="color: var(--cor-texto-suave);">{{ $texto }}</span>
            </div>
        @endforeach
    </div>
</div>
