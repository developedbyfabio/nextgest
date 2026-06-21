<div>
    <flux:modal name="alterar-senha" class="md:w-96" @close="$wire.limparFormulario()">
        <form wire:submit="salvar" class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">Alterar senha</flux:heading>
                <flux:subheading>Confirme a senha atual e defina uma nova.</flux:subheading>
            </div>

            <flux:input
                wire:model="atual"
                type="password"
                label="Senha atual"
                autocomplete="current-password"
                viewable
                required
            />

            <flux:input
                wire:model="password"
                type="password"
                label="Nova senha"
                placeholder="Mínimo 8 caracteres"
                autocomplete="new-password"
                viewable
                required
            />

            <flux:input
                wire:model="password_confirmation"
                type="password"
                label="Confirmar nova senha"
                autocomplete="new-password"
                viewable
                required
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
