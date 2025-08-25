<?php

require_once '../config/database.php';
require_once '../includes/auth.php';
 // conexão PDO
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Novo Agendamento - BarberShop</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; // se você tiver um menu fixo igual no dashboard ?>

    <div class="container mt-4">
        <h2 class="mb-4"><i class="fa fa-calendar-plus"></i> Novo Agendamento</h2>

        <form method="POST" action="appointment.php" class="card p-4 shadow-sm">
            <input type="hidden" name="action" value="criar">

            <!-- Barbeiro -->
            <div class="mb-3">
                <label for="barbeiro_id" class="form-label">Barbeiro</label>
                <select class="form-control" name="barbeiro_id" id="barbeiro_id" required>
                    <option value="">Selecione</option>
                    <?php
                    $stmt = $db->query("SELECT id, nome FROM barbeiros");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<option value='{$row['id']}'>{$row['nome']}</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- Serviço -->
            <div class="mb-3">
                <label for="servico_id" class="form-label">Serviço</label>
                <select class="form-control" name="servico_id" id="servico_id" required>
                    <option value="">Selecione</option>
                    <?php
                    $stmt = $db->query("SELECT id, nome, preco FROM servicos");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<option value='{$row['id']}'>{$row['nome']} - R$ {$row['preco']}</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- Data -->
            <div class="mb-3">
                <label for="data_agendamento" class="form-label">Data</label>
                <input type="date" class="form-control" name="data_agendamento" id="data_agendamento" required>
            </div>

            <!-- Hora -->
            <div class="mb-3">
                <label for="hora_agendamento" class="form-label">Hora</label>
                <input type="time" class="form-control" name="hora_agendamento" id="hora_agendamento" required>
            </div>

            <!-- Observações -->
            <div class="mb-3">
                <label for="observacoes" class="form-label">Observações</label>
                <textarea class="form-control" name="observacoes" id="observacoes"></textarea>
            </div>

            <!-- Botões -->
            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fa fa-arrow-left"></i> Voltar
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="fa fa-check"></i> Confirmar Agendamento
                </button>
            </div>
        </form>
    </div>
</body>
</html>