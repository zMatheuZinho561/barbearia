<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/appointment.php';
require_once 'includes/products.php';

$auth = new Auth();
$appointment = new Appointment();
$products = new Products();

// Verificar se cliente está logado
$cliente_logado = $auth->isClientLoggedIn();
$cliente_data = null;
if ($cliente_logado) {
    $cliente_data = $auth->getClientData();
}

// Buscar dados para exibição
$barbeiros = $appointment->getBarbeiros();
$servicos = $appointment->getServicos();
$produtos_destaque = $products->getProdutosDestaque(6);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BarberShop - Sua Barbearia de Confiança</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./style/style_cliente/st_index.css">
</head>
<body>
    <!-- Navbar Melhorado -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-cut"></i> BarberShop
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">
                            <i class="fas fa-home me-1"></i> Início
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#servicos">
                            <i class="fas fa-cut me-1"></i> Serviços
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#barbeiros">
                            <i class="fas fa-users me-1"></i> Barbeiros
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#produtos">
                            <i class="fas fa-shopping-bag me-1"></i> Produtos
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if ($cliente_logado): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($cliente_data['nome']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="cliente/agenda.php">
                                        <i class="fas fa-calendar-alt"></i> Meus Agendamentos
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="cliente/agendar.php">
                                        <i class="fas fa-calendar-plus"></i> Novo Agendamento
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="cliente/perfil.php">
                                        <i class="fas fa-user-cog"></i> Perfil
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="cliente/logout.php">
                                        <i class="fas fa-sign-out-alt"></i> Sair
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="cliente/login.php">
                                <i class="fas fa-sign-in-alt me-1"></i> Entrar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cliente/register.php">
                                <i class="fas fa-user-plus me-1"></i> Cadastrar
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Bem-vindo à BarberShop</h1>
            <p class="lead mb-5">O melhor cuidado para seu visual está aqui. Profissionais experientes e ambiente acolhedor.</p>
            <?php if ($cliente_logado): ?>
                <a href="cliente/agendar.php" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-calendar-plus"></i> Agendar Horário
                </a>
                <a href="cliente/agenda.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-calendar-alt"></i> Meus Agendamentos
                </a>
            <?php else: ?>
                <a href="cliente/register.php" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-user-plus"></i> Cadastre-se
                </a>
                <a href="cliente/login.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Serviços -->
    <section id="servicos" class="py-5">
        <div class="container">
            <h2 class="section-title text-center">Nossos Serviços</h2>
            <div class="row">
                <?php foreach ($servicos as $servico): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-cut fa-3x text-primary"></i>
                                </div>
                                <h5 class="card-title"><?= htmlspecialchars($servico['nome']) ?></h5>
                                <p class="card-text"><?= htmlspecialchars($servico['descricao']) ?></p>
                                <div class="price">R$ <?= number_format($servico['preco'], 2, ',', '.') ?></div>
                                <small class="text-muted"><?= $servico['duracao'] ?> minutos</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Barbeiros -->
    <section id="barbeiros" class="py-5 bg-light">
        <div class="container">
            <h2 class="section-title text-center">Nossos Profissionais</h2>
            <div class="row">
                <?php foreach ($barbeiros as $barbeiro): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card barbeiro-card h-100">
                            <?php if ($barbeiro['foto']): ?>
                                <img src="uploads/barbeiros/<?= htmlspecialchars($barbeiro['foto']) ?>" class="card-img-top" alt="<?= htmlspecialchars($barbeiro['nome']) ?>">
                            <?php else: ?>
                                <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 200px;">
                                    <i class="fas fa-user fa-4x text-white"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body text-center">
                                <h5 class="card-title"><?= htmlspecialchars($barbeiro['nome']) ?></h5>
                                <p class="card-text"><?= htmlspecialchars($barbeiro['especialidade']) ?></p>
                                <?php if ($barbeiro['telefone']): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($barbeiro['telefone']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Produtos -->
    <?php if (!empty($produtos_destaque)): ?>
    <section id="produtos" class="py-5">
        <div class="container">
            <h2 class="section-title text-center">Produtos em Destaque</h2>
            <div class="row">
                <?php foreach ($produtos_destaque as $produto): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100">
                            <?php if ($produto['foto']): ?>
                                <img src="uploads/produtos/<?= htmlspecialchars($produto['foto']) ?>" class="card-img-top" alt="<?= htmlspecialchars($produto['nome']) ?>" style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                    <i class="fas fa-box fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($produto['nome']) ?></h5>
                                <?php if ($produto['marca']): ?>
                                    <p class="card-text"><small class="text-muted"><?= htmlspecialchars($produto['marca']) ?></small></p>
                                <?php endif; ?>
                                <p class="card-text"><?= htmlspecialchars($produto['descricao']) ?></p>
                                <div class="price">R$ <?= number_format($produto['preco'], 2, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="produtos.php" class="btn btn-primary">Ver Todos os Produtos</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5><i class="fas fa-cut"></i> BarberShop</h5>
                    <p>Sua barbearia de confiança. Cuidamos do seu visual com profissionalismo e qualidade.</p>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5>Contato</h5>
                    <p><i class="fas fa-phone"></i> (11) 99999-9999</p>
                    <p><i class="fas fa-envelope"></i> contato@barbearia.com</p>
                    <p><i class="fas fa-map-marker-alt"></i> Rua da Barbearia, 123 - São Paulo</p>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5>Horários</h5>
                    <p>Segunda a Sexta: 8h às 18h</p>
                    <p>Sábado: 8h às 14h</p>
                    <p>Domingo: Fechado</p>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; <?= date('Y') ?> BarberShop. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Botão flutuante para agendamento -->
    <?php if ($cliente_logado): ?>
        <a href="cliente/agendar.php" class="btn btn-primary floating-btn">
            <i class="fas fa-calendar-plus"></i> Agendar
        </a>
    <?php endif; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
 document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    
                    // Fechar menu mobile após clique
                    const navbarCollapse = document.querySelector('.navbar-collapse');
                    if (navbarCollapse.classList.contains('show')) {
                        const bsCollapse = new bootstrap.Collapse(navbarCollapse);
                        bsCollapse.hide();
                    }
                }
            });
        });

        // Adicionar classe active baseada na seção visível
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.navbar-nav .nav-link[href^="#"]');
            
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop - 100;
                const sectionHeight = section.offsetHeight;
                if (scrollY >= sectionTop && scrollY < sectionTop + sectionHeight) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${current}`) {
                    link.classList.add('active');
                }
            });
        });

        // Animação do botão flutuante
        window.addEventListener('scroll', function() {
            const floatingBtn = document.querySelector('.floating-btn');
            if (floatingBtn) {
                if (window.scrollY > 300) {
                    floatingBtn.style.opacity = '1';
                    floatingBtn.style.transform = 'translateY(0)';
                } else {
                    floatingBtn.style.opacity = '0';
                    floatingBtn.style.transform = 'translateY(20px)';
                }
            }
        });
    </script>
</body>
</html>