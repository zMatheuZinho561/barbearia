<?php
require_once '../config/database.php';

// Script para corrigir a senha do administrador

// Senha que funcionará
$nova_senha = "Admin123!";

// Gerar hash correto
$hash_correto = password_hash($nova_senha, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Atualizar a senha do admin
    $stmt = $db->prepare("UPDATE administradores SET senha = ? WHERE email = 'admin@barbearia.com'");
    
    if ($stmt->execute([$hash_correto])) {
        echo "<h2>✅ Senha do administrador atualizada com sucesso!</h2>";
        echo "<p><strong>Email:</strong> admin@barbearia.com</p>";
        echo "<p><strong>Nova Senha:</strong> Admin123!</p>";
        echo "<p><strong>Hash gerado:</strong> " . $hash_correto . "</p>";
        echo "<hr>";
        echo "<p>Agora você pode fazer login no sistema admin.</p>";
        echo "<a href='admin_login.php'>Ir para Login Admin</a>";
    } else {
        echo "<h2>❌ Erro ao atualizar senha</h2>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Erro de conexão: " . $e->getMessage() . "</h2>";
}

// Também vamos verificar se o usuário existe
try {
    $stmt = $db->prepare("SELECT * FROM administradores WHERE email = 'admin@barbearia.com'");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo "<hr><h3>🔧 Criando usuário administrador...</h3>";
        
        // Criar o usuário admin
        $stmt = $db->prepare("
            INSERT INTO administradores (nome, email, senha, nivel) 
            VALUES ('Administrador', 'admin@barbearia.com', ?, 'super_admin')
        ");
        
        if ($stmt->execute([$hash_correto])) {
            echo "<p>✅ Usuário administrador criado com sucesso!</p>";
        } else {
            echo "<p>❌ Erro ao criar usuário administrador</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro ao verificar usuário: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Atualização de Senha Admin</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h2 { color: #2c3e50; }
        .success { color: #27ae60; }
        .error { color: #e74c3c; }
    </style>
</head>
<body>
    <!-- O conteúdo PHP será exibido aqui -->
</body>
</html>