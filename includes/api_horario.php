<?php
// admin/ajax_horarios.php
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/appointment.php';

$auth = new Auth();
$appointment = new Appointment();

// Verificar se admin está logado
if (!$auth->isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['barbeiro_id']) || !isset($input['data'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$barbeiro_id = $input['barbeiro_id'];
$data = $input['data'];

// Validar data
$data_obj = DateTime::createFromFormat('Y-m-d', $data);
if (!$data_obj || $data_obj->format('Y-m-d') !== $data) {
    echo json_encode(['success' => false, 'message' => 'Data inválida']);
    exit;
}

// Verificar se a data não é no passado
$hoje = new DateTime();
if ($data_obj < $hoje->setTime(0, 0, 0)) {
    echo json_encode(['success' => false, 'message' => 'Data no passado']);
    exit;
}

try {
    // Buscar horários disponíveis
    $horarios = $appointment->getHorariosDisponiveis($barbeiro_id, $data);
    
    echo json_encode([
        'success' => true,
        'horarios' => $horarios,
        'data' => $data,
        'barbeiro_id' => $barbeiro_id
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar horários: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>