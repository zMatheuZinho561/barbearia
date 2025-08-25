<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth();

// Verificar se cliente já está logado
if ($auth->isClientLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Processar cadastro
if ($_POST && isset($_POST['register'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança inválido.';
    } else {
        $result = $auth->registerClient(
            $_POST['nome'] ?? '',
            $_POST['telefone'] ?? '',
            $_POST['email'] ?? '',
            $_POST['senha'] ?? ''
        );
        
        if ($result['success']) {
            $success = $result['message'];
            // Aguardar 2 segundos e redirecionar para login
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'login.php';
                }, 2000);
            </script>";
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
    <title>Cadastro - BarberShop</title>
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
            padding: 2rem 0;
        }
        
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .register-header {
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
        
        .password-strength {
            height: 4px;
            background-color: #e9ecef;
            border-radius: 2px;
            margin-top: 5px;
        }
        
        .strength-bar {
            height: 100%;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="register-card">
                    <div class="register-header">
                        <h3><i class="fas fa-cut"></i> BarberShop</h3>
                        <p class="mb-0">Crie sua conta</p>
                    </div>
                    <div class="p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                                <small class="d-block">Redirecionando para o login...</small>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="registerForm">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nome" class="form-label">
                                        <i class="fas fa-user"></i> Nome Completo
                                    </label>
                                    <input type="text" class="form-control" id="nome" name="nome" 
                                           value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="telefone" class="form-label">
                                        <i class="fas fa-phone"></i> Telefone
                                    </label>
                                    <input type="tel" class="form-control" id="telefone" name="telefone" 
                                           value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>" 
                                           placeholder="(11) 99999-9999" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope"></i> Email
                                </label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="senha" class="form-label">
                                    <i class="fas fa-lock"></i> Senha
                                </label>
                                <input type="password" class="form-control" id="senha" name="senha" 
                                       minlength="6" required>
                                <div class="password-strength">
                                    <div class="strength-bar" id="strengthBar"></div>
                                </div>
                                <small class="form-text text-muted">
                                    Mínimo de 6 caracteres
                                </small>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirmar_senha" class="form-label">
                                    <i class="fas fa-lock"></i> Confirmar Senha
                                </label>
                                <input type="password" class="form-control" id="confirmar_senha" 
                                       name="confirmar_senha" required>
                                <div class="invalid-feedback" id="passwordMismatch">
                                    As senhas não coincidem
                                </div>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" name="register" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="fas fa-user-plus"></i> Criar Conta
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center">
                            <p class="mb-0">
                                Já tem uma conta? 
                                <a href="login.php" class="text-decoration-none">
                                    <strong>Entre aqui</strong>
                                </a>
                            </p>
                            <hr class="my-3">
                            <a href="../index.php" class="text-muted text-decoration-none">
                                <i class="fas fa-arrow-left"></i> Voltar ao site
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length > 0) {
                value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
                value = value.replace(/(\d)(\d{4})$/, '$1-$2');
            }
            
            e.target.value = value;
        });
        
        // Verificar força da senha
        document.getElementById('senha').addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthBar = document.getElementById('strengthBar');
            let strength = 0;
            
            if (password.length >= 6) strength += 25;
            if (password.match(/[a-z]/)) strength += 25;
            if (password.match(/[A-Z]/)) strength += 25;
            if (password.match(/[0-9]/) || password.match(/[^a-zA-Z0-9]/)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 50) {
                strengthBar.style.backgroundColor = '#dc3545';
            } else if (strength < 75) {
                strengthBar.style.backgroundColor = '#fd7e14';
            } else {
                strengthBar.style.backgroundColor = '#28a745';
            }
        });
        
        // Verificar se senhas coincidem
        function checkPasswordMatch() {
            const senha = document.getElementById('senha').value;
            const confirmar = document.getElementById('confirmar_senha').value;
            const confirmarInput = document.getElementById('confirmar_senha');
            const submitBtn = document.getElementById('submitBtn');
            
            if (confirmar.length > 0) {
                if (senha !== confirmar) {
                    confirmarInput.classList.add('is-invalid');
                    submitBtn.disabled = true;
                } else {
                    confirmarInput.classList.remove('is-invalid');
                    confirmarInput.classList.add('is-valid');
                    submitBtn.disabled = false;
                }
            } else {
                confirmarInput.classList.remove('is-invalid', 'is-valid');
                submitBtn.disabled = false;
            }
        }
        
        document.getElementById('senha').addEventListener('input', checkPasswordMatch);
        document.getElementById('confirmar_senha').addEventListener('input', checkPasswordMatch);
    </script>
</body>
</html>