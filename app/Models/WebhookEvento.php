<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Registro de webhook já processado (D62) — dedupe. Central. Ver ProcessadorWebhook.
 */
class WebhookEvento extends Model
{
    use CentralConnection;

    protected $table = 'webhook_eventos';

    protected $fillable = ['gateway', 'evento_id', 'tipo', 'processado_em'];

    protected function casts(): array
    {
        return ['processado_em' => 'datetime'];
    }
}
