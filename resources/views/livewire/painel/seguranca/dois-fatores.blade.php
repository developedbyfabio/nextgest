<div class="flex flex-col gap-6">
    @if ($permitePular)
        <div>
            <flux:heading size="lg">Autenticação em duas etapas</flux:heading>
            <flux:subheading>
                Opcional. Adiciona uma camada extra de proteção à sua conta de Dono: além da
                senha, um código gerado por um app autenticador (Google Authenticator, Authy…).
            </flux:subheading>
        </div>
    @else
        <div>
            <flux:heading size="lg">Autenticação em duas etapas</flux:heading>
            <flux:subheading>Proteja sua conta de Dono com um app autenticador (TOTP).</flux:subheading>
        </div>
    @endif

    {{-- ESTADO: exibindo os códigos de recuperação (uma vez) --}}
    @if ($ativo && $mostrarRecuperacao)
        <flux:callout variant="warning" icon="key">
            <flux:callout.heading>Guarde seus códigos de recuperação</flux:callout.heading>
            <flux:callout.text>
                Cada código funciona <strong>uma única vez</strong> e permite entrar se você
                perder o acesso ao app. Guarde-os em local seguro — eles não serão exibidos
                de novo (só com sua senha).
            </flux:callout.text>
        </flux:callout>

        <div
            x-data="{
                codigos: @js($codigosRecuperacao),
                copiado: false,
                copiar() {
                    navigator.clipboard.writeText(this.codigos.join('\n')).then(() => {
                        this.copiado = true;
                        setTimeout(() => this.copiado = false, 2000);
                    });
                },
            }"
            class="rounded-lg border p-4"
            style="border-color: color-mix(in srgb, var(--cor-texto) 12%, transparent); background-color: color-mix(in srgb, var(--cor-texto) 3%, var(--cor-superficie));"
        >
            <div class="grid grid-cols-2 gap-x-6 gap-y-2 font-mono text-sm">
                @foreach ($codigosRecuperacao as $codigo)
                    <div class="tracking-wider">{{ $codigo }}</div>
                @endforeach
            </div>

            <div class="mt-4 flex items-center gap-2">
                <flux:button size="sm" variant="subtle" icon="clipboard" x-on:click="copiar()">
                    <span x-show="!copiado">Copiar todos</span>
                    <span x-show="copiado" style="display:none">Copiado!</span>
                </flux:button>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            @if ($permitePular)
                <flux:button wire:click="concluir" variant="primary">Concluir e ir ao painel</flux:button>
            @else
                <flux:button wire:click="ocultarRecuperacao" variant="primary">Já guardei meus códigos</flux:button>
            @endif
        </div>

    {{-- ESTADO: 2FA ATIVO (gestão) --}}
    @elseif ($ativo)
        <div class="flex items-center gap-2">
            <flux:badge color="green" icon="check-badge">Ativo</flux:badge>
            <flux:text class="text-sm">A duas etapas está protegendo sua conta.</flux:text>
        </div>

        @if ($acaoSenha)
            <form
                wire:submit="{{ $acaoSenha === 'ver' ? 'reexibir' : ($acaoSenha === 'regenerar' ? 'regenerar' : 'desativar') }}"
                class="flex flex-col gap-3 rounded-lg border p-4"
                style="border-color: color-mix(in srgb, var(--cor-texto) 12%, transparent);"
            >
                <flux:text class="text-sm">
                    @if ($acaoSenha === 'desativar')
                        Para <strong>desativar</strong> a duas etapas, confirme sua senha.
                    @elseif ($acaoSenha === 'regenerar')
                        Gerar novos códigos <strong>invalida os atuais</strong>. Confirme sua senha.
                    @else
                        Para ver seus códigos de recuperação, confirme sua senha.
                    @endif
                </flux:text>

                <flux:input
                    wire:model="senha"
                    type="password"
                    label="Sua senha"
                    autocomplete="current-password"
                    viewable
                    required
                />

                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="cancelarSenha">Cancelar</flux:button>
                    <flux:button
                        type="submit"
                        :variant="$acaoSenha === 'desativar' ? 'danger' : 'primary'"
                    >
                        @if ($acaoSenha === 'desativar') Desativar
                        @elseif ($acaoSenha === 'regenerar') Gerar novos códigos
                        @else Ver códigos
                        @endif
                    </flux:button>
                </div>
            </form>
        @else
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" variant="subtle" icon="eye" wire:click="pedirSenha('ver')">
                    Ver códigos de recuperação
                </flux:button>
                <flux:button size="sm" variant="subtle" icon="arrow-path" wire:click="pedirSenha('regenerar')">
                    Gerar novos códigos
                </flux:button>
                <flux:button size="sm" variant="danger" icon="shield-exclamation" wire:click="pedirSenha('desativar')">
                    Desativar
                </flux:button>
            </div>
        @endif

        @if ($permitePular)
            <div class="flex justify-end">
                <flux:button wire:click="concluir" variant="ghost">Concluir e ir ao painel</flux:button>
            </div>
        @endif

    {{-- ESTADO: EM CONFIGURAÇÃO (QR + chave + código) --}}
    @elseif ($emConfiguracao)
        <flux:text class="text-sm">
            1. Abra seu app autenticador e escaneie o QR (ou digite a chave manual).
            2. Informe o código de 6 dígitos que o app mostrar para confirmar.
        </flux:text>

        <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
            @if ($qrSvg)
                <div class="rounded-lg bg-white p-3 shrink-0">{!! $qrSvg !!}</div>
            @endif

            <div class="flex flex-col gap-3 w-full">
                <div>
                    <flux:text class="text-xs uppercase tracking-wide" style="color: var(--cor-texto-suave);">
                        Chave manual
                    </flux:text>
                    <div class="font-mono text-sm break-all select-all">{{ $chaveManual }}</div>
                </div>

                <form wire:submit="confirmar" class="flex flex-col gap-3">
                    <flux:input
                        wire:model="codigo"
                        label="Código do app"
                        placeholder="000000"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        required
                    />
                    <div class="flex gap-2">
                        <flux:button type="submit" variant="primary">Confirmar e ativar</flux:button>
                        <flux:button type="button" variant="ghost" wire:click="cancelar">Cancelar</flux:button>
                    </div>
                </form>
            </div>
        </div>

    {{-- ESTADO: INATIVO --}}
    @else
        <flux:text class="text-sm">
            Quando ativada, além da senha o sistema pedirá um código do seu app autenticador
            a cada login. Você receberá códigos de recuperação para emergências.
        </flux:text>

        <div class="flex flex-wrap justify-end gap-2">
            @if ($permitePular)
                <flux:button wire:click="pular" variant="ghost">Pular por enquanto</flux:button>
            @endif
            <flux:button wire:click="ativar" variant="primary" icon="shield-check">Ativar</flux:button>
        </div>
    @endif
</div>
