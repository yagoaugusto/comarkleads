<?php
session_start();
require_once '../config/database.php';

// 1. VERIFICAÇÕES DE SEGURANÇA E DADOS
if (!isset($_SESSION['usuario_id'])) {
    die("Acesso negado.");
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Método não permitido.");
}

$usuario_id = $_SESSION['usuario_id'];
$campanha_id = $_POST['campanha_id'];
$mensagem_template = $_POST['mensagem'];

if (empty($campanha_id) || empty($mensagem_template)) {
    header('Location: ../public/visualizar_campanha.php?id=' . $campanha_id . '&erro=dados_insuficientes');
    exit();
}

// 2. BUSCAR CREDENCIAIS DO ULTRAMSG DO USUÁRIO
$stmt_user = $conexao->prepare("SELECT ultramsg_instance_id, ultramsg_token FROM usuarios WHERE id = ?");
$stmt_user->bind_param("i", $usuario_id);
$stmt_user->execute();
$user_creds = $stmt_user->get_result()->fetch_assoc();

if (empty($user_creds['ultramsg_instance_id']) || empty($user_creds['ultramsg_token'])) {
    header('Location: ../public/visualizar_campanha.php?id=' . $campanha_id . '&erro=credenciais_ultramsg');
    exit();
}

$ultramsg_instance = $user_creds['ultramsg_instance_id'];
$ultramsg_token = $user_creds['ultramsg_token'];

// 3. BUSCAR LEADS DA CAMPANHA QUE TENHAM WHATSAPP
$stmt_leads = $conexao->prepare("SELECT nome_lead, whatsapp FROM leads WHERE campanha_id = ? AND whatsapp IS NOT NULL AND whatsapp != ''");
$stmt_leads->bind_param("i", $campanha_id);
$stmt_leads->execute();
$resultado_leads = $stmt_leads->get_result();

$total_enviados = 0;

// 4. LOOP PARA ENVIAR MENSAGEM PARA CADA LEAD
while ($lead = $resultado_leads->fetch_assoc()) {
    $nome_lead = $lead['nome_lead'];
    $whatsapp_lead = preg_replace('/[^0-9]/', '', $lead['whatsapp']);

    // Adiciona '55' se o número não tiver, assumindo Brasil.
    if (strlen($whatsapp_lead) <= 11) {
        $whatsapp_lead = '55' . $whatsapp_lead;
    }

    // Personaliza a mensagem substituindo {nome}
    $mensagem_final = str_replace('{nome}', $nome_lead, $mensagem_template);

    // 5. CONFIGURAÇÃO E EXECUÇÃO DA REQUISIÇÃO PARA A API ULTRAMSG (USANDO cURL)
    $params = [
        'token' => $ultramsg_token,
        'to' => $whatsapp_lead,
        'body' => $mensagem_final,
        'priority' => 10
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.ultramsg.com/" . $ultramsg_instance . "/messages/chat",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_HTTPHEADER => ["Content-Type: application/x-www-form-urlencoded"],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    // Podemos adicionar lógica para verificar a resposta ($response) e contar apenas os envios bem-sucedidos
    $total_enviados++;

    // Pequena pausa para não sobrecarregar a API
    sleep(1);
}

$stmt_user->close();
$stmt_leads->close();
$conexao->close();

// 6. REDIRECIONA DE VOLTA COM MENSAGEM DE SUCESSO
header('Location: ../public/visualizar_campanha.php?id=' . $campanha_id . '&sucesso=disparo_finalizado&total=' . $total_enviados);
exit();
