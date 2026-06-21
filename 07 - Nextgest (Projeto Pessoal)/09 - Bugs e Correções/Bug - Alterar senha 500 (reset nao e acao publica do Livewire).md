# Bug — "Alterar senha" dava 500 (`reset` não é ação pública do Livewire)

> Projeto: [[Nextgest - Visão Geral]] · Resolvido · Ver [[Gotchas e Aprendizados do Projeto]]

## Problema
No painel, abrir **menu de perfil → Alterar senha** e clicar **Cancelar** (ou fechar
o modal por Esc/clique fora) estourava **500**:

```
Livewire\Exceptions\MethodNotFoundException
Unable to call component method. Public method [reset] not found on component
POST /livewire-<hash>/update
```

## Sintoma
Qualquer fechamento do modal de troca de senha (`Painel\AlterarSenha`) derrubava a
página com 500. O happy path (salvar) também passava por isso ao fechar.

## Causa (confirmada)
No Blade `resources/views/livewire/painel/alterar-senha.blade.php`, o modal tinha:

```blade
<flux:modal name="alterar-senha" @close="$wire.reset('atual', 'password', 'password_confirmation')">
```

O `@close` (disparado pelo botão `flux:modal.close` "Cancelar" e por Esc/backdrop)
chamava **`$wire.reset(...)`**. O `reset()` do Livewire é um método **interno**
(`protected`, herdado de `Livewire\Component`), **não** uma ação pública chamável do
frontend. Ao receber `reset` como `callMethod`, o Livewire procura um método **público**
`reset` na classe, não encontra e lança `MethodNotFoundException` (500).

> Regra: pelo frontend (`wire:click`, `@close`, `$wire.x()`) só se chama **método público
> próprio**. `reset`, `resetValidation`, `mount`, etc. são internos — nunca os exponha
> como ação.

## Correção
- Criar um **método público próprio** no componente (`AlterarSenha`) que encapsula o
  reset interno:

```php
public function limparFormulario(): void
{
    $this->reset(['atual', 'password', 'password_confirmation']);
    $this->resetValidation();
}
```

- Apontar o `@close` do modal para essa ação:

```blade
<flux:modal name="alterar-senha" @close="$wire.limparFormulario()">
```

- O `salvar()` passou a chamar `$this->limparFormulario()` (em vez de `$this->reset(...)`
  direto) — uso interno em PHP continua válido, mas centraliza a limpeza + reseta erros.

A lógica de senha (senha atual via `Hash::check`, regras de `App\Support\Senhas`, hash ao
gravar) e a **troca forçada no 1º login** (`Auth\TrocarSenha`) **não** foram tocadas.

## Como testar / evitar no futuro
- Fluxo HTTP autenticado real (não só `Livewire::test`) confirmado em **21/06/2026**
  contra o endpoint `/livewire-<hash>/update`, para **Dono e Profissional**:
  - `limparFormulario` (cancelar/fechar) → **200** (sem `MethodNotFoundException`);
  - **controle negativo** chamando `reset` (gatilho antigo) → **500** (reproduz o bug);
  - senha atual errada → **200** com recusa (nunca 500);
  - troca com senha atual correta → **200**, sucesso.
- Testes em `tests/Feature/Auth/SenhaTest.php`:
  - exercita `limparFormulario` (cenário do cancelar/fechar) — campos limpos, sem erro;
  - guard de wiring: o HTML do componente contém `limparFormulario` e **não** contém
    `$wire.reset(`.
- Gotcha geral: **nunca** chamar `reset`/`resetValidation`/`mount` como ação do frontend.
  Se precisar limpar campos ao fechar/cancelar, crie um método público dedicado.
