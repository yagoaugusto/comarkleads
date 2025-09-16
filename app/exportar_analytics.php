<?php
// app/exportar_analytics.php

session_start();
require_once '../config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../public/index.php?erro=acesso_negado');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$campanha_id = $_GET['campanha_id'] ?? null;

if (!$campanha_id || !filter_var($campanha_id, FILTER_VALIDATE_INT)) {
    header('Location: ../public/dashboard.php?erro=id_invalido');
    exit();
}

// Verificar se a campanha pertence ao usuário
$stmt = $conexao->prepare("SELECT nome_campanha FROM campanhas WHERE id = ? AND usuario_id = ?");
$stmt->bind_param("ii", $campanha_id, $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows !== 1) {
    header('Location: ../public/dashboard.php?erro=acesso_negado');
    exit();
}

$campanha = $resultado->fetch_assoc();

// Buscar dados dos indicadores
$stmt_dados = $conexao->prepare("
    SELECT 
        inicio_relatorios,
        termino_relatorios,
        nome_campanha_origem,
        valor_usado_brl,
        resultados,
        alcance,
        impressoes,
        frequencia,
        cpm_brl,
        cliques_link,
        ctr_link,
        visitas_perfil_instagram,
        conversas_mensagem_iniciadas,
        custo_conversa_mensagem_brl,
        seguidores_instagram,
        data_upload
    FROM campanha_indicadores 
    WHERE campanha_id = ? 
    ORDER BY inicio_relatorios DESC
");
$stmt_dados->bind_param("i", $campanha_id);
$stmt_dados->execute();
$resultado_dados = $stmt_dados->get_result();

// Definir headers para download CSV
$filename = "analytics_" . preg_replace('/[^a-zA-Z0-9]/', '_', $campanha['nome_campanha']) . "_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Criar output stream
$output = fopen('php://output', 'w');

// Escrever BOM para UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalhos do CSV
$headers = [
    'Data Início',
    'Data Término', 
    'Nome Campanha',
    'Valor Usado (R$)',
    'Resultados',
    'Alcance',
    'Impressões',
    'Frequência',
    'CPM (R$)',
    'Cliques Link',
    'CTR (%)',
    'Visitas Perfil Instagram',
    'Conversas Iniciadas',
    'Custo por Conversa (R$)',
    'Seguidores Instagram',
    'Data Upload'
];

fputcsv($output, $headers);

// Escrever dados
while ($row = $resultado_dados->fetch_assoc()) {
    $csv_row = [
        $row['inicio_relatorios'] ? date('d/m/Y', strtotime($row['inicio_relatorios'])) : '',
        $row['termino_relatorios'] ? date('d/m/Y', strtotime($row['termino_relatorios'])) : '',
        $row['nome_campanha_origem'] ?? '',
        $row['valor_usado_brl'] ? number_format($row['valor_usado_brl'], 2, ',', '.') : '',
        $row['resultados'] ?? '',
        $row['alcance'] ?? '',
        $row['impressoes'] ?? '',
        $row['frequencia'] ? number_format($row['frequencia'], 6, ',', '.') : '',
        $row['cpm_brl'] ? number_format($row['cpm_brl'], 2, ',', '.') : '',
        $row['cliques_link'] ?? '',
        $row['ctr_link'] ? number_format($row['ctr_link'], 2, ',', '.') : '',
        $row['visitas_perfil_instagram'] ?? '',
        $row['conversas_mensagem_iniciadas'] ?? '',
        $row['custo_conversa_mensagem_brl'] ? number_format($row['custo_conversa_mensagem_brl'], 2, ',', '.') : '',
        $row['seguidores_instagram'] ?? '',
        $row['data_upload'] ? date('d/m/Y H:i', strtotime($row['data_upload'])) : ''
    ];
    
    fputcsv($output, $csv_row);
}

fclose($output);

$stmt->close();
$stmt_dados->close();
$conexao->close();
exit();
?>