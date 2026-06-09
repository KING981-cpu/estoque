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
  item VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS movimentacao (
  id_movimentacao INT PRIMARY KEY AUTO_INCREMENT,
  tipo ENUM('entrada', 'saída') NOT NULL,
  data_item DATE NOT NULL,
  quantidade INT NOT NULL,
  assinatura LONGTEXT NOT NULL,
  uso ENUM('Consumo', 'Empréstimo') NOT NULL,
  observação VARCHAR(200) NOT NULL,
  id_funcionario INT,
  id_localidade INT,
  id_item INT,
  FOREIGN KEY (id_funcionario) REFERENCES funcionario(id_funcionario),
  FOREIGN KEY (id_localidade) REFERENCES localidade(id_localidade),
  FOREIGN KEY (id_item) REFERENCES item(id_item)
);
