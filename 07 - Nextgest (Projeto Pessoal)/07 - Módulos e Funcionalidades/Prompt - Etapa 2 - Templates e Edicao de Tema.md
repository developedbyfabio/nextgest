# Nextgest — Prompt (Etapa 2): templates + edição de tema + prévia ao vivo

> Cole para o Claude root em `/srv/www/nextgest`. Ambiente local (VM, http).
> Trabalhe a fundo, com calma. Sem comandos destrutivos. **Não quebre o que já
> funciona** (93 testes + smoke verdes). Padrão de UI/UX (D27). Reaproveite a
> fundação `App\Support\Aparencia` da Etapa 1 — **não reimplemente o tema**.
> Esta é a Etapa 2; o onboarding guiado é a Etapa 3 — **não o construa agora**.
> Ver [[Identidade Visual do Estabelecimento (Tema)]] e [[Decisões de Arquitetura]] (D30).

---

## 2A. Templates de aparência (presets)
Crie um conjunto de **presets** dos campos de `Aparencia` (D30), definidos em
código/config (não por tenant): **barbearia, salão feminino, salão masculino,
neutro, premium, moderno, minimalista**. Cada preset traz cores (principal,
secundária, fundo, superfície, texto), fonte e tamanho base e, quando fizer
sentido, posição de menu e estilo de ícone.
- Aplicar um template = **copiar** os valores do preset para o
  `configuracoes.aparencia` do tenant (continua 100% editável depois).
- Centralize os presets de forma reutilizável (ex.: `Aparencia::TEMPLATES` ou um
  `TemplatesAparencia`), para o onboarding (Etapa 3) usar os mesmos.

## 2B. Tela de edição de identidade visual (dono)
No painel do tenant, uma área **"Aparência / Identidade visual"** (permissão de
configuração — Dono/Gerente) para o dono ajustar o tema:
- Escolher um **template** como ponto de partida (mostrar os presets).
- Editar: cores (color pickers), fonte, tamanho base; posição de menu; estilo de
  ícone. **Salvar** persiste via `Aparencia::salvar()`.
- **Uploads** (logo, imagem/cor de header, imagem de fundo): aceite os arquivos no
  **disco do tenant** e gere as URLs com **`tenant_asset()`** (atenção ao gotcha de
  assets de tenant). Se preferir, entregue os uploads num commit próprio, focado.
- Estados de loading/sucesso/erro; tudo no padrão `x-ng.*`/Flux.

## 2C. Prévia ao vivo (componente REUTILIZÁVEL)
Construa um componente de **prévia do portal do cliente** que reflete, em tempo
real, as escolhas de tema enquanto o dono edita (reescrevendo as CSS vars — sem
recarregar). Pode ser um `<iframe>` do portal ou um painel de preview
autocontido que renderiza um recorte fiel (cabeçalho + card de serviço + botão
primário) usando as mesmas variáveis.
- **Requisito-chave:** este componente precisa ser **reaproveitável**, porque o
  onboarding da Etapa 3 vai usá-lo do lado do super-admin. Isole-o bem (props:
  recebe o conjunto de aparência e renderiza).

## Restrições e qualidade
- Reutilizável, organizado, escalável; sem gambiarra. Reusar `Aparencia`.
- Não mexer em regra de agendamento, login, agenda. Suíte (93 + smoke) verde.
- Sem segredos no código. Uploads no disco do tenant com `tenant_asset()`.
- Testes: aplicar template grava as vars esperadas; editar/salvar persiste; a
  prévia renderiza com as variáveis aplicadas.

## Relatório final
- Os templates criados (lista) e onde ficam os presets.
- A tela de edição (o que dá para ajustar) e como salvar.
- O componente de prévia ao vivo e **como ele será reusado no onboarding**.
- Como tratou os uploads (disco do tenant / `tenant_asset()`).
- Arquivos criados/alterados, testes adicionados e próximos passos.
