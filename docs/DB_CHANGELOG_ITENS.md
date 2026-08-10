# Changelog de Alterações no Banco de Dados: Tabela `itens`

## Tabela Alterada
- `itens`

## Objetivo
Refatorar a tabela para suportar os novos requisitos de cadastro do sistema de controle de estoque, tornando todos os campos obrigatórios e adicionando a classificação de `tipo_cadastro`.

> Observação: o esquema atual do projeto usa a tabela `item` no banco de dados. Este registro documenta a criação da nova tabela `itens` e a migração de dados a partir da tabela existente, preservando o comportamento atual até que o código seja adaptado para a nova estrutura.

## Campos Modificados/Adicionados
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `categoria` VARCHAR(100) NOT NULL
- `nome` VARCHAR(255) NOT NULL
- `quantidade` INT NOT NULL DEFAULT 0
- `tipo_cadastro` ENUM('novo', 'existente') NOT NULL
- `sinonimos` TEXT NOT NULL

## Campos Removidos/Substituídos
- `item` foi substituído por `nome` para melhor semântica de cadastro
- `tipo_registro` foi substituído por `tipo_cadastro` com valores descritivos
- `saldo_atual` foi substituído por `quantidade`

## Impacto nas Consultas e Relatórios
- Relatórios de consumo mensal e estimativas de estoque devem incluir apenas itens com `tipo_cadastro = 'novo'`.
- Itens com `tipo_cadastro = 'existente'` devem ser mantidos no banco como histórico, mas não devem influenciar:
  - Relatório de Consumo Mensal
  - Cálculos de estimativa de esgotamento
  - Recomendação de compras

## Ajustes Recomendados em Consultas Existentes
- No relatório de consumo mensal:
  - Adicionar `AND i.tipo_cadastro = 'novo'` no `WHERE`.

- Na consulta de primeira data de consumo e total consumido:
  - Adicionar `AND i.tipo_cadastro = 'novo'` contra a tabela `item` / `itens`.

## Exemplo de consulta ajustada
```sql
SELECT i.id_item,
       i.item AS item_name,
       DATE_FORMAT(m.data_item, '%Y-%m') AS mes,
       SUM(m.quantidade) AS consumo_mensal
FROM movimentacao m
JOIN item i ON m.id_item = i.id_item
WHERE m.uso = 'Consumo'
  AND i.tipo_cadastro = 'novo'
  AND m.tipo = 'saída'
  AND COALESCE(m.tipo_registro, 1) = 1
GROUP BY i.id_item, i.item, mes
ORDER BY mes DESC, i.item ASC;
```

## Observações
- A nova coluna `categoria` é obrigatória, mas o script de migração inicial define valor vazio (`''`) para registros migrados automaticamente. Idealmente, categorias devem ser preenchidas de forma manual ou via integração.
- A coluna `sinonimos` continua sendo `TEXT NOT NULL` e deve ser mantida em formato separado por vírgulas.
- Como `tipo_cadastro` é um `ENUM`, o banco passa a aceitar apenas os valores `'novo'` ou `'existente'`.

## Ação de migração
1. Fazer backup do banco de dados existente.
2. Executar o script `db/alter_itens_table.sql` no ambiente MySQL.
3. Validar que os relatórios de consumo e de estimativa retornam somente itens `novo`.
