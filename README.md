# Estoque

## O que é este projeto

Este projeto é um sistema simples de controle de estoque construído em PHP com MySQL e Docker. Ele permite:

- cadastrar itens
- registrar movimentações de estoque (entrada e saída)
- controlar uso por consumo ou empréstimo
- associar movimentações a funcionários e localidades
- armazenar assinaturas digitais como prova de registro
- gerar relatório de saldo por item

## Dor que o projeto resolve

A dor atendida por este sistema é a falta de controle centralizado de estoque em um ambiente administrativo com:

- itens registrados manualmente
- entradas e saídas documentadas
- assinaturas de quem movimentou o item
- localidades organizadas em secretaria > divisão > setor

Ele foi pensado para substituir planilhas e registros manuais, fornecendo uma interface única para registrar e consultar estoques com validação básica.

## Para quem é este projeto

- equipes de compras ou almoxarifado que precisam registrar entradas e saídas de materiais
- gestores que precisam de histórico de movimentações
- operações que exigem assinatura e vínculo de movimentação com funcionários e locais

## O que o sistema faz

1. Cadastro de itens
   - o usuário informa o nome do item
   - pode definir quantidade inicial na criação
   - se o item já existir, o sistema atualiza o saldo em vez de criar item duplicado

2. Registro de movimentações
   - tipo: `entrada` ou `saída`
   - uso: `Consumo` ou `Empréstimo`
   - data do movimento
   - item e quantidade
   - funcionário responsável (para empréstimo)
   - localidade (secretaria > divisão > setor)
   - observação opcional
   - assinatura digital em canvas

3. Histórico
   - lista de movimentações registradas
   - visualização de assinatura como miniatura clicável

4. Relatório de estoque
   - mostra saldo inicial, retiradas e saldo final por item

5. Gestão de limites e alertas
   - definir quantidade mínima e desejável para cada item
   - registrar e-mails de destino para receber notificações de estoque baixo
   - alertar automaticamente quando o estoque atingir ou ficar abaixo do mínimo
   - estimar em quantos dias o item chegará ao mínimo com base no consumo histórico
   - indicar quantidade recomendada para compra visando manter o estoque acima do mínimo ou desejável

## Novas funcionalidades implementadas
- Os itens agora armazenam `quantidade_minima` e `quantidade_desejavel`.
- Há nova tabela `item_notificacao_email` para guardar destinatários de alerta por item.
- A página de itens permite configurar limites e adicionar emails de notificação.
- O relatório agora também mostra consumo mensal por item e mês.
- O cálculo de recomendação considera estoque atual, mínimo/desejável e consumo dos últimos 30 dias.
- O sistema envia alertas por e-mail quando um item fica abaixo do mínimo.

## Como usar as notificações de estoque
1. Cadastre ou selecione um item em `Itens`.
2. Preencha `Quantidade Mínima` e `Quantidade Desejável`.
3. Adicione pelo menos um endereço de e-mail válido em `Email de alerta`.
4. Registre movimentações de saída com `Uso = Consumo`.
5. Quando o saldo atingir ou ficar abaixo do mínimo, o sistema tentará enviar um e-mail.

> Observação: o envio de e-mail utiliza a função PHP `mail()` e requer um servidor de e-mail configurado no ambiente.

## Estrutura do projeto

- `docker-compose.yml` - define contêineres PHP e MySQL
- `Dockerfile` - imagem PHP com `pdo_mysql`
- `db/init.sql` - script inicial do banco
- `app/Database.php` - conexão com MySQL e criação automática de esquema
- `app/InventoryApp.php` - regras e fluxos de negócio
- `app/ItemModel.php` - CRUD de itens e saldo
- `app/FuncionarioModel.php` - funcionários disponíveis para movimentações
- `app/LocalidadeModel.php` - localidades hierárquicas
- `app/MovementModel.php` - grava entradas e saídas
- `app/SpreadsheetLocationLoader.php` - carrega hierarquia de localidades de JSON ou XLSX
- `public/index.php` - interface web principal
- `public/style.css` - estilo visual
- `public/form.js` - comportamento do formulário de movimentação
- `data/localidade_hierarchy.json` - árvore de localidades usada pela UI
- `openspec/` - pasta de documentação e especificações do projeto

## Banco de dados

O sistema usa MySQL e cria automaticamente as tabelas necessárias se ainda não existirem.

Tabelas principais:

- `funcionario`
- `localidade` (`secretaria`, `divisao`, `setor`)
- `item`
- `movimentacao`

A hierarquia de `localidade` é mantida em colunas textuais para permitir atualizações sem dependência de ENUMs fixos.

## Como rodar

No diretório do projeto:

```bash
cd /opt/projetos/estoque
docker compose up -d --build
```

Acesse em:

```text
http://localhost:8001
```

## Configuração de ambiente

Copie o arquivo de exemplo:

```bash
cp .env.example .env
```

Configure os valores de conexão do banco:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

## Documentação adicional

- `openspec/` - local reservado para especificações e documentação extra do projeto
- `data/localidade_hierarchy.json` - define a estrutura de secretarias, divisões e setores
- `ORGANOGRAMA DAS SECRETARIAS CHEFES E DIRETORES - ATUALIZADO 2026-04-20.xlsx` - fonte de dados de localidade

## Futuras melhorias

Este projeto pode ser expandido com as seguintes funcionalidades de nível 2:

- relatórios de consumo mensal para cada item
- registro de quantidade mínima e quantidade desejável por item
- envio de alerta por e-mail quando um item atingir ou ficar abaixo da quantidade mínima
- cadastro de destinatários de e-mail para receber notificações
- estimativa de quando cada item deve atingir a quantidade mínima com base no consumo histórico
- sugestão de quantidade a comprar para manter os estoques acima da quantidade mínima ou desejável
