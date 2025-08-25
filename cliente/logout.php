<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth();

// Realizar logout
$auth->logoutClient();

// Destruir completamente a sessão
session_start();
session_destroy();

// Redirecionar para página inicial
header('Location: ../index.php?logout=success');
exit;
?>