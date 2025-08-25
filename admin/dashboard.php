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
    header('Location: ../cliente/login.php');
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
                            <i class="fas fa-user-shield"></i> <?= htmlspecialchars($admin_data['nome']) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../index.php"><i class="fas fa-home"></i> Início</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
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
                        <h4 class="mb-1">Bem-vindo, <?= htmlspecialchars($admin_data['nome']) ?>!</h4>
                        <p class="mb-0">Gerencie clientes, agendamentos, barbeiros, serviços e produtos da barbearia.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estatísticas rápidas -->
        <div class="row mb-4">
            <div class="col-md-2 col-6 mb-3">
                <div class="card text-center"><div class="card-body"><h5><?= $stats['total_clientes'] ?></h5><small class="text-muted">Clientes</small></div></div>
            </div>
            <div class="col-md-2 col-6 mb-3">
                <div class="card text-center"><div class="card-body"><h5><?= $stats['total_barbeiros'] ?></h5><small class="text-muted">Barbeiros</small></div></div>
            </div>
            <div class="col-md-2 col-6 mb-3">
                <div class="card text-center"><div class="card-body"><h5><?= $stats['total_servicos'] ?></h5><small class="text-muted">Serviços</small></div></div>
            </div>
            <div class="col-md-2 col-6 mb-3">
                <div class="card text-center"><div class="card-body"><h5><?= $stats['total_agendamentos'] ?></h5><small class="text-muted">Agendamentos</small></div></div>
            </div>
            <div class="col-md-2 col-6 mb-3">
                <div class="card text-center"><div class="card-body"><h5><?= $stats['total_produtos'] ?></h5><small class="text-muted">Produtos</small></div></div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>