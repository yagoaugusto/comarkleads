<?php 
// Usamos o header para carregar o Bootstrap, mas vamos esconder a barra de navegação padrão.
$titulo_pagina = 'Obrigado!';
$esconder_nav = true; // Variável para controlar a exibição da navbar
require_once '../templates/header.php'; 
?>

<style>
    /* Animação para o ícone aparecer suavemente */
    @keyframes fadeInScale {
        from {
            opacity: 0;
            transform: scale(0.8);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .agradecimento-container {
        /* Usa Flexbox para centralizar tudo na tela inteira */
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 80vh; /* Ocupa quase toda a altura da tela */
        text-align: center;
    }

    .agradecimento-container .icon {
        font-size: 6rem; /* Tamanho grande para o ícone */
        color: #198754; /* Cor de sucesso do Bootstrap */
        animation: fadeInScale 0.8s ease-out;
    }

    .agradecimento-container h1 {
        font-weight: 300; /* Fonte mais leve para um visual moderno */
    }
</style>

<div class="agradecimento-container">
    <div class="icon">
        <i class="bi bi-check-circle-fill"></i>
    </div>
    <h1 class="display-4 mt-3">Obrigado!</h1>
    <p class="lead">
        Suas informações foram recebidas com sucesso.
    </p>
    <p class="mt-3">
        Nossa equipe entrará em contato assim que possível.
    </p>
    <a href="javascript:history.back()" class="btn btn-outline-secondary mt-4">
        <i class="bi bi-arrow-left"></i> Voltar à página anterior
    </a>
</div>

<?php require_once '../templates/footer.php'; ?>