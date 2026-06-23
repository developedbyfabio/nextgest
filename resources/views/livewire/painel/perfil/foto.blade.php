<div>
    {{-- Modal da foto de perfil — aberto pelo dropdown do perfil (rodapé). O recorte
         quadrado roda no cliente (Alpine `cropperFoto` + Cropper.js empacotado); aqui
         só validamos/persistimos. Ao fechar, resetCropper() descarta o palco. --}}
    <flux:modal name="foto-perfil" class="md:w-[32rem]" x-data="cropperFoto()" @close="resetCropper()">
        <div class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">Foto de perfil</flux:heading>
                <flux:subheading>Envie uma imagem e ajuste o recorte quadrado.</flux:subheading>
            </div>

            {{-- Sem recorte em andamento: avatar atual (ou iniciais do nome). --}}
            <div class="flex items-center gap-4" x-show="!temImagem">
                <flux:avatar
                    :src="$fotoUrl"
                    :name="auth('web')->user()?->name"
                    size="xl"
                    circle
                />
                <flux:text class="text-sm">
                    @if ($fotoUrl)
                        Você já tem uma foto. Envie outra para substituir.
                    @else
                        Sem foto — exibindo as iniciais do seu nome.
                    @endif
                </flux:text>
            </div>

            {{-- Palco do recorte (após escolher a imagem). O Cropper força 1:1 (quadrado). --}}
            <div x-show="temImagem" x-cloak>
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-zinc-900">
                    <img x-ref="imagem" class="block max-w-full" style="max-height: 20rem;" alt="Pré-visualização para recorte" />
                </div>
                <flux:text class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                    Arraste para posicionar e use a roda do mouse para aproximar. O recorte é sempre quadrado.
                </flux:text>
            </div>

            {{-- Seletor de arquivo escondido — acionado pelos botões. Sem SVG (mesma
                 política da Aparência: PNG/JPG/WebP). --}}
            <input
                type="file"
                x-ref="arquivo"
                accept="image/png,image/jpeg,image/webp"
                class="hidden"
                @change="selecionar($event)"
            />

            @error('foto')
                <div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-400">
                    {{ $message }}
                </div>
            @enderror

            <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" variant="subtle" icon="arrow-up-tray" @click="$refs.arquivo.click()">
                        <span x-text="temImagem ? 'Trocar imagem' : 'Escolher imagem'"></span>
                    </flux:button>
                    <flux:button x-show="temImagem" x-cloak size="sm" variant="ghost" icon="arrow-path" @click="girar()">Girar</flux:button>
                    @if ($fotoUrl)
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="remover">Remover foto</flux:button>
                    @endif
                </div>
                <div class="flex gap-2">
                    <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                    <flux:button variant="primary" x-bind:disabled="!temImagem || enviando" @click="salvar()">
                        <span x-show="!enviando">Salvar</span>
                        <span x-show="enviando" x-cloak>Enviando…</span>
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
