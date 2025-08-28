<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/appointment.php';

$auth = new Auth();
$appointment = new Appointment();

// Verificar se usuário logado é admin
if (!$auth->isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$admin_data = $auth->getAdminData();

// Processar ações
$error = '';
$success = '';
$database = new Database();
$db = $database->getConnection();

if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'cadastrar_cliente':
            if (!empty($_POST['nome']) && !empty($_POST['telefone']) && !empty($_POST['email'])) {
                try {
                    // Verificar se já existe
                    $stmt = $db->prepare("SELECT id FROM clientes WHERE email = ? OR telefone = ?");
                    $stmt->execute([$_POST['email'], $_POST['telefone']]);
                    
                    if ($stmt->rowCount() > 0) {
                        $error = 'Cliente já cadastrado com este email ou telefone.';
                    } else {
                        // Inserir cliente
                        $stmt = $db->prepare("
                            INSERT INTO clientes (nome, telefone, email, senha, endereco, data_nascimento, observacoes) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $senha_padrao = password_hash('123456', PASSWORD_BCRYPT);
                        
                        if ($stmt->execute([
                            $_POST['nome'],
                            $_POST['telefone'],
                            $_POST['email'],
                            $senha_padrao,
                            $_POST['endereco'] ?? '',
                            $_POST['data_nascimento'] ?? null,
                            $_POST['observacoes'] ?? ''
                        ])) {
                            $success = 'Cliente cadastrado com sucesso! Senha padrão: 123456';
                        } else {
                            $error = 'Erro ao cadastrar cliente.';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Erro interno do servidor.';
                }
            } else {
                $error = 'Nome, telefone e email são obrigatórios.';
            }
            break;
            
        case 'criar_agendamento':
            if (!empty($_POST['cliente_id']) && !empty($_POST['barbeiro_id']) && 
                !empty($_POST['servico_id']) && !empty($_POST['data']) && !empty($_POST['hora'])) {
                
                $result = $appointment->criarAgendamento(
                    $_POST['cliente_id'],
                    $_POST['barbeiro_id'],
                    $_POST['servico_id'],
                    $_POST['data'],
                    $_POST['hora'],
                    $_POST['observacoes'] ?? ''
                );
                
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
            } else {
                $error = 'Todos os campos são obrigatórios para o agendamento.';
            }
            break;
            
        case 'editar_cliente':
            if (!empty($_POST['cliente_id']) && !empty($_POST['nome']) && 
                !empty($_POST['telefone']) && !empty($_POST['email'])) {
                try {
                    // Verificar se email/telefone já existem em outro cliente
                    $stmt = $db->prepare("
                        SELECT id FROM clientes 
                        WHERE (email = ? OR telefone = ?) AND id != ?
                    ");
                    $stmt->execute([$_POST['email'], $_POST['telefone'], $_POST['cliente_id']]);
                    
                    if ($stmt->rowCount() > 0) {
                        $error = 'Email ou telefone já está sendo usado por outro cliente.';
                    } else {
                        // Atualizar cliente
                        $stmt = $db->prepare("
                            UPDATE clientes 
                            SET nome = ?, telefone = ?, email = ?, endereco = ?, 
                                data_nascimento = ?, observacoes = ?
                            WHERE id = ?
                        ");
                        
                        if ($stmt->execute([
                            $_POST['nome'],
                            $_POST['telefone'],
                            $_POST['email'],
                            $_POST['endereco'] ?? '',
                            $_POST['data_nascimento'] ?? null,
                            $_POST['observacoes'] ?? '',
                            $_POST['cliente_id']
                        ])) {
                            $success = 'Cliente atualizado com sucesso!';
                        } else {
                            $error = 'Erro ao atualizar cliente.';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Erro interno do servidor.';
                }
            } else {
                $error = 'Todos os campos obrigatórios devem ser preenchidos.';
            }
            break;
            
        case 'desativar_cliente':
            if (!empty($_POST['cliente_id'])) {
                try {
                    $stmt = $db->prepare("UPDATE clientes SET ativo = 0 WHERE id = ?");
                    if ($stmt->execute([$_POST['cliente_id']])) {
                        $success = 'Cliente desativado com sucesso.';
                    } else {
                        $error = 'Erro ao desativar cliente.';
                    }
                } catch (Exception $e) {
                    $error = 'Erro interno do servidor.';
                }
            }
            break;
            
        case 'ativar_cliente':
            if (!empty($_POST['cliente_id'])) {
                try {
                    $stmt = $db->prepare("UPDATE clientes SET ativo = 1 WHERE id = ?");
                    if ($stmt->execute([$_POST['cliente_id']])) {
                        $success = 'Cliente ativado com sucesso.';
                    } else {
                        $error = 'Erro ao ativar cliente.';
                    }
                } catch (Exception $e) {
                    $error = 'Erro interno do servidor.';
                }
            }
            break;
    }
}

// Filtros
$filtro_nome = $_GET['nome'] ?? '';
$filtro_telefone = $_GET['telefone'] ?? '';
$filtro_status = $_GET['status'] ?? '';

// Buscar clientes com filtros
$clientes = [];
try {
    $sql = "
        SELECT c.*, 
               (SELECT COUNT(*) FROM agendamentos WHERE cliente_id = c.id) as total_agendamentos,
               (SELECT COUNT(*) FROM agendamentos WHERE cliente_id = c.id AND status = 'finalizado') as agendamentos_finalizados,
               (SELECT MAX(data_agendamento) FROM agendamentos WHERE cliente_id = c.id) as ultimo_agendamento
        FROM clientes c
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($filtro_nome) {
        $sql .= " AND c.nome LIKE ?";
        $params[] = '%' . $filtro_nome . '%';
    }
    
    if ($filtro_telefone) {
        $sql .= " AND c.telefone LIKE ?";
        $params[] = '%' . $filtro_telefone . '%';
    }
    
    if ($filtro_status === 'ativo') {
        $sql .= " AND c.ativo = 1";
    } elseif ($filtro_status === 'inativo') {
        $sql .= " AND c.ativo = 0";
    }
    
    $sql .= " ORDER BY c.nome ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Erro ao buscar clientes: " . $e->getMessage());
    $error = 'Erro ao carregar clientes.';
}

// Buscar barbeiros para agendamento
$barbeiros = $appointment->getBarbeiros();
$servicos = $appointment->getServicos();

// Estatísticas
$stats = [];
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM clientes WHERE ativo = 1");
    $stats['ativos'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM clientes WHERE ativo = 0");
    $stats['inativos'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM clientes WHERE DATE(data_cadastro) = CURDATE()");
    $stats['cadastrados_hoje'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("
        SELECT COUNT(DISTINCT c.id) as total 
        FROM clientes c 
        JOIN agendamentos a ON c.id = a.cliente_id 
        WHERE MONTH(a.data_agendamento) = MONTH(CURDATE()) 
        AND YEAR(a.data_agendamento) = YEAR(CURDATE())
    ");
    $stats['agendaram_mes'] = $stmt->fetch()['total'];
    
} catch (Exception $e) {
    $stats = ['ativos' => 0, 'inativos' => 0, 'cadastrados_hoje' => 0, 'agendaram_mes' => 0];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Admin Moderno - BarberShop</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            --gold-gradient: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
            --navbar-bg: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --text-light: rgba(255, 255, 255, 0.9);
            --text-lighter: rgba(255, 255, 255, 0.7);
            --hover-bg: rgba(255, 255, 255, 0.15);
            --active-bg: rgba(255, 255, 255, 0.25);
            --shadow-dark: 0 8px 32px rgba(0, 0, 0, 0.3);
            --shadow-light: 0 4px 16px rgba(0, 0, 0, 0.1);
        }
        
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Navbar Principal */
        .admin-navbar {
            background: var(--navbar-bg);
            backdrop-filter: blur(20px);
            border: none;
            box-shadow: var(--shadow-dark);
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 0.8rem 0;
        }

        .admin-navbar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.05"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.05"/><circle cx="75" cy="25" r="1" fill="white" opacity="0.03"/><circle cx="25" cy="75" r="1" fill="white" opacity="0.03"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        /* Logo */
        .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 1.5rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
            color: #ffd700 !important;
        }

        .brand-icon {
            background: var(--gold-gradient);
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            box-shadow: 0 4px 15px rgba(247, 151, 30, 0.4);
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .brand-title {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .brand-subtitle {
            font-size: 0.7rem;
            opacity: 0.8;
            font-weight: 400;
            letter-spacing: 1px;
        }

        /* Menu Items */
        .navbar-nav .nav-link {
            color: var(--text-light) !important;
            font-weight: 500;
            padding: 0.75rem 1.25rem !important;
            margin: 0 0.25rem;
            border-radius: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid transparent;
        }

        .navbar-nav .nav-link:hover {
            background: var(--hover-bg);
            color: white !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(255, 255, 255, 0.1);
            border-color: var(--glass-border);
        }

        .navbar-nav .nav-link.active {
            background: var(--active-bg);
            color: #ffd700 !important;
            box-shadow: 0 4px 20px rgba(255, 215, 0, 0.3);
            border-color: rgba(255, 215, 0, 0.3);
        }

        .navbar-nav .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 20px;
            background: var(--gold-gradient);
            border-radius: 0 3px 3px 0;
        }

        .nav-link i {
            font-size: 1rem;
            width: 16px;
            text-align: center;
        }

        /* Dropdown Admin */
        .admin-dropdown .nav-link {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(15px);
        }

        .admin-dropdown .nav-link:hover {
            background: var(--hover-bg);
            border-color: rgba(255, 215, 0, 0.4);
        }

        .admin-badge {
            background: var(--gold-gradient);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            margin-left: 0.5rem;
            box-shadow: 0 2px 8px rgba(247, 151, 30, 0.4);
            animation: pulse-gold 2s infinite;
        }

        @keyframes pulse-gold {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        /* Dropdown Menu */
        .dropdown-menu {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            padding: 0.75rem 0;
            min-width: 280px;
        }

        .dropdown-item {
            color: #2c3e50;
            padding: 0.75rem 1.25rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .dropdown-item:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateX(5px);
        }

        .dropdown-item.text-danger:hover {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
        }

        .dropdown-header {
            color: #6c757d;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.5rem 1.25rem;
        }

        .dropdown-divider {
            margin: 0.5rem 0;
            border-color: rgba(0, 0, 0, 0.1);
        }

        /* Mobile Navbar */
        .navbar-toggler {
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            padding: 0.5rem;
        }

        .navbar-toggler:focus {
            box-shadow: 0 0 0 0.25rem rgba(255, 215, 0, 0.25);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='m4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                margin-top: 1rem;
                border-radius: 12px;
                padding: 1rem;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            }

            .navbar-nav .nav-link {
                color: #2c3e50 !important;
                margin: 0.25rem 0;
            }

            .navbar-nav .nav-link:hover {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white !important;
            }

            .admin-dropdown .nav-link {
                background: linear-gradient(135deg, #2c3e50, #3498db);
                color: white !important;
            }
        }

        /* Animações suaves */
        .navbar-nav, .dropdown-menu {
            animation: slideInDown 0.3s ease-out;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Indicador de notificações */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-3px); }
            60% { transform: translateY(-2px); }
        }

        /* Demo content styling */
        .demo-content {
            padding: 2rem 0;
        }

        .demo-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            box-shadow: var(--shadow-light);
        }
    </style>
</head>
<body>

    <!-- Navbar Admin Moderna -->
    <nav class="navbar navbar-expand-lg admin-navbar">
        <div class="container-fluid">
            <!-- Brand Logo -->
            <a class="navbar-brand" href="dashboard.php">
                <div class="brand-icon">
                    <i class="fas fa-cut"></i>
                </div>
                <div class="brand-text">
                    <span class="brand-title">BarberShop</span>
                    <span class="brand-subtitle">ADMIN PANEL</span>
                </div>
            </a>

            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation Menu -->
            <div class="collapse navbar-collapse" id="adminNavbar">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_agendamentos.php">
                            <i class="fas fa-calendar-check"></i>
                            <span>Agendamentos</span>
                            <span class="notification-badge">5</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="clientes.php">
                            <i class="fas fa-users"></i>
                            <span>Clientes</span>
                        </a>
                    </li>
                        <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-user-tie"></i>
                            <span>Barbeiros</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-scissors"></i>
                            <span>Serviços</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="planos.php">
                            <i class="fas fa-crown"></i>
                            <span>Planos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="clientes_planos.php">
                            <i class="fas fa-users-cog"></i>
                            <span>Assinaturas</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="produtos.php">
                            <i class="fas fa-box-open"></i>
                            <span>Produtos</span>
                        </a>
                    </li>
                </ul>

                <!-- Admin Profile Dropdown -->
                <ul class="navbar-nav">
                    <li class="nav-item dropdown admin-dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-shield"></i>
                            <span>Admin Master</span>
                            <span class="admin-badge">ADMIN</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <h6 class="dropdown-header">
                                    <i class="fas fa-crown"></i> Painel Administrativo
                                </h6>
                            </li>
                            <li>
                                <a class="dropdown-item" href="perfil.php">
                                    <i class="fas fa-user-cog"></i>
                                    <span>Meu Perfil</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="configuracoes.php">
                                    <i class="fas fa-cogs"></i>
                                    <span>Configurações</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="relatorios.php">
                                    <i class="fas fa-chart-line"></i>
                                    <span>Relatórios</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="backup.php">
                                    <i class="fas fa-database"></i>
                                    <span>Backup</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="admin_logout.php">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>Sair do Admin</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Mensagens -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-success mb-2"></i>
                        <h4 class="text-success mb-1"><?= $stats['ativos'] ?></h4>
                        <small class="text-muted">Clientes Ativos</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-user-times fa-2x text-danger mb-2"></i>
                        <h4 class="text-danger mb-1"><?= $stats['inativos'] ?></h4>
                        <small class="text-muted">Inativos</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-user-plus fa-2x text-primary mb-2"></i>
                        <h4 class="text-primary mb-1"><?= $stats['cadastrados_hoje'] ?></h4>
                        <small class="text-muted">Novos Hoje</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-calendar-check fa-2x text-info mb-2"></i>
                        <h4 class="text-info mb-1"><?= $stats['agendaram_mes'] ?></h4>
                        <small class="text-muted">Agendaram no Mês</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card filters-card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" name="nome" id="nome" 
                               value="<?= htmlspecialchars($filtro_nome) ?>" placeholder="Buscar por nome">
                    </div>
                    <div class="col-md-3">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" name="telefone" id="telefone_filtro" 
                               value="<?= htmlspecialchars($filtro_telefone) ?>" placeholder="Buscar por telefone">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" name="status" id="status">
                            <option value="">Todos os status</option>
                            <option value="ativo" <?= $filtro_status === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inativo" <?= $filtro_status === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                            <a href="clientes.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Limpar
                            </a>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#novoClienteModal">
                                <i class="fas fa-plus"></i> Novo
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Clientes -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-users"></i> 
                    Clientes 
                    <span class="badge bg-secondary"><?= count($clientes) ?></span>
                </h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#novoClienteModal">
                        <i class="fas fa-user-plus"></i> Cadastrar Cliente
                    </button>
                    <button class="btn btn-info btn-sm" onclick="window.location.reload()">
                        <i class="fas fa-sync"></i> Atualizar
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($clientes)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhum cliente encontrado</h5>
                        <p class="text-muted">Tente ajustar os filtros ou cadastre o primeiro cliente.</p>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#novoClienteModal">
                            <i class="fas fa-user-plus"></i> Cadastrar Primeiro Cliente
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Cliente</th>
                                    <th>Contato</th>
                                    <th>Agendamentos</th>
                                    <th>Último Agendamento</th>
                                    <th>Status</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes as $cliente): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="client-avatar me-3">
                                                    <?= strtoupper(substr($cliente['nome'], 0, 2)) ?>
                                                </div>
                                                <div>
                                                    <strong><?= htmlspecialchars($cliente['nome']) ?></strong><br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar-plus"></i> 
                                                        Cadastrado: <?= date('d/m/Y', strtotime($cliente['data_cadastro'])) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="mb-1">
                                                    <i class="fas fa-phone text-muted me-1"></i> 
                                                    <span><?= htmlspecialchars($cliente['telefone']) ?></span>
                                                </div>
                                                <div>
                                                    <i class="fas fa-envelope text-muted me-1"></i> 
                                                    <span class="text-break"><?= htmlspecialchars($cliente['email']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="mb-1">
                                                    <span class="badge bg-primary"><?= $cliente['total_agendamentos'] ?></span>
                                                    <small class="text-muted d-block">Total</small>
                                                </div>
                                                <?php if ($cliente['agendamentos_finalizados'] > 0): ?>
                                                    <div>
                                                        <span class="badge bg-success"><?= $cliente['agendamentos_finalizados'] ?></span>
                                                        <small class="text-muted d-block">Finalizados</small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($cliente['ultimo_agendamento']): ?>
                                                <span class="text-primary">
                                                    <i class="fas fa-calendar"></i>
                                                    <?= date('d/m/Y', strtotime($cliente['ultimo_agendamento'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-calendar-times"></i>
                                                    Nunca agendou
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($cliente['ativo']): ?>
                                                <span class="badge bg-success status-badge">
                                                    <i class="fas fa-check"></i> Ativo
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger status-badge">
                                                    <i class="fas fa-times"></i> Inativo
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons">
                                                <button class="btn btn-primary btn-sm" title="Novo Agendamento" 
                                                        onclick="novoAgendamento(<?= $cliente['id'] ?>, '<?= htmlspecialchars($cliente['nome']) ?>')">
                                                    <i class="fas fa-calendar-plus"></i>
                                                </button>
                                                
                                                <button class="btn btn-info btn-sm" title="Ver Detalhes"
                                                        onclick="verDetalhes(<?= $cliente['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <button class="btn btn-warning btn-sm" title="Editar"
                                                        onclick="editarCliente(<?= htmlspecialchars(json_encode($cliente)) ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <?php if ($cliente['ativo']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="desativar_cliente">
                                                        <input type="hidden" name="cliente_id" value="<?= $cliente['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Desativar"
                                                                onclick="return confirm('Desativar este cliente?')">
                                                            <i class="fas fa-user-times"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="ativar_cliente">
                                                        <input type="hidden" name="cliente_id" value="<?= $cliente['id'] ?>">
                                                        <button type="submit" class="btn btn-success btn-sm" title="Ativar">
                                                            <i class="fas fa-user-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Novo Cliente -->
    <div class="modal fade" id="novoClienteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Cadastrar Novo Cliente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formNovoCliente">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cadastrar_cliente">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nome" class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" name="nome" id="nome" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="telefone" class="form-label">Telefone *</label>
                                <input type="tel" class="form-control" name="telefone" id="telefone" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" id="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="data_nascimento" class="form-label">Data de Nascimento</label>
                                <input type="date" class="form-control" name="data_nascimento" id="data_nascimento">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="observacoes" class="form-label">Observações</label>
                                <textarea class="form-control" name="observacoes" id="observacoes" rows="3" 
                                          placeholder="Informações adicionais sobre o cliente..."></textarea>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Senha padrão:</strong> Será criada a senha "123456" para este cliente. 
                            Ele poderá alterá-la no primeiro acesso.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-user-plus"></i> Cadastrar Cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Cliente -->
    <div class="modal fade" id="editarClienteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Editar Cliente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formEditarCliente">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar_cliente">
                        <input type="hidden" name="cliente_id" id="edit_cliente_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_nome" class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" name="nome" id="edit_nome" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_telefone" class="form-label">Telefone *</label>
                                <input type="tel" class="form-control" name="telefone" id="edit_telefone" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_email" class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" id="edit_email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_data_nascimento" class="form-label">Data de Nascimento</label>
                                <input type="date" class="form-control" name="data_nascimento" id="edit_data_nascimento">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="edit_endereco" class="form-label">Endereço</label>
                                <input type="text" class="form-control" name="endereco" id="edit_endereco" 
                                       placeholder="Rua, número, bairro, cidade">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="edit_observacoes" class="form-label">Observações</label>
                                <textarea class="form-control" name="observacoes" id="edit_observacoes" rows="3" 
                                          placeholder="Informações adicionais sobre o cliente..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Novo Agendamento -->
    <div class="modal fade" id="novoAgendamentoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus"></i> Novo Agendamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formNovoAgendamento">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="criar_agendamento">
                        <input type="hidden" name="cliente_id" id="agend_cliente_id">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-user"></i> 
                            Cliente: <strong id="agend_cliente_nome"></strong>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="agend_barbeiro_id" class="form-label">Barbeiro *</label>
                                <select class="form-select" name="barbeiro_id" id="agend_barbeiro_id" required>
                                    <option value="">Selecione o barbeiro</option>
                                    <?php foreach ($barbeiros as $barbeiro): ?>
                                        <option value="<?= $barbeiro['id'] ?>">
                                            <?= htmlspecialchars($barbeiro['nome']) ?>
                                            <?php if ($barbeiro['especialidade']): ?>
                                                - <?= htmlspecialchars($barbeiro['especialidade']) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="agend_servico_id" class="form-label">Serviço *</label>
                                <select class="form-select" name="servico_id" id="agend_servico_id" required>
                                    <option value="">Selecione o serviço</option>
                                    <?php foreach ($servicos as $servico): ?>
                                        <option value="<?= $servico['id'] ?>" data-preco="<?= $servico['preco'] ?>" data-duracao="<?= $servico['duracao'] ?>">
                                            <?= htmlspecialchars($servico['nome']) ?> - R$ <?= number_format($servico['preco'], 2, ',', '.') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="agend_data" class="form-label">Data *</label>
                                <input type="date" class="form-control" name="data" id="agend_data" 
                                       min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="agend_hora" class="form-label">Horário *</label>
                                <select class="form-select" name="hora" id="agend_hora" required>
                                    <option value="">Primeiro selecione barbeiro e data</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="agend_observacoes" class="form-label">Observações</label>
                                <textarea class="form-control" name="observacoes" id="agend_observacoes" rows="3" 
                                          placeholder="Observações sobre o agendamento..."></textarea>
                            </div>
                        </div>
                        
                        <div id="resumo_agendamento" class="alert alert-light" style="display: none;">
                            <h6><i class="fas fa-info-circle"></i> Resumo do Agendamento:</h6>
                            <div id="resumo_content"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Criar Agendamento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Detalhes Cliente -->
    <div class="modal fade" id="detalhesClienteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-user"></i> Detalhes do Cliente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detalhesClienteContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Função para abrir modal de novo agendamento
        function novoAgendamento(clienteId, clienteNome) {
            document.getElementById('agend_cliente_id').value = clienteId;
            document.getElementById('agend_cliente_nome').textContent = clienteNome;
            
            // Limpar formulário
            document.getElementById('formNovoAgendamento').reset();
            document.getElementById('agend_cliente_id').value = clienteId;
            document.getElementById('agend_hora').innerHTML = '<option value="">Primeiro selecione barbeiro e data</option>';
            document.getElementById('resumo_agendamento').style.display = 'none';
            
            const modal = new bootstrap.Modal(document.getElementById('novoAgendamentoModal'));
            modal.show();
        }

        // Função para editar cliente
        function editarCliente(cliente) {
            document.getElementById('edit_cliente_id').value = cliente.id;
            document.getElementById('edit_nome').value = cliente.nome;
            document.getElementById('edit_telefone').value = cliente.telefone;
            document.getElementById('edit_email').value = cliente.email;
            document.getElementById('edit_data_nascimento').value = cliente.data_nascimento || '';
            document.getElementById('edit_endereco').value = cliente.endereco || '';
            document.getElementById('edit_observacoes').value = cliente.observacoes || '';
            
            const modal = new bootstrap.Modal(document.getElementById('editarClienteModal'));
            modal.show();
        }

        // Função para ver detalhes do cliente
        function verDetalhes(clienteId) {
            const modal = new bootstrap.Modal(document.getElementById('detalhesClienteModal'));
            const content = document.getElementById('detalhesClienteContent');
            
            content.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            `;
            
            modal.show();
            
            // Aqui você pode fazer uma requisição AJAX para buscar os detalhes
            // Por enquanto, vamos simular
            setTimeout(() => {
                content.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        Funcionalidade em desenvolvimento. Em breve você poderá ver histórico completo de agendamentos, 
                        preferências e outras informações detalhadas do cliente.
                    </div>
                `;
            }, 1000);
        }

        // Buscar horários quando barbeiro e data forem selecionados
        document.addEventListener('DOMContentLoaded', function() {
            const barbeiroSelect = document.getElementById('agend_barbeiro_id');
            const dataInput = document.getElementById('agend_data');
            const horaSelect = document.getElementById('agend_hora');
            const servicoSelect = document.getElementById('agend_servico_id');
            
            function buscarHorarios() {
                const barbeiroId = barbeiroSelect.value;
                const data = dataInput.value;
                
                if (barbeiroId && data) {
                    horaSelect.innerHTML = '<option value="">Carregando horários...</option>';
                    
                    // Simular busca de horários disponíveis
                    setTimeout(() => {
                        const horarios = [
                            '08:00', '08:30', '09:00', '09:30', '10:00', '10:30',
                            '11:00', '11:30', '14:00', '14:30', '15:00', '15:30',
                            '16:00', '16:30', '17:00', '17:30', '18:00'
                        ];
                        
                        horaSelect.innerHTML = '<option value="">Selecione um horário</option>';
                        horarios.forEach(hora => {
                            horaSelect.innerHTML += `<option value="${hora}">${hora}</option>`;
                        });
                    }, 500);
                }
            }
            
            barbeiroSelect.addEventListener('change', buscarHorarios);
            dataInput.addEventListener('change', buscarHorarios);
            
            // Atualizar resumo quando todos os campos estiverem preenchidos
            function atualizarResumo() {
                const barbeiro = barbeiroSelect.options[barbeiroSelect.selectedIndex]?.text;
                const servico = servicoSelect.options[servicoSelect.selectedIndex]?.text;
                const data = dataInput.value;
                const hora = horaSelect.value;
                
                if (barbeiro && servico && data && hora) {
                    document.getElementById('resumo_content').innerHTML = `
                        <strong>Barbeiro:</strong> ${barbeiro}<br>
                        <strong>Serviço:</strong> ${servico}<br>
                        <strong>Data:</strong> ${new Date(data + 'T00:00:00').toLocaleDateString('pt-BR')}<br>
                        <strong>Horário:</strong> ${hora}
                    `;
                    document.getElementById('resumo_agendamento').style.display = 'block';
                } else {
                    document.getElementById('resumo_agendamento').style.display = 'none';
                }
            }
            
            [barbeiroSelect, servicoSelect, dataInput, horaSelect].forEach(element => {
                element.addEventListener('change', atualizarResumo);
            });
        });

        // Formatação automática do telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                if (value.length < 14) {
                    value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
                }
            }
            e.target.value = value;
        });

        document.getElementById('edit_telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                if (value.length < 14) {
                    value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
                }
            }
            e.target.value = value;
        });
    </script>
</body>
</html>
    