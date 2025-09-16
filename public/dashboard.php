<?php 
// LÓGICA DO DASHBOARD
$titulo_pagina = 'Dashboard';
require_once '../templates/header.php';
require_once '../config/database.php'; // Inclui a conexão

// Protege a página
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// --- BUSCA DE DADOS (KPIs) ---
// 1. Total de Campanhas
$query_total_campanhas = $conexao->query("SELECT COUNT(id) as total FROM campanhas WHERE usuario_id = $usuario_id");
$total_campanhas = $query_total_campanhas->fetch_assoc()['total'];

// 2. Total de Leads
$query_total_leads = $conexao->query("SELECT COUNT(l.id) as total FROM leads l JOIN campanhas c ON l.campanha_id = c.id WHERE c.usuario_id = $usuario_id");
$total_leads = $query_total_leads->fetch_assoc()['total'];

// 3. Leads com status 'Novo'
$query_novos_leads = $conexao->query("SELECT COUNT(l.id) as total FROM leads l JOIN campanhas c ON l.campanha_id = c.id WHERE c.usuario_id = $usuario_id AND l.status = 'Novo'");
$novos_leads = $query_novos_leads->fetch_assoc()['total'];

// 4. Leads 'Ganhos' (Conversão)
$query_leads_ganhos = $conexao->query("SELECT COUNT(l.id) as total FROM leads l JOIN campanhas c ON l.campanha_id = c.id WHERE c.usuario_id = $usuario_id AND l.status = 'Ganho'");
$leads_ganhos = $query_leads_ganhos->fetch_assoc()['total'];

// --- BUSCA DA LISTA DE CAMPANHAS ---
// Usamos LEFT JOIN para contar leads mesmo em campanhas que não têm nenhum.
$stmt_campanhas = $conexao->prepare("
    SELECT c.id, c.nome_campanha, c.data_criacao, COUNT(l.id) as total_leads
    FROM campanhas c
    LEFT JOIN leads l ON c.id = l.campanha_id
    WHERE c.usuario_id = ?
    GROUP BY c.id
    ORDER BY c.data_criacao DESC
");
$stmt_campanhas->bind_param("i", $usuario_id);
$stmt_campanhas->execute();
$resultado_campanhas = $stmt_campanhas->get_result();

?>

<?php if (isset($_GET['sucesso'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  Campanha criada com sucesso!
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>


<div class="row mb-4">
    <div class="col-md-3">
        <div class="card kpi-card shadow-sm">
            <div class="card-body">
                <i class="bi bi-megaphone text-primary me-3"></i>
                <div>
                    <h5 class="card-title"><?php echo $total_campanhas; ?></h5>
                    <p class="card-text">Campanhas</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card shadow-sm">
            <div class="card-body">
                <i class="bi bi-people-fill text-info me-3"></i>
                <div>
                    <h5 class="card-title"><?php echo $total_leads; ?></h5>
                    <p class="card-text">Leads Totais</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card shadow-sm">
            <div class="card-body">
                <i class="bi bi-person-plus-fill text-warning me-3"></i>
                <div>
                    <h5 class="card-title"><?php echo $novos_leads; ?></h5>
                    <p class="card-text">Novos Leads</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card shadow-sm">
            <div class="card-body">
                <i class="bi bi-trophy-fill text-success me-3"></i>
                <div>
                    <h5 class="card-title"><?php echo $leads_ganhos; ?></h5>
                    <p class="card-text">Leads Ganhos</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Minhas Campanhas</h4>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaCampanha">
            <i class="bi bi-plus-circle"></i> Criar Nova Campanha
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Nome da Campanha</th>
                        <th scope="col">Data de Criação</th>
                        <th scope="col" class="text-center">Leads</th>
                        <th scope="col" class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado_campanhas->num_rows > 0): ?>
                        <?php while($campanha = $resultado_campanhas->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($campanha['nome_campanha']); ?></strong></td>
                            <td><?php echo date('d/m/Y', strtotime($campanha['data_criacao'])); ?></td>
                            <td class="text-center"><span class="badge bg-secondary"><?php echo $campanha['total_leads']; ?></span></td>
                            <td class="text-end">
                                <a href="visualizar_campanha.php?id=<?php echo $campanha['id']; ?>" class="btn btn-info btn-sm">
                                    <i class="bi bi-eye"></i> Ver Leads
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-4">Nenhuma campanha encontrada. Comece criando uma!</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNovaCampanha" tabindex="-1" aria-labelledby="modalNovaCampanhaLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalNovaCampanhaLabel">Criar Nova Campanha</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="../app/criar_campanha.php" method="POST">
        <div class="modal-body">
            <div class="mb-3">
                <label for="nome_campanha" class="form-label">Nome da Campanha</label>
                <input type="text" class="form-control" id="nome_campanha" name="nome_campanha" required>
            </div>
            <div class="mb-3">
                <label for="descricao" class="form-label">Descrição (Opcional)</label>
                <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Salvar Campanha</button>
        </div>
      </form>
    </div>
  </div>
</div>


<?php 
$stmt_campanhas->close();
$conexao->close();
require_once '../templates/footer.php'; 
?>