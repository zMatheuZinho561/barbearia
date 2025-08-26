<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/appointment.php';
require_once '../includes/products.php';

$auth = new Auth();
$appointment = new Appointment();
$product = new Products();

// Verificar se usuário logado é admin
if (!$auth->isAdminLoggedIn()) {
    header('Location: ../admin/login.php');
    exit;
}

// Estatísticas rápidas
$stats = [
    'total_clientes' => $appointment->getTotalClientes(),
    'total_barbeiros' => $appointment->getTotalBarbeiros(),
    'total_servicos' => $appointment->getTotalServicos(),
    'total_agendamentos' => $appointment->getTotalAgendamentos(),
    'total_produtos' => $product->getTotalProdutos()
];

$admin_data = $auth->getAdminData();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel Admin - BarberShop</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #e67e22;
            --accent-color: #f39c12;
        }
        .navbar-brand { font-weight: bold; color: var(--primary-color) !important; }
        .card { border: none; box-shadow: 0 2px 15px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .card:hover { transform: translateY(-2px); }
        .welcome-card { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; }
        .admin-badge { background-color: var(--accent-color); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7em; }
    </style>
</head>
<body class="bg-light">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-cut"></i> BarberShop Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="agendamentos.php"><i class="fas fa-calendar"></i> Agendamentos</a></li>
                    <li class="nav-item"><a class="nav-link" href="clientes.php"><i class="fas fa-users"></i> Clientes</a></li>
                    <li class="nav-item"><a class="nav-link" href="barbeiros.php"><i class="fas fa-user-tie"></i> Barbeiros</a></li>
                    <li class="nav-item"><a class="nav-link" href="servicos.php"><i class="fas fa-list"></i> Serviços</a></li>
                    <li class="nav-item"><a class="nav-link" href="produtos.php"><i class="fas fa-box"></i> Produtos</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-shield"></i> 
                            <?= htmlspecialchars($admin_data['nome']) ?>
                            <span class="admin-badge">ADMIN</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <h6 class="dropdown-header">
                                    <i class="fas fa-info-circle"></i> Painel Administrativo
                                </h6>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="../index.php" target="_blank">
                                    <i class="fas fa-external-link-alt"></i> Ver Site Público
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="perfil.php">
                                    <i class="fas fa-user-cog"></i> Meu Perfil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="configuracoes.php">
                                    <i class="fas fa-cogs"></i> Configurações
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Sair do Admin
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Conteúdo -->
    <div class="container mt-4">
        <!-- Card de boas-vindas -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card welcome-card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1">
                                    <i class="fas fa-shield-alt"></i> 
                                    Bem-vindo, <?= htmlspecialchars($admin_data['nome']) ?>!
                                </h4>
                                <p class="mb-0">
                                    Painel de controle administrativo - Gerencie clientes, agendamentos, barbeiros, serviços e produtos da barbearia.
                                </p>
                                <small class="opacity-75">
                                    <i class="fas fa-user-tag"></i> 
                                    Nível de acesso: <?= ucfirst($admin_data['nivel']) ?>
                                </small>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="d-flex flex-column gap-2">
                                    <a href="agendamentos.php" class="btn btn-light btn-sm">
                                        <i class="fas fa-calendar-check"></i> Ver Agendamentos
                                    </a>
                                    <small class="text-light opacity-75">
                                        <i class="fas fa-clock"></i> 
                                        Último acesso: <?= date('d/m/Y H:i') ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estatísticas rápidas -->
        <div class="row mb-4">
            <div class="col-md-2 col-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-primary mb-2"></i>
                        <h5 class="mb-1"><?= $stats['total_clientes'] ?></h5>
                        <small class="text-muted">Clientes</small>
                        <div class="mt-2">
                            <a href="clientes.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-user-tie fa-2x text-success mb-2"></i>
                        <h5 class="mb-1"><?= $stats['total_barbeiros'] ?></h5>
                        <small class="text-muted">Barbeiros</small>
                        <div class="mt-2">
                            <a href="barbeiros.php" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-list fa-2x text-warning mb-2"></i>
                        <h5 class="mb-1"><?= $stats['total_servicos'] ?></h5>
                        <small class="text-muted">Serviços</small>
                        <div class="mt-2">
                            <a href="servicos.php" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-calendar-alt fa-2x text-info mb-2"></i>
                        <h5 class="mb-1"><?= $stats['total_agendamentos'] ?></h5>
                        <small class="text-muted">Agendamentos</small>
                        <div class="mt-2">
                            <a href="agendamentos.php" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-box fa-2x text-secondary mb-2"></i>
                        <h5 class="mb-1"><?= $stats['total_produtos'] ?></h5>
                        <small class="text-muted">Produtos</small>
                        <div class="mt-2">
                            <a href="produtos.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-chart-line fa-2x text-danger mb-2"></i>
                        <h5 class="mb-1">R$ 0,00</h5>
                        <small class="text-muted">Faturamento</small>
                        <div class="mt-2">
                            <a href="relatorios.php" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-chart-bar"></i> Ver
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ações rápidas -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt"></i> Ações Rápidas
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="agendamentos.php?action=novo" class="btn btn-primary w-100">
                                    <i class="fas fa-calendar-plus"></i><br>
                                    Novo Agendamento
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="clientes.php?action=novo" class="btn btn-success w-100">
                                    <i class="fas fa-user-plus"></i><br>
                                    Cadastrar Cliente
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="barbeiros.php?action=novo" class="btn btn-warning w-100">
                                    <i class="fas fa-user-tie"></i><br>
                                    Cadastrar Barbeiro
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="servicos.php?action=novo" class="btn btn-info w-100">
                                    <i class="fas fa-plus-circle"></i><br>
                                    Novo Serviço
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informações importantes -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-check"></i> Agendamentos de Hoje
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-day fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Nenhum agendamento para hoje</p>
                            <a href="agendamentos.php" class="btn btn-primary">
                                Ver Todos os Agendamentos
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle"></i> Sistema
                        </h5>
                    </div>
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">
                            <i class="fas fa-server"></i> Status: Online
                        </small>
                        <small class="text-muted d-block mb-2">
                            <i class="fas fa-database"></i> Banco: Conectado
                        </small>
                        <small class="text-muted d-block mb-2">
                            <i class="fas fa-shield-alt"></i> Segurança: Ativa
                        </small>
                        <small class="text-muted d-block">
                            <i class="fas fa-clock"></i> <?= date('d/m/Y H:i:s') ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>