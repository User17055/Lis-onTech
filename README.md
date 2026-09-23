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

Para executar todos os dias as 03:00 no horario de Sao Paulo, use `crontab -e`:

```cron
CRON_TZ=America/Sao_Paulo
0 3 * * * /usr/bin/flock -n /tmp/lisontech-simplesvet.lock /opt/lisontech-simplesvet/scripts/simplesvet_daily.sh >> /var/log/lisontech-simplesvet.log 2>&1
```

Os caminhos devem ser ajustados ao diretorio real da aplicacao na VPS.

Como alternativa, o instalador abaixo detecta o caminho atual e adiciona ou
atualiza o bloco do cron sem duplica-lo:

```bash
chmod +x /opt/lisontech-simplesvet/scripts/install_simplesvet_cron.sh
/opt/lisontech-simplesvet/scripts/install_simplesvet_cron.sh
```
