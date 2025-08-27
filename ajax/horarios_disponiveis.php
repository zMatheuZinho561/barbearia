<?php
// ajax/horarios_disponiveis.php
require_once '../config/database.php';
require_once '../includes/appointment.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Se não veio via JSON, tentar via POST normal
    if (!$input) {
        $input = $_POST;
    }
    
    if (!isset($input['barbeiro_id']) || !isset($input['data'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Parâmetros barbeiro_id e data são obrigatórios'
        ]);
        exit;
    }
    
    $barbeiro_id = (int) $input['barbeiro_id'];
    $data = $input['data'];
    
    // Validar data
    if (!DateTime::createFromFormat('Y-m-d', $data)) {
        echo json_encode([
            'success' => false,
            'message' => 'Data inválida'
        ]);
        exit;
    }
    
    // Verificar se a data não é no passado
    if (strtotime($data) < strtotime(date('Y-m-d'))) {
        echo json_encode([
            'success' => false,
            'message' => 'Não é possível agendar para datas passadas',
            'horarios' => []
        ]);
        exit;
    }
    
    $appointment = new Appointment();
    $horarios = $appointment->getHorariosDisponiveis($barbeiro_id, $data);
    
    echo json_encode([
        'success' => true,
        'horarios' => $horarios,
        'data' => $data,
        'barbeiro_id' => $barbeiro_id
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar horários disponíveis: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'horarios' => []
    ]);
}
?>