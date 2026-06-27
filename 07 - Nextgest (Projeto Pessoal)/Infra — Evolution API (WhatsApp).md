---
projeto: Nextgest
tipo: infra
ambiente: dev
status: no ar (dev, fechado no localhost)
criado: 2026-06-27
tags: [nextgest, whatsapp, evolution, docker, infra, dev]
---

# Infra — Evolution API (WhatsApp)

> **Projeto SEPARADO do Nextgest** (não é código do app). Roda em Docker, **só no dev**
> (`192.168.11.210`), **fechado no localhost**. Base para o roadmap WhatsApp (Fatias 1–3).
> **Nada** disto vive em `/srv/www/nextgest` — só esta nota (doc viva).

## Onde / o quê
- **Pasta:** `/srv/www/evolution/` (`docker-compose.yml`, `.env`, `.gitignore`).
- **Imagem (tag fixa):** `evoapicloud/evolution-api:v2.3.7` — última v2 estável. (O namespace
  antigo `atendai/evolution-api` foi **descontinuado**; usar `evoapicloud/*`.)
- **Serviços (compose):**
  - `evolution-api` → publica **só** `127.0.0.1:8088:8080` (a porta 8080 já estava ocupada por
    outro projeto php; por isso 8088). Volume `evolution_instances`.
  - `postgres:16-alpine` → **sem publicar porta** (só na rede interna `evonet`). Volume
    `evolution_pgdata`. Banco/usuário `evolution`.
  - `redis:7-alpine` → **sem publicar porta** (só `evonet`). Volume `evolution_redis`.
- **Docker data-root:** `/etc/docker/daemon.json` aponta para **`/srv/docker`** (o `/` é o disco
  pequeno; `/srv` tem espaço). Imagens/volumes ficam lá.

## Segredos
- **Só** em `/srv/www/evolution/.env` (permissão `600`), **gerados no servidor** (`openssl rand`):
  `AUTHENTICATION_API_KEY` (API key global) e `POSTGRES_PASSWORD`. **Nunca** versionar nem imprimir.
  O `.gitignore` da pasta ignora o `.env`.

## Como operar (sempre em `/srv/www/evolution`)
```bash
docker compose up -d          # sobe
docker compose ps             # status (esperado: healthy)
docker compose logs evolution-api --tail=50   # logs da API
docker compose restart        # reinicia (dados persistem nos volumes)
docker compose down           # derruba (mantém volumes)
# docker compose down -v      # CUIDADO: apaga os volumes (perde sessões/dados)
```

## Uso da API (local, com a API key do `.env`)
```bash
APIKEY=$(grep '^AUTHENTICATION_API_KEY=' /srv/www/evolution/.env | cut -d= -f2-)
# criar instância + QR:
curl -s -X POST http://127.0.0.1:8088/instance/create -H "apikey: $APIKEY" \
  -H 'Content-Type: application/json' \
  -d '{"instanceName":"minha_inst","integration":"WHATSAPP-BAILEYS","qrcode":true}'
# listar instâncias:
curl -s http://127.0.0.1:8088/instance/fetchInstances -H "apikey: $APIKEY"
```

## Validação (Fase 0 desta fatia — feita)
- Containers `healthy`; API responde **HTTP 200** em `127.0.0.1:8088`.
- Instância de teste **`nextgest_teste`** criada → **QR gerado** (base64 `data:image...`).
- **Persistência:** após `docker compose restart`, a instância continua (volumes ok).
- **Fechado:** `8088` faz bind só em `127.0.0.1`; pelo IP externo (`192.168.11.210:8088`) a conexão
  é **recusada** (HTTP 000). Postgres/Redis nem publicam porta.
- **Nextgest intacto** (MySQL 3306, pasta, serviços) — não tocado.

## Consumo pelo Nextgest (Fatia 1 / D75)
O Nextgest fala com esta Evolution via `config/whatsapp.php` (lendo `EVOLUTION_BASE_URL`/
`EVOLUTION_API_KEY` do **`.env` do Nextgest** — a key **global** vive lá, nunca no banco do tenant).
Cada salão é a instância `ng_{tenantId}`. Detalhes em [[WhatsApp (Evolution) no Nextgest]].
Instâncias já criadas no dev pela integração: `nextgest_teste` (prova da Fatia 0) e `ng_barbeariateste`.

## Limites / próximas fatias
- **Só dev**, fechado no localhost. **Produção** (com exposição/segurança blindada — TLS, firewall,
  domínio) fica para depois, à parte.
- Roadmap WhatsApp no Nextgest: (1) `WhatsAppService` + driver Evolution (config por tenant, enviar
  msg de teste); (2) tela do QR no painel (gated pelo recurso `whatsapp`/plano) + monitorar sessão;
  (3) lembrete antes do horário (job agendado, opt-in, idempotente, fuso correto).
- A instância `nextgest_teste` é só prova de fumaça; pode ser apagada
  (`curl -X DELETE http://127.0.0.1:8088/instance/delete/nextgest_teste -H "apikey: $APIKEY"`).
