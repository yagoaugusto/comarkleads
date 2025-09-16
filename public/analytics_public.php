<?php
$titulo_pagina = 'Analytics Público';
$esconder_nav = true; // This will hide the navigation bar
require_once '../templates/header.php';
require_once '../config/database.php';

// Validar token público
if (!isset($_GET['token'])) {
    echo '<div class="container mt-5"><div class="alert alert-danger">Token de acesso inválido.</div></div>';
    require_once '../templates/footer.php';
    exit();
}

$token = $_GET['token'];

// Buscar campanha pelo token
$stmt = $conexao->prepare("
    SELECT c.*, u.nome as usuario_nome 
    FROM campanhas c 
    JOIN usuarios u ON c.usuario_id = u.id 
    WHERE c.public_token = ? AND c.public_share_enabled = 1
");
$stmt->bind_param("s", $token);
$stmt->execute();
$resultado_campanha = $stmt->get_result();

if ($resultado_campanha->num_rows !== 1) {
    echo '<div class="container mt-5"><div class="alert alert-warning">
        <h4>Acesso Restrito</h4>
        <p>Este link de analytics não está disponível ou foi desativado pelo proprietário da campanha.</p>
    </div></div>';
    require_once '../templates/footer.php';
    exit();
}

$campanha = $resultado_campanha->fetch_assoc();
$campanha_id = $campanha['id'];

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
.public-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 3rem 0;
    margin-bottom: 2rem;
}

.public-badge {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    margin-bottom: 1rem;
    display: inline-block;
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

.watermark {
    text-align: center;
    margin-top: 2rem;
    color: #6c757d;
    font-size: 0.9rem;
}
</style>

<div class="public-header text-center">
    <div class="container">
        <div class="public-badge">
            <i class="bi bi-share me-2"></i>Relatório Público
        </div>
        <h1 class="mb-2">
            <i class="bi bi-bar-chart-line me-2"></i>
            Analytics Dashboard
        </h1>
        <h3 class="mb-2"><?php echo htmlspecialchars($campanha['nome_campanha']); ?></h3>
        <p class="opacity-75 mb-0">
            <i class="bi bi-person-circle me-1"></i>
            Compartilhado por: <?php echo htmlspecialchars($campanha['usuario_nome']); ?>
        </p>
    </div>
</div>

<?php if ($kpis['total_registros'] == 0): ?>
<div class="container">
    <div class="alert alert-info text-center">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Nenhum dado disponível</strong><br>
        Esta campanha ainda não possui dados de analytics carregados.
    </div>
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
        <div class="col-md-4">
            <div class="card kpi-card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-cursor text-danger" style="font-size: 2.5rem;"></i>
                    <h3 class="mt-2 mb-1"><?php echo number_format($kpis['total_cliques'] ?? 0, 0, ',', '.'); ?></h3>
                    <p class="text-muted">Cliques no Link</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card kpi-card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-graph-up text-success" style="font-size: 2.5rem;"></i>
                    <h3 class="mt-2 mb-1"><?php echo number_format($kpis['ctr_medio'] ?? 0, 2, ',', '.'); ?>%</h3>
                    <p class="text-muted">CTR Médio</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
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

    <!-- Summary Table -->
    <div class="chart-container">
        <h5 class="mb-3">
            <i class="bi bi-table me-2"></i>
            Resumo dos Dados
        </h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Período</th>
                        <th>Investimento</th>
                        <th>Alcance</th>
                        <th>Impressões</th>
                        <th>Cliques</th>
                        <th>CTR</th>
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
                        <td><?php echo number_format($ind['conversas_mensagem_iniciadas'] ?? 0, 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="watermark">
        <i class="bi bi-shield-check me-2"></i>
        Relatório gerado em <?php echo date('d/m/Y \à\s H:i'); ?> • 
        <strong>COMARK Leads</strong> - Sistema de Gestão de Campanhas
    </div>
</div>

<?php endif; ?>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Dados para os gráficos
const dadosGrafico = <?php echo json_encode($dados_grafico); ?>;

// Same chart configurations as the private dashboard
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
</script>

<?php
$stmt->close();
$stmt_indicadores->close();
$stmt_kpis->close();
$conexao->close();
require_once '../templates/footer.php';
?>