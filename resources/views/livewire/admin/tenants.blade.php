<div class="flex flex-col gap-6">
    <x-ng.page-header title="Estabelecimentos" subtitle="Tenants do Nextgest">
        <x-slot:actions>
            <flux:button wire:click="novo" variant="primary" icon="plus">Novo estabelecimento</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    <flux:input wire:model.live.debounce.300ms="busca" icon="magnifying-glass" placeholder="Buscar por nome ou slug" class="max-w-sm" />

    <flux:table :paginate="$tenants">
        <flux:table.columns>
            <flux:table.column>Nome</flux:table.column>
            <flux:table.column>Slug</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Criado</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($tenants as $tenant)
                <flux:table.row :key="$tenant->id">
                    <flux:table.cell variant="strong">{{ $tenant->nome }}</flux:table.cell>
                    <flux:table.cell><span class="font-mono text-sm">{{ $tenant->slug }}</span></flux:table.cell>
                    <flux:table.cell>
                        @if ($tenant->ativo)
                            <flux:badge color="green" size="sm">Ativo</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">Inativo</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $tenant->created_at?->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:button :href="route('tenant.home', ['tenant' => $tenant->id])" target="_blank" size="sm" variant="ghost" icon="arrow-top-right-on-square">Abrir</flux:button>
                        <flux:button wire:click="abrirDono('{{ $tenant->id }}')" size="sm" variant="ghost" icon="user-plus">Criar dono</flux:button>
                        @if ($tenant->ativo)
                            <flux:button wire:click="inativar('{{ $tenant->id }}')" wire:confirm="Inativar este estabelecimento?" size="sm" variant="subtle" icon="eye-slash">Inativar</flux:button>
                        @else
                            <flux:button wire:click="ativar('{{ $tenant->id }}')" size="sm" variant="subtle" icon="eye">Ativar</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <x-ng.empty icon="building-storefront" title="Nenhum estabelecimento" text="Crie o primeiro com o botão acima." />
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Modal: criar estabelecimento --}}
    <flux:modal wire:model.self="mostrarFormulario" class="md:w-96">
        <form wire:submit="criar" class="flex flex-col gap-4">
            <flux:heading size="lg">Novo estabelecimento</flux:heading>

            <flux:input wire:model="nome" label="Nome" placeholder="Ex.: Barbearia do Jorge" required />
            <flux:input wire:model="slug" label="Slug (URL)" placeholder="ex.: barbeariadojorge" required>
                <x-slot:description>Acesso em /{slug}. Apenas minúsculas, números e hífen.</x-slot:description>
            </flux:input>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Criar</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal: criar dono --}}
    <flux:modal wire:model.self="mostrarDono" class="md:w-96">
        <form wire:submit="criarDono" class="flex flex-col gap-4">
            <flux:heading size="lg">Criar dono</flux:heading>
            <flux:subheading>Usuário com papel Dono no estabelecimento <span class="font-mono">{{ $tenantDono }}</span>.</flux:subheading>

            <flux:input wire:model="donoNome" label="Nome" required />
            <flux:input wire:model="donoEmail" type="email" label="E-mail" required />
            <flux:input wire:model="donoSenha" type="password" label="Senha inicial" viewable required />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Criar dono</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
