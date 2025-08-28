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
    <link rel="stylesheet" href="../style/style_cliente/st_agenda.css">
</head>
<body>
    <!-- Navbar Modernizado -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
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
                        <a class="nav-link active" href="agenda.php">
                            <i class="fas fa-calendar-alt"></i> Meus Agendamentos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="agendar.php">
                            <i class="fas fa-calendar-plus"></i> Agendar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="perfil.php">
                            <i class="fas fa-user-cog"></i> Perfil
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?= htmlspecialchars($cliente_data['nome']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../index.php"><i class="fas fa-home"></i> Início</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
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
                <div class="card welcome-card animate-in">
                    <div class="card-body">
                        <div class="row align-items-center position-relative">
                            <div class="col-md-8">
                                <h4 class="mb-2">
                                    <i class="fas fa-calendar-check me-2"></i>
                                    Bem-vindo, <?= htmlspecialchars($cliente_data['nome']) ?>!
                                </h4>
                                <p class="mb-0 opacity-90">Gerencie seus agendamentos e acompanhe o status dos seus serviços.</p>
                                <div class="mt-3">
                                    <small class="opacity-75">
                                        <i class="fas fa-clock me-1"></i>
                                        Última atualização: <?= date('d/m/Y H:i') ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="agendar.php" class="btn btn-light btn-lg">
                                    <i class="fa fa-calendar-plus me-2"></i> Novo Agendamento
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
                <div class="card stats-card animate-in" style="animation-delay: 0.1s">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-info mb-2"></i>
                        <h5 class="text-info">
                            <?= count(array_filter($agendamentos ?? [], function($a) {  return is_array($a) && isset($a['status']) && $a['status'] == 'agendado';})); ?>
                        </h5>
                        <small class="text-muted fw-bold">Agendados</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card stats-card animate-in" style="animation-delay: 0.2s">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h5 class="text-success">
                            <?= count(array_filter($agendamentos ?? [], function($a) {   return is_array($a) && isset($a['status']) && $a['status'] == 'confirmado';  })); ?>
                        </h5>
                        <small class="text-muted fw-bold">Confirmados</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card stats-card animate-in" style="animation-delay: 0.3s">
                    <div class="card-body">
                        <i class="fas fa-star fa-2x text-secondary mb-2"></i>
                        <h5 class="text-secondary">
                            <?= count(array_filter($agendamentos ?? [], function($a) {   return is_array($a) && isset($a['status']) && $a['status'] == 'finalizado'; })); ?>
                        </h5>
                        <small class="text-muted fw-bold">Finalizados</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card stats-card animate-in" style="animation-delay: 0.4s">
                    <div class="card-body">
                        <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                        <h5 class="text-danger">
                            <?= count(array_filter($agendamentos ?? [], function($a) {    return is_array($a) && isset($a['status']) && $a['status'] == 'cancelado';  })); ?>
                        </h5>
                        <small class="text-muted fw-bold">Cancelados</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de agendamentos -->
        <div class="row">
            <div class="col-12">
                <div class="card animate-in" style="animation-delay: 0.5s">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt me-2"></i> Meus Agendamentos
                        </h5>
                        <a href="agendar.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i> Novo
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($agendamentos)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times fa-4x"></i>
                                <h4 class="text-muted mt-3 mb-2">Nenhum agendamento encontrado</h4>
                                <p class="text-muted mb-4">Faça seu primeiro agendamento e desfrute dos nossos serviços!</p>
                                <a href="agendar.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-calendar-plus me-2"></i> Agendar Agora
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-calendar me-1"></i> Data/Hora</th>
                                            <th><i class="fas fa-user-tie me-1"></i> Barbeiro</th>
                                            <th><i class="fas fa-cut me-1"></i> Serviço</th>
                                            <th><i class="fas fa-dollar-sign me-1"></i> Valor</th>
                                            <th><i class="fas fa-info-circle me-1"></i> Status</th>
                                            <th><i class="fas fa-cog me-1"></i> Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($agendamentos)): ?>
                                        <?php foreach ($agendamentos as $index => $row): ?>
                                            <tr style="animation: fadeInUp 0.6s ease-out <?= $index * 0.1 ?>s both;">
                                                <td>
                                                    <?php if (!empty($row['data_agendamento'])): ?>
                                                        <div>
                                                            <strong class="text-primary"><?= date('d/m/Y', strtotime($row['data_agendamento'])); ?></strong><br>
                                                            <small class="text-muted">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <?= date('H:i', strtotime($row['hora_agendamento'])); ?>
                                                            </small>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                                            <i class="fas fa-user-tie text-white"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?= htmlspecialchars($row['barbeiro_nome'] ?? '-') ?></strong>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($row['servico_nome'] ?? '-') ?></strong>
                                                        <?php if (!empty($row['duracao'])): ?>
                                                            <br><small class="text-muted">
                                                                <i class="fas fa-clock me-1"></i><?= $row['duracao'] ?> min
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong class="text-success fs-5">R$ <?= number_format($row['preco'] ?? 0, 2, ',', '.'); ?></strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status = $row['status'] ?? 'agendado';
                                                    $badge_class = 'status-' . $status;
                                                    $status_text = ucfirst($status);
                                                    $status_icon = match($status) {
                                                        'agendado' => 'fas fa-clock',
                                                        'confirmado' => 'fas fa-check-circle',
                                                        'finalizado' => 'fas fa-star',
                                                        'cancelado' => 'fas fa-times-circle',
                                                        default => 'fas fa-question-circle'
                                                    };
                                                    ?>
                                                    <span class="badge <?= $badge_class ?> status-badge">
                                                        <i class="<?= $status_icon ?> me-1"></i><?= $status_text ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($status == 'agendado' || $status == 'confirmado'): ?>
                                                        <button class="btn btn-outline-danger btn-sm" onclick="cancelarAgendamento(<?= $row['id'] ?>)">
                                                            <i class="fas fa-times me-1"></i> Cancelar
                                                        </button>
                                                    <?php elseif ($status == 'cancelado'): ?>
                                                        <span class="text-muted">
                                                            <i class="fas fa-ban me-1"></i>Cancelado
                                                        </span>
                                                    <?php elseif ($status == 'finalizado'): ?>
                                                        <span class="text-success">
                                                            <i class="fas fa-check-circle me-1"></i> Concluído
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-search fa-2x text-muted mb-2"></i>
                                                <br>Nenhum agendamento encontrado.
                                            </td>
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
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Confirmar Cancelamento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-3">
                        <i class="fas fa-times-circle fa-4x text-danger mb-3"></i>
                        <h5>Tem certeza de que deseja cancelar este agendamento?</h5>
                        <p class="text-muted">Esta ação não pode ser desfeita.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-arrow-left me-1"></i> Não
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmCancel">
                        <i class="fas fa-trash me-1"></i> Sim, Cancelar
                    </button>
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
                // Mostrar loading no botão
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Cancelando...';
                this.disabled = true;

                // Fazer requisição AJAX para cancelar o agendamento
                fetch('../includes/cancel.php', {
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
                        // Mostrar sucesso e recarregar
                         this.innerHTML = '<i class="fas fa-check me-1"></i> Cancelado!';
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        // Mostrar erro
                        this.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Erro';
                        this.classList.remove('btn-danger');
                        this.classList.add('btn-warning');
                        
                        setTimeout(() => {
                            this.innerHTML = '<i class="fas fa-trash me-1"></i> Sim, Cancelar';
                            this.classList.remove('btn-warning');
                            this.classList.add('btn-danger');
                            this.disabled = false;
                        }, 2000);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    // Restaurar botão em caso de erro
                    this.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Erro';
                    this.classList.remove('btn-danger');
                    this.classList.add('btn-warning');
                    
                    setTimeout(() => {
                        this.innerHTML = '<i class="fas fa-trash me-1"></i> Sim, Cancelar';
                        this.classList.remove('btn-warning');
                        this.classList.add('btn-danger');
                        this.disabled = false;
                    }, 2000);
                });
            }
        });

        // Smooth scroll para links do menu
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Animações de entrada quando a página carrega
        document.addEventListener('DOMContentLoaded', function() {
            // Adicionar animações aos elementos
            const animateElements = document.querySelectorAll('.animate-in');
            animateElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
                element.classList.add('animate-in');
            });

            // Atualizar contadores com animação
            const counters = document.querySelectorAll('.stats-card h5');
            counters.forEach(counter => {
                const target = parseInt(counter.innerText);
                let count = 0;
                const increment = Math.ceil(target / 20);
                
                const updateCounter = () => {
                    if (count < target) {
                        count += increment;
                        if (count > target) count = target;
                        counter.innerText = count;
                        setTimeout(updateCounter, 50);
                    }
                };
                
                // Iniciar animação após um delay
                setTimeout(updateCounter, 500 + (Array.from(counters).indexOf(counter) * 100));
            });
        });

        // Tooltip para elementos que precisam
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar tooltips do Bootstrap se existirem
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Função para atualizar a página automaticamente (opcional)
        let autoRefreshInterval;
        
        function startAutoRefresh() {
            // Atualizar a cada 5 minutos (300000ms)
            autoRefreshInterval = setInterval(() => {
                // Só atualizar se não houver modais abertos
                const openModals = document.querySelectorAll('.modal.show');
                if (openModals.length === 0) {
                    location.reload();
                }
            }, 300000);
        }

        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        }

        // Iniciar auto-refresh quando a página carrega
        // document.addEventListener('DOMContentLoaded', startAutoRefresh);

        // Parar auto-refresh quando há interação do usuário
        document.addEventListener('click', function() {
            stopAutoRefresh();
        });

        // Função para mostrar notificações (se necessário)
        function showNotification(message, type = 'info', duration = 3000) {
            // Criar elemento de notificação
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = `
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-radius: 10px;
            `;
            
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remover após o tempo especificado
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, duration);
        }
    </script>
</body>
</html>