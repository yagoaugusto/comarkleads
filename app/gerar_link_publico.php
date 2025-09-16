<?php
// app/gerar_link_publico.php

session_start();
require_once '../config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $campanha_id = $_POST['campanha_id'] ?? null;
    
    if (!$campanha_id || !filter_var($campanha_id, FILTER_VALIDATE_INT)) {
        echo json_encode(['success' => false, 'message' => 'ID de campanha inválido']);
        exit();
    }
    
    // Verificar se a campanha pertence ao usuário
    $stmt = $conexao->prepare("SELECT id, public_token FROM campanhas WHERE id = ? AND usuario_id = ?");
    $stmt->bind_param("ii", $campanha_id, $usuario_id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows !== 1) {
        echo json_encode(['success' => false, 'message' => 'Campanha não encontrada']);
        exit();
    }
    
    $campanha = $resultado->fetch_assoc();
    
    // Gerar ou usar token existente
    if (empty($campanha['public_token'])) {
        $public_token = bin2hex(random_bytes(32));
        
        $stmt_update = $conexao->prepare("UPDATE campanhas SET public_token = ?, public_share_enabled = 1 WHERE id = ?");
        $stmt_update->bind_param("si", $public_token, $campanha_id);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        $public_token = $campanha['public_token'];
        
        // Ativar compartilhamento se estiver desativado
        $stmt_enable = $conexao->prepare("UPDATE campanhas SET public_share_enabled = 1 WHERE id = ?");
        $stmt_enable->bind_param("i", $campanha_id);
        $stmt_enable->execute();
        $stmt_enable->close();
    }
    
    $public_url = 'https://' . $_SERVER['HTTP_HOST'] . '/public/analytics_public.php?token=' . $public_token;
    
    echo json_encode([
        'success' => true, 
        'public_url' => $public_url,
        'token' => $public_token
    ]);
    
    $stmt->close();
    $conexao->close();
}
?>