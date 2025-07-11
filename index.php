<?php
/**
 * index.php
 *
 * Point d'entrée principal de l'application.
 * - Si l'utilisateur est connecté, le redirige vers son tableau de bord.
 * - Sinon, affiche une page d'accueil spectaculaire pour l'inviter à se connecter.
 */

// Charger les configurations et fonctions de base
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth/permissions.php';
// --- LOGIQUE DE REDIRECTION ---
// Si l'utilisateur est déjà connecté, on n'affiche pas cette page.
// On le redirige directement vers son espace personnel.
if (isLoggedIn()) {
    redirectToDashboard(); // Cette fonction est dans includes/functions.php
}

// Si on arrive ici, l'utilisateur n'est pas connecté.
// On prépare l'affichage de la page d'accueil.
$page_title = "Bienvenue sur la plateforme";

// On inclut le header qui contient déjà l'arrière-plan animé tsParticles
require_once 'includes/header.php';
?>

<!-- Style CSS Spécifique à la page d'accueil -->
<style>
    /* Variables de couleur pour un thème néon facile à modifier */
    :root {
        --glow-color-1: #00ffff; /* Cyan */
        --glow-color-2: #9c27b0; /* Violet */
        --glow-color-3: #ff00ff; /* Magenta */
    }

    /* Effet de fond "Aurora" animé */
    body::before {
        content: '';
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: radial-gradient(circle at 15% 25%, var(--glow-color-1), transparent 40%),
                    radial-gradient(circle at 85% 75%, var(--glow-color-2), transparent 40%),
                    radial-gradient(circle at 50% 50%, var(--glow-color-3), transparent 50%);
        z-index: -2; /* Derrière les particules et le contenu */
        animation: aurora-flow 20s infinite linear;
        opacity: 0.5;
    }

    @keyframes aurora-flow {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Style de la section "Héros" */
    .hero-section {
        color: #fff;
        text-align: center;
        padding: 10rem 0;
        text-shadow: 0 0 15px rgba(0,0,0,0.7);
    }
    .hero-section h1 {
        font-size: 4.5rem;
        font-weight: 700;
        letter-spacing: 2px;
    }
    .hero-section p {
        font-size: 1.5rem;
        font-weight: 300;
        max-width: 800px;
        margin: 1rem auto 2rem;
    }
    .btn-glow {
        background: linear-gradient(45deg, var(--glow-color-2), var(--glow-color-1));
        border: none;
        color: #fff;
        padding: 15px 35px;
        font-size: 1.2rem;
        font-weight: 600;
        border-radius: 50px;
        transition: all 0.3s ease;
        box-shadow: 0 0 20px var(--glow-color-1), 0 0 30px var(--glow-color-2);
        position: relative;
        overflow: hidden;
    }
    .btn-glow:hover {
        transform: translateY(-5px);
        box-shadow: 0 0 30px var(--glow-color-1), 0 0 45px var(--glow-color-2);
    }

    /* Style des cartes "Glassmorphism" avec lueur */
    .feature-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px); /* Pour Safari */
        border-radius: 20px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        padding: 2rem;
        text-align: center;
        color: #fff;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .feature-card::before {
        content: '';
        position: absolute;
        top: -50%; left: -50%;
        width: 200%; height: 200%;
        background: conic-gradient(
            transparent,
            rgba(0, 255, 255, 0.5), /* Cyan */
            transparent 30%
        );
        animation: rotate 4s linear infinite;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .feature-card:hover::before {
        opacity: 1;
    }
    @keyframes rotate {
        100% { transform: rotate(360deg); }
    }
    .feature-card-content {
        position: relative; /* Pour être au-dessus du pseudo-élément */
        z-index: 2;
    }
    .feature-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 0 40px rgba(0, 255, 255, 0.3);
    }
    .feature-card .icon {
        font-size: 3.5rem;
        margin-bottom: 1.5rem;
        color: var(--glow-color-1);
        text-shadow: 0 0 15px var(--glow-color-1);
    }
    .feature-card h3 {
        color: #fff;
        margin-bottom: 1rem;
    }
    .feature-card p {
        color: rgba(255, 255, 255, 0.8);
    }

    /* Slider / Carousel */
    .carousel-section {
        padding: 5rem 0;
    }
    .carousel-item {
        height: 500px;
        background-size: cover;
        background-position: center;
        border-radius: 20px;
    }
    .carousel-caption {
        background: rgba(0, 0, 0, 0.6);
        border-radius: 15px;
        padding: 1.5rem;
    }
    .carousel-indicators [data-bs-target] {
        background-color: var(--glow-color-1);
    }
</style>

<div class="container-fluid">

    <!-- Section Héros -->
    <section class="hero-section">
        <h1 data-text="<?= escape(APP_NAME) ?>"><?= escape(APP_NAME) ?></h1>
        <p>Votre portail vers une éducation connectée, intuitive et performante. <br>Gérez tout, du bout des doigts.</p>
        <a href="<?= SITE_URL ?>auth/login.php" class="btn btn-lg btn-glow">
            <i class="fas fa-rocket me-2"></i> Accéder à mon Espace
        </a>
    </section>

    <!-- Section des Cartes de Fonctionnalités -->
    <section class="features-section my-5">
        <div class="row g-4">
            <!-- Carte Espace Étudiant -->
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-card-content">
                        <div class="icon"><i class="fas fa-user-graduate"></i></div>
                        <h3>Espace Étudiant</h3>
                        <p>Consultez vos notes, votre emploi du temps et accédez à vos cours en un seul clic. Restez toujours informé.</p>
                    </div>
                </div>
            </div>
            <!-- Carte Espace Enseignant -->
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-card-content">
                        <div class="icon"><i class="fas fa-chalkboard-teacher"></i></div>
                        <h3>Espace Enseignant</h3>
                        <p>Gérez vos classes, saisissez les notes, partagez des ressources et communiquez facilement avec vos élèves.</p>
                    </div>
                </div>
            </div>
            <!-- Carte Espace Administration -->
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-card-content">
                        <div class="icon"><i class="fas fa-user-shield"></i></div>
                        <h3>Espace Administration</h3>
                        <p>Pilotez l'ensemble de l'établissement, gérez le personnel, les inscriptions et analysez les statistiques clés.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Slider / Carousel -->
    <section class="carousel-section my-5">
        <h2 class="text-center text-white mb-5 display-5">Une Plateforme Complète</h2>
        <div id="featureCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#featureCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#featureCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#featureCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
            </div>
            <div class="carousel-inner">
                <div class="carousel-item active" style="background-image: url('https://images.unsplash.com/photo-1554189097-0d58220f048d?q=80&w=1887&auto=format&fit=crop');">
                    <div class="carousel-caption d-none d-md-block">
                        <h5>Suivi des Notes en Temps Réel</h5>
                        <p>Les bulletins sont générés automatiquement, accessibles à tout moment par les étudiants et les administrateurs.</p>
                    </div>
                </div>
                <div class="carousel-item" style="background-image: url('https://images.unsplash.com/photo-1519389950473-47ba0277781c?q=80&w=2070&auto=format&fit=crop');">
                    <div class="carousel-caption d-none d-md-block">
                        <h5>Ressources Pédagogiques Centralisées</h5>
                        <p>Les enseignants partagent cours, devoirs et liens utiles directement sur la plateforme.</p>
                    </div>
                </div>
                <div class="carousel-item" style="background-image: url('https://images.unsplash.com/photo-1521791136064-7986c2920216?q=80&w=2070&auto=format&fit=crop');">
                    <div class="carousel-caption d-none d-md-block">
                        <h5>Communication Simplifiée</h5>
                        <p>Une messagerie interne sécurisée pour des échanges fluides entre tous les acteurs de l'école.</p>
                    </div>
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#featureCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#featureCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </section>

</div>

<?php
// On inclut le footer qui contient les scripts JS pour Bootstrap et l'animation
require_once 'includes/footer.php';
?>