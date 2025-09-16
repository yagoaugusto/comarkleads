<?php
// Adicione estas 3 linhas para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// O resto do seu código continua aqui...
header("Access-Control-Allow-Origin: *");
// ...
// Define o cabeçalho para permitir requisições de qualquer origem (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Responde a requisições OPTIONS (pre-flight) que os navegadores enviam
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Verifica se a requisição é do tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido.']);
    exit();
}

require_once '../config/database.php';

// --- Validação da Chave de API ---
if (!isset($_POST['api_key']) || empty($_POST['api_key'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'Chave de API não fornecida.']);
    exit();
}

$api_key = $_POST['api_key'];

// Busca a campanha correspondente à chave
$stmt = $conexao->prepare("SELECT id FROM campanhas WHERE api_key = ?");
$stmt->bind_param("s", $api_key);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows !== 1) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Chave de API inválida.']);
    exit();
}

$campanha = $resultado->fetch_assoc();
$campanha_id = $campanha['id'];

// --- Captura e Sanitização dos Dados do Lead ---
// Os nomes dos campos (nome, email, etc.) são sugestões. O formulário do usuário deve usar esses nomes.
$nome = isset($_POST['nome']) ? trim(htmlspecialchars($_POST['nome'])) : 'Lead sem nome';
$email = isset($_POST['email']) ? trim(htmlspecialchars($_POST['email'])) : null;
$whatsapp = isset($_POST['whatsapp']) ? trim(htmlspecialchars($_POST['whatsapp'])) : null;
$empresa = isset($_POST['empresa']) ? trim(htmlspecialchars($_POST['empresa'])) : null;
$url_social = isset($_POST['url_social']) ? trim(htmlspecialchars($_POST['url_social'])) : null;
$qtd_funcionarios = isset($_POST['qtd_funcionarios']) ? trim(htmlspecialchars($_POST['qtd_funcionarios'])) : null;

// --- Inserção do Lead no Banco de Dados ---
$stmt = $conexao->prepare("
    INSERT INTO leads (campanha_id, nome_lead, email, whatsapp, empresa, url_social, qtd_funcionarios, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 'Novo')
");
$stmt->bind_param("issssss", $campanha_id, $nome, $email, $whatsapp, $empresa, $url_social, $qtd_funcionarios);

if ($stmt->execute()) {
    // Lead inserido com sucesso. Redireciona para uma página de "Obrigado".
    // O ideal é o usuário poder configurar esta URL no futuro.
    header('Location: https://' . $_SERVER['HTTP_HOST'] . '/public/obrigado.php');
    exit();
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar o lead.']);
    exit();
}

$stmt->close();
$conexao->close();