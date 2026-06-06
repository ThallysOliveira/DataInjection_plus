# Importacao de Nota Fiscal

Esta variante mantem o fluxo original do Data Injection 2.14 para GLPI 10 e
adiciona um PDF de Nota Fiscal aos lotes de importacao de ativos.

## Comportamento

- O campo `Invoice PDF` aparece apenas para modelos cujo tipo principal aceita
  informacoes financeiras do GLPI.
- O PDF e criado uma unica vez como um `Document` nativo do GLPI.
- Cada ativo novo importado recebe uma relacao em `glpi_documents_items`.
- Quando a linha cria ou encontra um `Infocom`, o mesmo documento tambem e
  associado ao registro financeiro.
- Atualizacoes de ativos existentes nao recebem automaticamente o documento.
- Cada linha e processada em uma transacao independente. Uma falha de criacao
  ou associacao desfaz somente aquela linha.
- Se nenhuma linha gerar uma associacao, o documento vazio e removido.

## Requisitos

1. GLPI entre `10.0.11` e `10.0.98`, conforme os requisitos desta branch.
2. Tipo de documento `PDF` habilitado para upload no GLPI.
3. Perfil com permissao para usar o Data Injection, criar os ativos, manipular
   informacoes financeiras e criar documentos.
4. Extensao PHP `fileinfo` habilitada.

## Arquivos alterados

- `front/clientinjection.form.php`
- `inc/clientinjection.class.php`
- `inc/engine.class.php`
- `inc/invoicedocument.class.php`

## Validacao local

```bash
composer install
vendor/bin/phpcs front/clientinjection.form.php \
  inc/clientinjection.class.php \
  inc/engine.class.php \
  inc/invoicedocument.class.php
```
