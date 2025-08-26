<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appointment.php';

$auth = new Auth();
$appointment = new Appointment();

// Verificar se cliente está logado
if (!$auth->isClientLoggedIn()) {
    header('Location: login.php');
    exit;
}

$cliente_data = $auth->getClientData();

// Inicializar conexão com o banco
$database = new Database();
$db = $database->getConnection();

// Processar formulário se foi enviado
$error = '';
$success = '';

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'criar') {
    $result = $appointment->criarAgendamento(
        $cliente_data['id'],
        $_POST['barbeiro_id'] ?? '',
        $_POST['servico_id'] ?? '',
        $_POST['data_agendamento'] ?? '',
        $_POST['hora_agendamento'] ?? '',
        $_POST['observacoes'] ?? ''
    );
    
    if ($result['success']) {
        $success = $result['message'];
        // Redirecionar após 2 segundos
        echo "<script>
            setTimeout(function() {
                window.location.href = 'agenda.php';
            }, 2000);
        </script>";
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Agendamento - BarberShop</title>
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
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(230, 126, 34, 0.25);
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
                        <a class="nav-link" href="agenda.php">
                            <i class="fas fa-tachometer-alt"></i> Meus Agendamentos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="agendar.php">
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
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fa fa-calendar-plus"></i> Novo Agendamento</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                                <small class="d-block">Redirecionando...</small>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="action" value="criar">

                            <!-- Barbeiro -->
                            <div class="mb-3">
                                <label for="barbeiro_id" class="form-label">
                                    <i class="fas fa-user-tie"></i> Barbeiro
                                </label>
                                <select class="form-control" name="barbeiro_id" id="barbeiro_id" required>
                                    <option value="">Selecione um barbeiro</option>
                                    <?php
                                    try {
                                        $stmt = $db->prepare("SELECT id, nome, especialidade FROM barbeiros WHERE ativo = 1 ORDER BY nome");
                                        $stmt->execute();
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($_POST['barbeiro_id']) && $_POST['barbeiro_id'] == $row['id']) ? 'selected' : '';
                                            echo "<option value='{$row['id']}' {$selected}>{$row['nome']}";
                                            if ($row['especialidade']) {
                                                echo " - {$row['especialidade']}";
                                            }
                                            echo "</option>";
                                        }
                                    } catch (Exception $e) {
                                        echo "<option value=''>Erro ao carregar barbeiros</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Serviço -->
                            <div class="mb-3">
                                <label for="servico_id" class="form-label">
                                    <i class="fas fa-cut"></i> Serviço
                                </label>
                                <select class="form-control" name="servico_id" id="servico_id" required>
                                    <option value="">Selecione um serviço</option>
                                    <?php
                                    try {
                                        $stmt = $db->prepare("SELECT id, nome, preco, duracao FROM servicos WHERE ativo = 1 ORDER BY nome");
                                        $stmt->execute();
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($_POST['servico_id']) && $_POST['servico_id'] == $row['id']) ? 'selected' : '';
                                            echo "<option value='{$row['id']}' {$selected}>";
                                            echo "{$row['nome']} - R$ " . number_format($row['preco'], 2, ',', '.') . " ({$row['duracao']}min)";
                                            echo "</option>";
                                        }
                                    } catch (Exception $e) {
                                        echo "<option value=''>Erro ao carregar serviços</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Data -->
                            <div class="mb-3">
                                <label for="data_agendamento" class="form-label">
                                    <i class="fas fa-calendar"></i> Data
                                </label>
                                <input type="date" class="form-control" name="data_agendamento" id="data_agendamento" 
                                       min="<?= date('Y-m-d') ?>" value="<?= $_POST['data_agendamento'] ?? '' ?>" required>
                            </div>

                            <!-- Hora -->
                            <div class="mb-3">
                                <label for="hora_agendamento" class="form-label">
                                    <i class="fas fa-clock"></i> Hora
                                </label>
                                <select class="form-control" name="hora_agendamento" id="hora_agendamento" required>
                                    <option value="">Primeiro selecione barbeiro e data</option>
                                </select>
                            </div>

                            <!-- Observações -->
                            <div class="mb-4">
                                <label for="observacoes" class="form-label">
                                    <i class="fas fa-comment"></i> Observações
                                </label>
                                <textarea class="form-control" name="observacoes" id="observacoes" rows="3" 
                                          placeholder="Informações adicionais (opcional)"><?= $_POST['observacoes'] ?? '' ?></textarea>
                            </div>

                            <!-- Botões -->
                            <div class="d-flex justify-content-between">
                                <a href="agenda.php" class="btn btn-secondary">
                                    <i class="fa fa-arrow-left"></i> Voltar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-check"></i> Confirmar Agendamento
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Carregar horários disponíveis quando barbeiro e data forem selecionados
        function carregarHorarios() {
            const barbeiroId = document.getElementById('barbeiro_id').value;
            const data = document.getElementById('data_agendamento').value;
            const horaSelect = document.getElementById('hora_agendamento');
            
            if (!barbeiroId || !data) {
                horaSelect.innerHTML = '<option value="">Primeiro selecione barbeiro e data</option>';
                return;
            }
            
            horaSelect.innerHTML = '<option value="">Carregando horários...</option>';
            
            // Fazer requisição AJAX para buscar horários disponíveis
            fetch('../ajax/horarios_disponiveis.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    barbeiro_id: barbeiroId,
                    data: data
                })
            })
            .then(response => response.json())
            .then(data => {
                horaSelect.innerHTML = '';
                
                if (data.success && data.horarios.length > 0) {
                    horaSelect.innerHTML = '<option value="">Selecione um horário</option>';
                    data.horarios.forEach(hora => {
                        horaSelect.innerHTML += `<option value="${hora}">${hora}</option>`;
                    });
                } else {
                    horaSelect.innerHTML = '<option value="">Nenhum horário disponível</option>';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                horaSelect.innerHTML = '<option value="">Erro ao carregar horários</option>';
            });
        }
        
        document.getElementById('barbeiro_id').addEventListener('change', carregarHorarios);
        document.getElementById('data_agendamento').addEventListener('change', carregarHorarios);
    </script>
</body>
</html>