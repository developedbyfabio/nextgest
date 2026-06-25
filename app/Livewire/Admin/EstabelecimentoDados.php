<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Estabelecimento;
use App\Models\Tenant;
use App\Rules\CelularBr;
use App\Rules\Cnpj;
use App\Rules\Cpf;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tela "Dados" do admin (D57): ler/editar o cadastro CENTRAL do estabelecimento
 * (App\Models\Estabelecimento, 1:1 com Tenant — D56). Funciona também para tenants
 * antigos sem registro: `firstOrNew` por `tenant_id` cria a linha sob demanda no save.
 *
 * Edita o contato CADASTRAL/cobrança do dono — NÃO o e-mail/senha de LOGIN (que vivem
 * no tenant). Reusa os validadores BR (App\Rules\*) e o model da 3a; não duplica regra.
 */
#[Layout('components.layouts.admin')]
#[Title('Dados do estabelecimento')]
class EstabelecimentoDados extends Component
{
    public Tenant $tenant;

    // Estabelecimento.
    public string $nomeFantasia = '';

    public string $cep = '';

    public string $logradouro = '';

    public string $numero = '';

    public string $complemento = '';

    public string $bairro = '';

    public string $cidade = '';

    public string $uf = '';

    public ?string $faturamentoMensal = null;

    public string $documentoTipo = '';

    public string $documento = '';

    // Contato do dono (cadastral/cobrança).
    public string $donoNome = '';

    public string $donoSobrenome = '';

    public string $donoEmail = '';

    public string $donoCelular = '';

    public string $donoCpf = '';

    public function mount(string $tenantId): void
    {
        abort_unless(auth('admin')->check(), 403);

        $this->tenant = Tenant::findOrFail($tenantId);

        // firstOrNew: tenant antigo sem registro abre o formulário vazio (cria no save).
        $est = Estabelecimento::firstOrNew(['tenant_id' => $this->tenant->getKey()]);

        $this->nomeFantasia = (string) ($est->nome_fantasia ?? '');
        $this->cep = (string) ($est->cep ?? '');
        $this->logradouro = (string) ($est->logradouro ?? '');
        $this->numero = (string) ($est->numero ?? '');
        $this->complemento = (string) ($est->complemento ?? '');
        $this->bairro = (string) ($est->bairro ?? '');
        $this->cidade = (string) ($est->cidade ?? '');
        $this->uf = (string) ($est->uf ?? '');
        $this->faturamentoMensal = $est->faturamento_mensal !== null ? (string) $est->faturamento_mensal : null;
        $this->documentoTipo = (string) ($est->documento_tipo ?? '');
        $this->documento = (string) ($est->documento ?? '');
        $this->donoNome = (string) ($est->dono_nome ?? '');
        $this->donoSobrenome = (string) ($est->dono_sobrenome ?? '');
        $this->donoEmail = (string) ($est->dono_email ?? '');
        $this->donoCelular = (string) ($est->dono_celular ?? '');
        $this->donoCpf = (string) ($est->dono_cpf ?? '');
    }

    protected function rules(): array
    {
        return [
            'nomeFantasia' => ['required', 'string', 'max:255'],
            'cep' => ['nullable', 'string', 'max:9'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'max:2'],
            'faturamentoMensal' => ['nullable', 'numeric', 'min:0'],
            'documentoTipo' => ['nullable', Rule::in(['cpf', 'cnpj']), 'required_with:documento'],
            'documento' => $this->regraDocumento(),
            'donoNome' => ['required', 'string', 'max:255'],
            'donoSobrenome' => ['required', 'string', 'max:255'],
            'donoEmail' => ['required', 'string', 'email', 'max:255'],
            'donoCelular' => ['required', 'string', new CelularBr],
            'donoCpf' => ['required', 'string', new Cpf],
        ];
    }

    /** Regra do documento conforme o tipo escolhido (validado só se preenchido). */
    protected function regraDocumento(): array
    {
        return match ($this->documentoTipo) {
            'cpf' => ['nullable', 'string', new Cpf],
            'cnpj' => ['nullable', 'string', new Cnpj],
            default => ['nullable', 'string'],
        };
    }

    protected function messages(): array
    {
        return [
            'documentoTipo.required_with' => 'Escolha o tipo (CPF ou CNPJ) do documento informado.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'nomeFantasia' => 'nome fantasia',
            'faturamentoMensal' => 'faturamento mensal',
            'documento' => 'documento',
            'documentoTipo' => 'tipo de documento',
            'uf' => 'UF',
            'donoNome' => 'nome',
            'donoSobrenome' => 'sobrenome',
            'donoEmail' => 'e-mail',
            'donoCelular' => 'celular',
            'donoCpf' => 'CPF',
        ];
    }

    public function salvar(): void
    {
        abort_unless(auth('admin')->check(), 403);

        if ($this->faturamentoMensal === '') {
            $this->faturamentoMensal = null;
        }

        $this->validate();

        // firstOrNew garante a criação sob demanda (tenant_id já vem setado) e evita
        // duplicar — tenant_id é unique; reabrir e salvar atualiza a MESMA linha.
        $est = Estabelecimento::firstOrNew(['tenant_id' => $this->tenant->getKey()]);

        $est->fill([
            'nome_fantasia' => $this->nomeFantasia,
            'cep' => Estabelecimento::soDigitos($this->cep),
            'logradouro' => $this->logradouro ?: null,
            'numero' => $this->numero ?: null,
            'complemento' => $this->complemento ?: null,
            'bairro' => $this->bairro ?: null,
            'cidade' => $this->cidade ?: null,
            'uf' => $this->uf !== '' ? mb_strtoupper($this->uf) : null,
            'faturamento_mensal' => $this->faturamentoMensal !== null && $this->faturamentoMensal !== '' ? $this->faturamentoMensal : null,
            'documento_tipo' => $this->documento !== '' ? ($this->documentoTipo ?: null) : null,
            'documento' => $this->documento !== '' ? Estabelecimento::soDigitos($this->documento) : null,
            'dono_nome' => $this->donoNome,
            'dono_sobrenome' => $this->donoSobrenome,
            'dono_email' => $this->donoEmail,
            'dono_celular' => Estabelecimento::soDigitos($this->donoCelular),
            'dono_cpf' => Estabelecimento::soDigitos($this->donoCpf),
        ]);

        $est->save();

        Flux::toast('Dados salvos.', variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.estabelecimento-dados');
    }
}
