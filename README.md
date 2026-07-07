# Estoque

## O que é este projeto

Este projeto é um sistema de controle de estoque em PHP com MySQL e Docker, pensado para ambientes administrativos que precisam registrar itens, entradas, saídas, assinaturas e relatórios de estoque com persistência de dados.

## O que o sistema resolve

Ele resolve a falta de um controle centralizado de estoque, substituindo processos manuais e planilhas por:

- cadastro de itens
- controle de entradas e saídas
- rastreamento de uso por consumo e empréstimo
- registro de responsável e localidade
- armazenamento de assinaturas digitais
- relatórios paginados e filtros por período
- alertas por e-mail de estoque baixo

## Para quem é este projeto

- equipes de almoxarifado ou compras
- gestores que precisam auditar movimentações
- operações que exigem assinatura física/documentada
- ambientes que precisam de histórico de movimentação e notificações

## Funcionalidades implementadas

### Cadastro e gestão de itens
- cadastro de itens por nome
- quantidade inicial opcional no registro do item
- regra de item único por nome, evitando duplicação
- limites de estoque por item:
  - `quantidade_minima`
  - `quantidade_desejavel`

### Movimentações
- tipos de movimentação:
  - `entrada`
  - `saída`
- usos:
  - `Consumo`
  - `Empréstimo`
- registro de data, quantidade, observação, funcionário e localidade
- assinatura digital em canvas para cada movimentação

### Itens temporários vs permanentes
- suporte a `tipo_registro`:
  - `0` → `Saldo de transição` (temporário)
  - `1` → `Carga permanente`
- itens temporários:
  - aparecem na tela de `Histórico`
  - compõem o saldo atual em exibição
  - não aparecem no `Relatório de Movimentações`
  - não entram no `Relatório de Consumo Mensal`
  - não influenciam a `Estimativa de Esgotamento`
- itens permanentes:
  - são considerados para relatórios e estimativas
  - compõem o histórico de consumo oficial

### Histórico
- lista completa de todas as movimentações
- exibe assinaturas em miniatura clicável
- aceita filtros de período:
  - `Data inicial`
  - `Data final`
- mantém paginação para grandes volumes de registros

### Relatórios
- `Relatório de Movimentações`
  - mostra apenas movimentações permanentes (`tipo_registro = 1`)
  - exclui registros temporários de carga inicial
  - mantém paginação configurável
- `Relatório de Consumo Mensal`
  - considera apenas saídas com `uso = "Consumo"` e `tipo_registro = 1`
  - filtra por ano e mês
  - possui paginação
- `Relatório de Estoque e Estimativa`
  - mostra saldo atual por item
  - inclui estimativa de esgotamento
  - usa apenas movimentações permanentes para cálculo

### Notificações e alertas
- registro de emails de alerta por item
- alerta automático quando estoque chega ao mínimo ou abaixo dele
- envio de e-mail via PHP `mail()`, compatível com ambiente MailHog em desenvolvimento

### Estimativa de esgotamento
- cálculo baseado em dias úteis
- usa somente consumo efetivo de saída (`tipo = "saída"`, `uso = "Consumo"`, `tipo_registro = 1`)
- usa a data do primeiro consumo registrado e a janela de dias úteis até hoje
- fórmula:
  - `dailyAverage = total_consumption / windowDays`
  - `estimativa = (stock - minimum) / dailyAverage`
- protege contra divisão por zero usando `1` dia quando `windowDays <= 0`

### Persistência
- banco MySQL com dados persistidos em `./db_data`
- MailHog configurado para persistência em disco em `./mailhog_data`
- a configuração evita perda de logs de email ao recriar containers

## Arquitetura e arquivos principais

- `docker-compose.yml` — containers PHP, MySQL e MailHog
- `Dockerfile` — imagem PHP com extensão `pdo_mysql`
- `db/init.sql` — esquema inicial do banco
- `app/Database.php` — conexão e migrações de esquema
- `app/InventoryApp.php` — regras de negócio e coordenador de fluxos
- `app/ItemModel.php` — CRUD de itens e cálculo de saldo
- `app/MovementModel.php` — grava movimentações e gera relatórios
- `app/FuncionarioModel.php` — CRUD de funcionários
- `app/LocalidadeModel.php` — CRUD e hierarquia de localidades
- `app/SpreadsheetLocationLoader.php` — carga de hierarquia de localidades
- `public/index.php` — interface web principal
- `public/style.css` — estilo visual
- `public/form.js` — comportamento de formulário de movimentação
- `data/localidade_hierarchy.json` — hierarquia de localidades para UI
- `openspec/` — documentação e especificações do projeto

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

- `openspec/` — local reservado para especificações e documentação extra do projeto
- `data/localidade_hierarchy.json` — define a estrutura de secretarias, divisões e setores
- `ORGANOGRAMA DAS SECRETARIAS CHEFES E DIRETORES - ATUALIZADO 2026-04-20.xlsx` — fonte de dados de localidade

## Futuras melhorias

Este projeto pode ser expandido com as seguintes funcionalidades de nível 2:

- novas integrações com sistemas de compras
- dashboard de tendência de consumo
- relatórios de previsão de compra por categoria
- exportação de histórico em CSV/PDF
