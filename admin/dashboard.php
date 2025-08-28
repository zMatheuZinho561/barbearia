<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/appointment.php';
require_once '../includes/products.php';
require_once '../includes/plans.php';

$auth = new Auth();
$appointment = new Appointment();
$product = new Products();
$plans = new Plans();

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
    
    // Estatísticas de planos
    $estatisticas_planos = $plans->getEstatisticas();
    
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
    
    // Planos que vencem em breve
    $stmt = $db->prepare("
        SELECT cp.*, c.nome as cliente_nome, p.nome as plano_nome,
               DATEDIFF(cp.data_vencimento, CURDATE()) as dias_restantes
        FROM cliente_planos cp
        JOIN clientes c ON cp.cliente_id = c.id
        JOIN planos p ON cp.plano_id = p.id
        WHERE cp.status = 'ativo' 
        AND cp.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY cp.data_vencimento ASC
        LIMIT 5
    ");
    $stmt->execute();
    $planos_vencendo = $stmt->fetchAll();
    
} catch (Exception $e) {
    $stats['agendamentos_hoje'] = 0;
    $stats['agendamentos_pendentes'] = 0;
    $stats['agendamentos_confirmados'] = 0;
    $stats['faturamento_mes'] = 0;
    $proximos_agendamentos = [];
    $planos_vencendo = [];
    $estatisticas_planos = [
        'assinaturas_ativas' => 0,
        'vencem_breve' => 0,
        'vencidas' => 0,
        'receita_mensal' => 0
    ];
}

$admin_data = $auth->getAdminData();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Admin Moderno - BarberShop</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style/style_adm/st_dashboard.css">
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

    <!-- Demo Content -->
   

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

        <!-- Alertas de planos vencendo -->
        <?php if (!empty($planos_vencendo)): ?>
            <div class="alert alert-vencimento alert-dismissible fade show" role="alert">
                <h6><i class="fas fa-exclamation-triangle"></i> Planos com vencimento próximo:</h6>
                <ul class="mb-0">
                    <?php foreach ($planos_vencendo as $vencimento): ?>
                        <li>
                            <strong><?= htmlspecialchars($vencimento['cliente_nome']) ?></strong> 
                            (<?= htmlspecialchars($vencimento['plano_nome']) ?>) - 
                            Vence em <?= $vencimento['dias_restantes'] ?> dia(s) 
                            (<?= date('d/m/Y', strtotime($vencimento['data_vencimento'])) ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="mt-2">
                    <a href="clientes_planos.php?vencimento=vence_breve" class="btn btn-sm btn-outline-warning">
                        <i class="fas fa-eye"></i> Ver Todos
                    </a>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Estatísticas principais incluindo planos -->
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
                        <i class="fas fa-crown stat-icon text-warning"></i>
                        <div class="stat-number text-warning"><?= $estatisticas_planos['assinaturas_ativas'] ?></div>
                        <div class="stat-label">Planos Ativos</div>
                        <a href="clientes_planos.php" class="btn btn-sm btn-outline-warning mt-2">
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
                        <a href="admin_agendamentos.php" class="btn btn-sm btn-outline-info mt-2">
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
                        <div class="stat-number text-danger">R$ <?= number_format($stats['faturamento_mes'] + $estatisticas_planos['receita_mensal'], 2, ',', '.') ?></div>
                        <div class="stat-label">Receita Total</div>
                        <a href="relatorios.php" class="btn btn-sm btn-outline-danger mt-2">
                            <i class="fas fa-chart-bar"></i> Relatórios
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estatísticas de agendamentos e planos -->
        <div class="row mb-4">
            <div class="col-md-2 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h4 class="text-primary"><?= $stats['agendamentos_hoje'] ?></h4>
                        <small class="text-muted">
                            <i class="fas fa-calendar-day"></i> Hoje
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h4 class="text-warning"><?= $stats['agendamentos_pendentes'] ?></h4>
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> Pendentes
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h4 class="text-success"><?= $stats['agendamentos_confirmados'] ?></h4>
                        <small class="text-muted">
                            <i class="fas fa-check"></i> Confirmados
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h4 class="text-warning"><?= $estatisticas_planos['vencem_breve'] ?></h4>
                        <small class="text-muted">
                            <i class="fas fa-exclamation-triangle"></i> Vencem 7d
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h4 class="text-danger"><?= $estatisticas_planos['vencidas'] ?></h4>
                        <small class="text-muted">
                            <i class="fas fa-times-circle"></i> Vencidos
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
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
                                <a href="admin_agendamentos.php?action=novo" class="btn btn-primary w-100 quick-action-btn">
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
                                <a href="clientes_planos.php" class="btn btn-warning w-100 quick-action-btn">
                                    <i class="fas fa-crown"></i>
                                    <span>Associar Plano</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="planos.php?action=novo" class="btn btn-info w-100 quick-action-btn">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>Novo Plano</span>
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
                        <a href="admin_agendamentos.php" class="btn btn-sm btn-outline-primary">
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