<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/legal.php';
$legal = legalSettings($cfg);
legalHeader(
    $legal,
    'Exclusão de Dados',
    'Instruções para solicitar acesso, correção, bloqueio ou eliminação de dados pessoais tratados pelo Lis-onTech.'
);
?>
      <p class="legal-updated">Última atualização: <?= legalEscape($legal['updated']) ?></p>

      <h2>Como solicitar</h2>
      <p>
        Para solicitar a exclusão de dados vinculados ao <strong><?= legalEscape($legal['service']) ?></strong>, entre em contato
        <?= legalContactHtml($legal) ?> e escreva no início da mensagem:
      </p>
      <div class="legal-callout"><strong>Solicitação de exclusão de dados – LGPD</strong></div>

      <p>Informe somente:</p>
      <ul>
        <li>seu nome;</li>
        <li>o número de telefone utilizado nas comunicações, com DDD;</li>
        <li>se deseja excluir todos os dados elegíveis ou apenas dados específicos;</li>
        <li>uma breve descrição que permita localizar o cadastro, pedido ou atendimento.</li>
      </ul>

      <h2>Validação da identidade</h2>
      <p>
        Para proteger você contra pedidos fraudulentos, poderemos confirmar a titularidade do número ou solicitar informações adicionais estritamente necessárias.
        Não envie senha, código de autenticação, dados completos de cartão ou documento de identidade na primeira mensagem.
      </p>

      <h2>O que acontece após o pedido</h2>
      <ol>
        <li>Registraremos a solicitação e confirmaremos o recebimento pelo canal utilizado.</li>
        <li>Validaremos a identidade do solicitante e localizaremos os dados relacionados.</li>
        <li>Avaliaremos quais dados podem ser eliminados, anonimizados, corrigidos ou bloqueados.</li>
        <li>Informaremos a conclusão ou eventual justificativa legal para retenção.</li>
      </ol>
      <p>O atendimento será gratuito e observará os prazos previstos na legislação aplicável.</p>

      <h2>Dados que podem ser abrangidos</h2>
      <p>Conforme a solicitação e a elegibilidade legal, o procedimento poderá alcançar:</p>
      <ul>
        <li>cadastro de contato e telefone;</li>
        <li>histórico de atendimento e conteúdo de mensagens armazenado no sistema;</li>
        <li>mídias recebidas e cópias técnicas associadas;</li>
        <li>registros de envio, entrega, leitura e falha;</li>
        <li>associações internas com pedidos, retiradas e cobranças.</li>
      </ul>

      <h2>Exceções de retenção</h2>
      <p>
        Alguns dados poderão ser preservados quando necessários ao cumprimento de obrigação legal ou regulatória, à execução de contrato,
        à prevenção de fraude, ao exercício regular de direitos ou a outras hipóteses autorizadas pela LGPD. Nesses casos, restringiremos o uso
        às finalidades que justificam a retenção e informaremos o titular quando aplicável.
      </p>

      <h2>Dados mantidos por terceiros</h2>
      <p>
        A exclusão em nossos sistemas não elimina automaticamente informações mantidas de forma independente por Meta/WhatsApp, Vindi,
        instituições financeiras ou outros controladores. Solicitações referentes a esses serviços também poderão precisar ser feitas diretamente a eles.
      </p>

      <h2>Outros direitos</h2>
      <p>
        Pelo mesmo canal, você também pode solicitar confirmação de tratamento, acesso, correção, informação sobre compartilhamentos,
        oposição, bloqueio, anonimização ou revogação de consentimento, conforme aplicável.
      </p>

      <div class="legal-contact">
        <strong>Responsável pelo atendimento</strong>
        <?= legalEscape($legal['controller']) ?> — solicitações pelo canal de privacidade indicado nesta página.
      </div>
<?php legalFooter($legal); ?>
