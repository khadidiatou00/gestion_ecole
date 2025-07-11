<?php
session_start();
require_once '../config/db.php';

// --- SÉCURITÉ & RÉCUPÉRATION DES DONNÉES ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'etudiant') {
    header('Location: ../auth/login.php');
    exit();
}

$etudiant_id = $_SESSION['user_id'];

// Marquer une notification comme lue
if (isset($_GET['mark_as_read'])) {
    $notif_id = $_GET['mark_as_read'];
    $stmt = $pdo->prepare("UPDATE notifications SET statut = 'lu' WHERE id = ? AND utilisateur_id = ?");
    $stmt->execute([$notif_id, $etudiant_id]);
    header('Location: notifications.php'); // Rediriger pour enlever le paramètre de l'URL
    exit();
}

// Marquer toutes les notifications comme lues
if (isset($_GET['mark_all_as_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET statut = 'lu' WHERE utilisateur_id = ?");
    $stmt->execute([$etudiant_id]);
    header('Location: notifications.php');
    exit();
}

try {
    // Récupérer toutes les notifications de l'étudiant, les plus récentes en premier
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE utilisateur_id = ? ORDER BY date_creation DESC");
    $stmt->execute([$etudiant_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

// Fonctions pour le style
function getNotificationInfo($type) {
    switch ($type) {
        case 'note': return ['icon' => 'fa-star', 'color' => '#ffc107'];
        case 'devoir': return ['icon' => 'fa-tasks', 'color' => '#0dcaf0'];
        case 'annonce': return ['icon' => 'fa-bullhorn', 'color' => '#6f42c1'];
        case 'absence': return ['icon' => 'fa-user-clock', 'color' => '#dc3545'];
        case 'message': return ['icon' => 'fa-envelope', 'color' => '#0d6efd'];
        default: return ['icon' => 'fa-info-circle', 'color' => '#6c757d'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Notifications - GestiSchool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6c5ce7; --light-bg: #f8f9fa; --white: #fff;
            --border-color: #e9ecef; --text-muted: #6c757d;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--light-bg); }
        #main-content { margin-left: 280px; padding: 30px; }
        .notification-list { list-style: none; padding: 0; }
        .notification-item {
            display: flex; align-items: flex-start;
            background-color: var(--white);
            border: 1px solid var(--border-color);
            border-left-width: 5px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .notification-item:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.07); }
        .notification-item.non-lu { background-color: #f0f8ff; font-weight: 500; }
        .notification-icon {
            width: 45px; height: 45px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--white);
            flex-shrink: 0;
            margin-right: 1.25rem;
        }
        .notification-content { flex-grow: 1; }
        .notification-content .message { margin: 0; }
        .notification-content .timestamp { font-size: 0.8rem; color: var(--text-muted); }
        .notification-actions { margin-left: 1rem; }
        .empty-state { text-align: center; padding: 4rem; background-color: var(--white); border-radius: 15px; }
        .empty-state i { font-size: 4rem; color: #dee2e6; margin-bottom: 1rem; }
        @media (max-width: 992px) { #main-content { margin-left: 0; } }
    </style>
</head>
<body>
     <!-- (Inclure votre sidebar ici, avec le lien "Notifications" actif) -->
    <main id="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
             <h2 class="mb-0 fw-bold">Notifications</h2>
             <a href="?mark_all_as_read=1" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-check-double me-1"></i> Tout marquer comme lu
             </a>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h4 class="fw-bold">Aucune notification</h4>
                <p class="text-muted">Vous n'avez aucune nouvelle notification pour le moment.</p>
            </div>
        <?php else: ?>
            <ul class="notification-list">
                <?php foreach ($notifications as $notif): 
                    $info = getNotificationInfo($notif['type']);
                    $is_read = $notif['statut'] === 'lu';
                ?>
                <li class="notification-item <?= $is_read ? '' : 'non-lu' ?>" style="border-left-color: <?= $info['color'] ?>;"
                    onclick="window.location.href='<?= !empty($notif['lien']) ? $notif['lien'] : '#' ?>'">
                    <div class="notification-icon" style="background-color: <?= $info['color'] ?>;">
                        <i class="fas <?= $info['icon'] ?>"></i>
                    </div>
                    <div class="notification-content">
                        <p class="message"><?= htmlspecialchars($notif['message']) ?></p>
                        <small class="timestamp">
                            <i class="far fa-clock"></i> <?= date('d/m/Y à H:i', strtotime($notif['date_creation'])) ?>
                        </small>
                    </div>
                    <?php if (!$is_read): ?>
                    <div class="notification-actions">
                         <a href="?mark_as_read=<?= $notif['id'] ?>" title="Marquer comme lu" 
                            class="btn btn-sm btn-light">
                            <i class="fas fa-check"></i>
                         </a>
                    </div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </main>
</body>
</html>