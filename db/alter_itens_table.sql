-- Script de refatoração da tabela de itens
-- Cria ou atualiza a tabela `itens` conforme os novos requisitos.

CREATE TABLE IF NOT EXISTS itens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  categoria VARCHAR(100) NOT NULL DEFAULT '',
  nome VARCHAR(255) NOT NULL DEFAULT '',
  quantidade INT NOT NULL DEFAULT 0,
  tipo_cadastro ENUM('novo', 'existente') NOT NULL DEFAULT 'novo',
  sinonimos TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Se existir uma tabela antiga `item`, esta seção permite migrar os dados básicos para a nova tabela `itens`.
-- Ajuste os valores de categoria se tiver informação disponível em outro lugar.

INSERT INTO itens (categoria, nome, quantidade, tipo_cadastro, sinonimos)
SELECT
  '' AS categoria,
  item AS nome,
  saldo_atual AS quantidade,
  CASE WHEN tipo_registro = 1 THEN 'novo' ELSE 'existente' END AS tipo_cadastro,
  sinonimos
FROM item
WHERE NOT EXISTS (
  SELECT 1 FROM itens i WHERE i.nome = item
);

-- NOTA: Em MySQL 5.7, `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` não é suportado. Execute a migração apenas depois de verificar a existência da nova estrutura.
-- Caso queira manter o esquema antigo em backup, renomeie a tabela original antes de remover: RENAME TABLE item TO item_backup;
