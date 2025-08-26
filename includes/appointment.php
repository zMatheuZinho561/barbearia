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
        try {
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM clientes WHERE ativo = 1");
            return $stmt->fetch()['total'];
        } catch (Exception $e) {
            return 0;
        }
    }

    public function getTotalBarbeiros() {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM barbeiros WHERE ativo = 1");
            return $stmt->fetch()['total'];
        } catch (Exception $e) {
            return 0;
        }
    }

    public function getTotalServicos() {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM servicos WHERE ativo = 1");
            return $stmt->fetch()['total'];
        } catch (Exception $e) {
            return 0;
        }
    }

    public function getTotalAgendamentos() {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM agendamentos");
            return $stmt->fetch()['total'];
        } catch (Exception $e) {
            return 0;
        }
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
    
    // Obter horários disponíveis de um barbeiro em uma data
    public function getHorariosDisponiveis($barbeiro_id, $data) {
        try {
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
                return []; // Barbeiro não trabalha neste dia
            }
            
            // Buscar agendamentos já marcados
            $stmt = $this->db->prepare("
                SELECT hora_agendamento, s.duracao
                FROM agendamentos a
                JOIN servicos s ON a.servico_id = s.id
                WHERE a.barbeiro_id = ? AND a.data_agendamento = ? 
                AND a.status IN ('agendado', 'confirmado')
            ");
            $stmt->execute([$barbeiro_id, $data]);
            $agendados = $stmt->fetchAll();
            
            // Gerar horários disponíveis (intervalos de 30 minutos)
            $horarios = [];
            $inicio = strtotime($funcionamento['hora_inicio']);
            $fim = strtotime($funcionamento['hora_fim']);
            
            while ($inicio < $fim) {
                $hora = date('H:i', $inicio);
                $disponivel = true;
                
                // Verificar se o horário conflita com agendamentos existentes
                foreach ($agendados as $agendado) {
                    $hora_agendada = strtotime($agendado['hora_agendamento']);
                    $fim_agendamento = $hora_agendada + ($agendado['duracao'] * 60);
                    
                    if ($inicio >= $hora_agendada && $inicio < $fim_agendamento) {
                        $disponivel = false;
                        break;
                    }
                }
                
                // Não permitir agendamento no passado
                $agora = new DateTime();
                $data_hora = new DateTime($data . ' ' . $hora);
                if ($data_hora <= $agora) {
                    $disponivel = false;
                }
                
                if ($disponivel) {
                    $horarios[] = $hora;
                }
                
                $inicio += 1800; // Adicionar 30 minutos
            }
            
            return $horarios;
        } catch (Exception $e) {
            error_log("Erro ao buscar horários: " . $e->getMessage());
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
                INSERT INTO agendamentos (cliente_id, barbeiro_id, servico_id, data_agendamento, hora_agendamento, observacoes, status, data_criacao)
                VALUES (?, ?, ?, ?, ?, ?, 'agendado', NOW())
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
    
    // CORRIGIDO: Listar agendamentos do cliente
    public function getAgendamentosCliente($cliente_id, $status = null) {
        try {
            $sql = "
                SELECT a.*, 
                       b.nome as barbeiro, 
                       s.nome as servico, 
                       s.preco as valor,
                       s.duracao,
                       DATE_FORMAT(CONCAT(a.data_agendamento, ' ', a.hora_agendamento), '%Y-%m-%d %H:%i:%s') as data_hora
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

    // NOVO: Listar todos os agendamentos (para admin)
    public function getAllAgendamentos($limit = null, $status = null) {
        try {
            $sql = "
                SELECT a.*, 
                       c.nome as cliente_nome,
                       c.telefone as cliente_telefone,
                       c.email as cliente_email,
                       b.nome as barbeiro_nome, 
                       s.nome as servico_nome, 
                       s.preco as valor,
                       s.duracao,
                       DATE_FORMAT(CONCAT(a.data_agendamento, ' ', a.hora_agendamento), '%Y-%m-%d %H:%i:%s') as data_hora_completa
                FROM agendamentos a
                JOIN clientes c ON a.cliente_id = c.id
                JOIN barbeiros b ON a.barbeiro_id = b.id
                JOIN servicos s ON a.servico_id = s.id
            ";
            
            $params = [];
            
            if ($status) {
                $sql .= " WHERE a.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY a.data_agendamento DESC, a.hora_agendamento DESC";
            
            if ($limit) {
                $sql .= " LIMIT ?";
                $params[] = $limit;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Erro ao buscar todos os agendamentos: " . $e->getMessage());
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
            $stmt = $this->db->prepare("
                SELECT SUM(s.preco) as total
                FROM agendamentos a
                JOIN servicos s ON a.servico_id = s.id
                WHERE MONTH(a.data_agendamento) = MONTH(CURDATE()) 
                AND YEAR(a.data_agendamento) = YEAR(CURDATE())
                AND a.status = 'finalizado'
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['faturamento_mes'] = $result['total'] ?? 0;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar estatísticas: " . $e->getMessage());
            return [];
        }
    }
}
?>