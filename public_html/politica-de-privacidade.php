<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/legal.php';
$legal = legalSettings($cfg);
legalHeader(
    $legal,
    'Política de Privacidade',
    'Saiba como tratamos dados pessoais nas comunicações, cobranças e atendimentos realizados pelo Lis-onTech.'
);
?>
      <p class="legal-updated">Última atualização: <?= legalEscape($legal['updated']) ?></p>

      <h2>1. Quem somos</h2>
      <p>
        Esta Política descreve o tratamento de dados pessoais realizado por
        <strong><?= legalEscape($legal['controller']) ?></strong>, por meio da solução
        <strong><?= legalEscape($legal['service']) ?></strong>, disponível no domínio
        <a href="<?= legalEscape($legal['domain']) ?>"><?= legalEscape($legal['domain']) ?></a>.
        Para as operações descritas aqui, a organização responsável define as finalidades e os meios essenciais do tratamento.
      </p>

      <h2>2. Dados que podemos tratar</h2>
      <p>De acordo com a relação mantida com você, podemos tratar:</p>
      <ul>
        <li>dados de identificação e contato, como nome e número de telefone;</li>
        <li>dados de cliente, pedido e retirada, incluindo número do pedido e código de retirada;</li>
        <li>dados de cobrança, como identificador da fatura, valor, vencimento, situação do pagamento, itens e link de pagamento;</li>
        <li>conteúdo e metadados de mensagens trocadas pelo WhatsApp, inclusive mídias enviadas voluntariamente;</li>
        <li>confirmações técnicas de envio, entrega, leitura ou falha das mensagens;</li>
        <li>registros técnicos necessários à segurança e ao diagnóstico do serviço.</li>
      </ul>
      <p>Não solicitamos senhas, códigos de autenticação ou dados completos de cartão por mensagens do WhatsApp.</p>

      <h2>3. Como usamos os dados</h2>
      <p>Os dados são utilizados para:</p>
      <ul>
        <li>enviar faturas, lembretes de vencimento e comunicações relacionadas a cobranças legítimas;</li>
        <li>informar o recebimento, a disponibilidade para retirada e a conclusão de pedidos;</li>
        <li>permitir atendimento e resposta a mensagens iniciadas pelo titular;</li>
        <li>confirmar a entrega das comunicações e investigar falhas;</li>
        <li>proteger o serviço contra uso indevido, fraude e incidentes de segurança;</li>
        <li>cumprir contratos, obrigações legais ou regulatórias e exercer direitos em processos.</li>
      </ul>

      <h2>4. Bases legais</h2>
      <p>
        O tratamento poderá ser fundamentado, conforme o caso, na execução de contrato ou de procedimentos relacionados ao contrato,
        no cumprimento de obrigação legal ou regulatória, no exercício regular de direitos, no legítimo interesse com avaliação dos
        direitos do titular e no consentimento, quando essa for a base adequada. O consentimento poderá ser revogado pelos canais informados nesta Política.
      </p>

      <h2>5. Compartilhamento e operadores</h2>
      <p>Podemos compartilhar dados estritamente necessários com fornecedores que apoiam a operação, incluindo:</p>
      <ul>
        <li><strong>Meta/WhatsApp</strong>, para envio e recebimento de mensagens pela Plataforma do WhatsApp Business;</li>
        <li><strong>Vindi</strong>, para consulta e gestão das informações de cobrança;</li>
        <li>provedores de hospedagem, banco de dados, segurança e suporte técnico;</li>
        <li>autoridades públicas, quando houver obrigação legal ou ordem válida.</li>
      </ul>
      <p>Não comercializamos dados pessoais. Cada fornecedor também poderá tratar dados segundo seus próprios termos e políticas aplicáveis.</p>

      <h2>6. Transferências internacionais</h2>
      <p>
        Alguns fornecedores, especialmente a Meta, podem armazenar ou processar informações em outros países. Quando aplicável,
        adotamos medidas compatíveis com a legislação brasileira para proteger os dados nessas transferências.
      </p>

      <h2>7. Retenção e eliminação</h2>
      <p>
        Mantemos os dados pelo período necessário às finalidades descritas, ao cumprimento de obrigações legais, à prevenção de fraude
        e ao exercício regular de direitos. Após esse período, os dados serão eliminados ou anonimizados, salvo quando a conservação for autorizada ou exigida por lei.
      </p>
      <p>As instruções para solicitar exclusão estão em <a href="/exclusao-de-dados.php">Exclusão de Dados</a>.</p>

      <h2>8. Segurança</h2>
      <p>
        Empregamos controles técnicos e administrativos razoáveis para reduzir riscos de acesso não autorizado, perda, alteração ou divulgação indevida.
        Nenhum sistema é absolutamente infalível; por isso, revisamos medidas e limitamos acessos de acordo com a necessidade operacional.
      </p>

      <h2>9. Cookies e tecnologias semelhantes</h2>
      <p>
        As páginas jurídicas não utilizam cookies publicitários. Áreas autenticadas do sistema podem utilizar cookies estritamente necessários
        para manter a sessão, autenticar o usuário e proteger o acesso.
      </p>

      <h2>10. Seus direitos</h2>
      <p>Nos termos da LGPD, você pode solicitar, quando aplicável:</p>
      <ul>
        <li>confirmação e acesso aos dados;</li>
        <li>correção de dados incompletos, inexatos ou desatualizados;</li>
        <li>informação sobre compartilhamentos;</li>
        <li>anonimização, bloqueio ou eliminação de dados desnecessários ou tratados em desconformidade;</li>
        <li>portabilidade, observada a regulamentação aplicável;</li>
        <li>revogação do consentimento e informação sobre suas consequências;</li>
        <li>oposição ao tratamento nas hipóteses previstas em lei;</li>
        <li>revisão de decisões tomadas unicamente com base em tratamento automatizado, quando aplicável.</li>
      </ul>

      <h2>11. Atendimento ao titular</h2>
      <div class="legal-contact">
        <strong>Canal de privacidade</strong>
        Solicitações podem ser feitas <?= legalContactHtml($legal) ?>. Informe que o assunto é “Privacidade/LGPD” e forneça apenas os dados necessários para localizar e validar sua solicitação.
      </div>

      <h2>12. Atualizações</h2>
      <p>
        Esta Política poderá ser atualizada para refletir mudanças legais, técnicas ou operacionais. A versão vigente permanecerá publicada nesta página com a data da última atualização.
      </p>
<?php legalFooter($legal); ?>
