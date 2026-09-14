CREATE DATABASE IF NOT EXISTS estoque CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE estoque;

CREATE TABLE IF NOT EXISTS funcionario (
  id_funcionario INT PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS localidade (
  id_localidade INT PRIMARY KEY AUTO_INCREMENT,
  secretaria VARCHAR(255) NOT NULL DEFAULT '',
  divisao VARCHAR(255) NOT NULL DEFAULT '',
  setor VARCHAR(255) NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS item (
  id_item INT PRIMARY KEY AUTO_INCREMENT,
  categoria VARCHAR(100) NOT NULL DEFAULT '',
  item VARCHAR(50) NOT NULL,
  condicao ENUM('novo', 'usado') NOT NULL DEFAULT 'novo',
  tipo_registro TINYINT(1) NOT NULL DEFAULT 1,
  tipo_item VARCHAR(20) NOT NULL DEFAULT 'temporario',
  unidade_medida VARCHAR(10) NOT NULL DEFAULT 'un',
  saldo_atual INT NOT NULL DEFAULT 0,
  quantidade_minima INT NOT NULL DEFAULT 0,
  quantidade_desejavel INT NOT NULL DEFAULT 0,
  quantidade_sobra_compra INT NOT NULL DEFAULT 0,
  sinonimos TEXT NOT NULL,
  requer_comprimento TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_item_condicao (item, condicao)
);

CREATE TABLE IF NOT EXISTS item_notificacao_email (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_item INT NOT NULL,
  email VARCHAR(255) NOT NULL,
  FOREIGN KEY (id_item) REFERENCES item(id_item)
);

CREATE TABLE IF NOT EXISTS movimentacao (
  id_movimentacao INT PRIMARY KEY AUTO_INCREMENT,
  tipo ENUM('entrada', 'saída') NOT NULL,
  data_item DATE NOT NULL,
  quantidade INT NOT NULL,
  assinatura LONGTEXT NOT NULL,
  uso ENUM('Consumo', 'Empréstimo') NOT NULL,
  tipo_registro TINYINT(1) NOT NULL DEFAULT 1,
  observação VARCHAR(200) NOT NULL,
  condicao_item VARCHAR(50) NOT NULL DEFAULT '',
  patrimonio VARCHAR(255) NOT NULL DEFAULT '',
  id_funcionario INT,
  id_localidade INT,
  id_item INT,
  FOREIGN KEY (id_funcionario) REFERENCES funcionario(id_funcionario),
  FOREIGN KEY (id_localidade) REFERENCES localidade(id_localidade),
  FOREIGN KEY (id_item) REFERENCES item(id_item)
);
