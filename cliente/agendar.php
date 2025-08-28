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
    <link rel="stylesheet" href="../style/style_cliente/st_agendar.css">
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
                        <a class="nav-link" href="agenda.php">
                            <i class="fas fa-calendar-alt"></i> Meus Agendamentos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="agendar.php">
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

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Progress Steps -->
                <div class="progress-steps animate-in mb-4">
                    <div class="progress-step active" id="step-1">
                        <div class="step-icon">1</div>
                        <div class="step-label">Barbeiro</div>
                    </div>
                    <div class="progress-step" id="step-2">
                        <div class="step-icon">2</div>
                        <div class="step-label">Serviço</div>
                    </div>
                    <div class="progress-step" id="step-3">
                        <div class="step-icon">3</div>
                        <div class="step-label">Data & Hora</div>
                    </div>
                    <div class="progress-step" id="step-4">
                        <div class="step-icon">4</div>
                        <div class="step-label">Confirmar</div>
                    </div>
                </div>

                <div class="card animate-in" style="animation-delay: 0.2s">
                    <div class="card-header">
                        <h4><i class="fas fa-calendar-plus"></i> Novo Agendamento</h4>
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
                                <div class="mt-2">
                                    <small class="d-block">
                                        <i class="fas fa-clock me-1"></i> Redirecionando em alguns segundos...
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="agendamentoForm">
                            <input type="hidden" name="action" value="criar">

                            <!-- Barbeiro -->
                            <div class="mb-4">
                                <label for="barbeiro_id" class="form-label">
                                    <i class="fas fa-user-tie"></i> Escolha o Barbeiro
                                </label>
                                <select class="form-select form-control" name="barbeiro_id" id="barbeiro_id" required>
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
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Selecione o profissional de sua preferência
                                </div>
                            </div>

                            <!-- Serviço -->
                            <div class="mb-4">
                                <label for="servico_id" class="form-label">
                                    <i class="fas fa-cut"></i> Escolha o Serviço
                                </label>
                                <select class="form-select form-control" name="servico_id" id="servico_id" required>
                                    <option value="">Selecione um serviço</option>
                                    <?php
                                    try {
                                        $stmt = $db->prepare("SELECT id, nome, preco, duracao, descricao FROM servicos WHERE ativo = 1 ORDER BY nome");
                                        $stmt->execute();
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($_POST['servico_id']) && $_POST['servico_id'] == $row['id']) ? 'selected' : '';
                                            echo "<option value='{$row['id']}' {$selected} data-preco='{$row['preco']}' data-duracao='{$row['duracao']}'>";
                                            echo "{$row['nome']} - R$ " . number_format($row['preco'], 2, ',', '.') . " ({$row['duracao']}min)";
                                            echo "</option>";
                                        }
                                    } catch (Exception $e) {
                                        echo "<option value=''>Erro ao carregar serviços</option>";
                                    }
                                    ?>
                                </select>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Preço e duração já incluídos na seleção
                                </div>
                                <div id="servico-info" class="mt-2" style="display: none;">
                                    <div class="p-3 bg-light rounded-3">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <strong class="text-success">
                                                    <i class="fas fa-dollar-sign me-1"></i>
                                                    Valor: R$ <span id="preco-valor">0,00</span>
                                                </strong>
                                            </div>
                                            <div class="col-sm-6">
                                                <strong class="text-info">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Duração: <span id="duracao-valor">0</span> min
                                                </strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Data -->
                            <div class="mb-4">
                                <label for="data_agendamento" class="form-label">
                                    <i class="fas fa-calendar"></i> Escolha a Data
                                </label>
                                <input type="date" class="form-control" name="data_agendamento" id="data_agendamento" 
                                       min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                                       value="<?= $_POST['data_agendamento'] ?? '' ?>" required>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Agendamentos disponíveis para os próximos 30 dias
                                </div>
                            </div>

                            <!-- Hora -->
                            <div class="mb-4">
                                <label for="hora_agendamento" class="form-label">
                                    <i class="fas fa-clock"></i> Escolha o Horário
                                </label>
                                <select class="form-select form-control" name="hora_agendamento" id="hora_agendamento" required>
                                    <option value="">Primeiro selecione barbeiro e data</option>
                                </select>
                                <div class="form-text" id="horarios-info">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Os horários disponíveis serão mostrados após selecionar barbeiro e data
                                </div>
                            </div>

                            <!-- Observações -->
                            <div class="mb-5">
                                <label for="observacoes" class="form-label">
                                    <i class="fas fa-comment-dots"></i> Observações
                                </label>
                                <textarea class="form-control" name="observacoes" id="observacoes" rows="4" 
                                          placeholder="Conte-nos se há algo específico que gostaria que soubéssemos sobre seu atendimento (opcional)"><?= $_POST['observacoes'] ?? '' ?></textarea>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Campo opcional - use para informações especiais ou preferências
                                </div>
                            </div>

                            <!-- Botões -->
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="agenda.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Voltar
                                </a>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-check me-2"></i> Confirmar Agendamento
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Card de Informações Adicionais -->
                <div class="card mt-4 animate-in" style="animation-delay: 0.4s">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            Informações Importantes
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        Confirmação por WhatsApp
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-clock text-info me-2"></i>
                                        Chegue 10 minutos antes
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <i class="fas fa-ban text-warning me-2"></i>
                                        Cancelamento até 2h antes
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-phone text-primary me-2"></i>
                                        Dúvidas: (11) 99999-9999
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variáveis globais
        let formData = {
            barbeiro_id: '',
            servico_id: '',
            data_agendamento: '',
            hora_agendamento: ''
        };

        // Elementos do DOM
        const barbeiroSelect = document.getElementById('barbeiro_id');
        const servicoSelect = document.getElementById('servico_id');
        const dataInput = document.getElementById('data_agendamento');
        const horaSelect = document.getElementById('hora_agendamento');
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('agendamentoForm');

        // Progress steps
        const steps = document.querySelectorAll('.progress-step');

        // Atualizar progress steps
        function updateProgressSteps() {
            steps.forEach((step, index) => {
                step.classList.remove('active', 'completed');
            });

            let currentStep = 1;
            
            if (formData.barbeiro_id) {
                document.getElementById('step-1').classList.add('completed');
                currentStep = 2;
            }
            
            if (formData.servico_id) {
                document.getElementById('step-2').classList.add('completed');
                currentStep = 3;
            }
            
            if (formData.data_agendamento) {
                document.getElementById('step-3').classList.add('completed');
                currentStep = 4;
            }
            
            if (formData.hora_agendamento) {
                document.getElementById('step-4').classList.add('completed');
                currentStep = 4;
            }

            if (currentStep <= 4) {
                document.getElementById(`step-${currentStep}`).classList.add('active');
            }
        }

        // Mostrar informações do serviço
        function mostrarInfoServico() {
            const selectedOption = servicoSelect.options[servicoSelect.selectedIndex];
            const servicoInfo = document.getElementById('servico-info');
            
            if (selectedOption.value) {
                const preco = selectedOption.dataset.preco;
                const duracao = selectedOption.dataset.duracao;
                
                document.getElementById('preco-valor').textContent = 
                    parseFloat(preco).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                document.getElementById('duracao-valor').textContent = duracao;
                
                servicoInfo.style.display = 'block';
                servicoInfo.classList.add('animate-in');
            } else {
                servicoInfo.style.display = 'none';
            }
        }

        // Carregar horários disponíveis
        function carregarHorarios() {
            if (!formData.barbeiro_id || !formData.data_agendamento) {
                horaSelect.innerHTML = '<option value="">Primeiro selecione barbeiro e data</option>';
                document.getElementById('horarios-info').innerHTML = 
                    '<i class="fas fa-info-circle me-1"></i> Os horários disponíveis serão mostrados após selecionar barbeiro e data';
                return;
            }
            
            horaSelect.innerHTML = '<option value="">Carregando horários...</option>';
            horaSelect.classList.add('loading');
            document.getElementById('horarios-info').innerHTML = 
                '<i class="fas fa-spinner fa-spin me-1"></i> Buscando horários disponíveis...';
            
            // Fazer requisição AJAX para buscar horários disponíveis
            fetch('../ajax/horarios_disponiveis.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    barbeiro_id: formData.barbeiro_id,
                    data: formData.data_agendamento
                })
            })
            .then(response => response.json())
            .then(data => {
                horaSelect.classList.remove('loading');
                horaSelect.innerHTML = '';
                
                if (data.success && data.horarios.length > 0) {
                    horaSelect.innerHTML = '<option value="">Selecione um horário</option>';
                    data.horarios.forEach(hora => {
                        horaSelect.innerHTML += `<option value="${hora}">${hora}</option>`;
                    });
                    document.getElementById('horarios-info').innerHTML = 
                        `<i class="fas fa-check-circle text-success me-1"></i> ${data.horarios.length} horários disponíveis`;
                } else {
                    horaSelect.innerHTML = '<option value="">Nenhum horário disponível</option>';
                    document.getElementById('horarios-info').innerHTML = 
                        '<i class="fas fa-exclamation-triangle text-warning me-1"></i> Nenhum horário disponível para esta data';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                horaSelect.classList.remove('loading');
                horaSelect.innerHTML = '<option value="">Erro ao carregar horários</option>';
                document.getElementById('horarios-info').innerHTML = 
                    '<i class="fas fa-exclamation-circle text-danger me-1"></i> Erro ao buscar horários. Tente novamente.';
            });
        }

        // Event listeners
        barbeiroSelect.addEventListener('change', function() {
            formData.barbeiro_id = this.value;
            updateProgressSteps();
            carregarHorarios();
        });

        servicoSelect.addEventListener('change', function() {
            formData.servico_id = this.value;
            updateProgressSteps();
            mostrarInfoServico();
        });

        dataInput.addEventListener('change', function() {
            formData.data_agendamento = this.value;
            updateProgressSteps();
            carregarHorarios();
        });

        horaSelect.addEventListener('change', function() {
            formData.hora_agendamento = this.value;
            updateProgressSteps();
        });

        // Validação do formulário em tempo real
        function validarFormulario() {
            const isValid = formData.barbeiro_id && 
                           formData.servico_id && 
                           formData.data_agendamento && 
                           formData.hora_agendamento;
            
            submitBtn.disabled = !isValid;
            
            if (isValid) {
                submitBtn.classList.remove('btn-secondary');
                submitBtn.classList.add('btn-primary');
            } else {
                submitBtn.classList.remove('btn-primary');
                submitBtn.classList.add('btn-secondary');
            }
        }

        // Monitorar mudanças nos campos
        [barbeiroSelect, servicoSelect, dataInput, horaSelect].forEach(element => {
            element.addEventListener('change', validarFormulario);
        });

        // Submissão do formulário com feedback visual
        form.addEventListener('submit', function(e) {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processando...';
            submitBtn.disabled = true;
            
            // Adicionar pequeno delay para melhor UX
            setTimeout(() => {
                // O formulário continuará o submit normal
            }, 500);
        });

        // Inicializar estado
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar valores pré-preenchidos
            formData.barbeiro_id = barbeiroSelect.value;
            formData.servico_id = servicoSelect.value;
            formData.data_agendamento = dataInput.value;
            formData.hora_agendamento = horaSelect.value;
            
            updateProgressSteps();
            validarFormulario();
            mostrarInfoServico();
            
            // Se barbeiro e data já estão selecionados, carregar horários
            if (formData.barbeiro_id && formData.data_agendamento) {
                carregarHorarios();
            }

            // Inicializar tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Adicionar animações aos elementos
            const animateElements = document.querySelectorAll('.animate-in');
            animateElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
        });

        // Função para mostrar notificações personalizadas
        function showNotification(message, type = 'info', duration = 4000) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = `
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 350px;
                max-width: 400px;
                box-shadow: 0 8px 25px rgba(0,0,0,0.15);
                border-radius: 15px;
                backdrop-filter: blur(10px);
            `;
            
            const icon = type === 'success' ? 'check-circle' : 
                        type === 'danger' ? 'exclamation-circle' : 
                        type === 'warning' ? 'exclamation-triangle' : 'info-circle';
            
            notification.innerHTML = `
                <i class="fas fa-${icon} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remover
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, duration);
        }

        // Smooth scroll para elementos
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

        // Prevenir duplo submit
        let isSubmitting = false;
        form.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;
        });

        // Máscara para campos se necessário
        function aplicarMascaras() {
            // Aqui você pode adicionar máscaras para telefone, CPF, etc.
            // se tiver campos adicionais no futuro
        }

        // Validação de data (não permitir domingos, por exemplo)
        dataInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const dayOfWeek = selectedDate.getDay();
            
            // Exemplo: bloquear domingos (0)
            if (dayOfWeek === 0) {
                showNotification('Desculpe, não atendemos aos domingos. Por favor, escolha outro dia.', 'warning');
                this.value = '';
                formData.data_agendamento = '';
                updateProgressSteps();
            }
        });
    </script>
</body>
</html>