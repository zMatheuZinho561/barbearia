<?php
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Registrar cliente
    public function registerClient($nome, $telefone, $email, $senha) {
        try {
            // Validações
            if (empty($nome) || empty($telefone) || empty($email) || empty($senha)) {
                return ['success' => false, 'message' => 'Todos os campos são obrigatórios.'];
            }
            
            if (!validateEmail($email)) {
                return ['success' => false, 'message' => 'Email inválido.'];
            }
            
            if (!validatePhone($telefone)) {
                return ['success' => false, 'message' => 'Telefone inválido.'];
            }
            
            if (strlen($senha) < 6) {
                return ['success' => false, 'message' => 'A senha deve ter pelo menos 6 caracteres.'];
            }
            
            // Verificar se email já existe
            $stmt = $this->db->prepare("SELECT id FROM clientes WHERE email = ? OR telefone = ?");
            $stmt->execute([$email, $telefone]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Email ou telefone já cadastrado.'];
            }
            
            // Inserir cliente
            $senhaHash = hashPassword($senha);
            $stmt = $this->db->prepare("
                INSERT INTO clientes (nome, telefone, email, senha) 
                VALUES (?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                sanitizeInput($nome),
                sanitizeInput($telefone), 
                sanitizeInput($email), 
                $senhaHash
            ])) {
                return ['success' => true, 'message' => 'Cadastro realizado com sucesso!'];
            } else {
                return ['success' => false, 'message' => 'Erro ao realizar cadastro.'];
            }
            
        } catch (Exception $e) {
            error_log("Erro no registro: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    // Login cliente
    public function loginClient($email, $senha) {
        try {
            if (empty($email) || empty($senha)) {
                return ['success' => false, 'message' => 'Email e senha são obrigatórios.'];
            }
            
            $stmt = $this->db->prepare("
                SELECT id, nome, telefone, email, senha, ativo 
                FROM clientes 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Credenciais inválidas.'];
            }
            
            $cliente = $stmt->fetch();
            
            if (!$cliente['ativo']) {
                return ['success' => false, 'message' => 'Conta desativada.'];
            }
            
            if (verifyPassword($senha, $cliente['senha'])) {
                // Iniciar sessão
                if (session_status() === PHP_SESSION_NONE) {
                     session_start();
                    }
                $_SESSION['cliente_id'] = $cliente['id'];
                $_SESSION['cliente_nome'] = $cliente['nome'];
                $_SESSION['cliente_email'] = $cliente['email'];
                $_SESSION['cliente_telefone'] = $cliente['telefone'];
                
                return [
                    'success' => true, 
                    'message' => 'Login realizado com sucesso!',
                    'cliente' => [
                        'id' => $cliente['id'],
                        'nome' => $cliente['nome'],
                        'email' => $cliente['email'],
                        'telefone' => $cliente['telefone']
                    ]
                ];
            } else {
                return ['success' => false, 'message' => 'Credenciais inválidas.'];
            }
            
        } catch (Exception $e) {
            error_log("Erro no login: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    // Login administrador
    public function loginAdmin($email, $senha) {
        try {
            if (empty($email) || empty($senha)) {
                return ['success' => false, 'message' => 'Email e senha são obrigatórios.'];
            }
            
            $stmt = $this->db->prepare("
                SELECT id, nome, email, senha, nivel, ativo 
                FROM administradores 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Credenciais inválidas.'];
            }
            
            $admin = $stmt->fetch();
            
            if (!$admin['ativo']) {
                return ['success' => false, 'message' => 'Conta desativada.'];
            }
            
            if (verifyPassword($senha, $admin['senha'])) {
                // Iniciar sessão de admin
                    if (session_status() === PHP_SESSION_NONE) {
                     session_start();
                    }
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_nome'] = $admin['nome'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_nivel'] = $admin['nivel'];
                
                return [
                    'success' => true, 
                    'message' => 'Login administrativo realizado com sucesso!',
                    'admin' => [
                        'id' => $admin['id'],
                        'nome' => $admin['nome'],
                        'email' => $admin['email'],
                        'nivel' => $admin['nivel']
                    ]
                ];
            } else {
                return ['success' => false, 'message' => 'Credenciais inválidas.'];
            }
            
        } catch (Exception $e) {
            error_log("Erro no login admin: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    // Verificar se cliente está logado
    public function isClientLoggedIn() {
            if (session_status() === PHP_SESSION_NONE) {
                     session_start();
                    }
        return isset($_SESSION['cliente_id']);
    }
    
    // Verificar se admin está logado
    public function isAdminLoggedIn() {
            if (session_status() === PHP_SESSION_NONE) {
                     session_start();
                    }
        return isset($_SESSION['admin_id']);
    }
    
    // Logout cliente
    public function logoutClient() {
            if (session_status() === PHP_SESSION_NONE) {
                     session_start();
                    }
        unset($_SESSION['cliente_id']);
        unset($_SESSION['cliente_nome']);
        unset($_SESSION['cliente_email']);
        unset($_SESSION['cliente_telefone']);
        return true;
    }
    
    // Logout admin
    public function logoutAdmin() {
            if (session_status() === PHP_SESSION_NONE) {
                     session_start();
                    }
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_nome']);
        unset($_SESSION['admin_email']);
        unset($_SESSION['admin_nivel']);
        return true;
    }
    
    // Obter dados do cliente logado
    public function getClientData() {
            if (session_status() === PHP_SESSION_NONE) {
                     session_start();
                    }
        if (!$this->isClientLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['cliente_id'],
            'nome' => $_SESSION['cliente_nome'],
            'email' => $_SESSION['cliente_email'],
            'telefone' => $_SESSION['cliente_telefone']
        ];
    }
    
    // Obter dados do admin logado
    public function getAdminData() {
            if (session_status() === PHP_SESSION_NONE) {
                     session_start();
                    }
        if (!$this->isAdminLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['admin_id'],
            'nome' => $_SESSION['admin_nome'],
            'email' => $_SESSION['admin_email'],
            'nivel' => $_SESSION['admin_nivel']
        ];
    }
}
?>