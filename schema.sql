-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 16/09/2025 às 22:53
-- Versão do servidor: 10.11.10-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u580429014_leads`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `campanhas`
--

CREATE TABLE `campanhas` (
  `id` int(11) NOT NULL,
  `nome_campanha` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) NOT NULL,
  `api_key` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `campanhas`
--

INSERT INTO `campanhas` (`id`, `nome_campanha`, `descricao`, `data_criacao`, `usuario_id`, `api_key`) VALUES
(1, 'COMARK', 'Campanha principal para coletar leads da comark', '2025-07-21 22:56:04', 1, NULL),
(2, 'COMARK TESTE', '', '2025-07-21 23:04:02', 1, '3bc370b128dff2b92331f5365ba8aa6a0d885d7519b8146ed6053f8109401767'),
(3, 'APEXVEICULAR', 'LandingPage Apex Veicular - Yago', '2025-07-23 16:32:27', 1, 'c77d3d317e1b035553677172269479d2ece6049e80952a067ef52e20679193f3');

-- --------------------------------------------------------

--
-- Estrutura para tabela `leads`
--

CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
  `campanha_id` int(11) NOT NULL,
  `nome_lead` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `empresa` varchar(255) DEFAULT NULL,
  `url_social` varchar(255) DEFAULT NULL,
  `qtd_funcionarios` enum('1-5','5-10','10-50','50-100','100+') DEFAULT NULL,
  `status` enum('Novo','Contatado','Qualificado','Proposta Enviada','Negociação','Ganho','Perdido') NOT NULL DEFAULT 'Novo',
  `origem` varchar(100) DEFAULT NULL,
  `responsavel_id` int(11) DEFAULT NULL,
  `data_ultimo_contato` datetime DEFAULT NULL,
  `anotacoes` text DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `leads`
--

INSERT INTO `leads` (`id`, `campanha_id`, `nome_lead`, `email`, `whatsapp`, `empresa`, `url_social`, `qtd_funcionarios`, `status`, `origem`, `responsavel_id`, `data_ultimo_contato`, `anotacoes`, `data_criacao`) VALUES
(1, 2, 'Yago', 'yagoacp@gmail.com', '98991668283', 'Teste', 'teste', '', 'Novo', NULL, NULL, NULL, NULL, '2025-07-22 00:07:40'),
(2, 2, 'Augusto', 'yago.augusto@dinamo.srv.br', '98991668283', 'Yago', '@yagoacp', '', 'Novo', NULL, NULL, NULL, NULL, '2025-07-22 00:09:42'),
(6, 2, 'Alef', 'aleffurtado2011@hotmail.com', '85997315131', 'Comark teste', 'Aleffurtado_', '', 'Novo', NULL, NULL, NULL, NULL, '2025-07-22 02:28:22'),
(7, 3, 'Lead sem nome', 'yagoacp@gmail.com', NULL, NULL, NULL, '1-5', 'Novo', NULL, NULL, NULL, NULL, '2025-07-23 17:47:08'),
(8, 3, 'augusto', 'yagoaugustocosta@gmail.com', NULL, NULL, NULL, '1-5', 'Novo', NULL, NULL, NULL, NULL, '2025-07-24 13:14:56');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `ultramsg_instance_id` varchar(100) DEFAULT NULL,
  `ultramsg_token` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `data_criacao`, `ultramsg_instance_id`, `ultramsg_token`) VALUES
(1, 'yago', 'yagoacp@gmail.com', '$2y$10$KtgULCjmFN9uO1OXVWjo3.Ul0xkAj.N2.Pqg7D4.V36dNof1AH54K', '2025-07-21 22:30:20', 'instance124122', 'vtts75qh13n0jdc7');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `campanhas`
--
ALTER TABLE `campanhas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `api_key` (`api_key`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `campanha_id` (`campanha_id`),
  ADD KEY `responsavel_id` (`responsavel_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `campanhas`
--
ALTER TABLE `campanhas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `campanhas`
--
ALTER TABLE `campanhas`
  ADD CONSTRAINT `campanhas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`campanha_id`) REFERENCES `campanhas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leads_ibfk_2` FOREIGN KEY (`responsavel_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
