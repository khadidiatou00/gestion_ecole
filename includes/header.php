<?php

// Inclure le fichier de configuration principal qui démarre aussi la session.
// __DIR__ . '/../' remonte d'un dossier pour trouver le dossier 'config'.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php'; // Inclure les fonctions utilitaires

// Déterminer la feuille de style spécifique au rôle de l'utilisateur
$layout_css = '';
if (isset($_SESSION['role'])) {
    $current_path = $_SERVER['PHP_SELF'];
    if (strpos($current_path, '/admin/') !== false) {
        $layout_css = 'layouts/admin.css';
    } elseif (strpos($current_path, '/enseignant/') !== false) {
        $layout_css = 'layouts/enseignant.css';
    } elseif (strpos($current_path, '/etudiant/') !== false) {
        $layout_css = 'layouts/etudiant.css';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? escape($page_title) . ' - ' : '' ?><?= APP_NAME ?></title>

    <!-- Google Fonts: Poppins pour un look moderne -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- CSS Personnalisé -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/core/theme.css">
    <?php if ($layout_css): ?>
        <link rel="stylesheet" href="<?= ASSETS_URL ?>css/<?= $layout_css ?>">
    <?php endif; ?>

    <style>
        /* Style pour l'arrière-plan animé et le contenu */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #0c0a1a; /* Fond sombre pour faire ressortir les particules */
        }
        #tsparticles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1; /* Placer l'animation en arrière-plan */
        }
        .navbar-custom {
            background-color: rgba(12, 10, 26, 0.7); /* Navbar semi-transparente */
            backdrop-filter: blur(10px); /* Effet de verre dépoli */
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .content-wrapper {
            position: relative;
            z-index: 1; /* S'assurer que le contenu est au-dessus de l'animation */
            min-height: calc(100vh - 120px); /* Hauteur minimale pour pousser le footer en bas */
        }
    </style>
</head>
<body>

<!-- Conteneur pour l'animation de particules -->
<div id="tsparticles"></div>

<!-- Barre de navigation -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= SITE_URL ?>">
            <i class="fas fa-graduation-cap me-2"></i><?= APP_NAME ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (isLoggedIn()): ?>
                    <?php if (hasRole(ROLE_ADMIN)): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>admin/dashboard.php">Dashboard Admin</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>admin/personnel/etudiants.php">Étudiants</a></li>
                    <?php elseif (hasRole(ROLE_ENSEIGNANT)): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>enseignant/dashboard.php">Mon Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>enseignant/evaluation/notes.php">Saisir Notes</a></li>
                    <?php elseif (hasRole(ROLE_ETUDIANT)): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>etudiant/dashboard.php">Mon Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>etudiant/scolarite/bulletin.php">Mes Notes</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>etudiant/scolarite/emploi.php">Emploi du temps</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                 <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?= escape($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="#">Mon Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= SITE_URL ?>auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a></li>
                        </ul>
                    </li>
                 <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>auth/login.php">Connexion</a>
                    </li>
                 <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Conteneur principal du contenu -->
<div class="content-wrapper container-fluid p-4">
    <?php
    // Inclure le gestionnaire de notifications
    include_once __DIR__ . '/notifications.php';
    ?>