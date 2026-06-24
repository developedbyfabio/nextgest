{{--
    CTA final (antes do footer): faixa em degradê de marca + motivo geométrico de
    blocos. Botões de contato (WhatsApp / E-mail / Instagram) — mesmos links e
    aria-label dos botões flutuantes; externos em nova aba. Responsivo, dark ok.
--}}
<section class="relative overflow-hidden py-16 sm:py-20">
    {{-- Fundo degradê de marca + grade de blocos sutil --}}
    <div aria-hidden="true" class="absolute inset-0 -z-10 bg-gradient-to-br from-violet-600 via-indigo-600 to-blue-600"></div>
    <div aria-hidden="true" class="absolute inset-0 -z-10 opacity-25 [background-image:linear-gradient(to_right,rgba(255,255,255,0.14)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.14)_1px,transparent_1px)] [background-size:40px_40px] [mask-image:radial-gradient(ellipse_60%_60%_at_50%_50%,#000,transparent)]"></div>

    <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
        <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">Pronto para organizar seus agendamentos?</h2>
        <p class="mx-auto mt-4 max-w-xl text-lg leading-relaxed text-white/85">
            Comece a transformar a forma como seus clientes marcam horários e como sua equipe gerencia a agenda.
        </p>

        <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
            <a href="https://wa.me/5541991541757?text=Olá!%20Vim%20pelo%20site%20do%20Nextgest%20e%20gostaria%20de%20conhecer%20melhor%20a%20plataforma."
                target="_blank" rel="noopener noreferrer" aria-label="Falar no WhatsApp"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-semibold text-indigo-700 shadow-lg shadow-black/10 transition duration-200 hover:-translate-y-0.5 hover:shadow-xl active:translate-y-0 active:scale-[0.98]">
                <svg viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 0 1 8.413 3.488 11.82 11.82 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 0 0 1.515 5.26l-.999 3.648 3.973-1.043zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                Falar pelo WhatsApp
            </a>
            <a href="mailto:fabio9384@gmail.com?subject=Contato%20pelo%20site%20Nextgest&body=Olá,%20vim%20pelo%20site%20do%20Nextgest%20e%20gostaria%20de%20saber%20mais."
                aria-label="Enviar e-mail"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/40 bg-white/10 px-6 py-3.5 text-sm font-semibold text-white backdrop-blur transition duration-200 hover:-translate-y-0.5 hover:bg-white/20 active:translate-y-0">
                <flux:icon name="envelope" class="size-5" />
                Enviar e-mail
            </a>
            <a href="https://www.instagram.com/nextgest"
                target="_blank" rel="noopener noreferrer" aria-label="Seguir no Instagram"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/40 bg-white/10 px-6 py-3.5 text-sm font-semibold text-white backdrop-blur transition duration-200 hover:-translate-y-0.5 hover:bg-white/20 active:translate-y-0">
                <svg viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>
                Conhecer no Instagram
            </a>
        </div>
    </div>
</section>
