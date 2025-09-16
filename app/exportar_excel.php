<?php
session_start();
require_once '../config/database.php';

// 1. VERIFICAÇÕES DE SEGURANÇA
if (!isset($_SESSION['usuario_id'])) {
    die("Acesso negado. Faça login para continuar.");
}
$usuario_id = $_SESSION['usuario_id'];

if (!isset($_GET['campanha_id']) || !filter_var($_GET['campanha_id'], FILTER_VALIDATE_INT)) {
    die("ID de campanha inválido.");
}
$campanha_id = $_GET['campanha_id'];

// 2. VERIFICA SE A CAMPANHA PERTENCE AO USUÁRIO
$stmt_check = $conexao->prepare("SELECT nome_campanha FROM campanhas WHERE id = ? AND usuario_id = ?");
$stmt_check->bind_param("ii", $campanha_id, $usuario_id);
$stmt_check->execute();
$resultado_check = $stmt_check->get_result();

if ($resultado_check->num_rows !== 1) {
    die("Campanha não encontrada ou acesso negado.");
}
$campanha = $resultado_check->fetch_assoc();
$nome_campanha = $campanha['nome_campanha'];

// 3. BUSCA OS LEADS DA CAMPANHA
$stmt_leads = $conexao->prepare("SELECT * FROM leads WHERE campanha_id = ? ORDER BY data_criacao ASC");
$stmt_leads->bind_param("i", $campanha_id);
$stmt_leads->execute();
$resultado_leads = $stmt_leads->get_result();

// 4. GERAÇÃO DO ARQUIVO CSV
// Define o nome do arquivo
$nome_arquivo = "leads_" . preg_replace('/[^a-z0-9_]/i', '', str_replace(' ', '_', $nome_campanha)) . ".csv";

// Define os cabeçalhos HTTP para forçar o download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $nome_arquivo);

// Cria um "ponteiro" para a saída do PHP, que será o nosso arquivo
$output = fopen('php://output', 'w');

// Adiciona o BOM (Byte Order Mark) para garantir a compatibilidade com acentos no Excel
fputs($output, "\xEF\xBB\xBF");

// Adiciona o cabeçalho do CSV (os títulos das colunas)
fputcsv($output, [
    'ID',
    'Nome do Lead',
    'Email',
    'WhatsApp',
    'Empresa',
    'Site/Social',
    'Qtd Funcionarios',
    'Status',
    'Origem',
    'Data de Criacao',
    'Ultimo Contato',
    'Anotacoes'
]);

// Percorre os resultados do banco e escreve cada linha no CSV
if ($resultado_leads->num_rows > 0) {
    while ($lead = $resultado_leads->fetch_assoc()) {
        fputcsv($output, [
            $lead['id'],
            $lead['nome_lead'],
            $lead['email'],
            $lead['whatsapp'],
            $lead['empresa'],
            $lead['url_social'],
            $lead['qtd_funcionarios'],
            $lead['status'],
            $lead['origem'],
            $lead['data_criacao'],
            $lead['data_ultimo_contato'],
            $lead['anotacoes']
        ]);
    }
}

// Fecha os ponteiros e finaliza o script
fclose($output);
$stmt_check->close();
$stmt_leads->close();
$conexao->close();
exit();
?>