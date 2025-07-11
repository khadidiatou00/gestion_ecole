<?php
/**
 * Échappe les chaînes de caractères pour prévenir les attaques XSS.
 * À utiliser à chaque fois que vous affichez une donnée provenant de la BDD ou d'un utilisateur.
 * @param string|null $html
 * @return string
 */
function escape(?string $html): string {
    return htmlspecialchars($html ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirige l'utilisateur vers une URL spécifiée et arrête le script.
 * @param string $url
 */
function redirect(string $url): void {
    header("Location: " . $url);
    exit();
}

/**
 * Définit une notification "flash" qui sera affichée sur la prochaine page.
 * @param string $message Le message à afficher.
 * @param string $type Le type d'alerte (success, danger, warning, info).
 */
function set_notification(string $message, string $type = 'success'): void {
    $_SESSION['notification'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Vérifie si l'utilisateur est connecté.
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Redirige un utilisateur non autorisé vers son propre tableau de bord.
 */
function redirectToDashboard(): void {
    if (!isLoggedIn()) {
        redirect(SITE_URL . 'auth/login.php');
    }

    $dashboard_url = SITE_URL . 'auth/login.php'; // Fallback
    switch ($_SESSION['role']) {
        case ROLE_ADMIN:
            $dashboard_url = SITE_URL . 'admin/dashboard.php';
            break;
        case ROLE_ENSEIGNANT:
            $dashboard_url = SITE_URL . 'enseignant/dashboard.php';
            break;
        case ROLE_ETUDIANT:
            $dashboard_url = SITE_URL . 'etudiant/dashboard.php';
            break;
    }
    redirect($dashboard_url);
}

/**
 * Charge la base de données de manière sécurisée.
 * @return PDO
 */
function getPdo(): PDO {
    // La variable $pdo est définie dans db.php
    // Pour l'utiliser dans une fonction, on doit l'importer dans le scope local.
    require __DIR__ . '/../config/db.php';
    return $pdo;
}



// Contenu existant de functions.php...

/**
 * Récupère l'ID de l'année scolaire actuellement active.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @return int|false L'ID de l'année scolaire ou false si aucune n'est active.
 */
function get_active_academic_year_id($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1");
        $stmt->execute();
        // fetchColumn() retourne la valeur de la première colonne ou false s'il n'y a pas de résultat
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        // En cas d'erreur, on peut logger l'erreur et retourner false
        error_log('Erreur dans get_active_academic_year_id: ' . $e->getMessage());
        return false;
    }
}

// ... autre fonctions existantes ou à venir
?>