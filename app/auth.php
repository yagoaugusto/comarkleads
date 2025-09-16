<?php
// app/auth.php

session_start();

// VAI SUBIR UM NÍVEL (../) PARA A PASTA RAIZ E DEPOIS ENTRAR NA PASTA 'config'
require_once __DIR__ . '/../config/database.php';

// Verifica se a variável $conexao foi criada com sucesso
if (!isset($conexao)) {
    die("Erro: A conexão com o banco de dados não foi estabelecida.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $senha_digitada = $_POST['senha'];

    // Esta é a linha 13 (ou próxima a ela) que estava dando erro
    $stmt = $conexao->prepare("SELECT id, nome, senha FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();
        
        if (password_verify($senha_digitada, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            
            header("Location: ../public/dashboard.php");
            exit();
        }
    }

    header("Location: ../public/index.php?erro=1");
    exit();
}

// Fecha a conexão ao final do script
$conexao->close();

?>