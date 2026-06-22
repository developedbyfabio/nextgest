<div class="flex flex-col gap-6">
    <x-ng.page-header title="Integrações" subtitle="Conecte seu estabelecimento a serviços externos" />

    @if ($disponiveis->isEmpty())
        <x-ng.empty
            icon="puzzle-piece"
            title="Nenhuma integração disponível"
            text="As integrações são liberadas pelo administrador do Nextgest conforme o seu plano. Assim que um recurso for ativado, ele aparece aqui." />
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach ($disponiveis as $integracao)
                <flux:card class="flex flex-col gap-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <flux:icon :name="$integracao->icone()" class="size-6 text-indigo-600 dark:text-indigo-400" />
                            <div>
                                <flux:heading size="lg">{{ $integracao->rotulo() }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500">{{ $integracao->descricao() }}</flux:text>
                            </div>
                        </div>

                        @if ($status[$integracao->value])
                            <flux:badge color="green" size="sm" icon="check-circle">Configurado</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">Não configurado</flux:badge>
                        @endif
                    </div>

                    <div class="flex justify-end">
                        <flux:button
                            :href="route($integracao->rota(), ['tenant' => tenant('id')])"
                            variant="primary"
                            size="sm"
                            icon="cog-6-tooth"
                            wire:navigate>
                            Configurar
                        </flux:button>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
