{{-- Documento ÚNICO e compartilhado por todos os tenants (D93). O conteúdo é o mesmo
     para todos; muda só o slug do tenant na URL e o nome exibido no cabeçalho. --}}
<x-portal.documento-legal titulo="Política de Privacidade">
    <p>
        Esta Política de Privacidade explica como os dados pessoais são tratados quando você
        usa o portal de agendamento de <strong>{{ tenant('nome') }}</strong>, disponibilizado
        por meio da plataforma <strong>Nextgest</strong>. Ela se aplica a clientes e visitantes
        do portal e deve ser lida em conjunto com os
        <a href="{{ route('tenant.termos-uso', ['tenant' => tenant('id')]) }}" wire:navigate>Termos de Uso</a>.
    </p>

    <h2>1. Introdução e a quem se aplica</h2>
    <p>
        Levamos a sua privacidade a sério e tratamos dados pessoais de acordo com a Lei nº
        13.709/2018 (Lei Geral de Proteção de Dados Pessoais — LGPD). Esta política aplica-se a
        qualquer pessoa que acesse o portal, crie uma conta ou realize agendamentos.
    </p>
    <p>
        De modo geral, o <strong>estabelecimento</strong> ({{ tenant('nome') }}) atua como
        <strong>controlador</strong> dos dados dos seus clientes, decidindo por que e como eles
        são tratados; a <strong>Nextgest</strong> atua como <strong>operadora</strong>, fornecendo
        a plataforma e tratando os dados conforme as instruções do estabelecimento, além de ser
        controladora dos dados estritamente necessários à operação da própria plataforma.
    </p>

    <h2>2. Definições (LGPD)</h2>
    <ul>
        <li><strong>Titular:</strong> a pessoa natural a quem os dados pessoais se referem (você).</li>
        <li><strong>Dado pessoal:</strong> informação relacionada a pessoa natural identificada ou identificável.</li>
        <li><strong>Controlador:</strong> quem decide sobre o tratamento dos dados pessoais.</li>
        <li><strong>Operador:</strong> quem trata os dados em nome do controlador.</li>
        <li><strong>Tratamento:</strong> toda operação com dados pessoais (coleta, uso, armazenamento, eliminação etc.).</li>
    </ul>

    <h2>3. Dados que coletamos</h2>
    <ul>
        <li><strong>Cadastro:</strong> nome, e-mail, telefone e <strong>CPF</strong> informados ao criar a conta.</li>
        <li><strong>Agendamentos:</strong> serviços escolhidos, datas, horários, histórico e eventuais observações.</li>
        <li><strong>Dados de uso:</strong> informações técnicas do acesso, como endereço IP, tipo de dispositivo e navegador, e registros de atividade (logs).</li>
        <li><strong>Cookies:</strong> identificadores necessários para manter a sessão e o funcionamento do portal (ver seção 6).</li>
    </ul>

    <h2>4. Finalidades e bases legais</h2>
    <p>Tratamos dados pessoais para as seguintes finalidades e bases legais:</p>
    <ul>
        <li><strong>Execução de contrato:</strong> criar e manter sua conta, permitir e gerenciar agendamentos e prestar o serviço solicitado.</li>
        <li><strong>Consentimento:</strong> envio de comunicações de marketing e promoções, quando você opta por recebê-las.</li>
        <li><strong>Legítimo interesse:</strong> segurança, prevenção a fraudes, melhoria do serviço e comunicações operacionais (por exemplo, confirmações e lembretes de agendamento). O <strong>CPF</strong> é usado para <strong>identificar você com segurança</strong> e <strong>evitar cadastros duplicados</strong> (antifraude).</li>
        <li><strong>Cumprimento de obrigação legal ou regulatória:</strong> quando exigido por lei.</li>
    </ul>

    <h2>5. Compartilhamento de dados</h2>
    <p>Podemos compartilhar dados pessoais, na medida do necessário, com:</p>
    <ul>
        <li><strong>Fornecedores de infraestrutura e hospedagem</strong> que operam a plataforma;</li>
        <li><strong>Meios de pagamento</strong>, para processar cobranças e assinaturas, quando aplicável;</li>
        <li><strong>Serviços de comunicação</strong>, para envio de mensagens por WhatsApp e e-mail;</li>
        <li><strong>Autoridades competentes</strong>, para cumprimento de obrigação legal ou ordem judicial.</li>
    </ul>
    <p>Não vendemos seus dados pessoais.</p>

    <h2>6. Cookies e tecnologias semelhantes</h2>
    <p>
        Utilizamos cookies e tecnologias semelhantes necessários para autenticar a sessão, lembrar
        preferências e manter o portal funcionando com segurança. A maioria dos navegadores permite
        bloquear ou remover cookies; note que desativá-los pode afetar o funcionamento do portal.
    </p>

    <h2>7. Retenção e eliminação de dados</h2>
    <p>
        Mantemos os dados pessoais apenas pelo tempo necessário às finalidades desta política ou ao
        cumprimento de obrigações legais. Encerrado esse período, os dados são eliminados ou
        anonimizados, salvo hipóteses de guarda autorizadas ou exigidas por lei.
    </p>

    <h2>8. Segurança da informação</h2>
    <p>
        Adotamos medidas técnicas e organizacionais para proteger os dados pessoais contra acessos
        não autorizados e situações de destruição, perda, alteração ou comunicação indevidas.
        Nenhum sistema é totalmente imune a riscos; por isso, mantenha sua senha em sigilo.
    </p>

    <h2>9. Direitos do titular</h2>
    <p>Nos termos do art. 18 da LGPD, você pode solicitar a qualquer momento:</p>
    <ul>
        <li>Confirmação da existência de tratamento e <strong>acesso</strong> aos dados;</li>
        <li><strong>Correção</strong> de dados incompletos, inexatos ou desatualizados;</li>
        <li><strong>Anonimização, bloqueio ou eliminação</strong> de dados desnecessários ou tratados em desconformidade;</li>
        <li><strong>Portabilidade</strong> dos dados a outro fornecedor, mediante requisição;</li>
        <li><strong>Revogação do consentimento</strong> e <strong>oposição</strong> a tratamentos, nos casos previstos em lei.</li>
    </ul>
    <p>Para exercer seus direitos, utilize o contato indicado na seção 12.</p>

    <h2>10. Menores de idade</h2>
    <p>
        O portal não se destina a menores sem a devida assistência ou representação dos responsáveis
        legais. O tratamento de dados de crianças e adolescentes observa o seu melhor interesse, na
        forma da LGPD.
    </p>

    <h2>11. Alterações desta política</h2>
    <p>
        Esta política pode ser atualizada a qualquer momento. A versão vigente e a data de atualização
        são indicadas no topo desta página. Mudanças relevantes poderão ser comunicadas pelos canais
        do portal.
    </p>

    <h2>12. Contato e encarregado (DPO)</h2>
    <p>
        Para dúvidas sobre esta política ou para exercer seus direitos, fale com o encarregado pelo
        tratamento de dados:
    </p>
    <ul>
        <li><strong>Encarregado (DPO):</strong> [encarregado/DPO]</li>
        <li><strong>E-mail de contato:</strong> [e-mail de contato]</li>
        <li><strong>Endereço:</strong> [endereço]</li>
    </ul>
</x-portal.documento-legal>
