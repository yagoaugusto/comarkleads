<?php
// app/criar_campanha.php

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../public/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome_campanha = trim($_POST['nome_campanha']);
    $descricao = trim($_POST['descricao']);
    $usuario_id = $_SESSION['usuario_id'];

    if (empty($nome_campanha)) {
        header('Location: ../public/dashboard.php?erro=nome_invalido');
        exit();
    }

    $conexao->begin_transaction(); // Inicia uma transação

    try {
        // 1. Insere a campanha
        $stmt = $conexao->prepare("INSERT INTO campanhas (nome_campanha, descricao, usuario_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nome_campanha, $descricao, $usuario_id);
        $stmt->execute();
        
        // Pega o ID da campanha que acabamos de inserir
        $campanha_id = $stmt->insert_id;

        // 2. Gera uma chave de API única e segura
        // Usamos hash('sha256') para tornar a chave mais segura e com tamanho fixo
        $api_key = hash('sha256', uniqid(rand(), true));

        // 3. Atualiza a campanha recém-criada com a nova chave
        $stmt_key = $conexao->prepare("UPDATE campanhas SET api_key = ? WHERE id = ?");
        $stmt_key->bind_param("si", $api_key, $campanha_id);
        $stmt_key->execute();

        // Se tudo deu certo, confirma as operações
        $conexao->commit();

        header('Location: ../public/dashboard.php?sucesso=1');

    } catch (mysqli_sql_exception $exception) {
        $conexao->rollback(); // Desfaz as operações em caso de erro
        header('Location: ../public/dashboard.php?erro=db');
    }

    $stmt->close();
    $conexao->close();
    exit();
}