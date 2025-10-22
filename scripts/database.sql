CREATE TABLE IF NOT EXISTS `ambientes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(255) NOT NULL,
  `comando` TEXT NOT NULL,
  `cpu_limit` VARCHAR(50) DEFAULT 'N/A',
  `memoria_limit` VARCHAR(50) DEFAULT 'N/A',
  `status` VARCHAR(50) NOT NULL,
  `pid` INT,
  `log_path` VARCHAR(255),
  `data_criacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
