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
        case 'cadastrar_barbeiro':
            if (!empty($_POST['nome']) && !empty($_POST['telefone']) && !empty($_POST['email'])) {
                try {
                    // Verificar se já existe
                    $stmt = $db->prepare("SELECT id FROM barbeiros WHERE email = ? OR telefone = ?");
                    $stmt->execute([$_POST['email'], $_POST['telefone']]);
                    
                    if ($stmt->rowCount() > 0) {
                        $error = 'Barbeiro já cadastrado com este email ou telefone.';
                    } else {
                        // Inserir barbeiro
                        $stmt = $db->prepare("
                            INSERT INTO barbeiros (nome, telefone, email, especialidade, ativo, comissao, horario_inicio, horario_fim) 
                            VALUES (?, ?, ?, ?, 1, ?, ?, ?)
                        ");
                        
                        if ($stmt->execute([
                            $_POST['nome'],
                            $_POST['telefone'],
                            $_POST['email'],
                            $_POST['especialidade'] ?? '',
                            $_POST['comissao'] ?? 0,
                            $_POST['horario_inicio'] ?? '08:00',
                            $_POST['horario_fim'] ?? '18:00'
                        ])) {
                            $success = 'Barbeiro cadastrado com sucesso!';
                        } else {
                            $error = 'Erro ao cadastrar barbeiro.';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Erro interno do servidor.';
                }
            } else {
                $error = 'Nome, telefone e email são obrigatórios.';
            }
            break;
            
        case 'editar_barbeiro':
            if (!empty($_POST['barbeiro_id']) && !empty($_POST['nome']) && 
                !empty($_POST['telefone']) && !empty($_POST['email'])) {
                try {
                    // Verificar se email/telefone já existem em outro barbeiro
                    $stmt = $db->prepare("
                        SELECT id FROM barbeiros 
                        WHERE (email = ? OR telefone = ?) AND id != ?
                    ");
                    $stmt->execute([$_POST['email'], $_POST['telefone'], $_POST['barbeiro_id']]);
                    
                    if ($stmt->rowCount() > 0) {
                        $error = 'Email ou telefone já está sendo usado por outro barbeiro.';
                    } else {
                        // Atualizar barbeiro
                        $stmt = $db->prepare("
                            UPDATE barbeiros 
                            SET nome = ?, telefone = ?, email = ?, especialidade = ?, 
                                comissao = ?, horario_inicio = ?, horario_fim = ?
                            WHERE id = ?
                        ");
                        
                        if ($stmt->execute([
                            $_POST['nome'],
                            $_POST['telefone'],
                            $_POST['email'],
                            $_POST['especialidade'] ?? '',
                            $_POST['comissao'] ?? 0,
                            $_POST['horario_inicio'] ?? '08:00',
                            $_POST['horario_fim'] ?? '18:00',
                            $_POST['barbeiro_id']
                        ])) {
                            $success = 'Barbeiro atualizado com sucesso!';
                        } else {
                            $error = 'Erro ao atualizar barbeiro.';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Erro interno do servidor.';
                }
            } else {
                $error = 'Todos os campos obrigatórios devem ser preenchidos.';
            }
            break;
            
        case 'desativar_barbeiro':
            if (!empty($_POST['barbeiro_id'])) {
                try {
                    $stmt = $db->prepare("UPDATE barbeiros SET ativo = 0 WHERE id = ?");
                    if ($stmt->execute([$_POST['barbeiro_id']])) {
                        $success = 'Barbeiro desativado com sucesso.';
                    } else {
                        $error = 'Erro ao desativar barbeiro.';
                    }
                } catch (Exception $e) {
                    $error = 'Erro interno do servidor.';
                }
            }
            break;
            
        case 'ativar_barbeiro':
            if (!empty($_POST['barbeiro_id'])) {
                try {
                    $stmt = $db->prepare("UPDATE barbeiros SET ativo = 1 WHERE id = ?");
                    if ($stmt->execute([$_POST['barbeiro_id']])) {
                        $success = 'Barbeiro ativado com sucesso.';
                    } else {
                        $error = 'Erro ao ativar barbeiro.';
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
$filtro_status = $_GET['status'] ?? '';
$data_agenda = $_GET['data_agenda'] ?? date('Y-m-d');

// Buscar barbeiros com estatísticas
$barbeiros = [];
try {
    $sql = "
        SELECT b.*, 
               (SELECT COUNT(*) FROM agendamentos WHERE barbeiro_id = b.id) as total_agendamentos,
               (SELECT COUNT(*) FROM agendamentos WHERE barbeiro_id = b.id AND status = 'finalizado') as agendamentos_finalizados,
               (SELECT COUNT(*) FROM agendamentos WHERE barbeiro_id = b.id AND DATE(data_agendamento) = ?) as agendamentos_hoje
        FROM barbeiros b
        WHERE 1=1
    ";
    
    $params = [$data_agenda];
    
    if ($filtro_nome) {
        $sql .= " AND b.nome LIKE ?";
        $params[] = '%' . $filtro_nome . '%';
    }
    
    if ($filtro_status === 'ativo') {
        $sql .= " AND b.ativo = 1";
    } elseif ($filtro_status === 'inativo') {
        $sql .= " AND b.ativo = 0";
    }
    
    $sql .= " ORDER BY b.nome ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $barbeiros = $stmt->fetchAll();
    
    // Para cada barbeiro, buscar agendamentos do dia
    foreach ($barbeiros as &$barbeiro) {
        $stmt_agenda = $db->prepare("
            SELECT a.*, c.nome as cliente_nome, s.nome as servico_nome, s.duracao
            FROM agendamentos a
            JOIN clientes c ON a.cliente_id = c.id
            JOIN servicos s ON a.servico_id = s.id
            WHERE a.barbeiro_id = ? AND DATE(a.data_agendamento) = ?
            ORDER BY a.hora_agendamento ASC
        ");
        $stmt_agenda->execute([$barbeiro['id'], $data_agenda]);
        $barbeiro['agenda_dia'] = $stmt_agenda->fetchAll();
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar barbeiros: " . $e->getMessage());
    $error = 'Erro ao carregar barbeiros.';
}

// Estatísticas
$stats = [];
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM barbeiros WHERE ativo = 1");
    $stats['ativos'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM barbeiros WHERE ativo = 0");
    $stats['inativos'] = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM agendamentos 
        WHERE DATE(data_agendamento) = ? AND status IN ('agendado', 'confirmado')
    ");
    $stmt->execute([$data_agenda]);
    $stats['agendamentos_dia'] = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM agendamentos 
        WHERE DATE(data_agendamento) = ? AND status = 'finalizado'
    ");
    $stmt->execute([$data_agenda]);
    $stats['finalizados_dia'] = $stmt->fetch()['total'];
    
} catch (Exception $e) {
    $stats = ['ativos' => 0, 'inativos' => 0, 'agendamentos_dia' => 0, 'finalizados_dia' => 0];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barbeiros - BarberShop Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../style/style_adm/st_barbeiros.css" rel="stylesheet">
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
                        <a class="nav-link" href="dashboard.php">
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
                        <a class="nav-link active" href="barbeiros.php">
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
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-user-tie fa-2x text-success mb-2"></i>
                        <h4 class="text-success mb-1"><?= $stats['ativos'] ?></h4>
                        <small class="text-muted">Barbeiros Ativos</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-user-times fa-2x text-danger mb-2"></i>
                        <h4 class="text-danger mb-1"><?= $stats['inativos'] ?></h4>
                        <small class="text-muted">Inativos</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                        <h4 class="text-primary mb-1"><?= $stats['agendamentos_dia'] ?></h4>
                        <small class="text-muted">Agendamentos Hoje</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-check-double fa-2x text-info mb-2"></i>
                        <h4 class="text-info mb-1"><?= $stats['finalizados_dia'] ?></h4>
                        <small class="text-muted">Finalizados Hoje</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card filters-card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="nome" class="form-label">Nome do Barbeiro</label>
                        <input type="text" class="form-control" name="nome" id="nome" 
                               value="<?= htmlspecialchars($filtro_nome) ?>" placeholder="Buscar por nome">
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
                        <label for="data_agenda" class="form-label">Data da Agenda</label>
                        <input type="date" class="form-control" name="data_agenda" id="data_agenda" 
                               value="<?= htmlspecialchars($data_agenda) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-light">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                            <a href="barbeiros.php" class="btn btn-outline-light">
                                <i class="fas fa-times"></i> Limpar
                            </a>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#novoBarbeiroModal">
                                <i class="fas fa-plus"></i> Novo
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Barbeiros com Agendas -->
        <?php if (empty($barbeiros)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Nenhum barbeiro encontrado</h5>
                    <p class="text-muted">Tente ajustar os filtros ou cadastre o primeiro barbeiro.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novoBarbeiroModal">
                        <i class="fas fa-user-plus"></i> Cadastrar Primeiro Barbeiro
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($barbeiros as $barbeiro): ?>
                <div class="card barbeiro-card">
                    <!-- Header do Barbeiro -->
                    <div class="barbeiro-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center">
                                    <div class="barbeiro-avatar me-3">
                                        <?= strtoupper(substr($barbeiro['nome'], 0, 2)) ?>
                                    </div>
                                    <div class="barbeiro-info">
                                        <h5><?= htmlspecialchars($barbeiro['nome']) ?></h5>
                                        <div class="d-flex gap-3">
                                            <small>
                                                <i class="fas fa-phone"></i> 
                                                <?= htmlspecialchars($barbeiro['telefone']) ?>
                                            </small>
                                            <small>
                                                <i class="fas fa-envelope"></i> 
                                                <?= htmlspecialchars($barbeiro['email']) ?>
                                            </small>
                                        </div>
                                        <?php if ($barbeiro['especialidade']): ?>
                                            <small>
                                                <i class="fas fa-star"></i> 
                                                <?= htmlspecialchars($barbeiro['especialidade']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="mb-2">
                                    <?php if ($barbeiro['ativo']): ?>
                                        <span class="status-badge bg-success">
                                            <i class="fas fa-check"></i> Ativo
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge bg-danger">
                                            <i class="fas fa-times"></i> Inativo
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2 justify-content-end">
                                    <small class="text-light opacity-75">
                                        <i class="fas fa-clock"></i> 
                                        <?= htmlspecialchars($barbeiro['horario_inicio']) ?> às <?= htmlspecialchars($barbeiro['horario_fim']) ?>
                                    </small>
                                </div>
                                <div class="action-buttons mt-2">
                                    <button class="btn btn-warning btn-sm" title="Editar"
                                            onclick="editarBarbeiro(<?= htmlspecialchars(json_encode($barbeiro)) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <?php if ($barbeiro['ativo']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="desativar_barbeiro">
                                            <input type="hidden" name="barbeiro_id" value="<?= $barbeiro['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Desativar"
                                                    onclick="return confirm('Desativar este barbeiro?')">
                                                <i class="fas fa-user-times"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="ativar_barbeiro">
                                            <input type="hidden" name="barbeiro_id" value="<?= $barbeiro['id'] ?>">
                                            <button type="submit" class="btn btn-success btn-sm" title="Ativar">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estatísticas rápidas -->
                        <div class="row mt-3">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h5 mb-0"><?= $barbeiro['total_agendamentos'] ?></div>
                                    <small class="opacity-75">Total Agendamentos</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h5 mb-0"><?= $barbeiro['agendamentos_finalizados'] ?></div>
                                    <small class="opacity-75">Finalizados</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h5 mb-0"><?= $barbeiro['agendamentos_hoje'] ?></div>
                                    <small class="opacity-75">Hoje</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h5 mb-0"><?= $barbeiro['comissao'] ?>%</div>
                                    <small class="opacity-75">Comissão</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Agenda do Dia -->
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">
                                <i class="fas fa-calendar-day"></i> 
                                Agenda do Dia - <?= date('d/m/Y', strtotime($data_agenda)) ?>
                            </h6>
                            <button class="btn btn-sm btn-primary" onclick="novoAgendamentoBarbeiro(<?= $barbeiro['id'] ?>, '<?= htmlspecialchars($barbeiro['nome']) ?>')">
                                <i class="fas fa-plus"></i> Novo Agendamento
                            </button>
                        </div>
                        
                        <div class="agenda-container">
                            <?php if (empty($barbeiro['agenda_dia'])): ?>
                                <div class="agenda-vazia">
                                    <i class="fas fa-calendar-times"></i>
                                    <h6>Agenda livre</h6>
                                    <p class="mb-0">Nenhum agendamento para este dia</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($barbeiro['agenda_dia'] as $agendamento): ?>
                                    <div class="agenda-item status-<?= $agendamento['status'] ?>">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <div class="agenda-horario">
                                                    <?= date('H:i', strtotime($agendamento['hora_agendamento'])) ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div>
                                                    <strong><?= htmlspecialchars($agendamento['cliente_nome']) ?></strong>
                                                </div>
                                                <div class="text-muted">
                                                    <i class="fas fa-cut"></i> 
                                                    <?= htmlspecialchars($agendamento['servico_nome']) ?>
                                                    <span class="ms-2">
                                                        <i class="fas fa-clock"></i> 
                                                        <?= $agendamento['duracao'] ?>min
                                                    </span>
                                                </div>
                                                <?php if ($agendamento['observacoes']): ?>
                                                    <div class="text-muted">
                                                        <i class="fas fa-comment"></i> 
                                                        <?= htmlspecialchars($agendamento['observacoes']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-2">
                                                <span class="badge status-<?= $agendamento['status'] ?> status-badge">
                                                    <?php
                                                    $status_icons = [
                                                        'agendado' => 'fas fa-clock',
                                                        'confirmado' => 'fas fa-check-circle',
                                                        'finalizado' => 'fas fa-check-double',
                                                        'cancelado' => 'fas fa-times-circle'
                                                    ];
                                                    $status_names = [
                                                        'agendado' => 'Agendado',
                                                        'confirmado' => 'Confirmado',
                                                        'finalizado' => 'Finalizado',
                                                        'cancelado' => 'Cancelado'
                                                    ];
                                                    ?>
                                                    <i class="<?= $status_icons[$agendamento['status']] ?>"></i>
                                                    <?= $status_names[$agendamento['status']] ?>
                                                </span>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="action-buttons justify-content-end">
                                                    <button class="btn btn-info btn-sm" title="Ver Detalhes"
                                                            onclick="verAgendamento(<?= $agendamento['id'] ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($agendamento['status'] == 'agendado'): ?>
                                                        <button class="btn btn-success btn-sm" title="Confirmar"
                                                                onclick="confirmarAgendamento(<?= $agendamento['id'] ?>)">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php elseif ($agendamento['status'] == 'confirmado'): ?>
                                                        <button class="btn btn-primary btn-sm" title="Finalizar"
                                                                onclick="finalizarAgendamento(<?= $agendamento['id'] ?>)">
                                                            <i class="fas fa-check-double"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Modal Novo Barbeiro -->
    <div class="modal fade" id="novoBarbeiroModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Cadastrar Novo Barbeiro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formNovoBarbeiro">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cadastrar_barbeiro">
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
                                <label for="especialidade" class="form-label">Especialidade</label>
                                <input type="text" class="form-control" name="especialidade" id="especialidade" 
                                       placeholder="Ex: Cortes modernos, Barba, etc.">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="comissao" class="form-label">Comissão (%)</label>
                                <input type="number" class="form-control" name="comissao" id="comissao" 
                                       min="0" max="100" step="0.5" value="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="horario_inicio" class="form-label">Horário Início</label>
                                <input type="time" class="form-control" name="horario_inicio" id="horario_inicio" value="08:00">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="horario_fim" class="form-label">Horário Fim</label>
                                <input type="time" class="form-control" name="horario_fim" id="horario_fim" value="18:00">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-user-plus"></i> Cadastrar Barbeiro
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Barbeiro -->
    <div class="modal fade" id="editarBarbeiroModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Editar Barbeiro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formEditarBarbeiro">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar_barbeiro">
                        <input type="hidden" name="barbeiro_id" id="edit_barbeiro_id">
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
                                <label for="edit_especialidade" class="form-label">Especialidade</label>
                                <input type="text" class="form-control" name="especialidade" id="edit_especialidade" 
                                       placeholder="Ex: Cortes modernos, Barba, etc.">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_comissao" class="form-label">Comissão (%)</label>
                                <input type="number" class="form-control" name="comissao" id="edit_comissao" 
                                       min="0" max="100" step="0.5">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_horario_inicio" class="form-label">Horário Início</label>
                                <input type="time" class="form-control" name="horario_inicio" id="edit_horario_inicio">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_horario_fim" class="form-label">Horário Fim</label>
                                <input type="time" class="form-control" name="horario_fim" id="edit_horario_fim">
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

    <!-- Modal Novo Agendamento para Barbeiro -->
    <div class="modal fade" id="novoAgendamentoBarbeiroModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus"></i> Novo Agendamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="admin_agendamentos.php" id="formNovoAgendamentoBarbeiro">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="criar_agendamento">
                        <input type="hidden" name="barbeiro_id" id="agend_barbeiro_id_fixed">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-user-tie"></i> 
                            Barbeiro: <strong id="agend_barbeiro_nome"></strong>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="agend_cliente_id" class="form-label">Cliente *</label>
                                <select class="form-select" name="cliente_id" id="agend_cliente_id" required>
                                    <option value="">Carregando clientes...</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="agend_servico_id" class="form-label">Serviço *</label>
                                <select class="form-select" name="servico_id" id="agend_servico_id" required>
                                    <option value="">Carregando serviços...</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="agend_data" class="form-label">Data *</label>
                                <input type="date" class="form-control" name="data" id="agend_data" 
                                       min="<?= date('Y-m-d') ?>" value="<?= $data_agenda ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="agend_hora" class="form-label">Horário *</label>
                                <select class="form-select" name="hora" id="agend_hora" required>
                                    <option value="">Selecione uma data</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="agend_observacoes" class="form-label">Observações</label>
                                <textarea class="form-control" name="observacoes" id="agend_observacoes" rows="3" 
                                          placeholder="Observações sobre o agendamento..."></textarea>
                            </div>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Função para editar barbeiro
        function editarBarbeiro(barbeiro) {
            document.getElementById('edit_barbeiro_id').value = barbeiro.id;
            document.getElementById('edit_nome').value = barbeiro.nome;
            document.getElementById('edit_telefone').value = barbeiro.telefone;
            document.getElementById('edit_email').value = barbeiro.email;
            document.getElementById('edit_especialidade').value = barbeiro.especialidade || '';
            document.getElementById('edit_comissao').value = barbeiro.comissao || 0;
            document.getElementById('edit_horario_inicio').value = barbeiro.horario_inicio || '08:00';
            document.getElementById('edit_horario_fim').value = barbeiro.horario_fim || '18:00';
            
            const modal = new bootstrap.Modal(document.getElementById('editarBarbeiroModal'));
            modal.show();
        }

        // Função para abrir modal de novo agendamento para barbeiro específico
        function novoAgendamentoBarbeiro(barbeiroId, barbeiroNome) {
            document.getElementById('agend_barbeiro_id_fixed').value = barbeiroId;
            document.getElementById('agend_barbeiro_nome').textContent = barbeiroNome;
            
            // Carregar clientes
            carregarClientes();
            carregarServicos();
            
            const modal = new bootstrap.Modal(document.getElementById('novoAgendamentoBarbeiroModal'));
            modal.show();
        }

        // Função para carregar clientes
        function carregarClientes() {
            const select = document.getElementById('agend_cliente_id');
            select.innerHTML = '<option value="">Carregando clientes...</option>';
            
            // Simular carregamento - você pode substituir por uma requisição AJAX real
            setTimeout(() => {
                select.innerHTML = '<option value="">Selecione um cliente</option>';
                // Aqui você faria uma requisição para buscar os clientes
                // Por enquanto, vamos simular alguns clientes
                const clientes = [
                    {id: 1, nome: 'João Silva'},
                    {id: 2, nome: 'Maria Santos'},
                    {id: 3, nome: 'Pedro Oliveira'}
                ];
                
                clientes.forEach(cliente => {
                    const option = document.createElement('option');
                    option.value = cliente.id;
                    option.textContent = cliente.nome;
                    select.appendChild(option);
                });
            }, 500);
        }

        // Função para carregar serviços
        function carregarServicos() {
            const select = document.getElementById('agend_servico_id');
            select.innerHTML = '<option value="">Carregando serviços...</option>';
            
            // Simular carregamento
            setTimeout(() => {
                select.innerHTML = '<option value="">Selecione um serviço</option>';
                const servicos = [
                    {id: 1, nome: 'Corte Masculino', preco: 25.00},
                    {id: 2, nome: 'Barba', preco: 15.00},
                    {id: 3, nome: 'Corte + Barba', preco: 35.00}
                ];
                
                servicos.forEach(servico => {
                    const option = document.createElement('option');
                    option.value = servico.id;
                    option.textContent = `${servico.nome} - R$ ${servico.preco.toFixed(2)}`;
                    select.appendChild(option);
                });
            }, 500);
        }

        // Função para buscar horários quando data for selecionada
        document.addEventListener('DOMContentLoaded', function() {
            const dataInput = document.getElementById('agend_data');
            const horaSelect = document.getElementById('agend_hora');
            
            dataInput?.addEventListener('change', function() {
                const barbeiroId = document.getElementById('agend_barbeiro_id_fixed').value;
                const data = this.value;
                
                if (barbeiroId && data) {
                    horaSelect.innerHTML = '<option value="">Carregando horários...</option>';
                    
                    // Simular busca de horários disponíveis
                    setTimeout(() => {
                        const horarios = [
                            '08:00', '08:30', '09:00', '09:30', '10:00', '10:30',
                            '11:00', '11:30', '14:00', '14:30', '15:00', '15:30',
                            '16:00', '16:30', '17:00', '17:30'
                        ];
                        
                        horaSelect.innerHTML = '<option value="">Selecione um horário</option>';
                        horarios.forEach(hora => {
                            const option = document.createElement('option');
                            option.value = hora;
                            option.textContent = hora;
                            horaSelect.appendChild(option);
                        });
                    }, 500);
                }
            });
        });

        // Funções para gerenciar agendamentos
        function verAgendamento(agendamentoId) {
            alert('Ver detalhes do agendamento ID: ' + agendamentoId + '\n\nFuncionalidade em desenvolvimento.');
        }

        function confirmarAgendamento(agendamentoId) {
            if (confirm('Confirmar este agendamento?')) {
                // Criar formulário para confirmar
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_agendamentos.php';
                form.innerHTML = `
                    <input type="hidden" name="action" value="confirmar_agendamento">
                    <input type="hidden" name="agendamento_id" value="${agendamentoId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function finalizarAgendamento(agendamentoId) {
            if (confirm('Finalizar este agendamento?')) {
                // Criar formulário para finalizar
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_agendamentos.php';
                form.innerHTML = `
                    <input type="hidden" name="action" value="finalizar_agendamento">
                    <input type="hidden" name="agendamento_id" value="${agendamentoId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

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

        // Auto-refresh da página a cada 5 minutos para manter agendas atualizadas
        setTimeout(function() {
            if (confirm('Atualizar agendas dos barbeiros?')) {
                location.reload();
            }
        }, 300000); // 5 minutos
    </script>
</body>
</html>