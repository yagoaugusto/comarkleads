<?php
$titulo_pagina = 'Analytics Dashboard';
require_once '../templates/header.php';
require_once '../config/database.php';

// Autenticação
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}
$usuario_id = (int)$_SESSION['usuario_id'];

// campanha_id
if (!isset($_GET['campanha_id']) || !filter_var($_GET['campanha_id'], FILTER_VALIDATE_INT)) {
    header('Location: dashboard.php?erro=id_invalido');
    exit();
}
$campanha_id = (int)$_GET['campanha_id'];

// Competência (YYYY-MM) baseada em data_criacao_campanha
$competencia_inicio = isset($_GET['competencia_inicio']) ? trim($_GET['competencia_inicio']) : '';
$competencia_fim = isset($_GET['competencia_fim']) ? trim($_GET['competencia_fim']) : '';

$validaComp = function ($c) {
    if ($c === '' || $c === null) return false;
    return (bool)preg_match('/^\d{4}-\d{2}$/', $c);
};

$hasCompInicio = $validaComp($competencia_inicio);
$hasCompFim = $validaComp($competencia_fim);

$compInicioData = null;
$compFimData = null;
if ($hasCompInicio) {
    $compInicioData = $competencia_inicio . '-01';
}
if ($hasCompFim) {
    $dtFim = DateTime::createFromFormat('Y-m-d', $competencia_fim . '-01');
    if ($dtFim) {
        $dtFim->modify('last day of this month');
        $compFimData = $dtFim->format('Y-m-d');
    }
}

// Verificar campanha do usuário
$stmt = $conexao->prepare('SELECT * FROM campanhas WHERE id = ? AND usuario_id = ?');
$stmt->bind_param('ii', $campanha_id, $usuario_id);
$stmt->execute();
$campanhaRes = $stmt->get_result();
if ($campanhaRes->num_rows !== 1) {
    header('Location: dashboard.php?erro=acesso_negado');
    exit();
}
$campanha = $campanhaRes->fetch_assoc();

// Competências disponíveis (YYYY-MM) para o dropdown
$competenciasDisponiveis = [];
$stmt_comp = $conexao->prepare("SELECT DISTINCT DATE_FORMAT(data_criacao_campanha, '%Y-%m') AS comp FROM campanha_indicadores WHERE campanha_id = ? ORDER BY comp DESC");
$stmt_comp->bind_param('i', $campanha_id);
$stmt_comp->execute();
$res_comp = $stmt_comp->get_result();
while ($row = $res_comp->fetch_assoc()) { $competenciasDisponiveis[] = $row['comp']; }
$stmt_comp->close();

// Preferências adicionais de filtro
$campanhasSelecionadas = isset($_GET['campanhas']) ? (array)$_GET['campanhas'] : [];
$hideZero = (isset($_GET['hide_zero']) && $_GET['hide_zero'] === '1');

// Buscar indicadores com filtro de competência
if ($hasCompInicio && $hasCompFim) {
    $stmt_ind = $conexao->prepare('SELECT * FROM campanha_indicadores WHERE campanha_id = ? AND data_criacao_campanha BETWEEN ? AND ? ORDER BY nome_campanha_origem ASC');
    $stmt_ind->bind_param('iss', $campanha_id, $compInicioData, $compFimData);
} elseif ($hasCompInicio) {
    $stmt_ind = $conexao->prepare('SELECT * FROM campanha_indicadores WHERE campanha_id = ? AND data_criacao_campanha >= ? ORDER BY nome_campanha_origem ASC');
    $stmt_ind->bind_param('is', $campanha_id, $compInicioData);
} elseif ($hasCompFim) {
    $stmt_ind = $conexao->prepare('SELECT * FROM campanha_indicadores WHERE campanha_id = ? AND data_criacao_campanha <= ? ORDER BY nome_campanha_origem ASC');
    $stmt_ind->bind_param('is', $campanha_id, $compFimData);
} else {
    $stmt_ind = $conexao->prepare('SELECT * FROM campanha_indicadores WHERE campanha_id = ? ORDER BY data_criacao_campanha DESC');
    $stmt_ind->bind_param('i', $campanha_id);
}
$stmt_ind->execute();
$indicadoresRes = $stmt_ind->get_result();
$indicadores = $indicadoresRes->fetch_all(MYSQLI_ASSOC);

// Lista de campanhas disponíveis (após filtro de competência)
$listaCampanhas = [];
foreach ($indicadores as $ind) {
  $nm = trim($ind['nome_campanha_origem'] ?? '');
  if ($nm === '') { $nm = 'Campanha (sem nome)'; }
  $listaCampanhas[$nm] = true;
}
$listaCampanhas = array_keys($listaCampanhas);

// Agregar por campanha com filtros adicionais
$agg = [];
foreach ($indicadores as $ind) {
    $nome = trim($ind['nome_campanha_origem'] ?? '');
    if ($nome === '') $nome = 'Campanha (sem nome)';
    // Se houver seleção de campanhas, filtrar
    if (!empty($campanhasSelecionadas) && !in_array($nome, $campanhasSelecionadas, true)) {
        continue;
    }
    if (!isset($agg[$nome])) {
        $agg[$nome] = [
            'investimento' => 0.0,
            'alcance' => 0,
            'impressoes' => 0,
            'cliques' => 0,
            'conversas' => 0,
      'resultados' => 0,
      'seguidores' => 0,
        ];
    }
    $agg[$nome]['investimento'] += (float)($ind['valor_usado_brl'] ?? 0);
    $agg[$nome]['alcance'] += (int)($ind['alcance'] ?? 0);
    $agg[$nome]['impressoes'] += (int)($ind['impressoes'] ?? 0);
    $agg[$nome]['cliques'] += (int)($ind['cliques_link'] ?? 0);
    $agg[$nome]['conversas'] += (int)($ind['conversas_mensagem_iniciadas'] ?? 0);
    $agg[$nome]['resultados'] += (int)($ind['resultados'] ?? 0);
  $agg[$nome]['seguidores'] += (int)($ind['seguidores_instagram'] ?? 0);
}

// Se solicitado, remover campanhas sem investimento
if ($hideZero) {
  foreach ($agg as $nm => $c) { if (($c['investimento'] ?? 0) <= 0) unset($agg[$nm]); }
}

// Ordenar por investimento desc
uksort($agg, function($a, $b) use ($agg) { return $agg[$b]['investimento'] <=> $agg[$a]['investimento']; });

// Métricas derivadas e medianas
$cprList = [];
$convList = [];
foreach ($agg as $nome => &$c) {
    $c['cpr'] = ($c['resultados'] > 0) ? ($c['investimento'] / $c['resultados']) : null;
    $c['conv_rate'] = ($c['cliques'] > 0) ? ($c['conversas'] / $c['cliques']) * 100.0 : null;
    if (!is_null($c['cpr'])) $cprList[] = $c['cpr'];
    if (!is_null($c['conv_rate'])) $convList[] = $c['conv_rate'];
}
unset($c);

$median = function(array $arr) { $n = count($arr); if ($n === 0) return null; sort($arr); $mid = intdiv($n, 2); return ($n % 2 === 0) ? (($arr[$mid-1] + $arr[$mid]) / 2.0) : $arr[$mid]; };
$medCPR = $median($cprList);
$medConv = $median($convList);

// Classificação
$classificacoes = [];
foreach ($agg as $nome => $c) {
    $classe = 'Oportunidade';
    $badge = 'secondary';
    $lowVolume = ($c['cliques'] < 30) || ($c['investimento'] < 50);
    if ($lowVolume) { $classe = 'Baixo Volume'; $badge = 'warning'; }
    else {
        $okCPR = (!is_null($c['cpr']) && !is_null($medCPR)) ? ($c['cpr'] <= $medCPR) : false;
        $okConv = (!is_null($c['conv_rate']) && !is_null($medConv)) ? ($c['conv_rate'] >= $medConv) : false;
        if ($okCPR && $okConv) { $classe = 'Alta Eficiência'; $badge = 'success'; }
        else if ($okCPR || $okConv) { $classe = 'Equilíbrio'; $badge = 'info'; }
        else { $classe = 'Oportunidade'; $badge = 'danger'; }
    }
    $classificacoes[$nome] = ['classe' => $classe, 'badge' => $badge];
}

// KPIs agregados (com base nas campanhas filtradas e visíveis em $agg)
$totInvest = 0.0; $totAlc = 0; $totImp = 0; $totCli = 0; $totConv = 0; $totRes = 0; $totSeg = 0;
foreach ($agg as $c) {
  $totInvest += (float)($c['investimento'] ?? 0);
  $totAlc   += (int)($c['alcance'] ?? 0);
  $totImp   += (int)($c['impressoes'] ?? 0);
  $totCli   += (int)($c['cliques'] ?? 0);
  $totConv  += (int)($c['conversas'] ?? 0);
  $totRes   += (int)($c['resultados'] ?? 0);
  $totSeg   += (int)($c['seguidores'] ?? 0);
}
$ctrMed = ($totImp > 0) ? ($totCli / $totImp) * 100.0 : 0.0;
$cpmMed = ($totImp > 0) ? ($totInvest / $totImp) * 1000.0 : 0.0;
$kpis = [
  'total_registros' => count($agg),
  'total_investido' => $totInvest,
  'total_alcance' => $totAlc,
  'total_impressoes' => $totImp,
  'total_cliques' => $totCli,
  'total_conversas' => $totConv,
  'total_resultados' => $totRes,
  'total_seguidores' => $totSeg,
  'ctr_medio' => $ctrMed,
  'cpm_medio' => $cpmMed,
];

// Preparar dados para gráficos sem usar arrow functions (compatibilidade)
$labels = array_keys($agg);
$vals = array_values($agg);
$invArr = [];$alcArr = [];$impArr = [];$cliArr = [];$convArr = [];$resArr = [];$cprArr = [];$crArr = [];
foreach ($vals as $c) {
  $invArr[] = round($c['investimento'], 2);
  $alcArr[] = (int)$c['alcance'];
  $impArr[] = (int)$c['impressoes'];
  $cliArr[] = (int)$c['cliques'];
  $convArr[] = (int)$c['conversas'];
  $resArr[] = (int)$c['resultados'];
  $cprArr[] = is_null($c['cpr']) ? null : round($c['cpr'], 2);
  $crArr[] = is_null($c['conv_rate']) ? null : round($c['conv_rate'], 2);
}

$dados_campanha = [
  'labels' => $labels,
  'investimento' => $invArr,
  'alcance' => $alcArr,
  'impressoes' => $impArr,
  'cliques' => $cliArr,
  'conversas' => $convArr,
  'resultados' => $resArr,
  'cpr' => $cprArr,
  'conv_rate' => $crArr,
];
?>

<style>
.analytics-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem 0; margin-bottom: 2rem; border-radius: 10px; }
.kpi-card { border: none; border-radius: 16px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); transition: transform 0.25s ease, box-shadow 0.25s ease; overflow: hidden; background: #fff; }
.kpi-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,0.10); }
.chart-container { background: white; border-radius: 15px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 2rem; }
.filter-section { background: white; border-radius: 15px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 2rem; }
.chart-container .chart-title { display:flex; align-items:center; justify-content:space-between; }
.chart-fixed { height: 340px; }
.kpi-value { font-size: 2rem; font-weight: 800; margin: .25rem 0 0; letter-spacing: -0.02em; color: #111827; text-align: center; }
.kpi-card .card-body { display: grid; place-items: center; padding: 1.25rem 1rem; }
.kpi-header { display:flex; align-items:center; justify-content:center; gap:.6rem; padding:.65rem 1rem; background: #f8fafc; border-bottom: 1px solid #eef2f7; }
.kpi-card .kpi-header { border-top-left-radius: 16px; border-top-right-radius: 16px; }
.kpi-title { font-weight: 600; color: #475569; margin: 0; font-size: .95rem; }
.kpi-icon { width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--kpi-color, #0d6efd); background: var(--kpi-bg, rgba(13,110,253,.12)); flex: 0 0 32px; line-height: 0; }
.kpi-icon i { display: block; line-height: 1; font-size: 18px; color: inherit; }
@media (min-width: 992px) {
  .kpi-value { font-size: 2.1rem; }
  .kpi-icon { width: 36px; height: 36px; flex-basis: 36px; }
  .kpi-icon i { font-size: 20px; }
}
/* Temas suaves por KPI */
.kpi-success { --kpi-color: #198754; --kpi-bg: rgba(25,135,84,.12); }
.kpi-primary { --kpi-color: #0d6efd; --kpi-bg: rgba(13,110,253,.12); }
.kpi-info { --kpi-color: #0dcaf0; --kpi-bg: rgba(13,202,240,.12); }
.kpi-danger { --kpi-color: #dc3545; --kpi-bg: rgba(220,53,69,.12); }
.kpi-teal { --kpi-color: #20c997; --kpi-bg: rgba(32,201,151,.12); }
.kpi-pink { --kpi-color: #C13584; --kpi-bg: rgba(193,53,132,.12); }
</style>

<div class="analytics-header text-center">
  <div class="container">
    <h1 class="mb-2"><i class="bi bi-graph-up-arrow me-2"></i>COMARK Dashboard</h1>
    <h4 class="mb-0 opacity-75"><?php echo htmlspecialchars($campanha['nome_campanha']); ?></h4>
    <div class="mt-3">
      <a href="visualizar_campanha.php?id=<?php echo $campanha_id; ?>" class="btn btn-outline-light me-2"><i class="bi bi-arrow-left"></i> Voltar à Campanha</a>
      <button class="btn btn-light" onclick="gerarLinkPublico()" id="btnCompartilhar"><i class="bi bi-share"></i> Compartilhar</button>
    </div>
  </div>
</div>

<!-- Filtros -->
<div class="container">
  <div class="filter-section">
    <form class="row g-3 align-items-end" method="get" action="analytics_dashboard.php">
      <input type="hidden" name="campanha_id" value="<?php echo htmlspecialchars($campanha_id); ?>">
      <div class="col-md-4">
        <label for="competencia_inicio" class="form-label">Competência inicial</label>
        <select id="competencia_inicio" name="competencia_inicio" class="form-select">
          <option value="">Todas</option>
          <?php foreach ($competenciasDisponiveis as $comp): ?>
            <option value="<?php echo htmlspecialchars($comp); ?>" <?php echo ($hasCompInicio && $competencia_inicio===$comp)?'selected':''; ?>><?php echo htmlspecialchars($comp); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label for="competencia_fim" class="form-label">Competência final</label>
        <select id="competencia_fim" name="competencia_fim" class="form-select">
          <option value="">Todas</option>
          <?php foreach ($competenciasDisponiveis as $comp): ?>
            <option value="<?php echo htmlspecialchars($comp); ?>" <?php echo ($hasCompFim && $competencia_fim===$comp)?'selected':''; ?>><?php echo htmlspecialchars($comp); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label for="campanhas" class="form-label">Campanhas</label>
        <select id="campanhas" name="campanhas[]" class="form-select" multiple>
          <?php foreach ($listaCampanhas as $nm): ?>
            <option value="<?php echo htmlspecialchars($nm); ?>" <?php echo (!empty($campanhasSelecionadas) && in_array($nm,$campanhasSelecionadas,true))?'selected':''; ?>><?php echo htmlspecialchars($nm); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6 d-flex align-items-center">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="hide_zero" name="hide_zero" value="1" <?php echo $hideZero?'checked':''; ?>>
          <label class="form-check-label" for="hide_zero">Ocultar campanhas com investimento zero</label>
        </div>
      </div>
      <div class="col-md-6 text-end">
        <button type="submit" class="btn btn-primary me-2"><i class="bi bi-funnel"></i> Aplicar</button>
        <a href="analytics_dashboard.php?campanha_id=<?php echo urlencode($campanha_id); ?>" class="btn btn-outline-secondary">Limpar</a>
      </div>
    </form>
  </div>
</div>

<?php if (($kpis['total_registros'] ?? 0) == 0): ?>
<div class="alert alert-warning text-center">
  <i class="bi bi-exclamation-triangle me-2"></i>
  <strong>Nenhum dado encontrado!</strong> Faça upload dos indicadores da campanha para visualizar os analytics.
  <br><br>
  <a href="visualizar_campanha.php?id=<?php echo $campanha_id; ?>" class="btn btn-warning"><i class="bi bi-upload"></i> Carregar Indicadores</a>
</div>
<?php else: ?>

<!-- KPIs -->
<div class="container-fluid">
  <div class="row g-4 mb-4">
    <div class="col-md-3">
      <div class="card kpi-card h-100">
        <div class="card-header kpi-header kpi-success"><span class="kpi-icon"><i class="bi bi-currency-dollar"></i></span><span class="kpi-title">Total Investido</span></div>
        <div class="card-body text-center">
          <div class="kpi-value">R$ <?php echo number_format($kpis['total_investido'] ?? 0, 2, ',', '.'); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card kpi-card h-100">
        <div class="card-header kpi-header kpi-primary"><span class="kpi-icon"><i class="bi bi-people"></i></span><span class="kpi-title">Alcance Total</span></div>
        <div class="card-body text-center">
          <div class="kpi-value"><?php echo number_format($kpis['total_alcance'] ?? 0, 0, ',', '.'); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card kpi-card h-100">
        <div class="card-header kpi-header kpi-info"><span class="kpi-icon"><i class="bi bi-eye"></i></span><span class="kpi-title">Impressões</span></div>
        <div class="card-body text-center">
          <div class="kpi-value"><?php echo number_format($kpis['total_impressoes'] ?? 0, 0, ',', '.'); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card kpi-card h-100">
        <div class="card-header kpi-header kpi-danger"><span class="kpi-icon"><i class="bi bi-cursor"></i></span><span class="kpi-title">Cliques</span></div>
        <div class="card-body text-center">
          <div class="kpi-value"><?php echo number_format($kpis['total_cliques'] ?? 0, 0, ',', '.'); ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="row g-4 mb-4">
    <div class="col-md-3">
      <div class="card kpi-card h-100">
        <div class="card-header kpi-header kpi-teal"><span class="kpi-icon"><i class="bi bi-telephone"></i></span><span class="kpi-title">Conversas</span></div>
        <div class="card-body text-center">
          <div class="kpi-value"><?php echo number_format($kpis['total_conversas'] ?? 0, 0, ',', '.'); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card kpi-card h-100">
        <div class="card-header kpi-header kpi-pink"><span class="kpi-icon"><i class="bi bi-instagram"></i></span><span class="kpi-title">Seguidores</span></div>
        <div class="card-body text-center">
          <div class="kpi-value"><?php echo number_format($kpis['total_seguidores'] ?? 0, 0, ',', '.'); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card kpi-card h-100">
        <div class="card-header kpi-header kpi-success"><span class="kpi-icon"><i class="bi bi-graph-up"></i></span><span class="kpi-title">CTR Médio</span></div>
        <div class="card-body text-center">
          <div class="kpi-value"><?php echo number_format($kpis['ctr_medio'] ?? 0, 2, ',', '.'); ?>%</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card kpi-card h-100">
        <div class="card-header kpi-header kpi-info"><span class="kpi-icon"><i class="bi bi-coin"></i></span><span class="kpi-title">CPM Médio</span></div>
        <div class="card-body text-center">
          <div class="kpi-value">R$ <?php echo number_format($kpis['cpm_medio'] ?? 0, 2, ',', '.'); ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

  <!-- Gráficos por Campanha -->
  <div class="row">
    <div class="col-12">
      <div class="chart-container">
        <div class="chart-title mb-3"><h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Investimento por Campanha</h5></div>
        <div class="chart-fixed"><canvas id="investPorCampanha"></canvas></div>
        <p class="small text-muted mt-2">Dimensão: campanha. Métrica: soma do investimento (R$).</p>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-6">
      <div class="chart-container">
        <div class="chart-title mb-3"><h5 class="mb-0"><i class="bi bi-people me-2"></i>Alcance e Impressões por Campanha</h5></div>
        <div class="chart-fixed"><canvas id="alcanceImpPorCampanha"></canvas></div>
        <p class="small text-muted mt-2">Dimensão: campanha. Métricas: alcance e impressões.</p>
      </div>
    </div>
    <div class="col-md-6">
      <div class="chart-container">
        <div class="chart-title mb-3"><h5 class="mb-0"><i class="bi bi-chat-text me-2"></i>Cliques e Conversas por Campanha</h5></div>
        <div class="chart-fixed"><canvas id="cliquesConvPorCampanha"></canvas></div>
        <p class="small text-muted mt-2">Dimensão: campanha. Métricas: cliques e conversas.</p>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-6">
      <div class="chart-container">
        <div class="chart-title mb-3"><h5 class="mb-0"><i class="bi bi-cash-coin me-2"></i>CPR por Campanha</h5></div>
        <div class="chart-fixed"><canvas id="cprPorCampanha"></canvas></div>
        <p class="small text-muted mt-2">Dimensão: campanha. Métrica: investimento ÷ resultados.</p>
      </div>
    </div>
    <div class="col-md-6">
      <div class="chart-container">
        <div class="chart-title mb-3"><h5 class="mb-0"><i class="bi bi-percent me-2"></i>Conversão (Cliques → Conversas) por Campanha</h5></div>
        <div class="chart-fixed"><canvas id="convPorCampanha"></canvas></div>
        <p class="small text-muted mt-2">Dimensão: campanha. Métrica: (conversas ÷ cliques) × 100.</p>
      </div>
    </div>
  </div>

  <!-- Gráficos adicionais -->
  <div class="row">
    <div class="col-md-6">
      <div class="chart-container">
        <div class="chart-title mb-3"><h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Top Investimento por Campanha</h5></div>
        <div class="chart-fixed"><canvas id="topInvestimento"></canvas></div>
        <p class="small text-muted mt-2">As campanhas com maior investimento no período selecionado.</p>
      </div>
    </div>
    <div class="col-md-6">
      <div class="chart-container">
        <div class="chart-title mb-3"><h5 class="mb-0"><i class="bi bi-bullseye me-2"></i>Eficiência: Conversão vs CPR</h5></div>
        <div class="chart-fixed"><canvas id="eficienciaBubble"></canvas></div>
        <p class="small text-muted mt-2">Cada ponto é uma campanha: eixo X = conversão (%), eixo Y = CPR (R$), tamanho = investimento.</p>
      </div>
    </div>
  </div>

  <!-- Resumo por campanha -->
  <div class="chart-container">
    <h5 class="mb-3"><i class="bi bi-table me-2"></i>Resumo por Campanha</h5>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead class="table-light">
          <tr>
            <th>Campanha</th>
            <th>Investimento</th>
            <th>Resultados</th>
            <th>CPR</th>
            <th>Cliques</th>
            <th>Conversas</th>
            <th>Conversão</th>
            <th>Classificação</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($agg as $nome => $c): $cls = $classificacoes[$nome]; ?>
          <tr>
            <td><?php echo htmlspecialchars($nome); ?></td>
            <td>R$ <?php echo number_format($c['investimento'], 2, ',', '.'); ?></td>
            <td><?php echo number_format($c['resultados'], 0, ',', '.'); ?></td>
            <td><?php echo is_null($c['cpr']) ? '-' : 'R$ '.number_format($c['cpr'], 2, ',', '.'); ?></td>
            <td><?php echo number_format($c['cliques'], 0, ',', '.'); ?></td>
            <td><?php echo number_format($c['conversas'], 0, ',', '.'); ?></td>
            <td><?php echo is_null($c['conv_rate']) ? '-' : number_format($c['conv_rate'], 2, ',', '.').' %'; ?></td>
            <td><span class="badge bg-<?php echo $cls['badge']; ?>"><?php echo $cls['classe']; ?></span></td>
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
const dadosCampanha = <?php echo json_encode($dados_campanha); ?>;

// Helper para criar gráficos de barras (com opção de pilha)
function barChart(canvasEl, labels, datasets, stacked = false) {
  const ctx = canvasEl.getContext('2d');
  return new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        x: { stacked: !!stacked },
        y: { beginAtZero: true, stacked: !!stacked }
      },
      plugins: { legend: { position: 'bottom' } }
    }
  });
}

// Preparar dados para Top N investimento (limitar a 10)
(function(){
  const zipped = dadosCampanha.labels.map((label, i) => ({ label, val: dadosCampanha.investimento[i] }));
  zipped.sort((a,b)=>b.val-a.val);
  const top = zipped.slice(0, Math.min(10, zipped.length));
  new Chart(document.getElementById('topInvestimento').getContext('2d'), {
    type: 'bar',
    data: { labels: top.map(x=>x.label), datasets: [{ label: 'Investimento (R$)', data: top.map(x=>x.val), backgroundColor: 'rgba(0, 123, 255, 0.7)' }] },
    options: { responsive:true, maintainAspectRatio:false, scales: { y: { beginAtZero:true } }, plugins: { legend: { position:'bottom' } } }
  });
})();

// Bubble: conversão vs CPR (tamanho = investimento)
(function(){
  const points = dadosCampanha.labels.map((label, i) => ({
    label,
    x: dadosCampanha.conv_rate[i] == null ? 0 : dadosCampanha.conv_rate[i],
    y: dadosCampanha.cpr[i] == null ? 0 : dadosCampanha.cpr[i],
    r: Math.max(4, Math.sqrt(Math.max(0, dadosCampanha.investimento[i])) / 4)
  }));
  new Chart(document.getElementById('eficienciaBubble').getContext('2d'), {
    type: 'bubble',
    data: { datasets: [{ label: 'Campanhas', data: points, backgroundColor: 'rgba(32, 201, 151, 0.5)', borderColor:'#20c997' }] },
    options: { responsive:true, maintainAspectRatio:false, scales: { x: { title: { display:true, text:'Conversão (%)' } }, y: { title: { display:true, text:'CPR (R$)' }, beginAtZero:true } }, plugins: { tooltip: { callbacks: { label: (ctx)=> `${ctx.raw.label}: Conv ${ctx.raw.x?.toFixed(1)||0}% · CPR R$ ${(ctx.raw.y||0).toFixed(2)} · Inv R$ ${dadosCampanha.investimento[ctx.dataIndex].toFixed(2)}` } } } }
  });
})();

// Investimento por campanha
barChart(document.getElementById('investPorCampanha'), dadosCampanha.labels, [{
  label: 'Investimento (R$)',
  data: dadosCampanha.investimento,
  backgroundColor: 'rgba(75, 192, 192, 0.6)'
}]);

// Alcance e Impressões por campanha
barChart(document.getElementById('alcanceImpPorCampanha'), dadosCampanha.labels, [
  { label: 'Alcance', data: dadosCampanha.alcance, backgroundColor: 'rgba(54, 162, 235, 0.7)' },
  { label: 'Impressões', data: dadosCampanha.impressoes, backgroundColor: 'rgba(153, 102, 255, 0.7)' }
], true);

// Cliques e Conversas por campanha
barChart(document.getElementById('cliquesConvPorCampanha'), dadosCampanha.labels, [
  { label: 'Cliques', data: dadosCampanha.cliques, backgroundColor: 'rgba(255, 206, 86, 0.8)' },
  { label: 'Conversas', data: dadosCampanha.conversas, backgroundColor: 'rgba(75, 192, 192, 0.8)' }
], true);

// CPR por campanha
new Chart(document.getElementById('cprPorCampanha').getContext('2d'), {
  type: 'line',
  data: { labels: dadosCampanha.labels, datasets: [{ label: 'CPR (R$)', data: dadosCampanha.cpr, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.15)', spanGaps: true }] },
  options: { responsive:true, maintainAspectRatio:false, scales: { y: { beginAtZero: true } } }
});

// Conversão por campanha
new Chart(document.getElementById('convPorCampanha').getContext('2d'), {
  type: 'line',
  data: { labels: dadosCampanha.labels, datasets: [{ label: 'Conversão (%)', data: dadosCampanha.conv_rate, borderColor: '#20c997', backgroundColor: 'rgba(32,201,151,.15)', spanGaps: true }] },
  options: { responsive:true, maintainAspectRatio:false, scales: { y: { beginAtZero: true } } }
});

// (Removido bloco duplicado de gráfico bubble para evitar recriação do canvas)

function gerarLinkPublico() {
  const btn = document.getElementById('btnCompartilhar');
  const originalText = btn.innerHTML;
  btn.innerHTML = '<i class="bi bi-spinner-border-sm spinner-border"></i> Gerando...';
  btn.disabled = true;
  fetch('../app/gerar_link_publico.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'campanha_id=<?php echo $campanha_id; ?>' })
    .then(r => r.json())
    .then(data => {
      btn.innerHTML = originalText; btn.disabled = false;
      if (data.success) {
        const modalHtml = `
        <div class="modal fade" id="modalLinkPublico" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
          <div class="modal-header"><h5 class="modal-title"><i class="bi bi-share me-2"></i>Link Público Gerado</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <p><strong>Seu link público está pronto!</strong></p>
            <p>Compartilhe este link para que outras pessoas possam visualizar os analytics desta campanha sem precisar fazer login:</p>
            <div class="input-group mb-3"><input type="text" class="form-control" id="linkPublico" value="${data.public_url}" readonly><button class="btn btn-outline-secondary" type="button" onclick="copiarLink()"><i class="bi bi-copy"></i> Copiar</button></div>
            <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i><strong>Importante:</strong> Qualquer pessoa com este link poderá visualizar os dados desta campanha.</div>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button><a href="${data.public_url}" target="_blank" class="btn btn-primary"><i class="bi bi-box-arrow-up-right"></i> Abrir Link</a></div>
        </div></div></div>`;
        const existing = document.getElementById('modalLinkPublico'); if (existing) existing.remove();
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('modalLinkPublico')); modal.show();
      } else { alert('Erro ao gerar link público: ' + data.message); }
    })
    .catch(() => { btn.innerHTML = originalText; btn.disabled = false; alert('Erro ao comunicar com o servidor'); });
}

function copiarLink() {
  const linkInput = document.getElementById('linkPublico');
  linkInput.select(); linkInput.setSelectionRange(0, 99999);
  try {
    document.execCommand('copy');
    const btn = event.target.closest('button'); const original = btn.innerHTML; btn.innerHTML = '<i class="bi bi-check"></i> Copiado!'; btn.classList.remove('btn-outline-secondary'); btn.classList.add('btn-success');
    setTimeout(() => { btn.innerHTML = original; btn.classList.remove('btn-success'); btn.classList.add('btn-outline-secondary'); }, 2000);
  } catch (err) { alert('Erro ao copiar link. Selecione o texto e copie manualmente.'); }
}
</script>

<?php
$stmt && $stmt->close();
$stmt_ind && $stmt_ind->close();
if (isset($stmt_kpis) && $stmt_kpis) { $stmt_kpis->close(); }
$conexao && $conexao->close();
require_once '../templates/footer.php';
?>