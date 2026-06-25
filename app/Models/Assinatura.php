<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Assinatura SaaS de um estabelecimento (salão → Nextgest) — D58. 1:1 com Tenant.
 *
 * NÃO confundir com o Clube (cliente → salão, no banco do tenant). Mora SEMPRE na
 * conexão central (CentralConnection). `valor_mensal` é SNAPSHOT do preço (o catálogo
 * config/planos.php muda sem reescrever histórico).
 */
class Assinatura extends Model
{
    use CentralConnection;

    protected $table = 'assinaturas';

    // Situações de ACESSO (fonte única em situacaoAcesso()).
    public const EM_TESTE = 'em_teste';

    public const ATIVA = 'ativa';

    public const ATRASADA = 'atrasada';

    public const SUSPENSA = 'suspensa';

    public const CANCELADA = 'cancelada';

    protected $fillable = [
        'tenant_id',
        'plano',
        'valor_mensal',
        'data_inicio',
        'trial_dias',
        'data_primeira_cobranca',
        'dia_vencimento',
        'status',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'valor_mensal' => 'decimal:2',
            'data_inicio' => 'date',
            'data_primeira_cobranca' => 'date',
            'trial_dias' => 'integer',
            'dia_vencimento' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function faturas(): HasMany
    {
        return $this->hasMany(Fatura::class, 'assinatura_id');
    }

    /**
     * Data da 1ª cobrança: `data_primeira_cobranca` (combinada) sobrescreve; senão é
     * `data_inicio + trial_dias`. Até essa data, a assinatura está em teste.
     */
    public function primeiraCobranca(): Carbon
    {
        if ($this->data_primeira_cobranca) {
            return $this->data_primeira_cobranca->copy()->startOfDay();
        }

        return $this->data_inicio->copy()->addDays((int) ($this->trial_dias ?? 0))->startOfDay();
    }

    /**
     * Fatura pendente (não paga/cancelada) mais antiga — a que está em cobrança/atraso.
     * Usada pelo aviso de carência (banner) e pela tela de suspensão. null se não houver.
     */
    public function faturaPendente(): ?Fatura
    {
        return $this->faturas()
            ->whereNotIn('status', [Fatura::PAGA, Fatura::CANCELADA])
            ->orderBy('data_vencimento')
            ->first();
    }

    /**
     * FONTE ÚNICA do estado de acesso (consumida pela tela 4b e pela suspensão 4c).
     *
     * - `cancelada` (manual) → cancelada.
     * - hoje < 1ª cobrança → em_teste.
     * - sem fatura vencida não paga → ativa.
     * - fatura não paga mais antiga vencida há 1..carência → atrasada.
     * - vencida há > carência → suspensa.
     *
     * Carência (dias após o vencimento) vem de config('cobranca.carencia_dias').
     * "Vence hoje" ainda NÃO é atraso (conta a partir do dia seguinte).
     */
    public function situacaoAcesso(?CarbonInterface $hoje = null): string
    {
        if ($this->status === self::CANCELADA) {
            return self::CANCELADA;
        }

        $hoje = ($hoje ? Carbon::parse($hoje) : Carbon::today())->copy()->startOfDay();

        if ($hoje->lt($this->primeiraCobranca())) {
            return self::EM_TESTE;
        }

        $carencia = (int) config('cobranca.carencia_dias', 20);

        $atrasos = $this->faturas()
            ->whereNotIn('status', [Fatura::PAGA, Fatura::CANCELADA])
            ->get()
            ->map(fn (Fatura $f) => $f->data_vencimento->copy()->startOfDay())
            ->filter(fn (Carbon $venc) => $venc->lt($hoje))            // só já vencidas (estritamente)
            ->map(fn (Carbon $venc) => (int) $venc->diffInDays($hoje)) // dias de atraso
            ->all();

        if ($atrasos === []) {
            return self::ATIVA;
        }

        return max($atrasos) <= $carencia ? self::ATRASADA : self::SUSPENSA;
    }
}
