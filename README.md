# Lis-onTech

Core privado do Lis'on ERP para automacao de cobrancas e mensagens via WhatsApp. Stack principal: PHP, MySQL e JavaScript.

## Estrutura

- `public_html/`: endpoints publicos, webhook e redirecionamento para o painel.
- `public_html/includes/`: configuracao, conexao com banco e helpers internos.
- `public_html/painel/`: roteador, paginas e APIs do painel administrativo.
- `public_html/assets/`: logos e imagens usados pelo painel.
- `public_html/storage/logs/`: logs gerados em runtime, ignorados pelo Git.
- `secure/`: configuracao local sensivel fora do versionamento.

## Configuracao local

Copie `secure/config.env.example` para `secure/config.env` no ambiente real e preencha os valores. Nunca versione `secure/config.env` ou logs.

## Paginas juridicas publicas

- `/termos-de-uso.php`
- `/politica-de-privacidade.php`
- `/exclusao-de-dados.php`

Defina `LEGAL_CONTROLLER_NAME` e, de preferencia, um e-mail monitorado em `LEGAL_CONTACT_EMAIL` no `secure/config.env` do ambiente real.

## Recobranca automatica

A recobranca automatica roda pelo endpoint:

```text
/painel/api/cron_recobranca.php?token=SEU_CRON_TOKEN
```

Configure no `secure/config.env`:

```env
CRON_TOKEN=um_token_grande_e_secreto
RECOBRANCA_FIRST_DELAY_DAYS=7
RECOBRANCA_INTERVAL_DAYS=7
RECOBRANCA_MAX_OVERDUE=12
```

Com isso, uma fatura entra na fila quando chega a 7 dias de atraso. Depois de enviada, a proxima recobranca fica agendada para 7 dias depois, enquanto a fatura continuar aberta e nao bloqueada.

Exemplo de cron em servidor Linux/cPanel, rodando a cada hora:

```cron
0 * * * * curl -fsS "https://seu-dominio.com/painel/api/cron_recobranca.php?token=SEU_CRON_TOKEN" >/dev/null 2>&1
```

Para testar sem enviar WhatsApp:

```text
https://seu-dominio.com/painel/api/cron_recobranca.php?token=SEU_CRON_TOKEN&dry_run=1&limit=5
```

## Marcacao automatica no SimplesVet

O processo e independente do site: roda na VPS, consulta diretamente na Vindi
e guarda o estado em um banco SQLite privado na propria VPS. Ele identifica
clientes com pelo menos uma fatura pendente
vencida ha 30 dias ou mais. Ele enfileira a inclusao da marcacao
`CONSULTAR GERENCIA`. A remocao so e enfileirada quando nao resta nenhuma
fatura nessa condicao e apenas para marcacoes que o proprio processo confirmou.

O CPF nao e salvo no SQLite: o worker o consulta na Vindi no momento de
pesquisar o responsavel no SimplesVet.

Na VPS, instale o worker uma vez:

```bash
cd /opt/lisontech-simplesvet/rpa
# Requer Node.js 20 ou superior
npm install
npm run install-browser
chmod +x ../scripts/simplesvet_daily.sh
```

Preencha as variaveis `SIMPLESVET_*` de `secure/config.env`, principalmente
`SIMPLESVET_USER`, `SIMPLESVET_PASSWORD`, a URL de pesquisa de responsaveis e
os seletores da tela. `SIMPLESVET_REPORT_URL` pode apontar para vendas, mas nao
e usada nesta automacao. Depois teste manualmente:

```bash
/opt/lisontech-simplesvet/scripts/simplesvet_daily.sh
```

Para verificar pagamentos e atualizar as marcacoes 12 vezes por dia, use `crontab -e`:

```cron
CRON_TZ=America/Sao_Paulo
0 */2 * * * /usr/bin/flock -n /tmp/lisontech-simplesvet.lock /opt/lisontech-simplesvet/scripts/simplesvet_daily.sh >> /var/log/lisontech-simplesvet.log 2>&1
```

Os caminhos devem ser ajustados ao diretorio real da aplicacao na VPS.

Como alternativa, o instalador abaixo detecta o caminho atual e adiciona ou
atualiza o bloco do cron sem duplica-lo:

```bash
chmod +x /opt/lisontech-simplesvet/scripts/install_simplesvet_cron.sh
/opt/lisontech-simplesvet/scripts/install_simplesvet_cron.sh
```

## Vendas pagas da Vindi no SimplesVet

Ao receber o webhook `bill_paid`, o Lis-onTech cria uma tarefa unica por fatura.
O worker `rpa/simplesvet-sales-worker.js` consulta essa fila, localiza o cliente
no SimplesVet pelo CPF da Vindi, cria a venda com os itens da fatura e registra
o recebimento. O andamento fica disponivel em **Realizados > Baixas SV**.

Para recuperar pagamentos feitos antes da ativacao do webhook, use
**Conciliacao de planos > Importar pagos de hoje**. A consulta considera o dia
de Sao Paulo e pode ser repetida: `bill_id` e unico, portanto uma fatura que ja
entrou pelo webhook nao gera uma segunda tarefa.

Antes de ativar, configure os campos `SIMPLESVET_SALE_*` do
`secure/config.env`, principalmente a URL do PDV, seletores de cliente/produto,
forma de recebimento, caixa e confirmacao de sucesso. Valide primeiro com
`SIMPLESVET_HEADLESS=0` e depois habilite:

```env
SIMPLESVET_SALES_ENABLED=1
```

Use `SIMPLESVET_SALES_USER`, `SIMPLESVET_SALES_PASSWORD` e
`SIMPLESVET_SALES_UNIT_NAME` para a conta exclusiva do bot. Essas credenciais
ficam separadas da conta usada pelo sincronizador de marcacoes. O worker de
vendas nao inicia se a conta exclusiva nao estiver configurada.

Cada venda recebe obrigatoriamente a observacao `VINDI #<bill_id>`. Antes de
salvar, o worker compara o total montado no SimplesVet com o total dos itens
vinculados da Vindi. Logo depois que o SimplesVet cria o codigo da venda, o
worker grava um checkpoint no Lis-onTech. Se houver queda antes da baixa, a
tarefa fica em **Venda criada / baixa pendente** e nao volta automaticamente
para a criacao, evitando uma segunda venda.

Teste manualmente com `npm run sales` na pasta `rpa`. O instalador do cron
executa o worker a cada cinco minutos. Falhas anteriores a confirmacao entram
em nova tentativa; uma falha depois do clique final vai para revisao manual,
evitando que uma venda potencialmente concluida seja criada novamente.

Pagamentos simultaneos sao gravados individualmente na fila. O worker reserva
e finaliza uma unica venda por vez, em ordem de pagamento, e o `flock` do cron
impede duas instancias de operarem o PDV ao mesmo tempo. As proximas tarefas
permanecem aguardando sem bloquear o webhook da Vindi.

Na pagina **Conciliacao de planos**, cada produto da Vindi deve ser vinculado
ao codigo correspondente no SimplesVet ou marcado como **Nao usar**. Produtos
novos ficam pendentes e seguram o pagamento na fila ate que sejam classificados.
Quando uma fatura mistura itens vinculados e ignorados, somente os vinculados e
seus valores entram na venda. O worker usa um caixa que ja esteja aberto e envia
a tarefa para revisao manual se nao encontrar nenhum, sem tentar abrir um caixa
por conta propria.
