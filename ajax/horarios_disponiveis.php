<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/appointment.php';

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Ler dados JSON da requisição
$input = json_decode(file_get_contents('php://input'), true);

// Validar dados recebidos
if (!isset($input['barbeiro_id']) || !isset($input['data'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

$barbeiro_id = (int)$input['barbeiro_id'];
$data = $input['data'];

// Validar formato da data
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de data inválido']);
    exit;
}

// Verificar se a data não é no passado
$hoje = new DateTime();
$data_agendamento = new DateTime($data);

if ($data_agendamento < $hoje->setTime(0, 0, 0)) {
    echo json_encode(['success' => false, 'message' => 'Não é possível agendar para datas passadas']);
    exit;
}

try {
    $appointment = new Appointment();
    $horarios = $appointment->getHorariosDisponiveis($barbeiro_id, $data);
    
    echo json_encode([
        'success' => true,
        'horarios' => $horarios,
        'message' => count($horarios) . ' horários encontrados'
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar horários: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>