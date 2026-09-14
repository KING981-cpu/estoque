-- --------------------------------------------------------
-- Servidor:                     192.168.1.53
-- Versão do servidor:           5.7.44 - MySQL Community Server (GPL)
-- OS do Servidor:               Linux
-- HeidiSQL Versão:              12.15.0.7171
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Copiando estrutura para tabela estoque.funcionario
CREATE TABLE IF NOT EXISTS `funcionario` (
  `id_funcionario` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  PRIMARY KEY (`id_funcionario`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela estoque.item
CREATE TABLE IF NOT EXISTS `item` (
  `id_item` int(11) NOT NULL AUTO_INCREMENT,
  `categoria` varchar(100) NOT NULL DEFAULT '',
  `item` varchar(50) NOT NULL,
  `condicao` enum('novo','usado') NOT NULL DEFAULT 'novo',
  `tipo_registro` tinyint(1) NOT NULL DEFAULT '1',
  `tipo_item` varchar(20) NOT NULL DEFAULT 'temporario',
  `unidade_medida` varchar(10) NOT NULL DEFAULT 'un',
  `saldo_atual` int(11) NOT NULL DEFAULT '0',
  `quantidade_minima` int(11) NOT NULL DEFAULT '0',
  `quantidade_desejavel` int(11) NOT NULL DEFAULT '0',
  `quantidade_sobra_compra` int(11) NOT NULL DEFAULT '0',
  `sinonimos` text NOT NULL,
  `requer_comprimento` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_item`),
  UNIQUE KEY `uniq_item_condicao` (`item`,`condicao`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela estoque.item_notificacao_email
CREATE TABLE IF NOT EXISTS `item_notificacao_email` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_item` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_item` (`id_item`),
  CONSTRAINT `item_notificacao_email_ibfk_1` FOREIGN KEY (`id_item`) REFERENCES `item` (`id_item`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela estoque.localidade
CREATE TABLE IF NOT EXISTS `localidade` (
  `id_localidade` int(11) NOT NULL AUTO_INCREMENT,
  `secretaria` varchar(255) NOT NULL DEFAULT '',
  `divisao` varchar(255) NOT NULL DEFAULT '',
  `setor` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id_localidade`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Exportação de dados foi desmarcado.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
