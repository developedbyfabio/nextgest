{{--
    Footer da landing: marca + tagline, colunas de links (âncoras das seções que
    chegam nas Fases 2/3), contato e copyright. Semântico (<footer>).
    O id="contato" é o alvo dos CTAs "Começar agora"/"Contato".
--}}
<footer id="contato" class="relative border-t border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-[#0B1120]">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr] md:py-16">
        {{-- Marca --}}
        <div>
            <div class="flex items-center gap-2.5">
                <img src="{{ asset('nextgest-logo.png') }}" alt="Nextgest" class="size-9 object-contain" />
                <span class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">Nextgest</span>
            </div>
            <p class="mt-3 max-w-xs text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                Agendamento online para barbearias, salões, estética e profissionais — agenda, equipe,
                clientes e vendas num só lugar.
            </p>
        </div>

        {{-- Navegação --}}
        <div>
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Navegação</h2>
            <ul class="mt-4 space-y-2.5 text-sm">
                @foreach (['#recursos' => 'Recursos', '#como-funciona' => 'Como funciona', '#planos' => 'Planos', '#faq' => 'FAQ'] as $href => $rotulo)
                    <li><a href="{{ $href }}" class="text-slate-600 transition hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-300">{{ $rotulo }}</a></li>
                @endforeach
            </ul>
        </div>

        {{-- Contato / acesso --}}
        <div>
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Contato</h2>
            <ul class="mt-4 space-y-2.5 text-sm">
                <li>
                    <a href="https://wa.me/5541991541757" target="_blank" rel="noopener noreferrer"
                        class="text-slate-600 transition hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-300">WhatsApp</a>
                </li>
                <li>
                    <a href="https://www.instagram.com/nextgest" target="_blank" rel="noopener noreferrer"
                        class="text-slate-600 transition hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-300">Instagram</a>
                </li>
                <li>
                    <a href="mailto:fabio9384@gmail.com"
                        class="text-slate-600 transition hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-300">E-mail</a>
                </li>
                <li>
                    <a href="{{ route('admin.login') }}"
                        class="text-slate-600 transition hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-300">Acesso administrativo</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="border-t border-slate-200 py-5 text-center text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
        © {{ date('Y') }} Nextgest. Todos os direitos reservados.
    </div>
</footer>
