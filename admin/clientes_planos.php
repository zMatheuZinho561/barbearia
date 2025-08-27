<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/plans.php';

$auth = new Auth();
$plans = new Plans();

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
            $result = $plans->associarPlanoCliente(
                $_POST['cliente_id'], 
                $_POST['plano_id'], 
                $admin_data['id'],
                $_POST['observacoes'] ?? ''
            );
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'cancelar_plano':
            $result = $plans->cancelarPlanoCliente(
                $_POST['assinatura_id'], 
                $_POST['motivo'], 
                $admin_data['id']
            );
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'renovar_plano':
            $result = $plans->renovarPlano(
                $_POST['assinatura_id'], 
                $admin_data['id'],
                $_POST['observacoes'] ?? ''
            );
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
    }
}

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_plano = $_GET['plano'] ?? '';
$filtro_vencimento = $_GET['vencimento'] ?? '';

// Buscar clientes com planos
$filtros = [];
if ($filtro_status) $filtros['status'] = $filtro_status;
if ($filtro_plano) $filtros['plano_id'] = $filtro_plano;
if ($filtro_vencimento) $filtros['vencimento'] = $filtro_vencimento;

$clientes_planos = $plans->getClientesComPlanos($filtros);
$todos_planos = $plans->getPlanos();
$estatisticas = $plans->getEstatisticas();

// Buscar todos os clientes para associar planos
$stmt = $db->query("SELECT id, nome, telefone FROM clientes WHERE ativo = 1 ORDER BY nome");
$todos_clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar vencimentos
$vencimentos = $plans->verificarVencimentos();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinaturas de Clientes - BarberShop Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #e67e22;
            --accent-color: #f39c12;
        }
        
        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand { font-weight: bold; color: var(--primary-color) !important; }
        .card { border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 10px; }
        .admin-badge { background-color: var(--accent-color); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.75em; font-weight: bold; }
        
        .nav-link.active {
            background-color: var(--primary-color) !important;
            color: white !important;
            border-radius: 5px;
        }
        
        .status-badge {
            font-size: 0.75em;
            padding: 0.4em 0.8em;
            border-radius: 15px;
        }
        
        .alert-vencimento {
            background: linear-gradient(135deg, #ffeaa7, #fdcb6e);
            border: none;
            color: #2d3436;
        }
        
        .plano-badge {
            font-size: 0.8em;
            padding: 0.3em 0.6em;
            border-radius: 10px;
        }
        
        .progress-cortes {
            height: 8px;
        }
        
        .filters-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
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
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_agendamentos.php"><i class="fas fa-calendar"></i> Agendamentos</a></li>
                    <li class="nav-item"><a class="nav-link" href="clientes.php"><i class="fas fa-users"></i> Clientes</a></li>
                    <li class="nav-item"><a class="nav-link" href="barbeiros.php"><i class="fas fa-user-tie"></i> Barbeiros</a></li>
                    <li class="nav-item"><a class="nav-link" href="servicos.php"><i class="fas fa-list"></i> Serviços</a></li>
                    <li class="nav-item"><a class="nav-link" href="planos.php"><i class="fas fa-crown"></i> Planos</a></li>
                    <li class="nav-item"><a class="nav-link" href="produtos.php"><i class="fas fa-box"></i> Produtos</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-shield"></i> 
                            <?= htmlspecialchars($admin_data['nome']) ?>
                            <span class="admin-badge">ADMIN</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-user-cog"></i> Meu Perfil</a></li>
                            <li><a class="dropdown-item" href="configuracoes.php"><i class="fas fa-cogs"></i> Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Sair do Admin</a></li>
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
                            <a href="cliente_planos.php" class="btn btn-outline-secondary">
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
                    <span class="badge bg-secondary"><?= count($clientes_planos) ?></span>
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
                    <div class="text-center py-5">
                        <i class="fas fa-crown fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhuma assinatura encontrada</h5>
                        <p class="text-muted">Tente ajustar os filtros ou associe o primeiro plano a um cliente.</p>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#associarPlanoModal">
                            <i class="fas fa-plus"></i> Associar Primeiro Plano
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
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
                                            <span class="badge plano-badge bg-<?= $cp['cor_badge'] ?>">
                                                <?= htmlspecialchars($cp['plano_nome']) ?>
                                            </span>
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
                                                        <strong><?= $cp['uso_cortes'] ?></strong>
                                                    </div>
                                                    <?php 
                                                    $percentual = ($cp['cortes_utilizados'] / $cp['cortes_inclusos']) * 100;
                                                    $cor_progress = $percentual >= 100 ? 'danger' : ($percentual >= 75 ? 'warning' : 'success');
                                                    ?>
                                                    <div class="progress progress-cortes">
                                                        <div class="progress-bar bg-<?= $cor_progress ?>" 
                                                             style="width: <?= min($percentual, 100) ?>%"></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge status-badge bg-<?= $cp['status_cor'] ?>">
                                                <?= $cp['status_descricao'] ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <?php if ($cp['plano_status'] === 'ativo' || $cp['plano_status'] === 'vencido'): ?>
                                                    <button class="btn btn-primary btn-sm" title="Renovar" 
                                                            onclick="renovarPlano(<?= $cp['assinatura_id'] ?>, '<?= htmlspecialchars($cp['cliente_nome']) ?>', '<?= htmlspecialchars($cp['plano_nome']) ?>')">
                                                        <i class="fas fa-redo"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($cp['plano_status'] === 'ativo'): ?>
                                                    <button class="btn btn-danger btn-sm" title="Cancelar" 
                                                            onclick="cancelarPlano(<?= $cp['assinatura_id'] ?>, '<?= htmlspecialchars($cp['cliente_nome']) ?>', '<?= htmlspecialchars($cp['plano_nome']) ?>')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-info btn-sm" title="Ver Histórico"
                                                        onclick="verHistorico(<?= $cp['assinatura_id'] ?>)">
                                                    <i class="fas fa-history"></i>
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
                                    <?php foreach ($todos_clientes as $cliente): ?>
                                        <option value="<?= $cliente['id'] ?>">
                                            <?= htmlspecialchars($cliente['nome']) ?> - <?= htmlspecialchars($cliente['telefone']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="plano_id" class="form-label">Plano *</label>
                                <select class="form-select" name="plano_id" id="plano_id" required>
                                    <option value="">Selecione o plano</option>
                                    <?php foreach ($todos_planos as $plano): ?>
                                        <option value="<?= $plano['id'] ?>" data-preco="<?= $plano['preco'] ?>" data-duracao="<?= $plano['duracao_dias'] ?>">
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
                        <button type="submit" class="btn btn-success">
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
                const dataInicio = new Date().toLocaleDateString('pt-BR');
                const dataVencimento = new Date();
                dataVencimento.setDate(dataVencimento.getDate() + parseInt(duracao));
                
                resumoContent.innerHTML = `
                    <strong>Plano:</strong> ${option.text}<br>
                    <strong>Data início:</strong> ${dataInicio}<br>
                    <strong>Data vencimento:</strong> ${dataVencimento.toLocaleDateString('pt-BR')}<br>
                    <strong>Duração:</strong> ${duracao} dias
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
                O plano será renovado por mais um período a partir de hoje.
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('renovarPlanoModal'));
            modal.show();
        }

        function cancelarPlano(assinaturaId, clienteNome, planoNome) {
            document.getElementById('cancelar_assinatura_id').value = assinaturaId;
            document.getElementById('cancelar_info').innerHTML = `
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Cancelar plano "${planoNome}" do cliente "${clienteNome}"</strong><br>
                Esta ação não pode ser desfeita.
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('cancelarPlanoModal'));
            modal.show();
        }

        function verHistorico(assinaturaId) {
            // Implementar modal de histórico ou redirecionar para página específica
            alert('Funcionalidade de histórico em desenvolvimento. ID da assinatura: ' + assinaturaId);
        }
    </script>
</body>
</html>