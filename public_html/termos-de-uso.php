<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/legal.php';
$legal = legalSettings($cfg);
legalHeader(
    $legal,
    'Termos de Uso',
    'Regras para utilização do Lis-onTech e para as comunicações automatizadas relacionadas a atendimento, pedidos e cobranças.'
);
?>
      <p class="legal-updated">Última atualização: <?= legalEscape($legal['updated']) ?></p>

      <h2>1. Aceitação</h2>
      <p>
        Estes Termos regulam o uso do <strong><?= legalEscape($legal['service']) ?></strong>, solução operada por
        <strong><?= legalEscape($legal['controller']) ?></strong>. Ao acessar o serviço ou interagir com suas comunicações,
        você declara que leu e compreendeu estes Termos e a <a href="/politica-de-privacidade.php">Política de Privacidade</a>.
      </p>

      <h2>2. Finalidade do serviço</h2>
      <p>O serviço apoia atividades empresariais como:</p>
      <ul>
        <li>envio de faturas e lembretes relacionados a pagamentos;</li>
        <li>comunicações sobre pedidos e retiradas;</li>
        <li>atendimento por WhatsApp;</li>
        <li>registro técnico de mensagens, entregas e falhas;</li>
        <li>integração com plataformas de comunicação e cobrança.</li>
      </ul>
      <p>As mensagens não substituem documentos fiscais, contratos, comprovantes de pagamento ou informações oficiais do fornecedor responsável pela compra ou cobrança.</p>

      <h2>3. Uso adequado</h2>
      <p>Você concorda em:</p>
      <ul>
        <li>fornecer informações verdadeiras e manter seus dados de contato atualizados;</li>
        <li>não utilizar o serviço para fraude, assédio, envio de conteúdo ilícito ou violação de direitos de terceiros;</li>
        <li>não tentar acessar áreas restritas, interferir na operação ou explorar vulnerabilidades;</li>
        <li>não compartilhar links, códigos de retirada ou informações de cobrança com pessoas não autorizadas.</li>
      </ul>

      <h2>4. Comunicações pelo WhatsApp</h2>
      <p>
        As comunicações poderão incluir mensagens transacionais, lembretes e respostas de atendimento. O recebimento e a entrega dependem
        da disponibilidade do WhatsApp, da validade do número informado e das regras da plataforma. Você pode solicitar atualização do número
        ou interrupção de comunicações não obrigatórias pelo mesmo canal em que recebeu a mensagem.
      </p>

      <h2>5. Serviços de terceiros</h2>
      <p>
        O serviço utiliza recursos de terceiros, incluindo Meta/WhatsApp e Vindi. Falhas, indisponibilidades ou alterações nesses serviços
        podem afetar temporariamente determinadas funções. O uso dessas plataformas também está sujeito aos termos e políticas dos respectivos fornecedores.
      </p>

      <h2>6. Disponibilidade e alterações</h2>
      <p>
        Buscamos manter o serviço disponível e seguro, mas não garantimos operação ininterrupta. Poderemos realizar manutenções, corrigir erros,
        alterar funcionalidades ou suspender acessos quando necessário à segurança, à conformidade legal ou à continuidade operacional.
      </p>

      <h2>7. Propriedade intelectual</h2>
      <p>
        A estrutura, o código, as marcas, os elementos visuais e os conteúdos próprios do serviço são protegidos pela legislação aplicável.
        Estes Termos não transferem ao usuário direitos de propriedade intelectual, salvo autorização expressa.
      </p>

      <h2>8. Responsabilidades</h2>
      <p>
        Cada parte responde pelos atos que praticar e pelos danos que causar nos limites da legislação. Não nos responsabilizamos por informações
        incorretas fornecidas pelo usuário, uso indevido de códigos ou links, nem por eventos atribuíveis exclusivamente a terceiros ou a caso fortuito ou força maior.
        Nada nestes Termos exclui direitos ou responsabilidades que não possam ser afastados por lei.
      </p>

      <h2>9. Proteção de dados</h2>
      <p>
        O tratamento de dados pessoais é descrito na <a href="/politica-de-privacidade.php">Política de Privacidade</a>.
        Solicitações de eliminação podem ser apresentadas conforme a página de <a href="/exclusao-de-dados.php">Exclusão de Dados</a>.
      </p>

      <h2>10. Suspensão e encerramento</h2>
      <p>
        O acesso poderá ser limitado ou encerrado em caso de violação destes Termos, risco à segurança, determinação legal ou descontinuação do serviço,
        preservadas as obrigações pendentes e os direitos adquiridos conforme a lei.
      </p>

      <h2>11. Lei aplicável</h2>
      <p>
        Estes Termos são regidos pelas leis brasileiras. Eventuais conflitos serão tratados pelos meios e foros competentes previstos na legislação,
        respeitados os direitos do consumidor quando aplicáveis.
      </p>

      <h2>12. Contato</h2>
      <div class="legal-contact">
        <strong>Canal de atendimento</strong>
        Dúvidas sobre estes Termos podem ser encaminhadas <?= legalContactHtml($legal) ?>.
      </div>
<?php legalFooter($legal); ?>
