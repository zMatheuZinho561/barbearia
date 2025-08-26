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
    <title>Agendamentos - BarberShop Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #e67e22;
            --accent-color: #f39c12;
        }
        
        .navbar-brand { font-weight: bold; color: var(--primary-color) !important; }
        .card { border: none; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        .status-badge { font-size: 0.75em; padding: 0.5em 0.75em; }
        .status-agendado { background-color: #17a2b8; }
        .status-confirmado { background-color: #28a745; }
        .status-finalizado { background-color: #6c757d; }
        .status-cancelado { background-color: #dc3545; }
        .admin-badge { background-color: var(--accent-color); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7em; }
        
        .action-buttons .btn {
            margin: 0 2px;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .filters-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }
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
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="agendamentos.php"><i class="fas fa-calendar"></i> Agendamentos</a></li>
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
                            <li><a class="dropdown-item" href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> Ver Site Público</a></li>
                            <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-user-cog"></i> Meu Perfil</a></li>
                            <li><a class="dropdown-item" href="configuracoes.php"><i class="fas fa-cogs"></i> Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair do Admin</a></li>
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