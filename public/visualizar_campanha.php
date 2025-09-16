<?php
$titulo_pagina = 'Detalhes da Campanha';
require_once '../templates/header.php';
require_once '../config/database.php';

// 1. VERIFICA SE O USUÁRIO ESTÁ LOGADO
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}
$usuario_id = $_SESSION['usuario_id'];

// 2. VALIDA O ID DA CAMPANHA E VERIFICA SE PERTENCE AO USUÁRIO
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header('Location: dashboard.php?erro=id_invalido');
    exit();
}
$campanha_id = $_GET['id'];

$stmt = $conexao->prepare("SELECT * FROM campanhas WHERE id = ? AND usuario_id = ?");
$stmt->bind_param("ii", $campanha_id, $usuario_id);
$stmt->execute();
$resultado_campanha = $stmt->get_result();

if ($resultado_campanha->num_rows !== 1) {
    // Se não encontrou a campanha ou ela não pertence ao usuário, volta ao dashboard
    header('Location: dashboard.php?erro=acesso_negado');
    exit();
}
$campanha = $resultado_campanha->fetch_assoc();

// 3. BUSCA OS LEADS ASSOCIADOS A ESTA CAMPANHA
$stmt_leads = $conexao->prepare("SELECT * FROM leads WHERE campanha_id = ? ORDER BY data_criacao DESC");
$stmt_leads->bind_param("i", $campanha_id);
$stmt_leads->execute();
$resultado_leads = $stmt_leads->get_result();

// Função para definir a cor do badge de status
function get_status_badge($status)
{
    switch ($status) {
        case 'Novo':
            return 'bg-warning text-dark';
        case 'Contatado':
            return 'bg-info text-dark';
        case 'Qualificado':
            return 'bg-primary';
        case 'Proposta Enviada':
            return 'bg-primary';
        case 'Ganho':
            return 'bg-success';
        case 'Perdido':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}
?>

<?php if (isset($_GET['sucesso'])): ?>
    <?php if ($_GET['sucesso'] == 'indicadores_carregados'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <strong>Indicadores carregados com sucesso!</strong> 
            <?php if (isset($_GET['registros'])): ?>
                <?php echo intval($_GET['registros']); ?> registros foram importados.
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if (isset($_GET['erro'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Erro:</strong> 
        <?php 
        switch($_GET['erro']) {
            case 'arquivo_invalido':
                echo 'Arquivo não foi enviado corretamente.';
                break;
            case 'formato_invalido':
                echo 'Formato de arquivo inválido. Use apenas .xlsx ou .xls';
                break;
            case 'arquivo_muito_grande':
                echo 'Arquivo muito grande. Tamanho máximo: 10MB';
                break;
            case 'processamento':
                echo 'Erro ao processar o arquivo Excel. Verifique o formato dos dados.';
                break;
            case 'acesso_negado':
                echo 'Acesso negado.';
                break;
            default:
                echo 'Erro desconhecido.';
        }
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($campanha['nome_campanha']); ?></h1>
            <p class="mb-0 text-muted"><?php echo htmlspecialchars($campanha['descricao']); ?></p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-code-slash me-2"></i> Documentação para Integração de Formulário</h5>
        </div>
        <div class="card-body">
            <p>Para capturar leads do seu site diretamente para esta campanha, configure seu formulário HTML para enviar os dados para o nosso endpoint usando o método <strong>POST</strong>.</p>

            <div class="mb-4">
                <label class="form-label fw-bold">URL do Endpoint:</label>
                <input type="text" class="form-control bg-light" value="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/api/capture.php'; ?>" readonly>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Sua Chave de API Secreta (adicionar como campo oculto):</label>

                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($campanha['api_key']); ?>" readonly>

            </div>

            <h6 class="fw-bold">Campos Disponíveis para Captura</h6>

            <p>Para que a captura funcione, o atributo <code>name</code> de cada campo (<code>&lt;input&gt;</code>, <code>&lt;select&gt;</code>, etc.) no seu formulário deve corresponder <strong>exatamente</strong> ao que está listado na tabela abaixo.</p>

            <div class="table-responsive mb-4">
                <table class="table table-bordered table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Atributo 'name'</th>
                            <th>Obrigatoriedade</th>
                            <th>Descrição</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>api_key</code></td>
                            <td><span class="badge bg-danger">Obrigatório</span></td>
                            <td>Sua chave secreta da campanha. Use um campo do tipo 'hidden'.</td>
                        </tr>
                        <tr>
                            <td><code>nome</code></td>
                            <td><span class="badge bg-danger">Obrigatório</span></td>
                            <td>O nome completo do lead.</td>
                        </tr>
                        <tr>
                            <td><code>email</code></td>
                            <td><span class="badge bg-success">Opcional</span></td>
                            <td>O endereço de e-mail do lead.</td>
                        </tr>
                        <tr>
                            <td><code>whatsapp</code></td>
                            <td><span class="badge bg-success">Opcional</span></td>
                            <td>Número do WhatsApp, idealmente com código do país (ex: 5585999999999).</td>
                        </tr>
                        <tr>
                            <td><code>empresa</code></td>
                            <td><span class="badge bg-success">Opcional</span></td>
                            <td>O nome da empresa do lead.</td>
                        </tr>
                        <tr>
                            <td><code>url_social</code></td>
                            <td><span class="badge bg-success">Opcional</span></td>
                            <td>Link do site, Instagram, LinkedIn ou outra rede social.</td>
                        </tr>
                        <tr>
                            <td><code>qtd_funcionarios</code></td>
                            <td><span class="badge bg-success">Opcional</span></td>
                            <td>
                                O texto exato de uma das opções: '1-5', '5-10', '10-50', '50-100', '100+'.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- <h6 class="fw-bold">Exemplo Completo de Código HTML</h6>
    <p>Você pode usar este código como base para o seu formulário:</p>
    <pre class="bg-light p-3 rounded"><code></code></pre> -->
        </div>
    </div>


    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Leads da Campanha</h5>
            <div class="btn-group"> 
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNovoLead">
                    <i class="bi bi-plus-circle"></i> Adicionar Lead
                </button>
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalCarregarIndicadores">
                    <i class="bi bi-graph-up"></i> CARREGAR INDICADORES
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Ações em Massa</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalWhatsappMassa">
                            <i class="bi bi-whatsapp me-2"></i>Disparar WhatsApp em Massa
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="../app/exportar_excel.php?campanha_id=<?php echo $campanha_id; ?>">
                            <i class="bi bi-file-earmark-excel me-2"></i>Exportar para Excel
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="analytics_dashboard.php?campanha_id=<?php echo $campanha_id; ?>">
                            <i class="bi bi-bar-chart me-2"></i>Ver Analytics / BI
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Contato</th>
                            <th class="text-center">Status</th>
                            <th>Data de Criação</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado_leads->num_rows > 0): ?>
                            <?php while ($lead = $resultado_leads->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($lead['nome_lead']); ?></strong></td>
                                    <td>
                                        <?php if ($lead['email']): ?>
                                            <small class="d-block"><?php echo htmlspecialchars($lead['email']); ?></small>
                                        <?php endif; ?>
                                        <?php if ($lead['whatsapp']): ?>
                                            <small class="d-block"><?php echo htmlspecialchars($lead['whatsapp']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo get_status_badge($lead['status']); ?>"><?php echo $lead['status']; ?></span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($lead['data_criacao'])); ?></td>
                                    <td class="text-end">
                                        <a href="editar_lead.php?id=<?php echo $lead['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil-square"></i> Editar
                                        </a>
                                        <a href="../app/excluir_lead.php?id=<?php echo $lead['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tem certeza que deseja excluir este lead?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">Nenhum lead encontrado nesta campanha.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="modalNovoLead" tabindex="-1" aria-labelledby="modalNovoLeadLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovoLeadLabel">Adicionar Novo Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="../app/adicionar_lead.php" method="POST">
                <input type="hidden" name="campanha_id" value="<?php echo $campanha_id; ?>">

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nome_lead" class="form-label">Nome Completo</label>
                            <input type="text" class="form-control" id="nome_lead" name="nome_lead" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="whatsapp" class="form-label">WhatsApp</label>
                            <input type="text" class="form-control" id="whatsapp" name="whatsapp">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="empresa" class="form-label">Empresa</label>
                            <input type="text" class="form-control" id="empresa" name="empresa">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="url_social" class="form-label">Instagram ou Site</label>
                        <input type="text" class="form-control" id="url_social" name="url_social">
                    </div>
                    <div class="mb-3">
                        <label for="qtd_funcionarios" class="form-label">Quantidade de Funcionários</label>
                        <select class="form-select" id="qtd_funcionarios" name="qtd_funcionarios">
                            <option value="">Selecione...</option>
                            <option value="1-5">1-5</option>
                            <option value="5-10">5-10</option>
                            <option value="10-50">10-50</option>
                            <option value="50-100">50-100</option>
                            <option value="100+">100+</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalWhatsappMassa" tabindex="-1" aria-labelledby="modalWhatsappMassaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalWhatsappMassaLabel">Disparar Mensagem em Massa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="../app/disparar_whatsapp.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="campanha_id" value="<?php echo $campanha_id; ?>">
                    <div class="mb-3">
                        <label for="mensagem_whatsapp" class="form-label">Mensagem:</label>
                        <textarea class="form-control" id="mensagem_whatsapp" name="mensagem" rows="6" required placeholder="Escreva sua mensagem aqui. Ex: Olá, {nome}! Vimos que você se interessou pela nossa campanha."></textarea>
                        <div class="form-text">
                            Você pode usar a variável <code>{nome}</code> para personalizar a mensagem com o nome do lead.
                        </div>
                    </div>
                    <div class="alert alert-warning">
                        <strong>Atenção:</strong> O disparo em massa pode levar ao bloqueio do seu número pelo WhatsApp. Use com responsabilidade.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Iniciar Disparo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for uploading campaign indicators -->
<div class="modal fade" id="modalCarregarIndicadores" tabindex="-1" aria-labelledby="modalCarregarIndicadoresLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCarregarIndicadoresLabel">
                    <i class="bi bi-graph-up me-2"></i>Carregar Indicadores da Campanha
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="../app/upload_indicadores.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="campanha_id" value="<?php echo $campanha_id; ?>">
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Instruções:</strong> Faça upload do arquivo Excel com os dados dos indicadores da campanha. 
                        O arquivo deve seguir o mesmo formato do arquivo de exemplo <code>campanha.xlsx</code>.
                    </div>
                    
                    <div class="mb-3">
                        <label for="arquivo_excel" class="form-label">Arquivo Excel</label>
                        <input type="file" class="form-control" id="arquivo_excel" name="arquivo_excel" 
                               accept=".xlsx,.xls" required>
                        <div class="form-text">
                            Formatos aceitos: Excel (.xlsx, .xls). Tamanho máximo: 10MB.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descricao_upload" class="form-label">Descrição (opcional)</label>
                        <textarea class="form-control" id="descricao_upload" name="descricao_upload" 
                                  rows="2" placeholder="Adicione uma descrição para este conjunto de dados..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-upload me-2"></i>Carregar Indicadores
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php
$stmt->close();
$stmt_leads->close();
$conexao->close();
require_once '../templates/footer.php';
?>