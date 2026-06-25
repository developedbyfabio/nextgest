<div class="flex flex-col gap-6">
    <x-ng.page-header :title="$tenant->nome" subtitle="Dados cadastrais">
        <x-slot:actions>
            <flux:button :href="route('admin.tenant.detalhe', ['tenantId' => $tenant->id])" variant="ghost" icon="arrow-left" wire:navigate>Voltar</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    <flux:callout icon="building-storefront">
        <flux:callout.heading>Cadastro central do estabelecimento</flux:callout.heading>
        <flux:callout.text>
            Fonte de verdade do admin/cobrança (1:1 com o tenant). Para tenants antigos, a linha é
            criada ao salvar pela primeira vez.
        </flux:callout.text>
    </flux:callout>

    <form wire:submit="salvar" class="flex flex-col gap-6">
        {{-- Estabelecimento --}}
        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg">Estabelecimento</flux:heading>

            <flux:input wire:model="nomeFantasia" label="Nome fantasia" required />

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="cep" label="CEP" mask="99999-999" placeholder="00000-000" />
                <div class="sm:col-span-2">
                    <flux:input wire:model="logradouro" label="Logradouro" placeholder="Rua / Av." />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="numero" label="Número" />
                <flux:input wire:model="complemento" label="Complemento" />
                <flux:input wire:model="bairro" label="Bairro" />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <flux:input wire:model="cidade" label="Cidade" />
                </div>
                <flux:input wire:model="uf" label="UF" maxlength="2" placeholder="PR" />
            </div>

            <flux:separator text="Opcional" />

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="faturamentoMensal" type="number" step="0.01" min="0" label="Faturamento mensal (R$)" placeholder="0,00" />
                <flux:select wire:model.live="documentoTipo" label="Documento">
                    <flux:select.option value="">— nenhum —</flux:select.option>
                    <flux:select.option value="cpf">CPF</flux:select.option>
                    <flux:select.option value="cnpj">CNPJ</flux:select.option>
                </flux:select>
                <flux:input wire:model="documento" label="Número do documento"
                    :mask="$documentoTipo === 'cnpj' ? '99.999.999/9999-99' : ($documentoTipo === 'cpf' ? '999.999.999-99' : null)"
                    :disabled="$documentoTipo === ''" placeholder="{{ $documentoTipo === '' ? 'Escolha o tipo' : '' }}" />
            </div>
        </flux:card>

        {{-- Contato do dono (cadastral) --}}
        <flux:card class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">Contato do dono</flux:heading>
                <flux:subheading>
                    Contato cadastral/cobrança. O <strong>e-mail e a senha de login</strong> do dono são
                    gerenciados à parte (no painel do tenant) — editar aqui não altera o acesso dele.
                </flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="donoNome" label="Nome" required />
                <flux:input wire:model="donoSobrenome" label="Sobrenome" required />
            </div>
            <flux:input wire:model="donoEmail" type="email" label="E-mail (cadastral)" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="donoCelular" label="Celular" mask="(99) 99999-9999" placeholder="(41) 99999-9999" required />
                <flux:input wire:model="donoCpf" label="CPF" mask="999.999.999-99" placeholder="000.000.000-00" required />
            </div>
        </flux:card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" icon="check">
                <span wire:loading.remove wire:target="salvar">Salvar dados</span>
                <span wire:loading wire:target="salvar">Salvando…</span>
            </flux:button>
        </div>
    </form>
</div>
