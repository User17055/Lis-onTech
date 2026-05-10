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
