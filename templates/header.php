<?php
// Inicia a sessão para poder acessar as variáveis de sessão
session_start();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_pagina ?? 'Gestão de Leads'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .kpi-card .card-body {
            display: flex;
            align-items: center;
        }

        .kpi-card i {
            font-size: 2.5rem;
        }
    </style>
</head>

<body>

<?php if (!isset($esconder_nav) || !$esconder_nav): // ADICIONE ESTA LINHA ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="#">COMARK Leads</a>
    <?php if (isset($_SESSION['usuario_id'])): ?>
      <div class="ms-auto text-white">
        Olá, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>!
        <a href="../app/logout.php" class="btn btn-danger btn-sm ms-2">Sair</a>
      </div>
    <?php endif; ?>
  </div>
</nav>
<?php endif; // ADICIONE ESTA LINHA ?>

    <main class="container mt-4">