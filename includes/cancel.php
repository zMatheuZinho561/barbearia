<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/appointment.php';

header('Content-Type: application/json');

$auth = new Auth();
$appointment = new Appointment();

// Verificar se cliente está logado
if (!$auth->isClientLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

// Obter dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['agendamento_id']) || empty($input['agendamento_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID do agendamento não fornecido.']);
    exit;
}

$cliente_data = $auth->getClientData();
$agendamento_id = $input['agendamento_id'];

try {
    // Verificar se o agendamento pertence ao cliente e pode ser cancelado
    $agendamento = $appointment->getAgendamento($agendamento_id, $cliente_data['id']);
    
    if (!$agendamento) {
        echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado.']);
        exit;
    }
    
    // Verificar se o agendamento pode ser cancelado
    if ($agendamento['status'] === 'cancelado') {
        echo json_encode(['success' => false, 'message' => 'Agendamento já foi cancelado.']);
        exit;
    }
    
    if ($agendamento['status'] === 'finalizado') {
        echo json_encode(['success' => false, 'message' => 'Não é possível cancelar um agendamento finalizado.']);
        exit;
    }
    
    // Verificar se não é muito próximo ao horário (permitir cancelamento até 2 horas antes)
    $data_agendamento = new DateTime($agendamento['data_agendamento'] . ' ' . $agendamento['hora_agendamento']);
    $agora = new DateTime();
    $diff = $data_agendamento->getTimestamp() - $agora->getTimestamp();
    
    if ($diff < 7200) { // 2 horas = 7200 segundos
        echo json_encode(['success' => false, 'message' => 'Não é possível cancelar com menos de 2 horas de antecedência.']);
        exit;
    }
    
    // Cancelar o agendamento
    $result = $appointment->cancelarAgendamento($agendamento_id, $cliente_data['id']);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Erro ao cancelar agendamento: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
?>