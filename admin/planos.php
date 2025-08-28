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

if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'criar_plano':
            $dados = [
                'nome' => $_POST['nome'],
                'descricao' => $_POST['descricao'],
                'preco' => (float)$_POST['preco'],
                'duracao_dias' => (int)$_POST['duracao_dias'],
                'cortes_inclusos' => (int)$_POST['cortes_inclusos'],
                'desconto_produtos' => (float)$_POST['desconto_produtos'],
                'desconto_servicos' => (float)$_POST['desconto_servicos'],
                'cor_badge' => $_POST['cor_badge'],
                'beneficios' => isset($_POST['beneficios']) ? explode("\n", trim($_POST['beneficios'])) : []
            ];
            
            $result = $plans->criarPlano($dados);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'editar_plano':
            $dados = [
                'nome' => $_POST['nome'],
                'descricao' => $_POST['descricao'],
                'preco' => (float)$_POST['preco'],
                'duracao_dias' => (int)$_POST['duracao_dias'],
                'cortes_inclusos' => (int)$_POST['cortes_inclusos'],
                'desconto_produtos' => (float)$_POST['desconto_produtos'],
                'desconto_servicos' => (float)$_POST['desconto_servicos'],
                'cor_badge' => $_POST['cor_badge'],
                'beneficios' => isset($_POST['beneficios']) ? explode("\n", trim($_POST['beneficios'])) : []
            ];
            
            $result = $plans->atualizarPlano($_POST['plano_id'], $dados);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'toggle_plano':
            $result = $plans->togglePlano($_POST['plano_id'], $_POST['ativo'] == '1');
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
    }
}

// Buscar planos
$todos_planos = $plans->getPlanos(false);
$estatisticas = $plans->getEstatisticas();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planos Mensais - BarberShop Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style/style_adm/st_planos.css">
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
   
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

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

        <!-- Ações rápidas -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-crown"></i> Gerenciamento de Planos
                            </h5>
                            <div class="d-flex gap-2">
                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#novoPlanoModal">
                                    <i class="fas fa-plus"></i> Criar Plano
                                </button>
                                <a href="clientes_planos.php" class="btn btn-primary">
                                    <i class="fas fa-users"></i> Ver Assinaturas
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Planos -->
        <div class="row">
            <?php foreach ($todos_planos as $plano): ?>
                <?php 
                $beneficios = json_decode($plano['beneficios'], true) ?: [];
                $inativo = !$plano['ativo'];
                ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card plan-card h-100 <?= $inativo ? 'plan-inactive' : '' ?>">
                        <div class="plan-header bg-<?= $plano['cor_badge'] ?>">
                            <h4 class="mb-2"><?= htmlspecialchars($plano['nome']) ?></h4>
                            <div class="plan-price">
                                R$ <?= number_format($plano['preco'], 2, ',', '.') ?>
                                <small>/mês</small>
                            </div>
                            <small class="opacity-75"><?= $plano['duracao_dias'] ?> dias</small>
                        </div>
                        <div class="card-body">
                            <p class="text-muted"><?= htmlspecialchars($plano['descricao']) ?></p>
                            
                            <div class="mb-3">
                                <h6><i class="fas fa-cut text-primary"></i> Cortes Inclusos:</h6>
                                <span class="badge bg-primary">
                                    <?= $plano['cortes_inclusos'] == 0 ? 'Ilimitados' : $plano['cortes_inclusos'] . ' cortes' ?>
                                </span>
                            </div>
                            
                            <?php if ($plano['desconto_produtos'] > 0 || $plano['desconto_servicos'] > 0): ?>
                                <div class="mb-3">
                                    <h6><i class="fas fa-percentage text-success"></i> Descontos:</h6>
                                    <?php if ($plano['desconto_produtos'] > 0): ?>
                                        <small class="badge bg-success me-1"><?= $plano['desconto_produtos'] ?>% produtos</small>
                                    <?php endif; ?>
                                    <?php if ($plano['desconto_servicos'] > 0): ?>
                                        <small class="badge bg-success"><?= $plano['desconto_servicos'] ?>% serviços</small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($beneficios)): ?>
                                <div class="mb-3">
                                    <h6><i class="fas fa-star text-warning"></i> Benefícios:</h6>
                                    <?php foreach ($beneficios as $beneficio): ?>
                                        <div class="benefit-item">
                                            <small><i class="fas fa-check text-success me-1"></i> <?= htmlspecialchars($beneficio) ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <span class="status-badge bg-<?= $plano['ativo'] ? 'success' : 'danger' ?>">
                                    <i class="fas fa-<?= $plano['ativo'] ? 'check' : 'times' ?>"></i>
                                    <?= $plano['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex gap-2">
                                <button class="btn btn-warning btn-sm flex-fill" 
                                        onclick="editarPlano(<?= htmlspecialchars(json_encode($plano)) ?>)">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                
                                <form method="POST" class="d-inline flex-fill">
                                    <input type="hidden" name="action" value="toggle_plano">
                                    <input type="hidden" name="plano_id" value="<?= $plano['id'] ?>">
                                    <input type="hidden" name="ativo" value="<?= $plano['ativo'] ? '0' : '1' ?>">
                                    <button type="submit" class="btn btn-<?= $plano['ativo'] ? 'danger' : 'success' ?> btn-sm w-100"
                                            onclick="return confirm('<?= $plano['ativo'] ? 'Desativar' : 'Ativar' ?> este plano?')">
                                        <i class="fas fa-<?= $plano['ativo'] ? 'times' : 'check' ?>"></i>
                                        <?= $plano['ativo'] ? 'Desativar' : 'Ativar' ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal Novo Plano -->
    <div class="modal fade" id="novoPlanoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Criar Novo Plano</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formNovoPlano">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="criar_plano">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nome" class="form-label">Nome do Plano *</label>
                                <input type="text" class="form-control" name="nome" id="nome" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="preco" class="form-label">Preço (R$) *</label>
                                <input type="number" class="form-control" name="preco" id="preco" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="duracao_dias" class="form-label">Duração (dias) *</label>
                                <input type="number" class="form-control" name="duracao_dias" id="duracao_dias" value="30" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="cortes_inclusos" class="form-label">Cortes Inclusos *</label>
                                <input type="number" class="form-control" name="cortes_inclusos" id="cortes_inclusos" min="0" required>
                                <small class="form-text text-muted">0 = ilimitados</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="desconto_produtos" class="form-label">Desconto Produtos (%)</label>
                                <input type="number" class="form-control" name="desconto_produtos" id="desconto_produtos" step="0.01" min="0" max="100" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="desconto_servicos" class="form-label">Desconto Serviços (%)</label>
                                <input type="number" class="form-control" name="desconto_servicos" id="desconto_servicos" step="0.01" min="0" max="100" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="cor_badge" class="form-label">Cor do Plano</label>
                                <select class="form-select" name="cor_badge" id="cor_badge">
                                    <option value="primary">Azul</option>
                                    <option value="success">Verde</option>
                                    <option value="warning">Amarelo</option>
                                    <option value="danger">Vermelho</option>
                                    <option value="info">Ciano</option>
                                    <option value="dark">Preto</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="descricao" class="form-label">Descrição</label>
                                <textarea class="form-control" name="descricao" id="descricao" rows="3"></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="beneficios" class="form-label">Benefícios</label>
                                <textarea class="form-control" name="beneficios" id="beneficios" rows="4" 
                                          placeholder="Digite um benefício por linha&#10;Exemplo:&#10;Agendamento prioritário&#10;Toalha exclusiva&#10;Bebida grátis"></textarea>
                                <small class="form-text text-muted">Um benefício por linha</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Criar Plano
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Plano -->
    <div class="modal fade" id="editarPlanoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Editar Plano</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formEditarPlano">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar_plano">
                        <input type="hidden" name="plano_id" id="edit_plano_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_nome" class="form-label">Nome do Plano *</label>
                                <input type="text" class="form-control" name="nome" id="edit_nome" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_preco" class="form-label">Preço (R$) *</label>
                                <input type="number" class="form-control" name="preco" id="edit_preco" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_duracao_dias" class="form-label">Duração (dias) *</label>
                                <input type="number" class="form-control" name="duracao_dias" id="edit_duracao_dias" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_cortes_inclusos" class="form-label">Cortes Inclusos *</label>
                                <input type="number" class="form-control" name="cortes_inclusos" id="edit_cortes_inclusos" min="0" required>
                                <small class="form-text text-muted">0 = ilimitados</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_desconto_produtos" class="form-label">Desconto Produtos (%)</label>
                                <input type="number" class="form-control" name="desconto_produtos" id="edit_desconto_produtos" step="0.01" min="0" max="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_desconto_servicos" class="form-label">Desconto Serviços (%)</label>
                                <input type="number" class="form-control" name="desconto_servicos" id="edit_desconto_servicos" step="0.01" min="0" max="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_cor_badge" class="form-label">Cor do Plano</label>
                                <select class="form-select" name="cor_badge" id="edit_cor_badge">
                                    <option value="primary">Azul</option>
                                    <option value="success">Verde</option>
                                    <option value="warning">Amarelo</option>
                                    <option value="danger">Vermelho</option>
                                    <option value="info">Ciano</option>
                                    <option value="dark">Preto</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="edit_descricao" class="form-label">Descrição</label>
                                <textarea class="form-control" name="descricao" id="edit_descricao" rows="3"></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="edit_beneficios" class="form-label">Benefícios</label>
                                <textarea class="form-control" name="beneficios" id="edit_beneficios" rows="4" 
                                          placeholder="Digite um benefício por linha"></textarea>
                                <small class="form-text text-muted">Um benefício por linha</small>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        function editarPlano(plano) {
            document.getElementById('edit_plano_id').value = plano.id;
            document.getElementById('edit_nome').value = plano.nome;
            document.getElementById('edit_preco').value = plano.preco;
            document.getElementById('edit_duracao_dias').value = plano.duracao_dias;
            document.getElementById('edit_cortes_inclusos').value = plano.cortes_inclusos;
            document.getElementById('edit_desconto_produtos').value = plano.desconto_produtos;
            document.getElementById('edit_desconto_servicos').value = plano.desconto_servicos;
            document.getElementById('edit_cor_badge').value = plano.cor_badge;
            document.getElementById('edit_descricao').value = plano.descricao || '';
            
            // Processar benefícios
            let beneficios = [];
            try {
                beneficios = JSON.parse(plano.beneficios || '[]');
            } catch (e) {
                beneficios = [];
            }
            document.getElementById('edit_beneficios').value = beneficios.join('\n');
            
            const modal = new bootstrap.Modal(document.getElementById('editarPlanoModal'));
            modal.show();
        }
    </script>
</body>
</html>