<?php
// config/database.php

// --- ATENÇÃO: EDITE AS INFORMAÇÕES ABAIXO ---
$servidor = 'localhost';          // Geralmente é 'localhost'
$usuario_db = 'root';             // Usuário padrão do XAMPP é 'root'
$senha_db = '';                   // Senha padrão do XAMPP é em branco
$banco = 'comarkleads';          // << COLOQUE AQUI O NOME DO SEU BANCO DE DADOS

// Cria a conexão
$conexao = new mysqli($servidor, $usuario_db, $senha_db, $banco);

// Verifica se houve erro na conexão
if ($conexao->connect_error) {
    die("Falha na conexão com o banco de dados: " . $conexao->connect_error);
}

// Define o charset para UTF-8 para evitar problemas com acentuação
$conexao->set_charset("utf8mb4");

?>