<?php
// S'assurer que la config est chargée pour avoir les constantes de Rôles
require_once __DIR__ . '/../../config/config.php';

/**
 * Vérifie si l'utilisateur connecté a l'un des rôles spécifiés.
 * @param string|array $roles Le ou les rôles à vérifier.
 * @return bool
 */
function hasRole($roles): bool {
    if (!isset($_SESSION['role'])) {
        return false;
    }

    if (is_array($roles)) {
        return in_array($_SESSION['role'], $roles);
    }

    return $_SESSION['role'] === $roles;
}

/**
 * Raccourci pour vérifier si l'utilisateur est un administrateur.
 * @return bool
 */
function isAdmin(): bool {
    return hasRole(ROLE_ADMIN);
}

/**
 * Raccourci pour vérifier si l'utilisateur est un enseignant.
 * @return bool
 */
function isEnseignant(): bool {
    return hasRole(ROLE_ENSEIGNANT);
}

/**
 * Raccourci pour vérifier si l'utilisateur est un étudiant.
 * @return bool
 */
function isEtudiant(): bool {
    return hasRole(ROLE_ETUDIANT);
}
?>