<?php


// Démarrer la session si elle n'est pas déjà active
if (session_status() == PHP_SESSION_NONE) {
    // Utiliser un nom de session spécifique pour éviter les conflits
    session_name('GESTION_ECOLE_SESSID');
    session_start();
}

// --- Configuration de l'environnement ---

// Afficher toutes les erreurs pour le développement. À mettre sur 0 en production.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Définir le fuseau horaire pour que les fonctions de date/heure fonctionnent correctement
date_default_timezone_set('Europe/Paris');


// --- Définition des chemins et URL ---

// Protocole (http ou https)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";

// Nom du serveur (ex: localhost, www.mon-ecole.com)
$server_name = $_SERVER['SERVER_NAME'];

// Racine du projet sur le serveur (ex: /gestion_ecole/)
$root_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
// S'assurer que la racine finit par un slash, sauf si c'est la racine du domaine
$root_dir = ($root_dir == '/') ? '/' : $root_dir . '/';

// URL de base du site (ex: http://localhost/gestion_ecole/)
define('SITE_URL', $protocol . $server_name . $root_dir);

// Chemin absolu vers la racine du projet sur le disque dur
// dirname(__DIR__) pointe vers le dossier parent de 'config', soit 'gestion_ecole/'
define('ROOT_PATH', dirname(__DIR__)); 

// Définir les autres chemins importants en se basant sur ROOT_PATH
define('CONFIG_PATH',   ROOT_PATH . '/config/');
define('INCLUDES_PATH', ROOT_PATH . '/includes/');
define('ADMIN_PATH',    ROOT_PATH . '/admin/');
define('UPLOADS_PATH',  ROOT_PATH . '/uploads/');
define('ASSETS_URL',    SITE_URL . 'assets/');


// --- Constantes de l'application ---

define('APP_NAME', 'Système de Gestion d\'École');
define('APP_VERSION', '1.0.0');

// Rôles utilisateurs, pour éviter les erreurs de frappe dans le code
define('ROLE_ADMIN', 'admin');
define('ROLE_ENSEIGNANT', 'enseignant');
define('ROLE_ETUDIANT', 'etudiant');

?>