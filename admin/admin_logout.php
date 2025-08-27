<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth();

// Realizar logout admin
$auth->logoutAdmin();

// Destruir completamente a sessão se não houver outras sessões ativas
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Verificar se não há cliente logado também
if (!$auth->isClientLoggedIn()) {
    session_destroy();
}

// Redirecionar para página de login admin
header('Location: admin_login.php?logout=success');
exit;
?>