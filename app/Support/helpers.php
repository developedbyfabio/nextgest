<?php

declare(strict_types=1);

use App\Models\Tenant;

if (! function_exists('tenant_tem_recurso')) {
    /**
     * O estabelecimento do CONTEXTO ATUAL (stancl) tem o recurso ligado?
     *
     * Robustez (é onde costuma quebrar):
     * - Sem contexto de tenant (ex.: rodando no central) → false, NÃO lança.
     * - Slug desconhecido (fora do enum App\Enums\Recurso) → false + aviso no log
     *   (delegado a Tenant::temRecurso()).
     *
     * Usável em Blade, controllers e components. Há também a diretiva
     * `@recurso('whatsapp') ... @endrecurso` (registrada no AppServiceProvider),
     * que reusa exatamente este helper.
     */
    function tenant_tem_recurso(string $recurso): bool
    {
        $tenant = tenant(); // helper do stancl: instância do tenant atual ou null

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return $tenant->temRecurso($recurso);
    }
}
