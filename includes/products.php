<?php
require_once __DIR__ . '/../config/database.php';

class Products {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    public function getTotalProdutos() {
    $stmt = $this->db->query("SELECT COUNT(*) as total FROM produtos");
    return $stmt->fetch()['total'];
}
    // Listar produtos ativos
    public function getProdutos($categoria = null, $destaque = false) {
        try {
            $sql = "
                SELECT id, nome, marca, descricao, preco, foto, categoria, destaque
                FROM produtos 
                WHERE ativo = 1
            ";
            
            $params = [];
            
            if ($categoria) {
                $sql .= " AND categoria = ?";
                $params[] = $categoria;
            }
            
            if ($destaque) {
                $sql .= " AND destaque = 1";
            }
            
            $sql .= " ORDER BY destaque DESC, nome ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao buscar produtos: " . $e->getMessage());
            return [];
        }
    }
    
    // Listar categorias
    public function getCategorias() {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT categoria 
                FROM produtos 
                WHERE ativo = 1 AND categoria IS NOT NULL
                ORDER BY categoria
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Erro ao buscar categorias: " . $e->getMessage());
            return [];
        }
    }
    
    // Buscar produto por ID
    public function getProduto($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, nome, marca, descricao, preco, foto, categoria, destaque
                FROM produtos 
                WHERE id = ? AND ativo = 1
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Erro ao buscar produto: " . $e->getMessage());
            return false;
        }
    }
    
    // Listar produtos em destaque
    public function getProdutosDestaque($limit = 6) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, nome, marca, descricao, preco, foto, categoria
                FROM produtos 
                WHERE ativo = 1 AND destaque = 1
                ORDER BY nome
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao buscar produtos em destaque: " . $e->getMessage());
            return [];
        }
    }
    
    // Para área administrativa - CRUD completo
    public function getAllProdutos() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, nome, marca, descricao, preco, foto, categoria, destaque, ativo, data_cadastro
                FROM produtos 
                ORDER BY data_cadastro DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao buscar todos os produtos: " . $e->getMessage());
            return [];
        }
    }
    
    // Adicionar produto (admin)
    public function adicionarProduto($nome, $marca, $descricao, $preco, $foto, $categoria, $destaque = 0) {
        try {
            if (empty($nome) || empty($preco)) {
                return ['success' => false, 'message' => 'Nome e preço são obrigatórios.'];
            }
            
            if (!is_numeric($preco) || $preco < 0) {
                return ['success' => false, 'message' => 'Preço inválido.'];
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO produtos (nome, marca, descricao, preco, foto, categoria, destaque)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                sanitizeInput($nome),
                sanitizeInput($marca),
                sanitizeInput($descricao),
                $preco,
                sanitizeInput($foto),
                sanitizeInput($categoria),
                $destaque ? 1 : 0
            ])) {
                return ['success' => true, 'message' => 'Produto adicionado com sucesso!', 'id' => $this->db->lastInsertId()];
            } else {
                return ['success' => false, 'message' => 'Erro ao adicionar produto.'];
            }
            
        } catch (Exception $e) {
            error_log("Erro ao adicionar produto: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    // Atualizar produto (admin)
    public function atualizarProduto($id, $nome, $marca, $descricao, $preco, $foto, $categoria, $destaque = 0) {
        try {
            if (empty($nome) || empty($preco)) {
                return ['success' => false, 'message' => 'Nome e preço são obrigatórios.'];
            }
            
            if (!is_numeric($preco) || $preco < 0) {
                return ['success' => false, 'message' => 'Preço inválido.'];
            }
            
            $stmt = $this->db->prepare("
                UPDATE produtos 
                SET nome = ?, marca = ?, descricao = ?, preco = ?, foto = ?, categoria = ?, destaque = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([
                sanitizeInput($nome),
                sanitizeInput($marca),
                sanitizeInput($descricao),
                $preco,
                sanitizeInput($foto),
                sanitizeInput($categoria),
                $destaque ? 1 : 0,
                $id
            ]) && $stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Produto atualizado com sucesso!'];
            } else {
                return ['success' => false, 'message' => 'Produto não encontrado ou nenhuma alteração feita.'];
            }
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar produto: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    // Desativar produto (admin)
    public function desativarProduto($id) {
        try {
            $stmt = $this->db->prepare("UPDATE produtos SET ativo = 0 WHERE id = ?");
            
            if ($stmt->execute([$id]) && $stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Produto desativado com sucesso.'];
            } else {
                return ['success' => false, 'message' => 'Produto não encontrado.'];
            }
            
        } catch (Exception $e) {
            error_log("Erro ao desativar produto: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    // Ativar produto (admin)
    public function ativarProduto($id) {
        try {
            $stmt = $this->db->prepare("UPDATE produtos SET ativo = 1 WHERE id = ?");
            
            if ($stmt->execute([$id]) && $stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Produto ativado com sucesso.'];
            } else {
                return ['success' => false, 'message' => 'Produto não encontrado.'];
            }
            
        } catch (Exception $e) {
            error_log("Erro ao ativar produto: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
}
?>