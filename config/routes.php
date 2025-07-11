<?php

// Inclure les constantes de rôles si ce n'est pas déjà fait
if (!defined('ROLE_ADMIN')) {
    require_once __DIR__ . '/config.php';
}

$permissions = [
    // --- Pages publiques et d'authentification ---
    '/index.php'                  => ['public'],
    '/auth/login.php'             => ['public'],
    '/auth/password_reset.php'    => ['public'],
    '/auth/logout.php'            => [ROLE_ADMIN, ROLE_ENSEIGNANT, ROLE_ETUDIANT], // Accessible à tous les connectés

    // --- Espace Administration ---
    // L'administrateur a accès à tout
    '/admin/dashboard.php'                 => [ROLE_ADMIN],
    '/admin/gestion/classes.php'           => [ROLE_ADMIN],
    '/admin/gestion/matieres.php'          => [ROLE_ADMIN],
    '/admin/gestion/salles.php'            => [ROLE_ADMIN],
    '/admin/gestion/annees.php'            => [ROLE_ADMIN],
    '/admin/personnel/enseignants.php'     => [ROLE_ADMIN],
    '/admin/personnel/etudiants.php'       => [ROLE_ADMIN],
    '/admin/pedagogie/emploi_temps.php'    => [ROLE_ADMIN],
    '/admin/pedagogie/affectations.php'    => [ROLE_ADMIN],
    '/admin/pedagogie/programmes.php'      => [ROLE_ADMIN],
    '/admin/evaluation/notes.php'          => [ROLE_ADMIN],
    '/admin/evaluation/bulletins.php'      => [ROLE_ADMIN],
    '/admin/evaluation/deliberations.php'  => [ROLE_ADMIN],
    '/admin/surveillance/absences.php'     => [ROLE_ADMIN],
    '/admin/surveillance/sanctions.php'    => [ROLE_ADMIN],
    '/admin/surveillance/statistiques.php' => [ROLE_ADMIN],
    '/auth/register.php'                   => [ROLE_ADMIN], // La création de compte est réservée à l'admin

    // --- Espace Enseignant ---
    // Les admins peuvent aussi accéder aux pages des enseignants pour supervision
    '/enseignant/dashboard.php'              => [ROLE_ADMIN, ROLE_ENSEIGNANT],
    '/enseignant/pedagogie/cours.php'        => [ROLE_ADMIN, ROLE_ENSEIGNANT],
    '/enseignant/pedagogie/ressources.php'   => [ROLE_ADMIN, ROLE_ENSEIGNANT],
    '/enseignant/pedagogie/projets.php'      => [ROLE_ADMIN, ROLE_ENSEIGNANT],
    '/enseignant/evaluation/notes.php'       => [ROLE_ADMIN, ROLE_ENSEIGNANT],
    '/enseignant/evaluation/competences.php' => [ROLE_ADMIN, ROLE_ENSEIGNANT],
    '/enseignant/presence/absences.php'      => [ROLE_ADMIN, ROLE_ENSEIGNANT],
    '/enseignant/presence/retard.php'        => [ROLE_ADMIN, ROLE_ENSEIGNANT],
    '/enseignant/communication/messagerie.php' => [ROLE_ADMIN, ROLE_ENSEIGNANT],
    '/enseignant/communication/annonces.php' => [ROLE_ADMIN, ROLE_ENSEIGNANT],

    // --- Espace Étudiant ---
    // Les admins peuvent aussi accéder aux pages des étudiants
    '/etudiant/dashboard.php'          => [ROLE_ADMIN, ROLE_ETUDIANT],
    '/etudiant/scolarite/bulletin.php' => [ROLE_ADMIN, ROLE_ETUDIANT],
    '/etudiant/scolarite/emploi.php'   => [ROLE_ADMIN, ROLE_ETUDIANT],
    '/etudiant/scolarite/cahier_texte.php' => [ROLE_ADMIN, ROLE_ETUDIANT],
    '/etudiant/ressources/cours.php'   => [ROLE_ADMIN, ROLE_ETUDIANT],
    '/etudiant/ressources/bibliotheque.php' => [ROLE_ADMIN, ROLE_ETUDIANT],
    '/etudiant/vie_scolaire/absences.php' => [ROLE_ADMIN, ROLE_ETUDIANT],
    '/etudiant/vie_scolaire/activites.php' => [ROLE_ADMIN, ROLE_ETUDIANT],

    // --- API Endpoints ---
    // Les permissions de l'API peuvent être plus complexes (ex: un étudiant ne peut voir que ses propres notes)
    // mais pour une vérification de base :
    '/api/v1/notes.php'      => [ROLE_ADMIN, ROLE_ENSEIGNANT, ROLE_ETUDIANT],
    '/api/v1/absences.php'   => [ROLE_ADMIN, ROLE_ENSEIGNANT, ROLE_ETUDIANT],
    '/api/v1/emploi.php'     => [ROLE_ADMIN, ROLE_ENSEIGNANT, ROLE_ETUDIANT],
];
?>