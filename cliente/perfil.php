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
$cliente_id = $cliente_data['id'];

// Inicializar conexão com o banco
$database = new Database();
$db = $database->getConnection();

// Buscar dados completos do cliente
try {
    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar plano ativo do cliente
    $stmt = $db->prepare("
        SELECT cp.*, p.nome as plano_nome, p.descricao as plano_descricao, 
               p.preco as plano_preco, p.cortes_inclusos, p.duracao_dias,
               DATEDIFF(cp.data_vencimento, CURDATE()) as dias_restantes,
               CASE 
                   WHEN cp.status = 'ativo' AND cp.data_vencimento >= CURDATE() THEN 'ativo'
                   WHEN cp.status = 'ativo' AND cp.data_vencimento < CURDATE() THEN 'vencido'
                   ELSE cp.status
               END as status_atual
        FROM cliente_planos cp
        JOIN planos p ON cp.plano_id = p.id
        WHERE cp.cliente_id = ? AND cp.status IN ('ativo', 'vencido')
        ORDER BY cp.data_inicio DESC
        LIMIT 1
    ");
    $stmt->execute([$cliente_id]);
    $plano_ativo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar histórico de uso de cortes (últimos 30 dias)
    $stmt = $db->prepare("
        SELECT a.data_agendamento, a.hora_agendamento, s.nome as servico_nome, 
               b.nome as barbeiro_nome, a.status
        FROM agendamentos a
        JOIN servicos s ON a.servico_id = s.id
        JOIN barbeiros b ON a.barbeiro_id = b.id
        WHERE a.cliente_id = ? 
        AND a.data_agendamento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND a.status = 'finalizado'
        ORDER BY a.data_agendamento DESC, a.hora_agendamento DESC
        LIMIT 10
    ");
    $stmt->execute([$cliente_id]);
    $cortes_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas do cliente
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_agendamentos,
            SUM(CASE WHEN status = 'finalizado' THEN 1 ELSE 0 END) as total_finalizados,
            SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as total_cancelados
        FROM agendamentos 
        WHERE cliente_id = ?
    ");
    $stmt->execute([$cliente_id]);
    $stats_cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Próximos agendamentos
    $stmt = $db->prepare("
        SELECT a.*, s.nome as servico_nome, b.nome as barbeiro_nome, s.preco, s.duracao
        FROM agendamentos a
        JOIN servicos s ON a.servico_id = s.id
        JOIN barbeiros b ON a.barbeiro_id = b.id
        WHERE a.cliente_id = ? 
        AND a.data_agendamento >= CURDATE() 
        AND a.status IN ('agendado', 'confirmado')
        ORDER BY a.data_agendamento ASC, a.hora_agendamento ASC
        LIMIT 5
    ");
    $stmt->execute([$cliente_id]);
    $proximos_agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $cliente = $cliente_data;
    $plano_ativo = null;
    $cortes_recentes = [];
    $stats_cliente = ['total_agendamentos' => 0, 'total_finalizados' => 0, 'total_cancelados' => 0];
    $proximos_agendamentos = [];
}

// Processar atualizações do perfil
$error = '';
$success = '';

if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'atualizar_perfil') {
        try {
            $stmt = $db->prepare("
                UPDATE clientes 
                SET nome = ?, telefone = ?, email = ? 
                WHERE id = ?
            ");
            $result = $stmt->execute([
                $_POST['nome'],
                $_POST['telefone'],
                $_POST['email'] ?? '',
                $cliente_id
            ]);
            
            if ($result) {
                $success = 'Perfil atualizado com sucesso!';
                // Atualizar dados do cliente na sessão
                $_SESSION['cliente_data']['nome'] = $_POST['nome'];
                // Recarregar dados
                header('Location: perfil.php?updated=1');
                exit;
            } else {
                $error = 'Erro ao atualizar perfil.';
            }
        } catch (Exception $e) {
            $error = 'Erro interno: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['updated'])) {
    $success = 'Perfil atualizado com sucesso!';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - BarberShop</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style/style_cliente/st_perfil.css">
</head>
<body>
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
                        <a class="nav-link" href="agenda.php">
                            <i class="fas fa-calendar-alt"></i> Meus Agendamentos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="agendar.php">
                            <i class="fas fa-calendar-plus"></i> Agendar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="perfil.php">
                            <i class="fas fa-user-cog"></i> Meu Perfil
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

        <!-- Card de boas-vindas -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card welcome-card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1">
                                    <i class="fas fa-user-circle"></i> 
                                    Olá, <?= htmlspecialchars($cliente['nome']) ?>!
                                </h4>
                                <p class="mb-0 opacity-90">Gerencie seu perfil e acompanhe seu plano de assinatura.</p>
                                <div class="d-flex gap-3 mt-3">
                                    <small class="opacity-75">
                                        <i class="fas fa-calendar-check"></i> 
                                        <?= $stats_cliente['total_finalizados'] ?> cortes realizados
                                    </small>
                                    <small class="opacity-75">
                                        <i class="fas fa-user-clock"></i> 
                                        Cliente desde <?= date('m/Y', strtotime($cliente['data_cadastro'] ?? 'now')) ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <?php if ($plano_ativo): ?>
                                    <div class="d-flex flex-column gap-1">
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-crown"></i> <?= htmlspecialchars($plano_ativo['plano_nome']) ?>
                                        </span>
                                        <?php if ($plano_ativo['status_atual'] === 'ativo'): ?>
                                            <small class="text-light opacity-75">
                                                Válido por <?= $plano_ativo['dias_restantes'] > 0 ? $plano_ativo['dias_restantes'] . ' dias' : 'hoje' ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-warning">
                                                <i class="fas fa-exclamation-triangle"></i> Plano vencido
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <a href="../planos.php" class="btn btn-light">
                                        <i class="fas fa-crown"></i> Escolher Plano
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Coluna esquerda -->
            <div class="col-lg-4 mb-4">
                <!-- Dados do perfil -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-edit"></i> Meus Dados
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="atualizar_perfil">
                            
                            <div class="mb-3">
                                <label for="nome" class="form-label">Nome Completo</label>
                                <input type="text" class="form-control" name="nome" id="nome" 
                                       value="<?= htmlspecialchars($cliente['nome']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="telefone" class="form-label">Telefone</label>
                                <input type="tel" class="form-control" name="telefone" id="telefone" 
                                       value="<?= htmlspecialchars($cliente['telefone']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" class="form-control" name="email" id="email" 
                                       value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save"></i> Salvar Alterações
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Estatísticas -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar"></i> Minhas Estatísticas
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4 mb-3">
                                <h4 class="text-primary"><?= $stats_cliente['total_agendamentos'] ?></h4>
                                <small class="text-muted">Total</small>
                            </div>
                            <div class="col-4 mb-3">
                                <h4 class="text-success"><?= $stats_cliente['total_finalizados'] ?></h4>
                                <small class="text-muted">Finalizados</small>
                            </div>
                            <div class="col-4 mb-3">
                                <h4 class="text-danger"><?= $stats_cliente['total_cancelados'] ?></h4>
                                <small class="text-muted">Cancelados</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna direita -->
            <div class="col-lg-8">
                <!-- Plano ativo -->
                <?php if ($plano_ativo): ?>
                    <div class="card plano-card mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h4 class="mb-2">
                                        <i class="fas fa-crown"></i> 
                                        <?= htmlspecialchars($plano_ativo['plano_nome']) ?>
                                        <span class="badge status-<?= $plano_ativo['status_atual'] ?> ms-2">
                                            <?= ucfirst($plano_ativo['status_atual']) ?>
                                        </span>
                                    </h4>
                                    <p class="mb-3 opacity-90">
                                        <?= htmlspecialchars($plano_ativo['plano_descricao'] ?? 'Plano de assinatura mensal') ?>
                                    </p>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <small class="d-block opacity-75">Início do plano:</small>
                                            <strong><?= date('d/m/Y', strtotime($plano_ativo['data_inicio'])) ?></strong>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <small class="d-block opacity-75">Vencimento:</small>
                                            <strong class="<?= $plano_ativo['status_atual'] === 'vencido' ? 'text-warning' : '' ?>">
                                                <?= date('d/m/Y', strtotime($plano_ativo['data_vencimento'])) ?>
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="mb-3">
                                        <h2 class="mb-0">R$ <?= number_format($plano_ativo['plano_preco'], 2, ',', '.') ?></h2>
                                        <small class="opacity-75">por mês</small>
                                    </div>
                                    
                                    <?php if ($plano_ativo['status_atual'] === 'vencido'): ?>
                                        <a href="../planos.php" class="btn btn-warning btn-sm">
                                            <i class="fas fa-redo"></i> Renovar Plano
                                        </a>
                                    <?php else: ?>
                                        <small class="d-block opacity-75">
                                            <?php if ($plano_ativo['dias_restantes'] > 0): ?>
                                                Restam <?= $plano_ativo['dias_restantes'] ?> dias
                                            <?php else: ?>
                                                Vence hoje
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Progress de cortes -->
                            <?php if ($plano_ativo['cortes_inclusos'] > 0): ?>
                                <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="opacity-75">Uso de Cortes</small>
                                        <small class="opacity-75">
                                            <?= $plano_ativo['cortes_utilizados'] ?> de <?= $plano_ativo['cortes_inclusos'] ?>
                                        </small>
                                    </div>
                                    <?php 
                                    $percentual = ($plano_ativo['cortes_utilizados'] / $plano_ativo['cortes_inclusos']) * 100;
                                    ?>
                                    <div class="progress progress-cortes">
                                        <div class="progress-bar" style="width: <?= min($percentual, 100) ?>%"></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                                <div class="text-center">
                                    <i class="fas fa-infinity fa-2x mb-2"></i>
                                    <div><strong>Cortes Ilimitados</strong></div>
                                    <small class="opacity-75">Aproveite sem limites!</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Sem plano -->
                    <div class="card mb-4">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-crown fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted mb-3">Você ainda não possui um plano</h4>
                            <p class="text-muted mb-4">
                                Escolha um de nossos planos de assinatura e desfrute de vantagens exclusivas!
                            </p>
                            <a href="../planos.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-crown"></i> Escolher Plano
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Próximos agendamentos -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-check"></i> Próximos Agendamentos
                        </h5>
                        <a href="agendar.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Agendar
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($proximos_agendamentos)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-calendar-times fa-2x text-muted mb-3"></i>
                                <p class="text-muted mb-3">Nenhum agendamento próximo</p>
                                <a href="agendar.php" class="btn btn-primary">
                                    <i class="fas fa-calendar-plus"></i> Agendar Agora
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($proximos_agendamentos as $agendamento): ?>
                                    <div class="list-group-item px-0 py-3">
                                        <div class="row align-items-center">
                                            <div class="col-md-3">
                                                <strong class="text-primary">
                                                    <?= date('d/m/Y', strtotime($agendamento['data_agendamento'])) ?>
                                                </strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?= date('H:i', strtotime($agendamento['hora_agendamento'])) ?>
                                                </small>
                                            </div>
                                            <div class="col-md-4">
                                                <div>
                                                    <strong><?= htmlspecialchars($agendamento['servico_nome']) ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user-tie"></i> 
                                                        <?= htmlspecialchars($agendamento['barbeiro_nome']) ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <strong class="text-success">
                                                    R$ <?= number_format($agendamento['preco'], 2, ',', '.') ?>
                                                </strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?= $agendamento['duracao'] ?> min
                                                </small>
                                            </div>
                                            <div class="col-md-2 text-end">
                                                <span class="badge bg-success">
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

                <!-- Histórico de cortes recentes -->
                <?php if (!empty($cortes_recentes)): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-history"></i> Cortes Recentes
                            </h5>
                            <a href="agenda.php" class="btn btn-outline-primary btn-sm">
                                Ver Todos
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($cortes_recentes as $corte): ?>
                                    <div class="list-group-item px-0 py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($corte['servico_nome']) ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-user-tie"></i> 
                                                    <?= htmlspecialchars($corte['barbeiro_nome']) ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted">
                                                    <?= date('d/m/Y', strtotime($corte['data_agendamento'])) ?>
                                                    <br>
                                                    <?= date('H:i', strtotime($corte['hora_agendamento'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            let formattedValue = '';
            
            if (value.length >= 11) {
                formattedValue = `(${value.substring(0, 2)}) ${value.substring(2, 7)}-${value.substring(7, 11)}`;
            } else if (value.length >= 10) {
                formattedValue = `(${value.substring(0, 2)}) ${value.substring(2, 6)}-${value.substring(6, 10)}`;
            } else if (value.length >= 6) {
                formattedValue = `(${value.substring(0, 2)}) ${value.substring(2, 6)}-${value.substring(6)}`;
            } else if (value.length >= 2) {
                formattedValue = `(${value.substring(0, 2)}) ${value.substring(2)}`;
            } else {
                formattedValue = value;
            }
            
            e.target.value = formattedValue;
        });

        // Animações suaves para os cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>