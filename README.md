# Estoque

Site PHP com Docker e banco MySQL, construído sobre a documentação do projeto e com a estrutura de banco solicitada.

## Estrutura do banco

A aplicação usa o esquema exato:

```sql
create database estoque;
use estoque;
create table funcionario(
  id_funcionario int primary key auto_increment,
  nome varchar(100) not null
);

create table localidade(
  id_localidade int primary key auto_increment,
  local varchar(100) not null
);

create table item(
  id_item int primary key auto_increment,
  item varchar(50) not null
);

create table movimentacao(
  id_movimentacao int primary key auto_increment,
  tipo ENUM('entrada', 'saída') not null,
  data_item date not null,
  quantidade int not null,
  assinatura longtext not null,
  uso ENUM('Consumo', 'Empréstimo') not null,
  observação varchar(200) not null,

  id_funcionario int,
  id_localidade int,
  id_item int,

  foreign key (id_funcionario) references funcionario(id_funcionario),
  foreign key (id_localidade) references localidade(id_localidade),
  foreign key (id_item) references item(id_item)
);
```

## Como executar

1. Entre no diretório do projeto:
   ```bash
   cd /opt/projetos/estoque
   ```
2. Inicie os containers com Docker Compose:
   ```bash
   docker compose up -d --build
   ```
3. Acesse o site:
   ```
   http://localhost:8001
   ```

## O que está disponível

- `docker-compose.yml` — orquestração do app PHP e do MySQL
- `Dockerfile` — imagem PHP com `pdo_mysql`
- `db/init.sql` — inicialização do banco `estoque` e tabelas
- `app/Database.php` — conexão MySQL e criação de esquema
- `app/ItemModel.php` — cadastro de itens
- `app/FuncionarioModel.php` — cadastro de funcionários
- `app/LocalidadeModel.php` — cadastro de localidades
- `app/MovementModel.php` — registro de movimentações
- `app/InventoryApp.php` — lógica de aplicação
- `public/index.php` — interface web
- `public/style.css` — layout do site

## Uso básico

- Cadastre itens, funcionários e localidades
- Ao cadastrar um item, defina a quantidade inicial diretamente no formulário
- Se o item já existir, não será criado novamente; a quantidade será adicionada ao estoque do item existente
- Registre entradas e saídas de estoque
- Visualize movimentações e histórico
- Use o botão `Histórico` ao lado de `Movimentações` para acessar o histórico separado
- No histórico, assinaturas são exibidas como miniaturas clicáveis para visualização ampliada
- Use o botão `Relatório` para ver uma linha por item com: nome do item, quantidade inicial (total de entradas), quantidade retirada (total de saídas) e quantidade final

## Notas

- O MySQL usa `mysql:5.7` e expõe o banco em `localhost:3308`
- O site roda em `localhost:8001`
- O banco `estoque` é criado automaticamente no primeiro boot
## Ambiente e segredos

- `.env.example` é apenas um modelo de configuração. Nunca coloque dados reais nele.
- Copie `.env.example` para `.env` e preencha todos os valores sensíveis.
- `.env` está listado em `.gitignore` e só deve conter dados específicos do ambiente.
- Dados sensíveis como tokens, senhas e endpoints privados devem ficar apenas em `.env`.
- O código já lê os valores do banco e da API usando variáveis de ambiente.
- Sempre verifique se não existe lixo de testes ou valores antigos nos arquivos `.env`, `.env.example`, `app/ApiClient.php` e `app/EnvLoader.php`.
- O formulário de movimentação deve ter campos limpos, bem ordenados e visibilidade condicional para: tipo de movimentação, uso, data, item, quantidade, funcionário, localidade, observação e assinatura.
- Para entrada ou saída de consumo, exibir somente: tipo, uso, data, item, quantidade e assinatura.
- Para saída por empréstimo, exibir: tipo, uso, data, item, quantidade, funcionário, localidade, observação e assinatura.
- A assinatura deve ser feita com canvas de desenho e submetida como `assinatura_data` em campo oculto.
- Remover o campo de descrição do item, pois ele não é necessário para o fluxo atual.
- O item deve ser inserido em texto livre; não usar lista suspensa de itens na tela de movimentações.
- Não misture HTML com CSS ou lógica JavaScript dentro do mesmo arquivo. Use `public/style.css` e `public/form.js` para isso.
- Para integração GLPI, use estas variáveis em `.env`:
  - `GLPI_URL`
  - `GLPI_APP_TOKEN`
  - `GLPI_USER_TOKEN`
- Para conectar ao banco, use estas variáveis em `.env`:
  - `DB_HOST`
  - `DB_PORT`
  - `DB_NAME`
  - `DB_USER`
  - `DB_PASS`

### Criar `.env`

No diretório do projeto:

```bash
cd /opt/projetos/estoque
cp .env.example .env
```

Em seguida, configure `.env` com os valores reais do ambiente.

## Teste de GLPI

Use `app/testGlpi.php` para validar a conexão com GLPI e carregar localidades. No container PHP, execute:

```bash
cd /opt/projetos/estoque

docker exec -e GLPI_URL='http://192.168.250.116:8091/apirest.php' \
  -e GLPI_APP_TOKEN='Nh3LYJ635oIcYgDxNjIyJoQrf7jZ3OsgGx22E0xH' \
  -e GLPI_USER_TOKEN='IkuhDVlY3OYmQXpEDxBoMo08tkJUMZLNNjm9HrsP' \
  estoque_app php /var/www/html/app/testGlpi.php
```
## Regras de alteração

- Sempre documentar qualquer funcionalidade nova pedida antes de implementá-la.
- Não alterar a estrutura do banco de dados existente sem solicitação explícita.
- Novas funções podem usar tabelas ou campos adicionais apenas depois de aprovação e documentação.
- Qualquer mudança deve ser registrada na documentação do projeto.
- Não responda que o trabalho está concluído enquanto não estiver funcionando corretamente.

