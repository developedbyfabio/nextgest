# Nextgest — Prompt: corrigir CSS das rotas de tenant + criar tenants no /admin

> Cole para o Claude root do servidor em `/srv/www/nextgest`. Dois problemas
> distintos. Sem comandos destrutivos; manter os 74 testes verdes; seguir o
> padrão de UI/UX (D27). Ver [[Gotchas e Aprendizados do Projeto]].

---

## Problema A — CSS/JS não carrega nas rotas de tenant (bug)
Sintoma observado no navegador (host acessando `http://192.168.3.100:8000`):
- `/admin/login` (rota **central**) renderiza **com estilo correto**.
- `/barbeariateste` (portal) e `/barbeariateste/painel/login` (painel)
  renderizam **sem nenhum CSS/JS** — o logo aparece gigante e cru. Os assets
  não carregam **apenas** nas rotas com o `/{slug}` no caminho.

### Diagnóstico (faça primeiro)
Abra uma página de tenant e veja, no DevTools → Network, qual URL de CSS/JS está
dando 404 ou erro. Provas prováveis:
- URL do asset saindo **relativa ao caminho do tenant** (ex.:
  `/barbeariateste/painel/build/...`) em vez de root-absoluta (`/build/...`); ou
- URL apontando para `APP_URL` (`https://nextgest.com.br/build/...`), que não
  resolve nesta rede/sem SSL; ou
- o layout das páginas de tenant não inclui o `@vite` igual ao layout central; ou
- as requisições de `/build/...` em contexto de tenant sendo capturadas pelo
  catch-all de tenant em vez de servir o arquivo estático.

### Correção
Garanta que `@vite`/`asset()` gere URLs **root-absolutas e no host atual** tanto
na área central quanto na de tenant — assets servidos de `/build/...`
independentemente do caminho do tenant. Para o ambiente de teste, evite que o
`APP_URL=https://nextgest.com.br` force o host/https dos assets (use URL relativa
à raiz, ou ajuste `ASSET_URL`/config de Vite conforme o caso). A regra: a página
de tenant deve carregar os mesmos assets que a página central.

### Verificação
- `/barbeariateste` e `/barbeariateste/painel/login` passam a renderizar
  **estilizados**, iguais ao `/admin/login`.
- Network sem 404 de asset. Testes seguem verdes.

---

## Problema B — Criar e listar tenants no painel /admin
Hoje o super-admin (`/admin`) só mostra um total; não dá para criar
estabelecimentos pela interface (vinha por tinker). Adicione uma gestão mínima de
tenants no painel central, seguindo o padrão de UI/UX:
1. **Listar** tenants (nome, slug, status ativo, data) com busca e paginação.
2. **Criar** tenant (nome + slug; valida slug único, formato e contra a lista de
   slugs reservados) — cria o tenant e dispara a criação do banco/migrations/seed
   como já acontece no fluxo normal.
3. **Ativar/inativar** tenant (sem excluir banco; nada destrutivo).
4. (Opcional, útil) Ação para **criar o Dono** inicial daquele tenant (nome,
   e-mail, senha) reaproveitando a lógica do `nextgest:criar-dono`.
5. Link/atalho para abrir o estabelecimento (`/{slug}`).
> É a versão mínima do que será a Fatia 8 (painel super-admin completo). Manter
> simples, mas no padrão visual (tabela, modal de criar, toasts, estados).

## Restrições
- Sem comandos destrutivos autônomos; inativar ≠ excluir banco.
- Permissão: só o super-admin (guard `admin`) acessa.
- Testes (Pest) para criar/listar/inativar tenant e validação de slug. Suíte
  toda verde.

## Ao terminar
Reportar: a causa real do bug de assets e o que mudou; confirmação de que portal e
painel de tenant agora carregam estilizados; e como criar um tenant pelo /admin
(com print/descrição do fluxo).

## Nota (não bloqueia)
A rota central `/` ainda mostra a página padrão do Laravel (imagem 1). É só um
placeholder; podemos trocar por uma landing simples da marca depois — não faz
parte deste prompt.
