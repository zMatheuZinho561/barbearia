<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth();

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
        case 'associar_plano':
            try {
                // Verificar se o cliente já tem plano ativo
                $stmt = $db->prepare("SELECT id FROM cliente_planos WHERE cliente_id = ? AND status = 'ativo'");
                $stmt->execute([$_POST['cliente_id']]);
                
                if ($stmt->rowCount() > 0) {
                    $error = 'Cliente já possui um plano ativo.';
                    break;
                }

                // Buscar dados do plano
                $stmt = $db->prepare("SELECT * FROM planos WHERE id = ?");
                $stmt->execute([$_POST['plano_id']]);
                $plano = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$plano) {
                    $error = 'Plano não encontrado.';
                    break;
                }

                // Calcular data de vencimento
                $data_inicio = date('Y-m-d');
                $data_vencimento = date('Y-m-d', strtotime($data_inicio . ' +' . $plano['duracao_dias'] . ' days'));

                // Inserir nova assinatura
                $stmt = $db->prepare("
                    INSERT INTO cliente_planos (
                        cliente_id, plano_id, data_inicio, data_vencimento, 
                        status, valor_pago, observacoes, data_pagamento, admin_id
                    ) VALUES (?, ?, ?, ?, 'ativo', ?, ?, NOW(), ?)
                ");
                
                $result = $stmt->execute([
                    $_POST['cliente_id'],
                    $_POST['plano_id'],
                    $data_inicio,
                    $data_vencimento,
                    $plano['preco'],
                    $_POST['observacoes'] ?? '',
                    $admin_data['id']
                ]);

                if ($result) {
                    $success = 'Plano associado com sucesso!';
                } else {
                    $error = 'Erro ao associar plano.';
                }
            } catch (Exception $e) {
                $error = 'Erro: ' . $e->getMessage();
            }
            break;
            
        case 'cancelar_plano':
            try {
                $stmt = $db->prepare("
                    UPDATE cliente_planos 
                    SET status = 'cancelado', 
                        data_cancelamento = NOW(), 
                        motivo_cancelamento = ?, 
                        admin_id = ?
                    WHERE id = ?
                ");
                
                $result = $stmt->execute([
                    $_POST['motivo'],
                    $admin_data['id'],
                    $_POST['assinatura_id']
                ]);

                if ($result) {
                    $success = 'Plano cancelado com sucesso!';
                } else {
                    $error = 'Erro ao cancelar plano.';
                }
            } catch (Exception $e) {
                $error = 'Erro: ' . $e->getMessage();
            }
            break;
            
        case 'renovar_plano':
            try {
                // Buscar dados da assinatura atual
                $stmt = $db->prepare("
                    SELECT cp.*, p.duracao_dias, p.preco 
                    FROM cliente_planos cp
                    JOIN planos p ON cp.plano_id = p.id
                    WHERE cp.id = ?
                ");
                $stmt->execute([$_POST['assinatura_id']]);
                $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$assinatura) {
                    $error = 'Assinatura não encontrada.';
                    break;
                }

                // Calcular nova data de vencimento
                $nova_data_inicio = date('Y-m-d');
                $nova_data_vencimento = date('Y-m-d', strtotime($nova_data_inicio . ' +' . $assinatura['duracao_dias'] . ' days'));

                // Atualizar assinatura
                $stmt = $db->prepare("
                    UPDATE cliente_planos 
                    SET data_inicio = ?, 
                        data_vencimento = ?, 
                        status = 'ativo',
                        cortes_utilizados = 0,
                        data_pagamento = NOW(),
                        observacoes = ?,
                        admin_id = ?
                    WHERE id = ?
                ");
                
                $result = $stmt->execute([
                    $nova_data_inicio,
                    $nova_data_vencimento,
                    $_POST['observacoes'] ?? '',
                    $admin_data['id'],
                    $_POST['assinatura_id']
                ]);

                if ($result) {
                    $success = 'Plano renovado com sucesso!';
                } else {
                    $error = 'Erro ao renovar plano.';
                }
            } catch (Exception $e) {
                $error = 'Erro: ' . $e->getMessage();
            }
            break;
    }
}

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_plano = $_GET['plano'] ?? '';
$filtro_vencimento = $_GET['vencimento'] ?? '';

// Buscar clientes com planos
$where_conditions = [];
$params = [];

if ($filtro_status) {
    $where_conditions[] = "cp.status = ?";
    $params[] = $filtro_status;
}

if ($filtro_plano) {
    $where_conditions[] = "cp.plano_id = ?";
    $params[] = $filtro_plano;
}

if ($filtro_vencimento) {
    switch ($filtro_vencimento) {
        case 'vencido':
            $where_conditions[] = "cp.data_vencimento < CURDATE() AND cp.status = 'ativo'";
            break;
        case 'vence_breve':
            $where_conditions[] = "cp.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND cp.status = 'ativo'";
            break;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$sql = "
    SELECT 
        cp.*,
        c.nome as cliente_nome,
        c.telefone,
        c.email,
        p.nome as plano_nome,
        p.cor_badge,
        p.cortes_inclusos,
        p.preco as plano_preco,
        DATEDIFF(cp.data_vencimento, CURDATE()) as dias_restantes,
        CASE 
            WHEN cp.status = 'ativo' AND cp.data_vencimento >= CURDATE() THEN 'success'
            WHEN cp.status = 'ativo' AND cp.data_vencimento < CURDATE() THEN 'danger'
            WHEN cp.status = 'vencido' THEN 'danger'
            WHEN cp.status = 'cancelado' THEN 'secondary'
            WHEN cp.status = 'suspenso' THEN 'warning'
            ELSE 'secondary'
        END as status_cor,
        CASE 
            WHEN cp.status = 'ativo' AND cp.data_vencimento >= CURDATE() THEN 'Ativo'
            WHEN cp.status = 'ativo' AND cp.data_vencimento < CURDATE() THEN 'Vencido'
            WHEN cp.status = 'vencido' THEN 'Vencido'
            WHEN cp.status = 'cancelado' THEN 'Cancelado'
            WHEN cp.status = 'suspenso' THEN 'Suspenso'
            ELSE 'Indefinido'
        END as status_descricao
    FROM cliente_planos cp
    JOIN clientes c ON cp.cliente_id = c.id
    JOIN planos p ON cp.plano_id = p.id
    $where_clause
    ORDER BY cp.data_vencimento ASC, c.nome ASC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$clientes_planos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar todos os planos
$stmt = $db->query("SELECT * FROM planos WHERE ativo = 1 ORDER BY nome");
$todos_planos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar estatísticas
$stats_sql = "
    SELECT 
        COUNT(CASE WHEN cp.status = 'ativo' THEN 1 END) as assinaturas_ativas,
        COUNT(CASE WHEN cp.status = 'ativo' AND cp.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as vencem_breve,
        COUNT(CASE WHEN cp.status = 'vencido' OR (cp.status = 'ativo' AND cp.data_vencimento < CURDATE()) THEN 1 END) as vencidas,
        COALESCE(SUM(CASE WHEN cp.status = 'ativo' AND cp.data_pagamento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN cp.valor_pago END), 0) as receita_mensal
    FROM cliente_planos cp
";
$stmt = $db->query($stats_sql);
$estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);

// Buscar clientes sem plano ativo para associar planos
$stmt = $db->query("
    SELECT c.id, c.nome, c.telefone 
    FROM clientes c 
    LEFT JOIN cliente_planos cp ON c.id = cp.cliente_id AND cp.status = 'ativo'
    WHERE c.ativo = 1 AND cp.id IS NULL
    ORDER BY c.nome
");
$clientes_sem_plano = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar vencimentos próximos
$stmt = $db->query("
    SELECT 
        cp.id,
        c.nome as cliente_nome,
        c.telefone,
        c.email,
        p.nome as plano_nome,
        cp.data_vencimento,
        DATEDIFF(cp.data_vencimento, CURDATE()) as dias_restantes
    FROM cliente_planos cp
    JOIN clientes c ON cp.cliente_id = c.id
    JOIN planos p ON cp.plano_id = p.id
    WHERE cp.status = 'ativo'
    AND cp.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY cp.data_vencimento ASC
");
$vencimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Admin Moderno - BarberShop</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style/style_adm/st_clientesplanos.css">
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
                            <li><hr class="dropdown-divider"></li>
                        
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

        <!-- Alertas de vencimento -->
        <?php if (!empty($vencimentos)): ?>
            <div class="alert alert-vencimento alert-dismissible fade show" role="alert">
                <h6><i class="fas fa-exclamation-triangle"></i> Planos com vencimento próximo:</h6>
                <ul class="mb-0">
                    <?php foreach ($vencimentos as $vencimento): ?>
                        <li>
                            <strong><?= htmlspecialchars($vencimento['cliente_nome']) ?></strong> 
                            (<?= htmlspecialchars($vencimento['plano_nome']) ?>) - 
                            Vence em <?= $vencimento['dias_restantes'] ?> dia(s) 
                            (<?= date('d/m/Y', strtotime($vencimento['data_vencimento'])) ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-crown fa-2x text-success mb-2"></i>
                        <h4 class="text-success mb-1"><?= $estatisticas['assinaturas_ativas'] ?></h4>
                        <small class="text-muted">Assinaturas Ativas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                        <h4 class="text-warning mb-1"><?= $estatisticas['vencem_breve'] ?></h4>
                        <small class="text-muted">Vencem em 7 dias</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                        <h4 class="text-danger mb-1"><?= $estatisticas['vencidas'] ?></h4>
                        <small class="text-muted">Planos Vencidos</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-money-bill-wave fa-2x text-info mb-2"></i>
                        <h4 class="text-info mb-1">R$ <?= number_format($estatisticas['receita_mensal'], 2, ',', '.') ?></h4>
                        <small class="text-muted">Receita Mensal</small>
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
                            <option value="ativo" <?= $filtro_status === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="vencido" <?= $filtro_status === 'vencido' ? 'selected' : '' ?>>Vencido</option>
                            <option value="cancelado" <?= $filtro_status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                            <option value="suspenso" <?= $filtro_status === 'suspenso' ? 'selected' : '' ?>>Suspenso</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="plano" class="form-label">Plano</label>
                        <select class="form-select" name="plano" id="plano">
                            <option value="">Todos os planos</option>
                            <?php foreach ($todos_planos as $plano): ?>
                                <option value="<?= $plano['id'] ?>" <?= $filtro_plano == $plano['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($plano['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="vencimento" class="form-label">Vencimento</label>
                        <select class="form-select" name="vencimento" id="vencimento">
                            <option value="">Todos</option>
                            <option value="vencido" <?= $filtro_vencimento === 'vencido' ? 'selected' : '' ?>>Vencidos</option>
                            <option value="vence_breve" <?= $filtro_vencimento === 'vence_breve' ? 'selected' : '' ?>>Vencem em breve</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                            <a href="clientes_planos.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Limpar
                            </a>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#associarPlanoModal">
                                <i class="fas fa-plus"></i> Associar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Assinaturas -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-users-cog"></i> 
                    Assinaturas de Clientes
                    <span class="badge bg-light text-dark ms-2"><?= count($clientes_planos) ?></span>
                </h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#associarPlanoModal">
                        <i class="fas fa-user-plus"></i> Associar Plano
                    </button>
                    <button class="btn btn-info btn-sm" onclick="window.location.reload()">
                        <i class="fas fa-sync"></i> Atualizar
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($clientes_planos)): ?>
                    <div class="empty-state">
                        <i class="fas fa-crown"></i>
                        <h5>Nenhuma assinatura encontrada</h5>
                        <p>Tente ajustar os filtros ou associe o primeiro plano a um cliente.</p>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#associarPlanoModal">
                            <i class="fas fa-plus"></i> Associar Primeiro Plano
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Plano</th>
                                    <th>Período</th>
                                    <th>Uso de Cortes</th>
                                    <th>Status</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes_planos as $cp): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($cp['cliente_nome']) ?></strong><br>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone"></i> <?= htmlspecialchars($cp['telefone']) ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $cp['cor_badge'] ?>">
                                                <?= htmlspecialchars($cp['plano_nome']) ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">R$ <?= number_format($cp['plano_preco'], 2, ',', '.') ?></small>
                                        </td>
                                        <td>
                                            <div>
                                                <small class="text-muted">Início:</small> 
                                                <strong><?= date('d/m/Y', strtotime($cp['data_inicio'])) ?></strong><br>
                                                <small class="text-muted">Vencimento:</small> 
                                                <strong class="<?= $cp['dias_restantes'] < 0 ? 'text-danger' : ($cp['dias_restantes'] <= 7 ? 'text-warning' : 'text-success') ?>">
                                                    <?= date('d/m/Y', strtotime($cp['data_vencimento'])) ?>
                                                </strong><br>
                                                <small class="<?= $cp['dias_restantes'] < 0 ? 'text-danger' : ($cp['dias_restantes'] <= 7 ? 'text-warning' : 'text-muted') ?>">
                                                    <?php if ($cp['dias_restantes'] < 0): ?>
                                                        Vencido há <?= abs($cp['dias_restantes']) ?> dias
                                                    <?php elseif ($cp['dias_restantes'] == 0): ?>
                                                        Vence hoje
                                                    <?php else: ?>
                                                        Restam <?= $cp['dias_restantes'] ?> dias
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <?php if ($cp['cortes_inclusos'] == 0): ?>
                                                    <span class="badge bg-success">Ilimitados</span>
                                                <?php else: ?>
                                                    <div class="mb-1">
                                                        <strong><?= $cp['cortes_utilizados'] ?></strong> de <strong><?= $cp['cortes_inclusos'] ?></strong>
                                                    </div>
                                                    <?php 
                                                    $percentual = ($cp['cortes_utilizados'] / $cp['cortes_inclusos']) * 100;
                                                    $cor_progress = $percentual >= 100 ? 'danger' : ($percentual >= 75 ? 'warning' : 'success');
                                                    ?>
                                                    <div class="progress">
                                                        <div class="progress-bar bg-<?= $cor_progress ?>" 
                                                             style="width: <?= min($percentual, 100) ?>%"></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $cp['status_cor'] ?>">
                                                <?= $cp['status_descricao'] ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <?php if ($cp['status'] === 'ativo' || $cp['status'] === 'vencido'): ?>
                                                    <button class="btn btn-primary btn-sm" title="Renovar" 
                                                            onclick="renovarPlano(<?= $cp['id'] ?>, '<?= htmlspecialchars($cp['cliente_nome']) ?>', '<?= htmlspecialchars($cp['plano_nome']) ?>')">
                                                        <i class="fas fa-redo"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($cp['status'] === 'ativo'): ?>
                                                    <button class="btn btn-danger btn-sm" title="Cancelar" 
                                                            onclick="cancelarPlano(<?= $cp['id'] ?>, '<?= htmlspecialchars($cp['cliente_nome']) ?>', '<?= htmlspecialchars($cp['plano_nome']) ?>')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-info btn-sm" title="Ver Detalhes"
                                                        onclick="verDetalhes(<?= $cp['id'] ?>)">
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

    <!-- Modal Associar Plano -->
    <div class="modal fade" id="associarPlanoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Associar Plano ao Cliente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formAssociarPlano">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="associar_plano">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Importante:</strong> Apenas clientes sem plano ativo podem receber um novo plano.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cliente_id" class="form-label">Cliente *</label>
                                <select class="form-select" name="cliente_id" id="cliente_id" required>
                                    <option value="">Selecione o cliente</option>
                                    <?php foreach ($clientes_sem_plano as $cliente): ?>
                                        <option value="<?= $cliente['id'] ?>">
                                            <?= htmlspecialchars($cliente['nome']) ?> - <?= htmlspecialchars($cliente['telefone']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($clientes_sem_plano)): ?>
                                    <div class="form-text text-warning">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        Todos os clientes já possuem plano ativo.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="plano_id" class="form-label">Plano *</label>
                                <select class="form-select" name="plano_id" id="plano_id" required>
                                    <option value="">Selecione o plano</option>
                                    <?php foreach ($todos_planos as $plano): ?>
                                        <option value="<?= $plano['id'] ?>" 
                                                data-preco="<?= $plano['preco'] ?>" 
                                                data-duracao="<?= $plano['duracao_dias'] ?>"
                                                data-cortes="<?= $plano['cortes_inclusos'] ?>">
                                            <?= htmlspecialchars($plano['nome']) ?> - R$ <?= number_format($plano['preco'], 2, ',', '.') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="observacoes" class="form-label">Observações</label>
                                <textarea class="form-control" name="observacoes" id="observacoes" rows="3" 
                                          placeholder="Observações sobre esta assinatura..."></textarea>
                            </div>
                        </div>
                        
                        <div id="resumo_plano" class="alert alert-light" style="display: none;">
                            <h6><i class="fas fa-info-circle"></i> Resumo da Assinatura:</h6>
                            <div id="resumo_content"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success" <?= empty($clientes_sem_plano) ? 'disabled' : '' ?>>
                            <i class="fas fa-user-plus"></i> Associar Plano
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Renovar Plano -->
    <div class="modal fade" id="renovarPlanoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-redo"></i> Renovar Plano</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formRenovarPlano">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="renovar_plano">
                        <input type="hidden" name="assinatura_id" id="renovar_assinatura_id">
                        
                        <div class="alert alert-info">
                            <div id="renovar_info"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="renovar_observacoes" class="form-label">Observações da Renovação</label>
                            <textarea class="form-control" name="observacoes" id="renovar_observacoes" rows="3" 
                                      placeholder="Observações sobre esta renovação..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-redo"></i> Renovar Plano
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Cancelar Plano -->
    <div class="modal fade" id="cancelarPlanoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times"></i> Cancelar Plano</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formCancelarPlano">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cancelar_plano">
                        <input type="hidden" name="assinatura_id" id="cancelar_assinatura_id">
                        
                        <div class="alert alert-warning">
                            <div id="cancelar_info"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="motivo" class="form-label">Motivo do Cancelamento *</label>
                            <textarea class="form-control" name="motivo" id="motivo" rows="3" required
                                      placeholder="Descreva o motivo do cancelamento..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> Confirmar Cancelamento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Ver Detalhes -->
    <div class="modal fade" id="detalhesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-eye"></i> Detalhes da Assinatura</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="detalhes_content">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
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
        // Atualizar resumo do plano ao selecionar
        document.getElementById('plano_id').addEventListener('change', function() {
            const planoSelect = this;
            const resumoDiv = document.getElementById('resumo_plano');
            const resumoContent = document.getElementById('resumo_content');
            
            if (planoSelect.value) {
                const option = planoSelect.options[planoSelect.selectedIndex];
                const preco = option.getAttribute('data-preco');
                const duracao = option.getAttribute('data-duracao');
                const cortes = option.getAttribute('data-cortes');
                const dataInicio = new Date().toLocaleDateString('pt-BR');
                const dataVencimento = new Date();
                dataVencimento.setDate(dataVencimento.getDate() + parseInt(duracao));
                
                resumoContent.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Plano:</strong> ${option.text}<br>
                            <strong>Valor:</strong> R$ ${parseFloat(preco).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                        </div>
                        <div class="col-md-6">
                            <strong>Data início:</strong> ${dataInicio}<br>
                            <strong>Data vencimento:</strong> ${dataVencimento.toLocaleDateString('pt-BR')}
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Duração:</strong> ${duracao} dias
                        </div>
                        <div class="col-md-6">
                            <strong>Cortes inclusos:</strong> ${cortes == 0 ? 'Ilimitados' : cortes}
                        </div>
                    </div>
                `;
                resumoDiv.style.display = 'block';
            } else {
                resumoDiv.style.display = 'none';
            }
        });

        function renovarPlano(assinaturaId, clienteNome, planoNome) {
            document.getElementById('renovar_assinatura_id').value = assinaturaId;
            document.getElementById('renovar_info').innerHTML = `
                <i class="fas fa-redo"></i> 
                <strong>Renovar plano "${planoNome}" do cliente "${clienteNome}"</strong><br>
                O plano será renovado por mais um período a partir de hoje.<br>
                <small class="text-muted">Os cortes utilizados serão zerados.</small>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('renovarPlanoModal'));
            modal.show();
        }

        function cancelarPlano(assinaturaId, clienteNome, planoNome) {
            document.getElementById('cancelar_assinatura_id').value = assinaturaId;
            document.getElementById('cancelar_info').innerHTML = `
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Cancelar plano "${planoNome}" do cliente "${clienteNome}"</strong><br>
                Esta ação não pode ser desfeita. O cliente perderá acesso aos benefícios do plano.
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('cancelarPlanoModal'));
            modal.show();
        }

        function verDetalhes(assinaturaId) {
            const modal = new bootstrap.Modal(document.getElementById('detalhesModal'));
            const detalhesContent = document.getElementById('detalhes_content');
            
            // Simular carregamento dos detalhes
            detalhesContent.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            `;
            
            modal.show();
            
            // Simular dados dos detalhes
            setTimeout(() => {
                detalhesContent.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Funcionalidade de detalhes completos em desenvolvimento.
                    </div>
                    <p><strong>ID da Assinatura:</strong> ${assinaturaId}</p>
                    <p>Aqui serão exibidos:</p>
                    <ul>
                        <li>Histórico de pagamentos</li>
                        <li>Histórico de uso de cortes</li>
                        <li>Alterações na assinatura</li>
                        <li>Observações detalhadas</li>
                    </ul>
                `;
            }, 1000);
        }

        // Validação do formulário de associar plano
        document.getElementById('formAssociarPlano').addEventListener('submit', function(e) {
            const clienteId = document.getElementById('cliente_id').value;
            const planoId = document.getElementById('plano_id').value;
            
            if (!clienteId || !planoId) {
                e.preventDefault();
                alert('Por favor, selecione um cliente e um plano.');
                return;
            }
            
            if (confirm('Tem certeza que deseja associar este plano ao cliente?')) {
                return true;
            } else {
                e.preventDefault();
                return false;
            }
        });

        // Validação do formulário de cancelar plano
        document.getElementById('formCancelarPlano').addEventListener('submit', function(e) {
            const motivo = document.getElementById('motivo').value.trim();
            
            if (motivo.length < 10) {
                e.preventDefault();
                alert('Por favor, forneça um motivo detalhado para o cancelamento (mínimo 10 caracteres).');
                return;
            }
            
            if (confirm('Tem certeza que deseja CANCELAR esta assinatura? Esta ação não pode ser desfeita!')) {
                return true;
            } else {
                e.preventDefault();
                return false;
            }
        });

        // Auto-reload da página a cada 5 minutos para atualizar vencimentos
        setTimeout(function() {
            window.location.reload();
        }, 300000); // 5 minutos

        // Destacar linhas com vencimento próximo
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const statusBadge = row.querySelector('.badge');
                if (statusBadge && statusBadge.textContent.includes('Vencido')) {
                    row.style.backgroundColor = '#ffe6e6';
                    row.style.borderLeft = '4px solid #dc3545';
                }
            });
        });
    </script>
</body>
</html>