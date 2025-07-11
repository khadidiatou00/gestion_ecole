<?php

// S'assurer que la config et les fonctions sont chargées
// __DIR__ est le dossier courant (auth), on remonte de 2 niveaux
require_once __DIR__ . '/../../config/config.php'; 
require_once __DIR__ . '/../../config/routes.php'; // Contient le tableau $permissions
require_once __DIR__ . '/../functions.php';

// Le tableau $permissions vient de routes.php
global $permissions;

// Obtenir le chemin du script actuel, relatif à la racine du site
$current_page = str_replace(dirname($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
if ($current_page === '/index.php' && $_SERVER['SCRIPT_NAME'] !== '/index.php') {
   $current_page = $_SERVER['SCRIPT_NAME'];
}

// Vérifier si la page est définie dans nos routes. Sinon, accès refusé.
if (!isset($permissions[$current_page])) {
    // Page non trouvée dans la configuration des routes, on considère l'accès comme interdit.
    http_response_code(404);
    echo "<h1>404 Not Found</h1><p>La page demandée n'existe pas ou n'est pas configurée.</p>";
    exit();
}

$allowed_roles = $permissions[$current_page];

// Si la page est publique, on n'a rien à faire.
if (in_array('public', $allowed_roles)) {
    return; // On autorise l'accès et on continue l'exécution de la page.
}

// Si on arrive ici, la page est protégée. On vérifie si l'utilisateur est connecté.
if (!isLoggedIn()) {
    set_notification("Vous devez être connecté pour accéder à cette page.", "warning");
    redirect(SITE_URL . 'auth/login.php');
}

// L'utilisateur est connecté, on vérifie maintenant s'il a le bon rôle.
$user_role = $_SESSION['role'];
if (!in_array($user_role, $allowed_roles)) {
    // L'utilisateur est connecté mais n'a pas la permission pour cette page.
    set_notification("Accès refusé. Vous n'avez pas les permissions nécessaires.", "danger");
    redirectToDashboard(); // On le renvoie à son tableau de bord.
}

// Si toutes les vérifications passent, le script de la page peut continuer.
?>