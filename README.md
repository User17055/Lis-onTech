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
