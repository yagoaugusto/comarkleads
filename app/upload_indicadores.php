<?php
// app/upload_indicadores.php

session_start();
require_once '../config/database.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../public/index.php?erro=acesso_negado');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $campanha_id = $_POST['campanha_id'];
    $descricao_upload = trim($_POST['descricao_upload'] ?? '');

    // Validar campanha_id e verificar se pertence ao usuário
    $stmt = $conexao->prepare("SELECT id FROM campanhas WHERE id = ? AND usuario_id = ?");
    $stmt->bind_param("ii", $campanha_id, $usuario_id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows !== 1) {
        header('Location: ../public/visualizar_campanha.php?id=' . $campanha_id . '&erro=acesso_negado');
        exit();
    }

    // Validar upload do arquivo
    if (!isset($_FILES['arquivo_excel']) || $_FILES['arquivo_excel']['error'] !== UPLOAD_ERR_OK) {
        header('Location: ../public/visualizar_campanha.php?id=' . $campanha_id . '&erro=arquivo_invalido');
        exit();
    }

    $arquivo = $_FILES['arquivo_excel'];
    $nome_arquivo = $arquivo['name'];
    $caminho_temp = $arquivo['tmp_name'];
    $tamanho_arquivo = $arquivo['size'];

    // Validar tipo de arquivo
    $extensao = strtolower(pathinfo($nome_arquivo, PATHINFO_EXTENSION));
    if (!in_array($extensao, ['xlsx', 'xls'])) {
        header('Location: ../public/visualizar_campanha.php?id=' . $campanha_id . '&erro=formato_invalido');
        exit();
    }

    // Validar tamanho (10MB máximo)
    if ($tamanho_arquivo > 10 * 1024 * 1024) {
        header('Location: ../public/visualizar_campanha.php?id=' . $campanha_id . '&erro=arquivo_muito_grande');
        exit();
    }

    try {
        // Ler o arquivo Excel
        $spreadsheet = IOFactory::load($caminho_temp);
        $worksheet = $spreadsheet->getActiveSheet();
        $dados = $worksheet->toArray();

        // Remover cabeçalho (primeira linha)
        array_shift($dados);

        $conexao->begin_transaction();

        // Preparar statement para inserção
        $sql = "INSERT INTO campanha_indicadores (
            campanha_id, inicio_relatorios, termino_relatorios, nome_campanha_origem, 
            data_criacao_campanha, veiculacao_campanha, orcamento_conjunto_anuncios, 
            tipo_orcamento_conjunto_anuncios, valor_usado_brl, resultados, indicador_resultados, 
            custo_por_resultados, alcance, impressoes, frequencia, cpm_brl, cliques_link, 
            ctr_link, visitas_perfil_instagram, conversas_mensagem_iniciadas, 
            custo_conversa_mensagem_brl, seguidores_instagram, usuario_upload_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt_insert = $conexao->prepare($sql);

        $registros_inseridos = 0;

        foreach ($dados as $linha) {
            // Pular linhas vazias
            if (empty(array_filter($linha))) {
                continue;
            }

            // Mapear dados das colunas (baseado na análise do Excel)
            $inicio_relatorios = !empty($linha[0]) ? date('Y-m-d', strtotime($linha[0])) : null;
            $termino_relatorios = !empty($linha[1]) ? date('Y-m-d', strtotime($linha[1])) : null;
            $nome_campanha_origem = $linha[2] ?? null;
            $data_criacao_campanha = !empty($linha[3]) ? date('Y-m-d', strtotime($linha[3])) : null;
            $veiculacao_campanha = $linha[4] ?? null;
            $orcamento_conjunto_anuncios = $linha[5] ?? null;
            $tipo_orcamento_conjunto_anuncios = $linha[6] ?? null;
            $valor_usado_brl = is_numeric($linha[7]) ? floatval($linha[7]) : null;
            $resultados = is_numeric($linha[8]) ? intval($linha[8]) : null;
            $indicador_resultados = $linha[9] ?? null;
            $custo_por_resultados = is_numeric($linha[10]) ? floatval($linha[10]) : null;
            $alcance = is_numeric($linha[11]) ? intval($linha[11]) : null;
            $impressoes = is_numeric($linha[12]) ? intval($linha[12]) : null;
            $frequencia = is_numeric($linha[13]) ? floatval($linha[13]) : null;
            $cpm_brl = is_numeric($linha[14]) ? floatval($linha[14]) : null;
            $cliques_link = is_numeric($linha[15]) ? intval($linha[15]) : null;
            $ctr_link = is_numeric($linha[16]) ? floatval($linha[16]) : null;
            $visitas_perfil_instagram = is_numeric($linha[17]) ? intval($linha[17]) : null;
            $conversas_mensagem_iniciadas = is_numeric($linha[18]) ? intval($linha[18]) : null;
            $custo_conversa_mensagem_brl = is_numeric($linha[19]) ? floatval($linha[19]) : null;
            $seguidores_instagram = is_numeric($linha[20]) ? intval($linha[20]) : null;

            $stmt_insert->bind_param(
                "issssssssissdiiddiiidi",
                $campanha_id, $inicio_relatorios, $termino_relatorios, $nome_campanha_origem,
                $data_criacao_campanha, $veiculacao_campanha, $orcamento_conjunto_anuncios,
                $tipo_orcamento_conjunto_anuncios, $valor_usado_brl, $resultados, $indicador_resultados,
                $custo_por_resultados, $alcance, $impressoes, $frequencia, $cpm_brl, $cliques_link,
                $ctr_link, $visitas_perfil_instagram, $conversas_mensagem_iniciadas,
                $custo_conversa_mensagem_brl, $seguidores_instagram, $usuario_id
            );

            if ($stmt_insert->execute()) {
                $registros_inseridos++;
            }
        }

        $conexao->commit();

        header('Location: ../public/visualizar_campanha.php?id=' . $campanha_id . '&sucesso=indicadores_carregados&registros=' . $registros_inseridos);

    } catch (Exception $e) {
        $conexao->rollback();
        error_log("Erro ao processar Excel: " . $e->getMessage());
        header('Location: ../public/visualizar_campanha.php?id=' . $campanha_id . '&erro=processamento');
    }

    $stmt->close();
    $conexao->close();
    exit();
}

// Se não for POST, redirecionar
header('Location: ../public/dashboard.php');
exit();
?>