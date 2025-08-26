<?php
// Corrigir caminhos relativos
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/appointment.php';

// Script para testar horários disponíveis

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔧 Testando sistema de horários...</h2>";
    
    // Verificar se existem barbeiros
    $stmt = $db->query("SELECT id, nome FROM barbeiros WHERE ativo = 1 LIMIT 1");
    $barbeiro = $stmt->fetch();
    
    if (!$barbeiro) {
        echo "<p>❌ Nenhum barbeiro encontrado!</p>";
        echo "<p>Criando barbeiro de teste...</p>";
        
        $stmt = $db->prepare("INSERT INTO barbeiros (nome, especialidade, ativo) VALUES (?, ?, 1)");
        $stmt->execute(['João Silva', 'Cortes em geral']);
        $barbeiro_id = $db->lastInsertId();
        
        // Criar horários para o barbeiro
        for ($dia = 1; $dia <= 6; $dia++) {
            $hora_fim = ($dia == 6) ? '14:00:00' : '18:00:00';
            $stmt = $db->prepare("
                INSERT INTO horarios_disponiveis (barbeiro_id, dia_semana, hora_inicio, hora_fim, ativo) 
                VALUES (?, ?, '08:00:00', ?, 1)
            ");
            $stmt->execute([$barbeiro_id, $dia, $hora_fim]);
        }
        
        echo "<p>✅ Barbeiro e horários criados!</p>";
        $barbeiro = ['id' => $barbeiro_id, 'nome' => 'João Silva'];
    }
    
    echo "<p>Testando com barbeiro: <strong>{$barbeiro['nome']}</strong> (ID: {$barbeiro['id']})</p>";
    
    // Testar classe Appointment
    $appointment = new Appointment();
    
    // Testar para amanhã
    $data_teste = date('Y-m-d', strtotime('+1 day'));
    $dia_semana = date('N', strtotime($data_teste));
    
    echo "<p>Data de teste: <strong>$data_teste</strong> (Dia da semana: $dia_semana)</p>";
    
    // Verificar horários no banco
    $stmt = $db->prepare("
        SELECT * FROM horarios_disponiveis 
        WHERE barbeiro_id = ? AND dia_semana = ?
    ");
    $stmt->execute([$barbeiro['id'], $dia_semana]);
    $horario_banco = $stmt->fetch();
    
    if ($horario_banco) {
        echo "<p>✅ Horário encontrado no banco: {$horario_banco['hora_inicio']} às {$horario_banco['hora_fim']}</p>";
    } else {
        echo "<p>❌ Nenhum horário encontrado no banco para o dia $dia_semana</p>";
        
        // Inserir horário para teste
        if ($dia_semana <= 6) { // Segunda a sábado
            $hora_fim = ($dia_semana == 6) ? '14:00:00' : '18:00:00';
            $stmt = $db->prepare("
                INSERT INTO horarios_disponiveis (barbeiro_id, dia_semana, hora_inicio, hora_fim, ativo) 
                VALUES (?, ?, '08:00:00', ?, 1)
            ");
            $stmt->execute([$barbeiro['id'], $dia_semana, $hora_fim]);
            echo "<p>✅ Horário inserido para o dia</p>";
        }
    }
    
    // Testar método getHorariosDisponiveis
    $horarios = $appointment->getHorariosDisponiveis($barbeiro['id'], $data_teste);
    
    if (!empty($horarios)) {
        echo "<p>✅ <strong>" . count($horarios) . "</strong> horários disponíveis:</p>";
        echo "<p>" . implode(', ', array_slice($horarios, 0, 10)) . (count($horarios) > 10 ? '...' : '') . "</p>";
    } else {
        echo "<p>❌ Nenhum horário disponível retornado</p>";
    }
    
    echo "<hr>";
    echo "<h3>📊 Resumo dos testes:</h3>";
    echo "<ul>";
    echo "<li>Barbeiro: ✅ {$barbeiro['nome']}</li>";
    echo "<li>Horários no banco: " . ($horario_banco ? "✅" : "❌") . "</li>";
    echo "<li>Horários disponíveis: " . (count($horarios) > 0 ? "✅ " . count($horarios) : "❌") . "</li>";
    echo "</ul>";
    
    if (count($horarios) > 0) {
        echo "<p>🎉 <strong>Sistema funcionando corretamente!</strong></p>";
        echo "<p><a href='agendar.php' class='btn'>Testar Agendamento</a></p>";
    } else {
        echo "<p>⚠️ <strong>Sistema precisa de ajustes</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Erro: " . $e->getMessage() . "</h2>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Teste de Horários - BarberShop</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
            margin: 20px auto; 
            padding: 20px; 
            line-height: 1.6;
        }
        h2, h3 { color: #2c3e50; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #e67e22;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
        }
        ul { background: #f8f9fa; padding: 15px; border-radius: 5px; }
        pre { background: #f1f1f1; padding: 10px; border-radius: 5px; font-size: 12px; }
    </style>
</head>
<body>
    <!-- O conteúdo PHP será exibido aqui -->
</body>
</html>