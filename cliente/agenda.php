<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/appointment.php';

$auth = new Auth();
$appointment = new Appointment();

// Verificar se cliente está logado
if (!$auth->isClientLoggedIn()) {
    header('Location: login.php');
    exit;
}

$cliente_data = $auth->getClientData();
$agendamentos = $appointment->getAgendamentosCliente($cliente_data['id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Agendamentos - BarberShop</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #e67e22;
            --accent-color: #f39c12;
        }
        
        .navbar-brand {
            font-weight: bold;
            color: var(--primary-color) !important;
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .status-badge {
            font-size: 0.75em;
            padding: 0.5em 0.75em;
        }
        
        .status-agendado {
            background-color: #17a2b8;
        }
        
        .status-confirmado {
            background-color: #28a745;
        }
        
        .status-finalizado {
            background-color: #6c757d;
        }
        
        .status-cancelado {
            background-color: #dc3545;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-cut"></i> BarberShop
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
                        <a class="nav-link" href="agendar.php">
                            <i class="fas fa-calendar-plus"></i> Agendar
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($cliente_data['nome']) ?>
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

    <div class="container mt-4">
        <!-- Card de boas-vindas -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card welcome-card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1">Bem-vindo, <?= htmlspecialchars($cliente_data['nome']) ?>!</h4>
                                <p class="mb-0">Gerencie seus agendamentos e acompanhe o status dos seus serviços.</p>
                            </div>
                            <div class="col-md-4 text-end">
                             <a href="agendar.php" class="btn btn-primary">
    <i class="fa fa-calendar-plus"></i> Novo Agendamento
</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estatísticas rápidas -->
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-primary">
                          <?= count(array_filter($agendamentos ?? [], function($a) {  return is_array($a) && isset($a['status']) && $a['status'] == 'agendado';})); ?>
                        </h5>
                        <small class="text-muted">Agendados</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-success">
                               <?= count(array_filter($agendamentos ?? [], function($a) {   return is_array($a) && isset($a['status']) && $a['status'] == 'confirmado';  })); ?>
                        </h5>
                        <small class="text-muted">Confirmados</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-secondary">
                           <?= count(array_filter($agendamentos ?? [], function($a) {   return is_array($a) && isset($a['status']) && $a['status'] == 'finalizado'; })); ?>
                        </h5>
                        <small class="text-muted">Finalizados</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-danger">
                              <?= count(array_filter($agendamentos ?? [], function($a) {    return is_array($a) && isset($a['status']) && $a['status'] == 'cancelado';  })); ?>
                        </h5>
                        <small class="text-muted">Cancelados</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de agendamentos -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt"></i> Meus Agendamentos
                        </h5>
                        <a href="agendar.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Novo
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($agendamentos)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Nenhum agendamento encontrado</h5>
                                <p class="text-muted mb-4">Faça seu primeiro agendamento e desfrute dos nossos serviços!</p>
                                <a href="agendar.php" class="btn btn-primary">
                                    <i class="fas fa-calendar-plus"></i> Agendar Agora
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Data/Hora</th>
                                            <th>Barbeiro</th>
                                            <th>Serviço</th>
                                            <th>Valor</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
<tbody>
<?php if (!empty($appointments)): ?>
    <?php foreach ($appointments as $row): ?>
        <tr>
            <td>
                <?php if (!empty($row['data_hora'])): ?>
                    <?= date('d/m/Y', strtotime($row['data_hora'])); ?>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
            <td>
                <?= htmlspecialchars($row['barbeiro'] ?? '-') ?>
            </td>
            <td>
                <?= htmlspecialchars($row['servico'] ?? '-') ?>
            </td>
            <td>
                R$ <?= number_format($row['valor'] ?? 0, 2, ',', '.'); ?>
            </td>
            <td>
                <?= ucfirst($row['status'] ?? '-') ?>
            </td>
            <td>
                <!-- Aqui você coloca as ações (editar, cancelar, etc.) -->
                <a href="editar.php?id=<?= $row['id'] ?>">Editar</a> | 
                <a href="cancelar.php?id=<?= $row['id'] ?>">Cancelar</a>
            </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="6" style="text-align:center;">Nenhum agendamento encontrado.</td>
    </tr>
<?php endif; ?>
</tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmação de cancelamento -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Cancelamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza de que deseja cancelar este agendamento?</p>
                    <p class="text-muted">Esta ação não pode ser desfeita.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button>
                    <button type="button" class="btn btn-danger" id="confirmCancel">Sim, Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        let agendamentoIdParaCancelar = null;

        function cancelarAgendamento(id) {
            agendamentoIdParaCancelar = id;
            const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
            modal.show();
        }

        document.getElementById('confirmCancel').addEventListener('click', function() {
            if (agendamentoIdParaCancelar) {
                // Fazer requisição AJAX para cancelar o agendamento
                fetch('cancelar_agendamento.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        agendamento_id: agendamentoIdParaCancelar
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erro ao cancelar agendamento: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro interno do sistema');
                });

                const modal = bootstrap.Modal.getInstance(document.getElementById('cancelModal'));
                modal.hide();
            }
        });
    </script>
</body>
</html>