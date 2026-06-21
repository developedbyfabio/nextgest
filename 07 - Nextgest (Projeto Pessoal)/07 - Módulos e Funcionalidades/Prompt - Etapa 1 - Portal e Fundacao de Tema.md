# Nextgest — Prompt (Etapa 1): análise + portal do cliente + fundação de tema

> Cole para o Claude root em `/srv/www/nextgest`. Ambiente local (VM, http).
> Trabalhe a fundo, com calma. Sem comandos destrutivos. **Não quebre o que já
> funciona**: mantenha os 91 testes + smoke verdes. Siga o padrão de UI/UX (D27).
> Esta é a **Etapa 1** de uma evolução visual planejada — implemente só o que está
> em "Parte 1". O onboarding guiado, templates e dashboard do dono são próximos
> prompts; **não os construa agora**. Ver [[Decisões de Arquitetura]] (D28–D31).

---

## Parte 0 — Análise completa e plano (entregar primeiro)
Antes de codar, faça um levantamento do estado atual cobrindo **todo** o escopo da
evolução pedida (portal do cliente; identidade visual por estabelecimento;
onboarding guiado com prévia ao vivo; templates visuais; dashboard do dono com
gráficos/indicadores; kanban; áreas de gestão). Para cada frente, classifique:
1. o que já existe; 2. o que está bugado; 3. o que está incompleto; 4. o que
precisa refatorar; 5. o que criar do zero; 6. a melhor ordem de implementação.
Salve em `docs/ANALISE-E-PLANO.md` e resuma no relatório. **Aguarde** — não avance
para as etapas 2+ neste prompt; só execute a Parte 1.

## Parte 1 — Implementar agora

### 1A. Corrigir os bugs visuais do portal do cliente
Sintomas observados (mobile, http://192.168.3.100:8000/{slug} e /{slug}/agendar):
- **Textos somem**: no card de serviço aparece só "20 min · R$ 30,00" sem o
  **nome do serviço**; na etapa de profissional aparece o avatar (ex.: "AN") sem o
  **nome**; há blocos brancos/sem conteúdo visível.
- Baixo contraste em itens como "Clube de assinatura / Em breve".

Diagnostique a causa real (inspecione o *computed style* dos elementos de nome:
provável cor de texto saindo igual ao fundo, token de cor não definido no contexto
do portal, ou classe de cor ausente no componente de card). Corrija de modo que:
- nome do serviço e do profissional apareçam com contraste correto (claro/escuro);
- nenhum bloco fique branco/vazio sem motivo;
- o portal (home, agendar, próximos, login/registrar) fique consistente, completo
  e bonito, mobile-first.

### 1B. Fundação de identidade visual por estabelecimento (tema via CSS variables)
Implemente a base que as próximas etapas (templates, onboarding com prévia) vão
usar — ver decisão D28:
- **Armazenamento por tenant**: guarde a aparência no banco do tenant (em
  `configuracoes`, como JSON, ou tabela `aparencia` — escolha e justifique). Campos
  iniciais: cor principal, secundária, de fundo, de texto; fonte e tamanho base;
  (deixe ganchos para logo/imagens de header/fundo, posição de menu e estilo de
  ícone, a serem usados nas próximas etapas).
- **Aplicação em runtime**: o layout do portal (e, quando fizer sentido, do painel)
  injeta esses valores como **CSS custom properties** (`--cor-principal`, etc.) num
  escopo do tenant; os componentes passam a usar essas variáveis. Nada de CSS por
  tenant compilado.
- **Defaults sensatos**: um tema padrão para que todo tenant já nasça com aparência
  finalizada (e o portal pare de parecer "cru").
- **Reaproveitável**: centralize num helper/serviço + componente de layout, para
  templates e a prévia ao vivo só trocarem as variáveis depois.

> Não construa ainda a tela de edição de tema, o wizard nem os templates — só a
> fundação + defaults aplicados. (Se ajudar a testar, pode semear o tema padrão no
> `nextgest:demo`.)

## Restrições e qualidade
- Mantenha tudo reutilizável, organizado e escalável; sem gambiarra.
- Não quebre rotas/logins/agenda já funcionando; suíte (91 + smoke) verde.
- Adicione teste(s) de que o tema é aplicado (variáveis presentes no HTML do portal)
  e de que o portal renderiza nomes de serviço/profissional.

## Relatório final
- O `docs/ANALISE-E-PLANO.md` resumido (estado atual + ordem proposta das etapas).
- Causa real dos textos invisíveis e o que mudou no portal (antes/depois).
- Onde a aparência é guardada e como é aplicada (variáveis), e o tema padrão.
- Arquivos criados/alterados e testes adicionados.
