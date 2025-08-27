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
    header('Location: login.php');
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

// Estatísticas extras do banco
$database = new Database();
$db = $database->getConnection();

try {
    // Agendamentos hoje
    $stmt = $db->query("SELECT COUNT(*) as total FROM agendamentos WHERE data_agendamento = CURDATE()");
    $stats['agendamentos_hoje'] = $stmt->fetch()['total'];
    
    // Agendamentos pendentes
    $stmt = $db->query("SELECT COUNT(*) as total FROM agendamentos WHERE status = 'agendado'");
    $stats['agendamentos_pendentes'] = $stmt->fetch()['total'];
    
    // Agendamentos confirmados
    $stmt = $db->query("SELECT COUNT(*) as total FROM agendamentos WHERE status = 'confirmado'");
    $stats['agendamentos_confirmados'] = $stmt->fetch()['total'];
    
    // Faturamento do mês
    $stmt = $db->query("
        SELECT SUM(s.preco) as total
        FROM agendamentos a
        JOIN servicos s ON a.servico_id = s.id
        WHERE MONTH(a.data_agendamento) = MONTH(CURDATE()) 
        AND YEAR(a.data_agendamento) = YEAR(CURDATE())
        AND a.status = 'finalizado'
    ");
    $result = $stmt->fetch();
    $stats['faturamento_mes'] = $result['total'] ?? 0;
    
    // Próximos agendamentos (5)
    $stmt = $db->prepare("
        SELECT a.*, c.nome as cliente_nome, b.nome as barbeiro_nome, s.nome as servico_nome
        FROM agendamentos a
        JOIN clientes c ON a.cliente_id = c.id
        JOIN barbeiros b ON a.barbeiro_id = b.id
        JOIN servicos s ON a.servico_id = s.id
        WHERE a.data_agendamento >= CURDATE() AND a.status IN ('agendado', 'confirmado')
        ORDER BY a.data_agendamento ASC, a.hora_agendamento ASC
        LIMIT 5
    ");
    $stmt->execute();
    $proximos_agendamentos = $stmt->fetchAll();
    
} catch (Exception $e) {
    $stats['agendamentos_hoje'] = 0;
    $stats['agendamentos_pendentes'] = 0;
    $stats['agendamentos_confirmados'] = 0;
    $stats['faturamento_mes'] = 0;
    $proximos_agendamentos = [];
}

$admin_data = $auth->getAdminData();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - BarberShop</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #e67e22;
            --accent-color: #f39c12;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --info-color: #3498db;
        }
        
        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand { 
            font-weight: bold; 
            color: var(--primary-color) !important; 
        }
        
        .card { 
            border: none; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            transition: all 0.3s ease;
            border-radius: 10px;
        }
        
        .card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 15px rgba(0,0,0,0.15);
        }
        
        .welcome-card { 
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); 
            color: white; 
            margin-bottom: 2rem;
        }
        
        .admin-badge { 
            background-color: var(--accent-color); 
            color: white; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 0.75em; 
            font-weight: bold;
        }
        
        .stat-card {
            text-align: center;
            padding: 1.5rem;
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .quick-action-btn {
            height: 80px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .quick-action-btn:hover {
            transform: scale(1.05);
            text-decoration: none;
        }
        
        .quick-action-btn i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,0.05);
        }
        
        .status-badge {
            font-size: 0.75em;
            padding: 0.5em 0.75em;
            border-radius: 20px;
        }
        
        .status-agendado { background-color: #17a2b8; color: white; }
        .status-confirmado { background-color: #28a745; color: white; }
        .status-finalizado { background-color: #6c757d; color: white; }
        .status-cancelado { background-color: #dc3545; color: white; }
        
        .nav-link.active {
            background-color: var(--primary-color) !important;
            color: white !important;
            border-radius: 5px;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-cut"></i> BarberShop Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_agendamentos.php">
                            <i class="fas fa-calendar"></i> Agendamentos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="clientes.php">
                            <i class="fas fa-users"></i> Clientes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="barbeiros.php">
                            <i class="fas fa-user-tie"></i> Barbeiros
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="servicos.php">
                            <i class="fas fa-list"></i> Serviços
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="produtos.php">
                            <i class="fas fa-box"></i> Produtos
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-shield"></i> 
                            <?= htmlspecialchars($admin_data['nome']) ?>
                            <span class="admin-badge">ADMIN</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
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
    <div class="container-fluid py-4">
        
        <!-- Card de boas-vindas -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card welcome-card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h3 class="mb-2">
                                    <i class="fas fa-shield-alt"></i> 
                                    Bem-vindo, <?= htmlspecialchars($admin_data['nome']) ?>!
                                </h3>
                                <p class="mb-2 opacity-90">
                                    Painel de controle administrativo - Gerencie todos os aspectos da sua barbearia
                                </p>
                                <div class="d-flex gap-3 mt-3">
                                    <small class="opacity-75">
                                        <i class="fas fa-user-tag"></i> 
                                        Nível: <?= ucfirst($admin_data['nivel']) ?>
                                    </small>
                                    <small class="opacity-75">
                                        <i class="fas fa-clock"></i> 
                                        <?= date('d/m/Y H:i') ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="d-flex flex-column gap-2">
                                    <a href="admin_agendamentos.php" class="btn btn-light btn-lg">
                                        <i class="fas fa-calendar-check"></i> Ver Agendamentos
                                    </a>
                                    <small class="text-light opacity-75">
                                        Sistema online e funcionando
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estatísticas principais -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card h-100">
                    <div class="card-body stat-card">
                        <i class="fas fa-users stat-icon text-primary"></i>
                        <div class="stat-number text-primary"><?= $stats['total_clientes'] ?></div>
                        <div class="stat-label">Clientes</div>
                        <a href="clientes.php" class="btn btn-sm btn-outline-primary mt-2">
                            <i class="fas fa-eye"></i> Gerenciar
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card h-100">
                    <div class="card-body stat-card">
                        <i class="fas fa-user-tie stat-icon text-success"></i>
                        <div class="stat-number text-success"><?= $stats['total_barbeiros'] ?></div>
                        <div class="stat-label">Barbeiros</div>
                        <a href="barbeiros.php" class="btn btn-sm btn-outline-success mt-2">
                            <i class="fas fa-eye"></i> Gerenciar
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card h-100">
                    <div class="card-body stat-card">
                        <i class="fas fa-list stat-icon text-warning"></i>
                        <div class="stat-number text-warning"><?= $stats['total_servicos'] ?></div>
                        <div class="stat-label">Serviços</div>
                        <a href="servicos.php" class="btn btn-sm btn-outline-warning mt-2">
                            <i class="fas fa-eye"></i> Gerenciar
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card h-100">
                    <div class="card-body stat-card">
                        <i class="fas fa-calendar-alt stat-icon text-info"></i>
                        <div class="stat-number text-info"><?= $stats['total_agendamentos'] ?></div>
                        <div class="stat-label">Agendamentos</div>
                        <a href="agendamentos.php" class="btn btn-sm btn-outline-info mt-2">
                            <i class="fas fa-eye"></i> Gerenciar
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card h-100">
                    <div class="card-body stat-card">
                        <i class="fas fa-box stat-icon text-secondary"></i>
                        <div class="stat-number text-secondary"><?= $stats['total_produtos'] ?></div>
                        <div class="stat-label">Produtos</div>
                        <a href="produtos.php" class="btn btn-sm btn-outline-secondary mt-2">
                            <i class="fas fa-eye"></i> Gerenciar
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card h-100">
                    <div class="card-body stat-card">
                        <i class="fas fa-money-bill-wave stat-icon text-danger"></i>
                        <div class="stat-number text-danger">R$ <?= number_format($stats['faturamento_mes'], 2, ',', '.') ?></div>
                        <div class="stat-label">Faturamento Mês</div>
                        <a href="relatorios.php" class="btn btn-sm btn-outline-danger mt-2">
                            <i class="fas fa-chart-bar"></i> Relatórios
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estatísticas de agendamentos -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h4 class="text-primary"><?= $stats['agendamentos_hoje'] ?></h4>
                        <small class="text-muted">
                            <i class="fas fa-calendar-day"></i> Agendamentos Hoje
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h4 class="text-warning"><?= $stats['agendamentos_pendentes'] ?></h4>
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> Pendentes
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h4 class="text-success"><?= $stats['agendamentos_confirmados'] ?></h4>
                        <small class="text-muted">
                            <i class="fas fa-check"></i> Confirmados
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <button class="btn btn-primary btn-sm" onclick="location.reload()">
                            <i class="fas fa-sync"></i> Atualizar
                        </button>
                        <small class="text-muted d-block mt-1">Status em tempo real</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ações rápidas e Próximos agendamentos -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt"></i> Ações Rápidas
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <a href="agendamentos.php?action=novo" class="btn btn-primary w-100 quick-action-btn">
                                    <i class="fas fa-calendar-plus"></i>
                                    <span>Novo Agendamento</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="clientes.php?action=novo" class="btn btn-success w-100 quick-action-btn">
                                    <i class="fas fa-user-plus"></i>
                                    <span>Cadastrar Cliente</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="barbeiros.php?action=novo" class="btn btn-warning w-100 quick-action-btn">
                                    <i class="fas fa-user-tie"></i>
                                    <span>Cadastrar Barbeiro</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="servicos.php?action=novo" class="btn btn-info w-100 quick-action-btn">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>Novo Serviço</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-check"></i> Próximos Agendamentos
                        </h5>
                        <a href="agendamentos.php" class="btn btn-sm btn-outline-primary">
                            Ver Todos
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($proximos_agendamentos)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-day fa-2x text-muted mb-3"></i>
                                <p class="text-muted mb-0">Nenhum agendamento próximo</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($proximos_agendamentos as $agendamento): ?>
                                    <div class="list-group-item px-0 py-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($agendamento['cliente_nome']) ?></h6>
                                                <p class="mb-1">
                                                    <small class="text-muted">
                                                        <i class="fas fa-user-tie"></i> <?= htmlspecialchars($agendamento['barbeiro_nome']) ?>
                                                        | <i class="fas fa-cut"></i> <?= htmlspecialchars($agendamento['servico_nome']) ?>
                                                    </small>
                                                </p>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-primary">
                                                    <?= date('d/m', strtotime($agendamento['data_agendamento'])) ?><br>
                                                    <?= date('H:i', strtotime($agendamento['hora_agendamento'])) ?>
                                                </small>
                                                <br>
                                                <span class="badge status-<?= $agendamento['status'] ?> status-badge">
                                                    <?= ucfirst($agendamento['status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informações do sistema -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <i class="fas fa-server text-success"></i>
                                <small class="text-muted d-block">Sistema Online</small>
                            </div>
                            <div class="col-md-3">
                                <i class="fas fa-database text-success"></i>
                                <small class="text-muted d-block">Banco Conectado</small>
                            </div>
                            <div class="col-md-3">
                                <i class="fas fa-shield-alt text-success"></i>
                                <small class="text-muted d-block">Segurança Ativa</small>
                            </div>
                            <div class="col-md-3">
                                <i class="fas fa-clock text-info"></i>
                                <small class="text-muted d-block"><?= date('d/m/Y H:i:s') ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh estatísticas a cada 60 segundos
        setTimeout(function() {
            location.reload();
        }, 60000);
        
        // Tooltip para botões
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>