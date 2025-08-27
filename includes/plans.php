<?php
class Plans {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Buscar todos os planos ativos
    public function getPlanos($ativo_apenas = true) {
        try {
            $sql = "SELECT * FROM planos";
            if ($ativo_apenas) {
                $sql .= " WHERE ativo = 1";
            }
            $sql .= " ORDER BY preco ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao buscar planos: " . $e->getMessage());
            return [];
        }
    }
    
    // Buscar plano por ID
    public function getPlano($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM planos WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao buscar plano: " . $e->getMessage());
            return false;
        }
    }
    
    // Criar novo plano
    public function criarPlano($dados) {
        try {
            $sql = "INSERT INTO planos (nome, descricao, preco, duracao_dias, cortes_inclusos, 
                    desconto_produtos, desconto_servicos, beneficios, cor_badge) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $dados['nome'],
                $dados['descricao'],
                $dados['preco'],
                $dados['duracao_dias'] ?? 30,
                $dados['cortes_inclusos'],
                $dados['desconto_produtos'] ?? 0,
                $dados['desconto_servicos'] ?? 0,
                isset($dados['beneficios']) ? json_encode($dados['beneficios']) : '[]',
                $dados['cor_badge'] ?? 'primary'
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Plano criado com sucesso!', 'id' => $this->db->lastInsertId()];
            }
            return ['success' => false, 'message' => 'Erro ao criar plano.'];
        } catch (Exception $e) {
            error_log("Erro ao criar plano: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    // Atualizar plano
    public function atualizarPlano($id, $dados) {
        try {
            $sql = "UPDATE planos SET nome = ?, descricao = ?, preco = ?, duracao_dias = ?, 
                    cortes_inclusos = ?, desconto_produtos = ?, desconto_servicos = ?, 
                    beneficios = ?, cor_badge = ?, data_atualizacao = CURRENT_TIMESTAMP 
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $dados['nome'],
                $dados['descricao'],
                $dados['preco'],
                $dados['duracao_dias'] ?? 30,
                $dados['cortes_inclusos'],
                $dados['desconto_produtos'] ?? 0,
                $dados['desconto_servicos'] ?? 0,
                isset($dados['beneficios']) ? json_encode($dados['beneficios']) : '[]',
                $dados['cor_badge'] ?? 'primary',
                $id
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Plano atualizado com sucesso!'];
            }
            return ['success' => false, 'message' => 'Erro ao atualizar plano.'];
        } catch (Exception $e) {
            error_log("Erro ao atualizar plano: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    // Ativar/Desativar plano
    public function togglePlano($id, $ativo) {
        try {
            $stmt = $this->db->prepare("UPDATE planos SET ativo = ? WHERE id = ?");
            $result = $stmt->execute([$ativo ? 1 : 0, $id]);
            
            if ($result) {
                $status = $ativo ? 'ativado' : 'desativado';
                return ['success' => true, 'message' => "Plano {$status} com sucesso!"];
            }
            return ['success' => false, 'message' => 'Erro ao alterar status do plano.'];
        } catch (Exception $e) {
            error_log("Erro ao alterar status do plano: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    // Associar plano a cliente (ADMIN APENAS)
    public function associarPlanoCliente($cliente_id, $plano_id, $admin_id, $observacoes = '') {
        try {
            // Verificar se cliente já tem plano ativo
            $stmt = $this->db->prepare("
                SELECT id FROM cliente_planos 
                WHERE cliente_id = ? AND status = 'ativo'
            ");
            $stmt->execute([$cliente_id]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Cliente já possui um plano ativo.'];
            }
            
            // Buscar dados do plano
            $plano = $this->getPlano($plano_id);
            if (!$plano) {
                return ['success' => false, 'message' => 'Plano não encontrado.'];
            }
            
            $data_inicio = date('Y-m-d');
            $data_vencimento = date('Y-m-d', strtotime("+{$plano['duracao_dias']} days"));
            
            $sql = "INSERT INTO cliente_planos (cliente_id, plano_id, data_inicio, data_vencimento, 
                    valor_pago, observacoes, admin_id, data_pagamento) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $cliente_id,
                $plano_id,
                $data_inicio,
                $data_vencimento,
                $plano['preco'],
                $observacoes,
                $admin_id
            ]);
            
            if ($result) {
                return [
                    'success' => true, 
                    'message' => 'Plano associado com sucesso!',
                    'data_vencimento' => $data_vencimento
                ];
            }
            return ['success' => false, 'message' => 'Erro ao associar plano.'];
        } catch (Exception $e) {
            error_log("Erro ao associar plano: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    // Cancelar plano do cliente
    public function cancelarPlanoCliente($assinatura_id, $motivo, $admin_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE cliente_planos 
                SET status = 'cancelado', data_cancelamento = CURRENT_TIMESTAMP, 
                    motivo_cancelamento = ?, admin_id = ?
                WHERE id = ?
            ");
            $result = $stmt->execute([$motivo, $admin_id, $assinatura_id]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Plano cancelado com sucesso!'];
            }
            return ['success' => false, 'message' => 'Erro ao cancelar plano.'];
        } catch (Exception $e) {
            error_log("Erro ao cancelar plano: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    // Renovar plano
    public function renovarPlano($assinatura_id, $admin_id, $observacoes = '') {
        try {
            // Buscar dados da assinatura atual
            $stmt = $this->db->prepare("
                SELECT cp.*, p.duracao_dias, p.preco 
                FROM cliente_planos cp 
                JOIN planos p ON cp.plano_id = p.id 
                WHERE cp.id = ?
            ");
            $stmt->execute([$assinatura_id]);
            $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$assinatura) {
                return ['success' => false, 'message' => 'Assinatura não encontrada.'];
            }
            
            $nova_data_vencimento = date('Y-m-d', strtotime("+{$assinatura['duracao_dias']} days"));
            
            $stmt = $this->db->prepare("
                UPDATE cliente_planos 
                SET status = 'ativo', data_vencimento = ?, cortes_utilizados = 0,
                    data_pagamento = CURRENT_TIMESTAMP, admin_id = ?, observacoes = ?
                WHERE id = ?
            ");
            $result = $stmt->execute([$nova_data_vencimento, $admin_id, $observacoes, $assinatura_id]);
            
            if ($result) {
                return [
                    'success' => true, 
                    'message' => 'Plano renovado com sucesso!',
                    'nova_data_vencimento' => $nova_data_vencimento
                ];
            }
            return ['success' => false, 'message' => 'Erro ao renovar plano.'];
        } catch (Exception $e) {
            error_log("Erro ao renovar plano: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    // Buscar clientes com planos
    public function getClientesComPlanos($filtros = []) {
        try {
            $sql = "SELECT * FROM view_clientes_planos WHERE 1=1";
            $params = [];
            
            if (!empty($filtros['status'])) {
                $sql .= " AND plano_status = ?";
                $params[] = $filtros['status'];
            }
            
            if (!empty($filtros['plano_id'])) {
                $sql .= " AND plano_id = ?";
                $params[] = $filtros['plano_id'];
            }
            
            if (!empty($filtros['vencimento'])) {
                if ($filtros['vencimento'] === 'vencido') {
                    $sql .= " AND data_vencimento < CURDATE() AND plano_status = 'ativo'";
                } elseif ($filtros['vencimento'] === 'vence_breve') {
                    $sql .= " AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND plano_status = 'ativo'";
                }
            }
            
            $sql .= " ORDER BY data_vencimento ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao buscar clientes com planos: " . $e->getMessage());
            return [];
        }
    }
    
    // Buscar plano ativo do cliente
    public function getPlanoAtivoCliente($cliente_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT cp.*, p.nome as plano_nome, p.cortes_inclusos, p.desconto_produtos, 
                       p.desconto_servicos, p.beneficios, p.cor_badge
                FROM cliente_planos cp
                JOIN planos p ON cp.plano_id = p.id
                WHERE cp.cliente_id = ? AND cp.status = 'ativo'
                ORDER BY cp.data_vencimento DESC
                LIMIT 1
            ");
            $stmt->execute([$cliente_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao buscar plano ativo: " . $e->getMessage());
            return false;
        }
    }
    
    // Verificar se cliente pode fazer agendamento (tem cortes disponíveis)
    public function podeAgendar($cliente_id) {
        $plano = $this->getPlanoAtivoCliente($cliente_id);
        
        if (!$plano) {
            return ['pode' => true, 'motivo' => 'Sem plano ativo']; // Cliente sem plano pode agendar normalmente
        }
        
        if ($plano['status'] !== 'ativo') {
            return ['pode' => false, 'motivo' => 'Plano não está ativo'];
        }
        
        if ($plano['data_vencimento'] < date('Y-m-d')) {
            return ['pode' => false, 'motivo' => 'Plano vencido'];
        }
        
        // Se tem cortes ilimitados (0) pode agendar
        if ($plano['cortes_inclusos'] == 0) {
            return ['pode' => true, 'motivo' => 'Cortes ilimitados'];
        }
        
        // Verificar se ainda tem cortes disponíveis
        if ($plano['cortes_utilizados'] < $plano['cortes_inclusos']) {
            $restantes = $plano['cortes_inclusos'] - $plano['cortes_utilizados'];
            return ['pode' => true, 'motivo' => "Restam {$restantes} cortes"];
        }
        
        return ['pode' => false, 'motivo' => 'Cortes do plano esgotados'];
    }
    
    // Registrar uso de corte do plano
    public function usarCorte($cliente_id, $agendamento_id) {
        try {
            $plano = $this->getPlanoAtivoCliente($cliente_id);
            
            if (!$plano || $plano['cortes_inclusos'] == 0) {
                return true; // Sem plano ou cortes ilimitados
            }
            
            // Incrementar cortes utilizados
            $stmt = $this->db->prepare("
                UPDATE cliente_planos 
                SET cortes_utilizados = cortes_utilizados + 1 
                WHERE id = ?
            ");
            $stmt->execute([$plano['id']]);
            
            // Registrar no histórico
            $stmt = $this->db->prepare("
                INSERT INTO plano_utilizacao (cliente_plano_id, agendamento_id, tipo, descricao) 
                VALUES (?, ?, 'corte', 'Corte realizado')
            ");
            $stmt->execute([$plano['id'], $agendamento_id]);
            
            return true;
        } catch (Exception $e) {
            error_log("Erro ao usar corte do plano: " . $e->getMessage());
            return false;
        }
    }
    
    // Verificar vencimentos e retornar avisos
    public function verificarVencimentos() {
        try {
            $stmt = $this->db->query("CALL VerificarVencimentosPlanos()");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao verificar vencimentos: " . $e->getMessage());
            return [];
        }
    }
    
    // Estatísticas dos planos
    public function getEstatisticas() {
        try {
            $stats = [];
            
            // Total de assinaturas ativas
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM cliente_planos WHERE status = 'ativo'");
            $stats['assinaturas_ativas'] = $stmt->fetch()['total'];
            
            // Assinaturas que vencem em 7 dias
            $stmt = $this->db->query("
                SELECT COUNT(*) as total FROM cliente_planos 
                WHERE status = 'ativo' AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ");
            $stats['vencem_breve'] = $stmt->fetch()['total'];
            
            // Assinaturas vencidas
            $stmt = $this->db->query("
                SELECT COUNT(*) as total FROM cliente_planos 
                WHERE status = 'ativo' AND data_vencimento < CURDATE()
            ");
            $stats['vencidas'] = $stmt->fetch()['total'];
            
            // Receita mensal estimada
            $stmt = $this->db->query("
                SELECT SUM(p.preco) as total
                FROM cliente_planos cp
                JOIN planos p ON cp.plano_id = p.id
                WHERE cp.status = 'ativo'
            ");
            $stats['receita_mensal'] = $stmt->fetch()['total'] ?? 0;
            
            return $stats;
        } catch (Exception $e) {
            error_log("Erro ao buscar estatísticas: " . $e->getMessage());
            return [
                'assinaturas_ativas' => 0,
                'vencem_breve' => 0,
                'vencidas' => 0,
                'receita_mensal' => 0
            ];
        }
    }
}