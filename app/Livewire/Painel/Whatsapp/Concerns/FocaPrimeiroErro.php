<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Whatsapp\Concerns;

use Flux\Flux;
use Illuminate\Support\Facades\Validator;

/**
 * Validação com feedback de UI (WhatsApp UI/UX, D84). Em vez de só lançar a exceção
 * (erro inline silencioso), ao falhar mostra um TOAST e dispara o evento de browser
 * `wa-erro-validacao` — o Alpine no topo da tela ROLA/FOCA o primeiro campo inválido
 * (`[data-invalid]`). Não muda nenhuma regra de validação, só a forma de avisar.
 *
 * Cada aba do WhatsApp é uma rota/componente própria, então o campo inválido está sempre
 * na aba atual (não há troca de aba a fazer). Retorna os dados validados ou null (falhou).
 */
trait FocaPrimeiroErro
{
    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $attributes
     * @return array<string, mixed>|null
     */
    protected function validarOuFocar(array $rules, array $messages = [], array $attributes = []): ?array
    {
        $validador = Validator::make($this->all(), $rules, $messages, $attributes);

        if ($validador->fails()) {
            $this->setErrorBag($validador->errors());
            Flux::toast('Confira os campos destacados em vermelho.', variant: 'danger');
            $this->dispatch('wa-erro-validacao');

            return null;
        }

        $this->resetErrorBag();

        return $validador->validated();
    }
}
