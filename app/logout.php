<?php
// app/logout.php
session_start();

// Destrói todas as variáveis da sessão
session_unset();

// Destrói a sessão
session_destroy();

// Redireciona para a página de login
header("Location: ../public/index.php");
exit();
?>