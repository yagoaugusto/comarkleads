<?php
$titulo_pagina = 'Analytics Dashboard';
require_once '../templates/header.php';
require_once '../config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}
$usuario_id = $_SESSION['usuario_id'];

// Validar ID da campanha
if (!isset($_GET['campanha_id']) || !filter_var($_GET['campanha_id'], FILTER_VALIDATE_INT)) {
    header('Location: dashboard.php?erro=id_invalido');
    exit();
}
$campanha_id = $_GET['campanha_id'];

// Verificar se a campanha pertence ao usuário
$stmt = $conexao->prepare("SELECT * FROM campanhas WHERE id = ? AND usuario_id = ?");
$stmt->bind_param("ii", $campanha_id, $usuario_id);
$stmt->execute();
$resultado_campanha = $stmt->get_result();

if ($resultado_campanha->num_rows !== 1) {
    header('Location: dashboard.php?erro=acesso_negado');
    exit();
}
$campanha = $resultado_campanha->fetch_assoc();

// Buscar dados dos indicadores
$stmt_indicadores = $conexao->prepare("
    SELECT * FROM campanha_indicadores 
    WHERE campanha_id = ? 
    ORDER BY inicio_relatorios DESC
");
$stmt_indicadores->bind_param("i", $campanha_id);
$stmt_indicadores->execute();
$resultado_indicadores = $stmt_indicadores->get_result();

// Calcular KPIs agregados
$stmt_kpis = $conexao->prepare("
    SELECT 
        COUNT(*) as total_registros,
        SUM(valor_usado_brl) as total_investido,
        SUM(resultados) as total_resultados,
        SUM(alcance) as total_alcance,
        SUM(impressoes) as total_impressoes,
        SUM(cliques_link) as total_cliques,
        SUM(conversas_mensagem_iniciadas) as total_conversas,
        SUM(seguidores_instagram) as total_seguidores,
        AVG(cpm_brl) as cpm_medio,
        AVG(ctr_link) as ctr_medio,
        AVG(custo_por_resultados) as custo_medio_resultado
    FROM campanha_indicadores 
    WHERE campanha_id = ?
");
$stmt_kpis->bind_param("i", $campanha_id);
$stmt_kpis->execute();
$kpis = $stmt_kpis->get_result()->fetch_assoc();

// Dados para gráficos (JSON)
$dados_grafico = [];
$indicadores = $resultado_indicadores->fetch_all(MYSQLI_ASSOC);
foreach ($indicadores as $ind) {
    $dados_grafico[] = [
        'data' => $ind['inicio_relatorios'],
        'investimento' => floatval($ind['valor_usado_brl'] ?? 0),
        'alcance' => intval($ind['alcance'] ?? 0),
        'impressoes' => intval($ind['impressoes'] ?? 0),
        'cliques' => intval($ind['cliques_link'] ?? 0),
        'conversas' => intval($ind['conversas_mensagem_iniciadas'] ?? 0),
        'cpm' => floatval($ind['cpm_brl'] ?? 0),
        'ctr' => floatval($ind['ctr_link'] ?? 0)
    ];
}
?>

<style>
.analytics-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 10px;
}

.kpi-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.kpi-card:hover {
    transform: translateY(-5px);
}

.chart-container {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.filter-section {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}
</style>

<div class="analytics-header text-center">
    <div class="container">
        <h1 class="mb-2">
            <i class="bi bi-graph-up-arrow me-2"></i>
            Analytics Dashboard
        </h1>
        <h4 class="mb-0 opacity-75"><?php echo htmlspecialchars($campanha['nome_campanha']); ?></h4>
        <div class="mt-3">
            <a href="visualizar_campanha.php?id=<?php echo $campanha_id; ?>" class="btn btn-outline-light me-2">
                <i class="bi bi-arrow-left"></i> Voltar à Campanha
            </a>
            <button class="btn btn-light" onclick="gerarLinkPublico()" id="btnCompartilhar">
                <i class="bi bi-share"></i> Compartilhar
            </button>
        </div>
    </div>
</div>

<?php if ($kpis['total_registros'] == 0): ?>
<div class="alert alert-warning text-center">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Nenhum dado encontrado!</strong> 
    Faça upload dos indicadores da campanha para visualizar os analytics.
    <br><br>
    <a href="visualizar_campanha.php?id=<?php echo $campanha_id; ?>" class="btn btn-warning">
        <i class="bi bi-upload"></i> Carregar Indicadores
    </a>
</div>
<?php else: ?>

<!-- KPIs Section -->
<div class="container-fluid">
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card kpi-card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-currency-dollar text-success" style="font-size: 2.5rem;"></i>
                    <h3 class="mt-2 mb-1">R$ <?php echo number_format($kpis['total_investido'] ?? 0, 2, ',', '.'); ?></h3>
                    <p class="text-muted">Total Investido</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-people text-primary" style="font-size: 2.5rem;"></i>
                    <h3 class="mt-2 mb-1"><?php echo number_format($kpis['total_alcance'] ?? 0, 0, ',', '.'); ?></h3>
                    <p class="text-muted">Alcance Total</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-eye text-info" style="font-size: 2.5rem;"></i>
                    <h3 class="mt-2 mb-1"><?php echo number_format($kpis['total_impressoes'] ?? 0, 0, ',', '.'); ?></h3>
                    <p class="text-muted">Impressões</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-chat-dots text-warning" style="font-size: 2.5rem;"></i>
                    <h3 class="mt-2 mb-1"><?php echo number_format($kpis['total_conversas'] ?? 0, 0, ',', '.'); ?></h3>
                    <p class="text-muted">Conversas Iniciadas</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional KPIs Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card kpi-card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-cursor text-danger" style="font-size: 2.5rem;"></i>
                    <h3 class="mt-2 mb-1"><?php echo number_format($kpis['total_cliques'] ?? 0, 0, ',', '.'); ?></h3>
                    <p class="text-muted">Cliques no Link</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-instagram text-purple" style="font-size: 2.5rem;"></i>
                    <h3 class="mt-2 mb-1"><?php echo number_format($kpis['total_seguidores'] ?? 0, 0, ',', '.'); ?></h3>
                    <p class="text-muted">Novos Seguidores</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-graph-up text-success" style="font-size: 2.5rem;"></i>
                    <h3 class="mt-2 mb-1"><?php echo number_format($kpis['ctr_medio'] ?? 0, 2, ',', '.'); ?>%</h3>
                    <p class="text-muted">CTR Médio</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-coin text-info" style="font-size: 2.5rem;"></i>
                    <h3 class="mt-2 mb-1">R$ <?php echo number_format($kpis['cpm_medio'] ?? 0, 2, ',', '.'); ?></h3>
                    <p class="text-muted">CPM Médio</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row">
        <div class="col-12">
            <div class="chart-container">
                <h5 class="mb-3">
                    <i class="bi bi-bar-chart me-2"></i>
                    Performance ao Longo do Tempo
                </h5>
                <canvas id="performanceChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="chart-container">
                <h5 class="mb-3">
                    <i class="bi bi-pie-chart me-2"></i>
                    Distribuição do Investimento
                </h5>
                <canvas id="investmentChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <h5 class="mb-3">
                    <i class="bi bi-graph-down me-2"></i>
                    Funil de Conversão
                </h5>
                <canvas id="funnelChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="chart-container">
        <h5 class="mb-3">
            <i class="bi bi-table me-2"></i>
            Dados Detalhados
            <button class="btn btn-sm btn-outline-success float-end" onclick="exportarDados()">
                <i class="bi bi-download"></i> Exportar
            </button>
        </h5>
        <div class="table-responsive">
            <table class="table table-hover" id="analyticsTable">
                <thead class="table-light">
                    <tr>
                        <th>Período</th>
                        <th>Investimento</th>
                        <th>Alcance</th>
                        <th>Impressões</th>
                        <th>Cliques</th>
                        <th>CTR</th>
                        <th>CPM</th>
                        <th>Conversas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($indicadores as $ind): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($ind['inicio_relatorios'] ?? '')); ?></td>
                        <td>R$ <?php echo number_format($ind['valor_usado_brl'] ?? 0, 2, ',', '.'); ?></td>
                        <td><?php echo number_format($ind['alcance'] ?? 0, 0, ',', '.'); ?></td>
                        <td><?php echo number_format($ind['impressoes'] ?? 0, 0, ',', '.'); ?></td>
                        <td><?php echo number_format($ind['cliques_link'] ?? 0, 0, ',', '.'); ?></td>
                        <td><?php echo number_format($ind['ctr_link'] ?? 0, 2, ',', '.'); ?>%</td>
                        <td>R$ <?php echo number_format($ind['cpm_brl'] ?? 0, 2, ',', '.'); ?></td>
                        <td><?php echo number_format($ind['conversas_mensagem_iniciadas'] ?? 0, 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Dados para os gráficos
const dadosGrafico = <?php echo json_encode($dados_grafico); ?>;

// Configuração do gráfico de performance
const ctxPerformance = document.getElementById('performanceChart').getContext('2d');
const performanceChart = new Chart(ctxPerformance, {
    type: 'line',
    data: {
        labels: dadosGrafico.map(d => {
            const date = new Date(d.data);
            return date.toLocaleDateString('pt-BR');
        }),
        datasets: [
            {
                label: 'Investimento (R$)',
                data: dadosGrafico.map(d => d.investimento),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                yAxisID: 'y'
            },
            {
                label: 'Alcance',
                data: dadosGrafico.map(d => d.alcance),
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                yAxisID: 'y1'
            },
            {
                label: 'Conversas',
                data: dadosGrafico.map(d => d.conversas),
                borderColor: 'rgb(255, 205, 86)',
                backgroundColor: 'rgba(255, 205, 86, 0.2)',
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});

// Gráfico de distribuição do investimento
const ctxInvestment = document.getElementById('investmentChart').getContext('2d');
const investmentChart = new Chart(ctxInvestment, {
    type: 'doughnut',
    data: {
        labels: dadosGrafico.map(d => {
            const date = new Date(d.data);
            return date.toLocaleDateString('pt-BR');
        }),
        datasets: [{
            data: dadosGrafico.map(d => d.investimento),
            backgroundColor: [
                '#FF6384',
                '#36A2EB',
                '#FFCE56',
                '#4BC0C0',
                '#9966FF',
                '#FF9F40'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    }
});

// Gráfico de funil
const ctxFunnel = document.getElementById('funnelChart').getContext('2d');
const totalImpressions = dadosGrafico.reduce((sum, d) => sum + d.impressoes, 0);
const totalClicks = dadosGrafico.reduce((sum, d) => sum + d.cliques, 0);
const totalConversations = dadosGrafico.reduce((sum, d) => sum + d.conversas, 0);

const funnelChart = new Chart(ctxFunnel, {
    type: 'bar',
    data: {
        labels: ['Impressões', 'Cliques', 'Conversas'],
        datasets: [{
            label: 'Funil de Conversão',
            data: [totalImpressions, totalClicks, totalConversations],
            backgroundColor: [
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

function gerarLinkPublico() {
    const btn = document.getElementById('btnCompartilhar');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="bi bi-spinner-border-sm spinner-border"></i> Gerando...';
    btn.disabled = true;
    
    fetch('../app/gerar_link_publico.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'campanha_id=<?php echo $campanha_id; ?>'
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.success) {
            // Create modal with the public link
            const modalHtml = `
                <div class="modal fade" id="modalLinkPublico" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="bi bi-share me-2"></i>Link Público Gerado</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p><strong>Seu link público está pronto!</strong></p>
                                <p>Compartilhe este link para que outras pessoas possam visualizar os analytics desta campanha sem precisar fazer login:</p>
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="linkPublico" value="${data.public_url}" readonly>
                                    <button class="btn btn-outline-secondary" type="button" onclick="copiarLink()">
                                        <i class="bi bi-copy"></i> Copiar
                                    </button>
                                </div>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Importante:</strong> Qualquer pessoa com este link poderá visualizar os dados desta campanha.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                <a href="${data.public_url}" target="_blank" class="btn btn-primary">
                                    <i class="bi bi-box-arrow-up-right"></i> Abrir Link
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('modalLinkPublico');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('modalLinkPublico'));
            modal.show();
        } else {
            alert('Erro ao gerar link público: ' + data.message);
        }
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Erro ao comunicar com o servidor');
    });
}

function copiarLink() {
    const linkInput = document.getElementById('linkPublico');
    linkInput.select();
    linkInput.setSelectionRange(0, 99999);
    
    try {
        document.execCommand('copy');
        
        // Change button text temporarily
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check"></i> Copiado!';
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-success');
        
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        }, 2000);
    } catch (err) {
        alert('Erro ao copiar link. Selecione o texto e copie manualmente.');
    }
}

function exportarDados() {
    // Implementar exportação de dados
    window.location.href = '../app/exportar_analytics.php?campanha_id=<?php echo $campanha_id; ?>';
}
</script>

<?php
$stmt->close();
$stmt_indicadores->close();
$stmt_kpis->close();
$conexao->close();
require_once '../templates/footer.php';
?>