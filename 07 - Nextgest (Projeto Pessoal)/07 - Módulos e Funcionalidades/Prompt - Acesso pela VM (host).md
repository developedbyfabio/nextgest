# Nextgest — Prompt: acesso a partir do host (VM em 192.168.3.100)

> Cole para o Claude root do servidor em `/srv/www/nextgest`. O sistema roda numa
> VM VirtualBox com IP `192.168.3.100`; o usuário acessa pelo navegador do host e
> não consegue abrir `127.0.0.1:8000` (esse é o loopback de dentro da VM).
> Sem comandos destrutivos. Ambiente de teste em rede local (não exposto à internet).

## Objetivo
Fazer o sistema abrir no navegador do host em `http://192.168.3.100:8000`,
incluindo a rota central (`/admin`) e os tenants por caminho (`/{slug}`).

## Tarefas
1. **Reconhecer o IP como domínio central.** Adicione `192.168.3.100` à lista de
   domínios centrais do stancl (no mesmo lugar onde `127.0.0.1` e `localhost` já
   estão configurados). Sem isso, ao acessar pelo IP a resolução central/tenant
   por caminho quebra. Limpe os caches depois (`php artisan optimize:clear`).
2. **Servir em todas as interfaces.** Suba com
   `php artisan serve --host=0.0.0.0 --port=8000` (não só no loopback).
3. **Abrir a porta no firewall.** `sudo ufw allow 8000/tcp` (é um ambiente de
   teste em LAN; explique como remover depois: `sudo ufw delete allow 8000/tcp`).
4. **Verificar.** Cheque que respondem 200:
   `http://192.168.3.100:8000/`, `/admin/login` e `/barbeariateste`.
5. **Atualizar o guia.** Em `docs/GUIA-DE-TESTES.md`, troque as URLs de
   `127.0.0.1` por `192.168.3.100` (mantendo a nota de que, de dentro da VM,
   `127.0.0.1` também vale) e registre o comando de serve com `--host=0.0.0.0`.

## Observação para o usuário (host-side, fora da VM)
- Isso pressupõe que o adaptador de rede da VM no VirtualBox seja **Bridge** ou
  **Host-only** (por isso a VM já tem o IP `192.168.3.100`). Se fosse só NAT, a
  alternativa seria **port forwarding** no VirtualBox (host 8000 → guest 8000) e
  acessar por `127.0.0.1:8000` no host.

## Ao terminar
Reportar: o que mudou na config de domínios centrais, o comando exato para servir,
e confirmar os 200 nas três URLs pelo IP.
