<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Recurso;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Tenant do Nextgest (um estabelecimento).
 *
 * Identificação por caminho: o `id` do tenant é o próprio slug usado na URL
 * (nextgest.com.br/{slug}). O banco do tenant fica nomeado como
 * `tenant_{id}` (prefixo definido em config/tenancy.php).
 *
 * Colunas reais (não guardadas no JSON `data`): id, nome, slug, ativo.
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    /**
     * O id é o slug — string fornecida na criação, não autoincremento/UUID.
     *
     * O trait GeneratesIds do stancl sobrescreve getIncrementing()/getKeyType()
     * com base na existência de um UniqueIdentifierGenerator (id_generator).
     * Como usamos id manual (slug), sobrescrevemos os métodos aqui para garantir
     * chave string não-incremental — senão o Eloquent sobrescreveria o id em
     * memória com o lastInsertId (0) após o insert.
     */
    public $incrementing = false;

    protected $keyType = 'string';

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    public function shouldGenerateId(): bool
    {
        return false;
    }

    /**
     * Colunas que existem fisicamente na tabela `tenants` (as demais iriam
     * para a coluna JSON `data` do stancl).
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'nome',
            'slug',
            'ativo',
        ];
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Recursos (módulos à la carte) — flag no banco central
    |--------------------------------------------------------------------------
    |
    | Os recursos ligados ficam num array de slugs sob a chave `recursos` do JSON
    | `data` do stancl (atributo virtual `$this->recursos`, igual ao `segmento`).
    | NUNCA reatribuir `$this->data` inteiro ao salvar — isso apagaria o segmento;
    | persista só o atributo virtual: `$tenant->recursos = [...]; $tenant->save();`.
    |
    */

    /**
     * Recursos ligados, NORMALIZADOS: só strings válidas do enum Recurso.
     * É o ponto único de leitura — descarta null/lixo/slugs desconhecidos, então o
     * default (DESLIGADO) e a robustez contra dado estranho no `data` saem de graça.
     *
     * @return list<string>
     */
    public function recursosAtivos(): array
    {
        $bruto = $this->recursos; // atributo virtual (mora no `data`); pode vir null/array/lixo

        if (! is_array($bruto)) {
            return [];
        }

        $limpos = array_filter($bruto, 'is_string');

        return array_values(array_intersect($limpos, Recurso::valores()));
    }

    /**
     * O recurso está ligado para este estabelecimento?
     * Slug desconhecido (fora do enum) → false + aviso no log (nunca lança).
     */
    public function temRecurso(string $recurso): bool
    {
        if (! Recurso::valido($recurso)) {
            Log::warning('Consulta a recurso desconhecido.', [
                'recurso' => $recurso,
                'tenant' => $this->getKey(),
            ]);

            return false;
        }

        return in_array($recurso, $this->recursosAtivos(), true);
    }

    /*
    |--------------------------------------------------------------------------
    | Plano nomeado (D55) — dirige os recursos
    |--------------------------------------------------------------------------
    |
    | O plano é só um NOME (slug do catálogo config/planos.php) que define o
    | conjunto de `recursos` ligados. Mora no MESMO JSON `data` (atributo virtual
    | `plano`, igual a `segmento`/`recursos`). Tenants antigos podem não ter plano
    | (null) — tratados como "não definido" (recursos personalizados); NUNCA mutar
    | em massa.
    |
    */

    /**
     * Slug do plano atual, NORMALIZADO: só retorna se for um plano conhecido do
     * catálogo. `null` = não definido (tenant antigo ou recursos personalizados).
     */
    public function planoAtual(): ?string
    {
        $chave = $this->plano; // atributo virtual (mora no `data`); pode vir null/lixo

        return is_string($chave) && is_array(config("planos.{$chave}"))
            ? $chave
            : null;
    }

    /**
     * Aplica um plano do catálogo: seta `plano` + redefine `recursos` para o padrão
     * do plano. Persiste SÓ via atributos virtuais (regra de ouro do `data`: nunca
     * reatribuir `$this->data` inteiro), então `segmento` e demais metadados sobrevivem.
     *
     * Rebaixar o plano só ESCONDE o acesso aos recursos retirados — os dados no banco
     * do tenant (ex.: clube) permanecem. Chave desconhecida lança (o chamador valida antes).
     *
     * @throws \InvalidArgumentException quando o plano não existe no catálogo.
     */
    public function aplicarPlano(string $chave): void
    {
        $plano = config("planos.{$chave}");

        if (! is_array($plano)) {
            throw new \InvalidArgumentException("Plano desconhecido: {$chave}");
        }

        $this->plano = $chave;
        $this->recursos = array_values(array_filter(
            (array) ($plano['recursos'] ?? []),
            'is_string',
        ));
        $this->save();
    }
}
