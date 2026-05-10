# Lis-onTech

Core privado do Lis'on ERP. Stack principal: PHP, MySQL e JavaScript.

## Estrutura

- `public_html/`: arquivos publicos servidos pelo hosting.
- `public_html/includes/`: configuracao, conexao com banco e helpers internos.
- `public_html/painel/`: roteador, paginas e APIs do painel administrativo.
- `public_html/assets/`: CSS, JS, imagens e logos usados pelo frontend.
- `public_html/storage/logs/`: logs gerados em runtime, ignorados pelo Git.
- `secure/`: configuracao local sensivel fora do versionamento.

## Configuracao local

Copie `secure/config.env.example` para `secure/config.env` no ambiente real e preencha os valores. Nunca versione `secure/config.env` ou logs.
