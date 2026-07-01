{{-- Documento ÚNICO e compartilhado por todos os tenants (D93). O conteúdo é o mesmo
     para todos; muda só o slug do tenant na URL e o nome exibido no cabeçalho. --}}
<x-portal.documento-legal titulo="Termos de Uso">
    <p>
        Estes Termos de Uso regem o acesso e o uso do portal de agendamento de
        <strong>{{ tenant('nome') }}</strong>, disponibilizado pela plataforma
        <strong>Nextgest</strong>. Ao usar o portal, você concorda com estes termos e com a
        <a href="{{ route('tenant.politica-privacidade', ['tenant' => tenant('id')]) }}" wire:navigate>Política de Privacidade</a>.
    </p>

    <h2>1. Objeto e aceitação</h2>
    <p>
        O portal permite consultar serviços, criar conta e realizar agendamentos com o
        estabelecimento. Ao acessar ou usar o portal, você declara ter lido e aceito estes
        termos. Se não concordar, não utilize o portal.
    </p>

    <h2>2. Definições</h2>
    <ul>
        <li><strong>Plataforma:</strong> a solução Nextgest que hospeda e opera o portal.</li>
        <li><strong>Estabelecimento:</strong> {{ tenant('nome') }}, que oferece os serviços e define suas políticas.</li>
        <li><strong>Usuário:</strong> a pessoa que acessa o portal ou mantém uma conta.</li>
    </ul>

    <h2>3. Cadastro e conta</h2>
    <ul>
        <li>Você se compromete a fornecer informações <strong>verdadeiras, exatas e atualizadas</strong>.</li>
        <li>A conta é <strong>pessoal e intransferível</strong>; você é responsável por manter a senha em sigilo e por toda atividade realizada na sua conta.</li>
        <li>Comunique imediatamente qualquer uso não autorizado da sua conta pelos canais de contato.</li>
    </ul>

    <h2>4. Uso permitido e condutas vedadas</h2>
    <p>Você concorda em usar o portal de forma lícita e de boa-fé. É vedado, entre outros:</p>
    <ul>
        <li>Violar leis, direitos de terceiros ou estes termos;</li>
        <li>Tentar acessar áreas ou dados sem autorização, ou comprometer a segurança e o funcionamento do portal;</li>
        <li>Inserir conteúdo falso, ofensivo ou fraudulento, ou usar o portal para fins abusivos.</li>
    </ul>

    <h2>5. Agendamentos, cancelamentos e políticas do estabelecimento</h2>
    <p>
        Os horários, serviços, prazos, políticas de cancelamento e de não comparecimento são
        definidos pelo <strong>estabelecimento</strong> e podem variar. Ao agendar, você concorda
        com as regras aplicáveis. Cancelamentos e remarcações devem respeitar essas políticas e as
        opções disponíveis no portal.
    </p>

    <h2>6. Pagamentos e assinaturas (Clube)</h2>
    <p>
        Quando houver cobrança de serviços ou planos de assinatura (por exemplo, um Clube de
        assinatura), os valores, formas de pagamento e condições serão informados no momento da
        contratação. As transações podem ser processadas por meios de pagamento de terceiros,
        sujeitos aos respectivos termos.
    </p>

    <h2>7. Comunicações</h2>
    <p>
        Poderemos enviar mensagens operacionais (confirmações, lembretes e avisos de agendamento)
        por <strong>WhatsApp</strong> e <strong>e-mail</strong>. Comunicações de marketing dependem
        do seu consentimento e você pode cancelá-las (<em>opt-out</em>) a qualquer momento, pelas
        instruções da própria mensagem ou pelos canais de contato.
    </p>

    <h2>8. Propriedade intelectual</h2>
    <p>
        A plataforma <strong>Nextgest</strong>, incluindo software, marcas, layout e demais
        elementos, é protegida por direitos de propriedade intelectual. O uso do portal não
        transfere quaisquer desses direitos. Marcas e conteúdos do estabelecimento pertencem ao
        respectivo titular.
    </p>

    <h2>9. Isenções e limitação de responsabilidade</h2>
    <p>
        O portal é oferecido "no estado em que se encontra". Na máxima extensão permitida pela lei,
        não nos responsabilizamos por indisponibilidades temporárias, por decisões e serviços
        prestados pelo estabelecimento, nem por danos decorrentes do uso indevido do portal.
    </p>

    <h2>10. Suspensão e encerramento</h2>
    <p>
        Podemos suspender ou encerrar o acesso em caso de violação destes termos, exigência legal ou
        risco à segurança. Você pode encerrar sua conta a qualquer momento, observadas as obrigações
        pendentes e a retenção de dados prevista na Política de Privacidade.
    </p>

    <h2>11. Legislação aplicável e foro</h2>
    <p>
        Estes termos são regidos pelas leis da <strong>República Federativa do Brasil</strong>. Fica
        eleito o foro do domicílio do consumidor para dirimir controvérsias, salvo disposição legal
        em contrário. [foro/comarca]
    </p>

    <h2>12. Contato</h2>
    <ul>
        <li><strong>E-mail de contato:</strong> [e-mail de contato]</li>
        <li><strong>Responsável:</strong> [responsável/estabelecimento]</li>
    </ul>
</x-portal.documento-legal>
