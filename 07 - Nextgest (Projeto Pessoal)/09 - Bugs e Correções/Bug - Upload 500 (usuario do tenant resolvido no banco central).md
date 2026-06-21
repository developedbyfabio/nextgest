---
projeto: Nextgest
tipo: bug-e-correcao
modulo: aparencia-tema
status: resolvido
criado: 2026-06-21
tags: [nextgest, bug, upload, livewire, tenancy, stancl, throttle, auth, 500]
---

# Bug — upload 500 (usuário do tenant resolvido no banco CENTRAL) — a causa real

> Projeto: [[Nextgest - Visão Geral]] · Resolvido (3ª tentativa, agora pela causa
> confirmada no log) · ver [[Bug - Upload 500 (disco temp do Livewire x tenancy)]]

## Por que as 2 correções anteriores não pegaram
As anteriores atacaram causas **reais mas secundárias** (limite de 2 MB do PHP; disco
temporário suffixado por tenant). O 500 persistia. O erro só apareceu ao **reproduzir o
fluxo EXATO do Fabio: logado no painel**. A reprodução isolada anterior dava 200 porque
**não estava autenticada** — sem usuário na sessão, nada resolvia o usuário do tenant.

## Exceção FRESCA (capturada no fluxo real, logado)
```
SQLSTATE[42S02]: Base table or view not found: 1146
Table 'nextgest_central.users' doesn't exist
(Connection: mysql, Database: nextgest_central, SQL: select * from `users` where `id` = 6 limit 1)
```
Fase: **`POST /livewire-.../upload-file` → 500** (fase 1). Stack (de baixo p/ cima):
`StartSession → ... → ThrottleRequests->resolveRequestSignature() → $request->user()
→ SessionGuard->user() → EloquentUserProvider->retrieveById(6)` → tabela inexistente.

## Causa raiz
O endpoint global `upload-file` corre no grupo `web` (logo, **com sessão**) mas **sem
tenancy** (não tem `{tenant}` no caminho e não passa pelo persistent middleware que
reinicializa o tenancy no `/update`). O **`ThrottleRequests`** monta a chave de rate-limit
com `$request->user()`. Como o Dono (id 6) está autenticado na sessão e vive no banco do
**tenant**, o `SessionGuard` tenta `retrieveById(6)` — mas, sem tenancy, a conexão padrão
é a **central** (`nextgest_central`), que **não tem tabela `users`** (são por tenant) →
`QueryException` → 500 → o front mostra "Falha no upload do arquivo logoUpload".

Por isso `/update` funciona e `upload-file` não: o `/update` reinicializa o tenancy via
persistent middleware (URL original com `{tenant}`); os controllers de arquivo
(`upload-file`/`preview-file`) ficam **fora** desse mecanismo.

## Correção
1. **Middleware `App\Http\Middleware\InicializarTenancyArquivosLivewire`**: inicializa o
   tenancy nos endpoints de arquivo, a partir do **`_tenant_sessao`** (gravado por
   `EscoparAutenticacaoPorTenant` quando o dono navega no painel) com **fallback** no 1º
   segmento do **Referer**. Uploads centrais (onboarding) ficam sem tenant — correto.
2. **Ordem (prioridade) do middleware**: o Laravel ordena por prioridade e puxava o
   `ThrottleRequests` para ANTES do nosso middleware. Em `bootstrap/app.php`:
   `$middleware->prependToPriorityList(before: ThrottleRequests::class, prepend: InicializarTenancyArquivosLivewire::class)`
   — garante tenancy ANTES do throttle (que aí resolve o usuário no banco do tenant).
3. **Wiring**: `upload-file` recebe o middleware via `config/livewire.php`
   (`temporary_file_upload.middleware`); `preview-file` via
   `FilePreviewController::$middleware` (push em `AppServiceProvider`).
4. **Mantido** o disco temporário central `livewire_tmp` (correção anterior, ainda válida)
   e a pasta por tenant: o arquivo final vai em `storage/tenant{id}/app/public/aparencia/`
   (disco `public` suffixado pelo stancl), servido por `Aparencia::urlArquivo()`.

## Limite de 5 MB e o PHP (gotcha)
A validação (app + upload temporário) é **5 MB** (`max:5120`). Mas o PHP corta antes:
`upload_max_filesize`/`post_max_size`. No `php artisan serve`, **`php -d ... artisan serve`
NÃO funciona** — o `serve` lança um `php -S` **filho** que ignora o `-d` (continua em 2 MB,
e uploads > ~2 MB voltam um **302** de redirect). O que funciona em dev:
```
PHP_INI_SCAN_DIR=":/caminho/com/uploads.ini" php artisan serve --host=0.0.0.0 --port=NNNNN
```
com `uploads.ini` contendo `upload_max_filesize=6M` e `post_max_size=8M`. Em **produção**
(php-fpm) basta ajustar o `php.ini`/pool. Sem esse ajuste, o teto real é ~2 MB.

## Prova (fluxo HTTP real, logado, no servidor)
Reproduzido com um driver que faz login → `_startUpload` → `upload-file` → `_finishUpload`
→ `salvar`:
- **24 KB**: `upload-file` 200, `_finishUpload` 200, `salvar` 200, arquivo em
  `storage/tenantbarbeariateste/app/public/aparencia/`, **log limpo**.
- **2,6 MB e 4 MB** (com o limite do PHP elevado via `PHP_INI_SCAN_DIR`): 200/200/200, log
  limpo. (Sem o ajuste do PHP, > ~2 MB dá 302.)

## Testes (mecanismo — o que o "teste verde × navegador" não pegava)
`tests/Feature/Painel/UploadTenancyTest.php`:
- a ORDEM real (com prioridade) do `upload-file` tem a tenancy-init **antes** do
  `ThrottleRequests` (via `Router::gatherRouteMiddleware`, que aplica a prioridade);
- o middleware inicializa o tenancy por `_tenant_sessao` e por Referer; e **não**
  inicializa para caminho central (admin).
Suíte: **242 verde**.

## Lição
- **Reproduzir como o usuário** (logado!) é decisivo: o mesmo endpoint dava 200 deslogado
  e 500 logado. Captar a exceção FRESCA antes de corrigir.
- Endpoints globais do Livewire (upload/preview) + auth por tenant: precisam de tenancy
  inicializada **antes** de qualquer middleware que resolva `$request->user()` (throttle).
