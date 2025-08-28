<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth();

// Verificar se admin já está logado
if ($auth->isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Processar login
if ($_POST && isset($_POST['login'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança inválido.';
    } else {
        $result = $auth->loginAdmin($_POST['email'] ?? '', $_POST['senha'] ?? '');
        
        if ($result['success']) {
            header('Location: dashboard.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

$csrf_token = generateCSRFToken();


?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - BarberShop</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #e67e22;
            --accent-color: #f39c12;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .login-header {
            background: var(--primary-color);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(230, 126, 34, 0.25);
        }
        
        .admin-badge {
            background-color: var(--accent-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="login-card">
                    <div class="login-header">
                        <h3>
                            <i class="fas fa-shield-alt"></i> BarberShop
                        </h3>
                        <p class="mb-2">Painel Administrativo</p>
                        <span class="admin-badge">ADMIN ACCESS</span>
                    </div>
                    <div class="p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope"></i> Email Administrativo
                                </label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                       placeholder="admin@barbearia.com" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="senha" class="form-label">
                                    <i class="fas fa-lock"></i> Senha
                                </label>
                                <input type="password" class="form-control" id="senha" name="senha" 
                                       placeholder="Sua senha administrativa" required>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" name="login" class="btn btn-primary btn-lg">
                                    <i class="fas fa-shield-alt"></i> Acessar Painel Admin
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center">
                            <div class="alert alert-info">
                                <small>
                                    <i class="fas fa-info-circle"></i> 
                                    <strong>Área restrita</strong> - Acesso apenas para administradores
                                </small>
                            </div>
                            <hr class="my-3">
                            <a href="../index.php" class="text-muted text-decoration-none">
                                <i class="fas fa-arrow-left"></i> Voltar ao site público
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>