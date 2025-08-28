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

if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'atualizar_status':
            if (isset($_POST['agendamento_id']) && isset($_POST['status'])) {
                $result = $appointment->atualizarStatus($_POST['agendamento_id'], $_POST['status']);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
            }
            break;
            
        case 'cancelar':
            if (isset($_POST['agendamento_id'])) {
                $result = $appointment->cancelarAgendamento($_POST['agendamento_id']);
                if ($result['success']) {
                    $success = 'Agendamento cancelado com sucesso!';
                } else {
                    $error = $result['message'];
                }
            }
            break;
    }
}

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_barbeiro = $_GET['barbeiro'] ?? '';
$filtro_data = $_GET['data'] ?? '';

// Buscar agendamentos com filtros
$agendamentos = [];
$database = new Database();
$db = $database->getConnection();

try {
    $sql = "
        SELECT a.*, 
               c.nome as cliente_nome,
               c.telefone as cliente_telefone,
               c.email as cliente_email,
               b.nome as barbeiro_nome, 
               s.nome as servico_nome, 
               s.preco as valor,
               s.duracao
        FROM agendamentos a
        JOIN clientes c ON a.cliente_id = c.id
        JOIN barbeiros b ON a.barbeiro_id = b.id
        JOIN servicos s ON a.servico_id = s.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($filtro_status) {
        $sql .= " AND a.status = ?";
        $params[] = $filtro_status;
    }
    
    if ($filtro_barbeiro) {
        $sql .= " AND a.barbeiro_id = ?";
        $params[] = $filtro_barbeiro;
    }
    
    if ($filtro_data) {
        $sql .= " AND a.data_agendamento = ?";
        $params[] = $filtro_data;
    }
    
    $sql .= " ORDER BY a.data_agendamento DESC, a.hora_agendamento DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $agendamentos = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Erro ao buscar agendamentos: " . $e->getMessage());
    $error = 'Erro ao carregar agendamentos.';
}

// Buscar barbeiros para filtro
$barbeiros = $appointment->getBarbeiros();

// Estatísticas rápidas
$stats = [];
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM agendamentos WHERE data_agendamento = CURDATE()");
    $stats['hoje'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM agendamentos WHERE status = 'agendado'");
    $stats['pendentes'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM agendamentos WHERE status = 'confirmado'");
    $stats['confirmados'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM agendamentos WHERE status = 'cancelado'");
    $stats['cancelados'] = $stmt->fetch()['total'];
    
} catch (Exception $e) {
    $stats = ['hoje' => 0, 'pendentes' => 0, 'confirmados' => 0, 'cancelados' => 0];
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
    <div class="container-fluid mt-4">
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
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-primary"><?= $stats['hoje'] ?></h4>
                        <small class="text-muted">Hoje</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-warning"><?= $stats['pendentes'] ?></h4>
                        <small class="text-muted">Pendentes</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-success"><?= $stats['confirmados'] ?></h4>
                        <small class="text-muted">Confirmados</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-danger"><?= $stats['cancelados'] ?></h4>
                        <small class="text-muted">Cancelados</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card filters-card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" name="status" id="status">
                            <option value="">Todos os status</option>
                            <option value="agendado" <?= $filtro_status === 'agendado' ? 'selected' : '' ?>>Agendado</option>
                            <option value="confirmado" <?= $filtro_status === 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                            <option value="finalizado" <?= $filtro_status === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
                            <option value="cancelado" <?= $filtro_status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="barbeiro" class="form-label">Barbeiro</label>
                        <select class="form-select" name="barbeiro" id="barbeiro">
                            <option value="">Todos os barbeiros</option>
                            <?php foreach ($barbeiros as $barbeiro): ?>
                                <option value="<?= $barbeiro['id'] ?>" <?= $filtro_barbeiro == $barbeiro['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($barbeiro['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="data" class="form-label">Data</label>
                        <input type="date" class="form-control" name="data" id="data" value="<?= htmlspecialchars($filtro_data) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                            <a href="agendamentos.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Limpar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Agendamentos -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt"></i> 
                    Agendamentos 
                    <span class="badge bg-secondary"><?= count($agendamentos) ?></span>
                </h5>
                <div>
                    <button class="btn btn-success btn-sm" onclick="window.location.reload()">
                        <i class="fas fa-sync"></i> Atualizar
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($agendamentos)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhum agendamento encontrado</h5>
                        <p class="text-muted">Tente ajustar os filtros ou aguarde novos agendamentos.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Cliente</th>
                                    <th>Barbeiro</th>
                                    <th>Serviço</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agendamentos as $agendamento): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?= date('d/m/Y', strtotime($agendamento['data_agendamento'])) ?></strong><br>
                                                <small class="text-muted"><?= date('H:i', strtotime($agendamento['hora_agendamento'])) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($agendamento['cliente_nome']) ?></strong><br>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone"></i> <?= htmlspecialchars($agendamento['cliente_telefone']) ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <i class="fas fa-user-tie text-muted me-1"></i>
                                            <?= htmlspecialchars($agendamento['barbeiro_nome']) ?>
                                        </td>
                                        <td>
                                            <div>
                                                <?= htmlspecialchars($agendamento['servico_nome']) ?><br>
                                                <small class="text-muted"><?= $agendamento['duracao'] ?> min</small>
                                            </div>
                                        </td>
                                        <td>
                                            <strong class="text-success">R$ <?= number_format($agendamento['valor'], 2, ',', '.') ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge status-<?= $agendamento['status'] ?> status-badge">
                                                <?= ucfirst($agendamento['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($agendamento['status'] === 'agendado'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="atualizar_status">
                                                        <input type="hidden" name="agendamento_id" value="<?= $agendamento['id'] ?>">
                                                        <input type="hidden" name="status" value="confirmado">
                                                        <button type="submit" class="btn btn-success btn-sm" title="Confirmar">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="cancelar">
                                                        <input type="hidden" name="agendamento_id" value="<?= $agendamento['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Cancelar" 
                                                                onclick="return confirm('Tem certeza que deseja cancelar?')">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                <?php elseif ($agendamento['status'] === 'confirmado'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="atualizar_status">
                                                        <input type="hidden" name="agendamento_id" value="<?= $agendamento['id'] ?>">
                                                        <input type="hidden" name="status" value="finalizado">
                                                        <button type="submit" class="btn btn-primary btn-sm" title="Finalizar">
                                                            <i class="fas fa-check-double"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="cancelar">
                                                        <input type="hidden" name="agendamento_id" value="<?= $agendamento['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Cancelar"
                                                                onclick="return confirm('Tem certeza que deseja cancelar?')">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-info btn-sm" title="Detalhes" 
                                                        onclick="verDetalhes(<?= $agendamento['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
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

    <!-- Modal de Detalhes -->
    <div class="modal fade" id="detalhesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Agendamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detalhesContent">
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
        function verDetalhes(id) {
            const modal = new bootstrap.Modal(document.getElementById('detalhesModal'));
            const content = document.getElementById('detalhesContent');
            
            content.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            `;
            
            modal.show();
            
            // Fazer requisição AJAX para buscar detalhes
            fetch('ajax_agendamento_detalhes.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        content.innerHTML = data.html;
                    } else {
                        content.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes.</div>';
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    content.innerHTML = '<div class="alert alert-danger">Erro interno do sistema.</div>';
                });
        }
        
        // Auto-refresh da página a cada 30 segundos
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>