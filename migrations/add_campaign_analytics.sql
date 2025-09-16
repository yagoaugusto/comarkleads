-- Migration for campaign analytics functionality
-- This table will store the data imported from Excel files

CREATE TABLE `campanha_indicadores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campanha_id` int(11) NOT NULL,
  `inicio_relatorios` date DEFAULT NULL,
  `termino_relatorios` date DEFAULT NULL,
  `nome_campanha_origem` varchar(255) DEFAULT NULL,
  `data_criacao_campanha` date DEFAULT NULL,
  `veiculacao_campanha` varchar(100) DEFAULT NULL,
  `orcamento_conjunto_anuncios` varchar(255) DEFAULT NULL,
  `tipo_orcamento_conjunto_anuncios` varchar(100) DEFAULT NULL,
  `valor_usado_brl` decimal(10,2) DEFAULT NULL,
  `resultados` int(11) DEFAULT NULL,
  `indicador_resultados` varchar(255) DEFAULT NULL,
  `custo_por_resultados` decimal(10,2) DEFAULT NULL,
  `alcance` int(11) DEFAULT NULL,
  `impressoes` int(11) DEFAULT NULL,
  `frequencia` decimal(10,6) DEFAULT NULL,
  `cpm_brl` decimal(10,2) DEFAULT NULL,
  `cliques_link` int(11) DEFAULT NULL,
  `ctr_link` decimal(10,6) DEFAULT NULL,
  `visitas_perfil_instagram` int(11) DEFAULT NULL,
  `conversas_mensagem_iniciadas` int(11) DEFAULT NULL,
  `custo_conversa_mensagem_brl` decimal(10,2) DEFAULT NULL,
  `seguidores_instagram` int(11) DEFAULT NULL,
  `data_upload` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_upload_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`campanha_id`) REFERENCES `campanhas` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`usuario_upload_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  INDEX `idx_campanha_data` (`campanha_id`, `inicio_relatorios`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add public sharing functionality to campaigns
ALTER TABLE `campanhas` 
ADD COLUMN `public_token` varchar(64) DEFAULT NULL,
ADD COLUMN `public_share_enabled` boolean DEFAULT FALSE,
ADD UNIQUE KEY `unique_public_token` (`public_token`);