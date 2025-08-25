<?php
$senha = "Admin@2024!"; // senha forte
$hash = password_hash($senha, PASSWORD_BCRYPT);
echo $hash;