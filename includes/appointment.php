<?php
require_once __DIR__ . '/../config/database.php';

class Appointment {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Listar barbeiros ativos
    public function getBarbeiros() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, nome, especialidade, telefone, foto
                FROM barbeiros 
                WHERE ativo = 1
                ORDER BY nome
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao buscar barbeiros: " . $e->getMessage());
            return [];
        }
    }
    
    public function getTotalClientes() {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM clientes");
        return $stmt->fetch()['total'];
    }

    public function getTotalBarbeiros() {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM barbeiros");
        return $stmt->fetch()['total'];
    }

    public function getTotalServicos() {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM servicos");
        return $stmt->fetch()['total'];
    }

    public function getTotalAgendamentos() {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM agendamentos");
        return $stmt->fetch()['total'];
    }
    
    // Listar serviços ativos
    public function getServicos() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, nome, descricao, preco, duracao
                FROM servicos 
                WHERE ativo = 1
                ORDER BY preco
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao buscar serviços: " . $e->getMessage());
            return [];
        }
    }
    
    // Obter horários disponíveis de um barbeiro em uma data - CORRIGIDO
    public function getHorariosDisponiveis($barbeiro_id, $data) {
    try {
        // Validar entrada
        if (empty($barbeiro_id) || empty($data)) {
            return [];
        }
        
        $dia_semana = date('N', strtotime($data)); // 1=Segunda, 7=Domingo
        
        // Buscar horário de funcionamento do barbeiro
        $stmt = $this->db->prepare("
            SELECT hora_inicio, hora_fim
            FROM horarios_disponiveis 
            WHERE barbeiro_id = ? AND dia_semana = ? AND ativo = 1
        ");
        $stmt->execute([$barbeiro_id, $dia_semana]);
        $funcionamento = $stmt->fetch();
        
        if (!$funcionamento) {
            // Se não encontrar configuração específica, usar horário padrão
            $horarios_padrao = [
                1 => ['08:00:00', '18:00:00'], // Segunda
                2 => ['08:00:00', '18:00:00'], // Terça
                3 => ['08:00:00', '18:00:00'], // Quarta
                4 => ['08:00:00', '18:00:00'], // Quinta
                5 => ['08:00:00', '18:00:00'], // Sexta
                6 => ['08:00:00', '16:00:00'], // Sábado
            ];
            
            if (!isset($horarios_padrao[$dia_semana])) {
                return []; // Domingo ou dia não configurado
            }
            
            $funcionamento = [
                'hora_inicio' => $horarios_padrao[$dia_semana][0],
                'hora_fim' => $horarios_padrao[$dia_semana][1]
            ];
        }
        
        // Buscar agendamentos já marcados
        $stmt = $this->db->prepare("
            SELECT TIME(hora_agendamento) as hora_agendamento, s.duracao
            FROM agendamentos a
            JOIN servicos s ON a.servico_id = s.id
            WHERE a.barbeiro_id = ? AND a.data_agendamento = ? 
            AND a.status IN ('agendado', 'confirmado')
        ");
        $stmt->execute([$barbeiro_id, $data]);
        $agendados = $stmt->fetchAll();
        
        // Converter agendamentos para array de horários ocupados
        $horarios_ocupados = [];
        foreach ($agendados as $agendado) {
            $inicio = strtotime($agendado['hora_agendamento']);
            $duracao_segundos = ($agendado['duracao'] ?? 30) * 60; // Default 30 min
            $fim = $inicio + $duracao_segundos;
            
            // Marcar todos os slots de 30 min ocupados neste período
            for ($slot = $inicio; $slot < $fim; $slot += 1800) { // 1800 = 30 min
                $horarios_ocupados[] = date('H:i', $slot);
            }
        }
        
        // Gerar horários disponíveis (intervalos de 30 minutos)
        $horarios = [];
        $inicio = strtotime($funcionamento['hora_inicio']);
        $fim = strtotime($funcionamento['hora_fim']);
        
        // Não permitir agendamentos muito próximos ao fim do expediente (deixar pelo menos 30 min)
        $fim -= 1800;
        
        while ($inicio <= $fim) {
            $hora = date('H:i', $inicio);
            
            // Verificar se não está ocupado
            if (!in_array($hora, $horarios_ocupados)) {
                // Não permitir agendamento no passado
                $agora = new DateTime();
                $data_hora = new DateTime($data . ' ' . $hora);
                
                // Se for hoje, verificar se o horário já passou (+1 hora de antecedência mínima)
                if ($data === date('Y-m-d')) {
                    $agora->add(new DateInterval('PT1H')); // Adicionar 1 hora
                }
                
                if ($data_hora > $agora) {
                    $horarios[] = $hora;
                }
            }
            
            $inicio += 1800; // Adicionar 30 minutos
        }
        
        return $horarios;
        
    } catch (Exception $e) {
        error_log("Erro ao buscar horários disponíveis: " . $e->getMessage());
        error_log("Barbeiro ID: $barbeiro_id, Data: $data");
        return [];
    }
}

    // Criar agendamento
    public function criarAgendamento($cliente_id, $barbeiro_id, $servico_id, $data, $hora, $observacoes = '') {
        try {
            // Validações básicas
            if (empty($cliente_id) || empty($barbeiro_id) || empty($servico_id) || empty($data) || empty($hora)) {
                return ['success' => false, 'message' => 'Dados incompletos.'];
            }
            
            // Verificar se a data não é no passado
            $data_agendamento = new DateTime($data . ' ' . $hora);
            $agora = new DateTime();
            if ($data_agendamento <= $agora) {
                return ['success' => false, 'message' => 'Não é possível agendar para data/hora no passado.'];
            }
            
            // Verificar se o horário ainda está disponível
            $horarios_disponiveis = $this->getHorariosDisponiveis($barbeiro_id, $data);
            if (!in_array($hora, $horarios_disponiveis)) {
                return ['success' => false, 'message' => 'Horário não disponível.'];
            }
            
            // Verificar se barbeiro e serviço existem
            $stmt = $this->db->prepare("SELECT id FROM barbeiros WHERE id = ? AND ativo = 1");
            $stmt->execute([$barbeiro_id]);
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Barbeiro não encontrado.'];
            }
            
            $stmt = $this->db->prepare("SELECT id FROM servicos WHERE id = ? AND ativo = 1");
            $stmt->execute([$servico_id]);
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Serviço não encontrado.'];
            }
            
            // Inserir agendamento
            $stmt = $this->db->prepare("
                INSERT INTO agendamentos (cliente_id, barbeiro_id, servico_id, data_agendamento, hora_agendamento, observacoes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$cliente_id, $barbeiro_id, $servico_id, $data, $hora, sanitizeInput($observacoes)])) {
                return ['success' => true, 'message' => 'Agendamento realizado com sucesso!', 'id' => $this->db->lastInsertId()];
            } else {
                return ['success' => false, 'message' => 'Erro ao criar agendamento.'];
            }
            
        } catch (Exception $e) {
            error_log("Erro ao criar agendamento: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    // Listar agendamentos do cliente - CORRIGIDO
    public function getAgendamentosCliente($cliente_id, $status = null) {
        try {
            $sql = "
                SELECT a.*, b.nome as barbeiro_nome, s.nome as servico_nome, s.preco, s.duracao
                FROM agendamentos a
                JOIN barbeiros b ON a.barbeiro_id = b.id
                JOIN servicos s ON a.servico_id = s.id
                WHERE a.cliente_id = ?
            ";
            
            $params = [$cliente_id];
            
            if ($status) {
                $sql .= " AND a.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY a.data_agendamento DESC, a.hora_agendamento DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Erro ao buscar agendamentos do cliente: " . $e->getMessage());
            return [];
        }
    }
    
    // Cancelar agendamento
    public function cancelarAgendamento($id, $cliente_id = null) {
        try {
            $sql = "UPDATE agendamentos SET status = 'cancelado' WHERE id = ?";
            $params = [$id];
            
            if ($cliente_id) {
                $sql .= " AND cliente_id = ?";
                $params[] = $cliente_id;
            }
            
            $stmt = $this->db->prepare($sql);
            
            if ($stmt->execute($params) && $stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Agendamento cancelado com sucesso.'];
            } else {
                return ['success' => false, 'message' => 'Agendamento não encontrado ou não pode ser cancelado.'];
            }
            
        } catch (Exception $e) {
            error_log("Erro ao cancelar agendamento: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    // Atualizar status do agendamento (para admin)
    public function atualizarStatus($id, $status) {
        try {
            $status_validos = ['agendado', 'confirmado', 'finalizado', 'cancelado'];
            if (!in_array($status, $status_validos)) {
                return ['success' => false, 'message' => 'Status inválido.'];
            }
            
            $stmt = $this->db->prepare("UPDATE agendamentos SET status = ? WHERE id = ?");
            
            if ($stmt->execute([$status, $id]) && $stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Status atualizado com sucesso.'];
            } else {
                return ['success' => false, 'message' => 'Agendamento não encontrado.'];
            }
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar status: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    // Buscar agendamento por ID
    public function getAgendamento($id, $cliente_id = null) {
        try {
            $sql = "
                SELECT a.*, c.nome as cliente_nome, c.telefone as cliente_telefone, c.email as cliente_email,
                       b.nome as barbeiro_nome, s.nome as servico_nome, s.preco, s.duracao, s.descricao as servico_descricao
                FROM agendamentos a
                JOIN clientes c ON a.cliente_id = c.id
                JOIN barbeiros b ON a.barbeiro_id = b.id
                JOIN servicos s ON a.servico_id = s.id
                WHERE a.id = ?
            ";
            
            $params = [$id];
            
            if ($cliente_id) {
                $sql .= " AND a.cliente_id = ?";
                $params[] = $cliente_id;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("Erro ao buscar agendamento: " . $e->getMessage());
            return false;
        }
    }
    
    // Métodos para o dashboard admin
    public function getProximosAgendamentos($limite = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT a.id, a.data_agendamento, a.hora_agendamento, a.status,
                       c.nome as cliente_nome, c.telefone as cliente_telefone,
                       b.nome as barbeiro_nome, s.nome as servico_nome, s.preco
                FROM agendamentos a
                JOIN clientes c ON a.cliente_id = c.id
                JOIN barbeiros b ON a.barbeiro_id = b.id
                JOIN servicos s ON a.servico_id = s.id
                WHERE a.data_agendamento >= CURDATE()
                AND a.status IN ('agendado', 'confirmado')
                ORDER BY a.data_agendamento ASC, a.hora_agendamento ASC
                LIMIT ?
            ");
            $stmt->execute([$limite]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao buscar próximos agendamentos: " . $e->getMessage());
            return [];
        }
    }
    
    public function getAgendamentosHoje() {
        try {
            $stmt = $this->db->prepare("
                SELECT a.id, a.hora_agendamento, a.status,
                       c.nome as cliente_nome, c.telefone as cliente_telefone,
                       b.nome as barbeiro_nome, s.nome as servico_nome, s.preco
                FROM agendamentos a
                JOIN clientes c ON a.cliente_id = c.id
                JOIN barbeiros b ON a.barbeiro_id = b.id
                JOIN servicos s ON a.servico_id = s.id
                WHERE a.data_agendamento = CURDATE()
                ORDER BY a.hora_agendamento ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao buscar agendamentos de hoje: " . $e->getMessage());
            return [];
        }
    }
    
    public function getFaturamentoMes($mes = null, $ano = null) {
        try {
            if (!$mes) $mes = date('m');
            if (!$ano) $ano = date('Y');
            
            $stmt = $this->db->prepare("
                SELECT SUM(s.preco) as total
                FROM agendamentos a
                JOIN servicos s ON a.servico_id = s.id
                WHERE MONTH(a.data_agendamento) = ? 
                AND YEAR(a.data_agendamento) = ?
                AND a.status = 'finalizado'
            ");
            $stmt->execute([$mes, $ano]);
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Erro ao buscar faturamento: " . $e->getMessage());
            return 0;
        }
    }
    
    // Estatísticas para dashboard admin
    public function getEstatisticas() {
        try {
            $stats = [];
            
            // Total de agendamentos hoje
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM agendamentos 
                WHERE data_agendamento = CURDATE()
            ");
            $stmt->execute();
            $stats['agendamentos_hoje'] = $stmt->fetch()['total'];
            
            // Total de agendamentos pendentes
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM agendamentos 
                WHERE status = 'agendado'
            ");
            $stmt->execute();
            $stats['agendamentos_pendentes'] = $stmt->fetch()['total'];
            
            // Total de clientes
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM clientes WHERE ativo = 1");
            $stmt->execute();
            $stats['total_clientes'] = $stmt->fetch()['total'];
            
            // Faturamento do mês
            $stats['faturamento_mes'] = $this->getFaturamentoMes();
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar estatísticas: " . $e->getMessage());
            return [];
        }
    }
}
?>
