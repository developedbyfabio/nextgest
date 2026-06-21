<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Venda;

class VendaPolicy
{
    /**
     * Pode gerir a comanda (ver detalhe, adicionar itens, escolher pagamento,
     * pagar, cancelar)?
     *
     * - Quem tem `criar_venda` (Dono/Gerente/Recepção): qualquer comanda.
     * - Profissional com `finalizar_atendimento_proprio`: SÓ a comanda de
     *   finalização do PRÓPRIO atendimento (tem `agendamento_id` e o profissional
     *   responsável é ele). Não abre avulsas nem comandas de outros.
     */
    public function gerir(User $user, Venda $venda): bool
    {
        if ($user->can('criar_venda')) {
            return true;
        }

        return $user->can('finalizar_atendimento_proprio')
            && $venda->agendamento_id !== null
            && (int) $venda->profissional_id === (int) $user->getKey();
    }
}
