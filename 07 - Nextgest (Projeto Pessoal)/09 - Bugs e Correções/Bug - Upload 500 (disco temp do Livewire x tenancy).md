---
projeto: Nextgest
tipo: bug-e-correcao
modulo: aparencia-tema
status: resolvido
criado: 2026-06-21
tags: [nextgest, bug, upload, livewire, tenancy, stancl, filesystem, 500]
---

# Bug — upload de imagem dá 500 (disco temporário do Livewire × tenancy)

> Projeto: [[Nextgest - Visão Geral]] · Resolvido · ver
> [[Identidade Visual do Estabelecimento (Tema)]] e
> [[Bug - Aparencia (upload, fonte e campos desconectados)]]

## Sintoma
Enviar logo/cabeçalho/fundo na Aparência → **`500 (Internal Server Error)`** no
`POST /livewire-.../upload` (console do navegador). Segundo prompt sobre o mesmo upload:
não era validação — o servidor **estourava**.

## Exceção REAL (reproduzida e lida no log/repro)
```
League\Flysystem\UnableToRetrieveMetadata:
Unable to retrieve the file_size for file at location:
livewire-tmp/jHumBrW8YwESCbAjZWOfgR3TtX6dWfS0aaJ8GXL2.png
```

## Causa raiz — disco temporário suffixado por tenant
O Livewire faz upload em duas fases:
1. **`POST /livewire-.../upload-file`** (endpoint **global**) grava o arquivo num disco
   **temporário**. Esse endpoint tem só middleware `web` — **sem** `InitializeTenancyByPath`
   e **sem** `{tenant}` no caminho. Logo, roda **SEM tenancy**.
2. **`/update` + `salvar()`** (ciclo do componente) rodam **COM tenancy** (o
   `InitializeTenancyByPath` é persistent middleware, reaplicado no `/update`).

O disco temporário padrão do Livewire é `filesystems.default` = **`local`**, que está em
`tenancy.filesystem.disks` → é **suffixado por tenant**. Então:
- na fase 1 (sem tenancy) o temp foi gravado no disco **central**
  (`storage/app/private/livewire-tmp/…`);
- na fase 2 (com tenancy) o mesmo disco `local` aponta para
  `storage/tenant{id}/app/livewire-tmp/…`, **onde o arquivo não existe**.

Qualquer leitura de metadados do temp (tamanho/mime/validação `image`/preview) na fase 2
lança `UnableToRetrieveMetadata` → **500**. (O store em si funcionava — por isso testes com
`Storage::fake` e `->set()` passavam: não passam pelo endpoint HTTP nem pela troca de
contexto de tenancy. "Teste verde × navegador".)

## Correção — disco temporário FIXO e CENTRAL
`config/filesystems.php`: novo disco **`livewire_tmp`** (driver local, raiz
`storage_path('app')` → `storage/app/livewire-tmp/`), **propositalmente FORA** de
`tenancy.filesystem.disks` (nunca suffixado).

`config/livewire.php` (publicado): `temporary_file_upload.disk = 'livewire_tmp'`. Assim o
**mesmo caminho central** vale com ou sem tenancy → o temp gravado na fase 1 é encontrado
na fase 2. O store final (`->store('aparencia','public')`) continua no disco **`public` do
tenant** (correto). Também restringi as regras do temp a `mimes:png,jpg,jpeg,webp|max:2048`
(rejeita tipo/tamanho já no upload).

## Verificação
- **Repro do caminho que estourava** (tinker): gravar o temp SEM tenancy + ler/`getSize`/
  store final COM tenancy → agora OK (antes: `UnableToRetrieveMetadata`).
- **HTTP real:** `POST .../upload-file` → **200**, arquivo em `storage/app/livewire-tmp/`,
  **log limpo**.
- **Testes** (`AparenciaFonteUploadTest`): guard de que o disco temp é local, dedicado e
  **fora** de `tenancy.filesystem.disks`; regras restritas a imagens ≤ 2 MB; uploads
  válidos (logo/cabeçalho/fundo) gravam e são referenciados; inválidos (PDF / > 2 MB)
  rejeitados já no upload. Suíte **238 verde**.

## Lição
- **Livewire + stancl (multi-tenancy):** o endpoint global de upload roda fora do contexto
  de tenant; use um **disco temporário dedicado e central** (fora de
  `tenancy.filesystem.disks`) para o temp não "mudar de lugar" entre o upload e o
  `/update`. O arquivo final, esse sim, vai no disco do tenant.
- O 500 do upload **não** era o limite de 2 MB do PHP (essa era uma hipótese do diagnóstico
  anterior, incompleta — ver [[Bug - Aparencia (upload, fonte e campos desconectados)]]). A
  causa real só apareceu **lendo o log / reproduzindo o caminho HTTP**.
