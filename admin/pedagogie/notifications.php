<?php
session_start();
require_once '../../config/db.php';

// --- SÉCURITÉ ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

$error_message = '';
$success_message = '';

// --- LOGIQUE CRUD (CREATE, READ, DELETE) ---
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

// --- CRÉER une Notification ---
if ($action === 'send_notification') {
    $message = trim($_POST['message']);
    $type = $_POST['type'];
    $lien = trim($_POST['lien']) ?: null;
    $cible = $_POST['cible']; // ex: 'tous', 'classe', 'etudiant_specifique'
    $cible_id = $_POST['cible_id'] ?? null; // ID de la classe ou de l'étudiant

    if (empty($message) || empty($type) || empty($cible)) {
        $error_message = "Message, type et cible sont obligatoires.";
    } else {
        try {
            $user_ids = [];
            // Déterminer les ID des destinataires en fonction de la cible
            switch ($cible) {
                case 'tous':
                    $stmt = $pdo->query("SELECT id FROM utilisateurs");
                    $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    break;
                case 'tous_etudiants':
                    $stmt = $pdo->query("SELECT id FROM utilisateurs WHERE role = 'etudiant'");
                    $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    break;
                case 'tous_enseignants':
                    $stmt = $pdo->query("SELECT id FROM utilisateurs WHERE role = 'enseignant'");
                    $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    break;
                case 'classe':
                    if (!empty($cible_id)) {
                        $stmt = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE classe_id = ? AND statut = 'actif'");
                        $stmt->execute([$cible_id]);
                        $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    }
                    break;
                case 'etudiant_specifique':
                    if (!empty($cible_id)) {
                        $user_ids[] = $cible_id;
                    }
                    break;
            }

            if (!empty($user_ids)) {
                $pdo->beginTransaction();
                $stmt_insert = $pdo->prepare("INSERT INTO notifications (utilisateur_id, type, message, lien) VALUES (?, ?, ?, ?)");
                foreach ($user_ids as $user_id) {
                    $stmt_insert->execute([$user_id, $type, $message, $lien]);
                }
                $pdo->commit();
                $success_message = "Notification envoyée avec succès à " . count($user_ids) . " utilisateur(s).";
            } else {
                $error_message = "Aucun destinataire trouvé pour la cible sélectionnée.";
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Erreur BDD : " . $e->getMessage();
        }
    }
}

// --- SUPPRIMER une Notification ---
if ($action === 'delete_notification' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $success_message = "Notification supprimée de l'historique.";
    } catch (PDOException $e) { $error_message = "Erreur BDD : " . $e->getMessage(); }
}


// --- LECTURE (READ) des données pour la page ---
try {
    // Historique des notifications envoyées (on groupe par message pour éviter les duplicatas)
    $notifications = $pdo->query("
        SELECT *, COUNT(id) as recipient_count, GROUP_CONCAT(utilisateur_id) as recipients
        FROM notifications 
        GROUP BY message, type, lien, date_creation
        ORDER BY date_creation DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Listes pour les formulaires
    $classes = $pdo->query("SELECT id, nom, niveau FROM classes ORDER BY niveau, nom")->fetchAll(PDO::FETCH_ASSOC);
    $etudiants = $pdo->query("SELECT id, nom, prenom FROM utilisateurs WHERE role = 'etudiant' ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des données : " . $e->getMessage();
    $notifications = $classes = $etudiants = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Admin GestiSchool</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS "Galaxy" du Dashboard Admin -->
    <style>
        :root {
            --bg-dark-primary: #0d1117; --bg-dark-secondary: #161b22; --border-color: rgba(255, 255, 255, 0.1);
            --text-primary: #c9d1d9; --text-secondary: #8b949e; --accent-glow-1: #00f2ff; --accent-glow-2: #da00ff;
            --font-primary: 'Poppins', sans-serif; --font-display: 'Orbitron', sans-serif;
        }
        body { font-family: var(--font-primary); background-color: var(--bg-dark-primary); color: var(--text-primary); background-image: radial-gradient(circle at 1px 1px, rgba(255, 255, 255, 0.05) 1px, transparent 0); background-size: 20px 20px; }
        .page-wrapper { display: flex; min-height: 100vh; }
        #sidebar { width: 260px; position: fixed; top: 0; left: 0; height: 100vh; z-index: 1000; background: rgba(16, 19, 26, 0.6); backdrop-filter: blur(10px); border-right: 1px solid var(--border-color); }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid var(--border-color); }
        .sidebar-header .logo { font-family: var(--font-display); font-size: 1.5rem; color: #fff; text-shadow: 0 0 5px var(--accent-glow-1), 0 0 10px var(--accent-glow-2); }
        .nav-link { display: flex; align-items: center; padding: 0.75rem 1rem; color: var(--text-primary); text-decoration: none; border-radius: 8px; margin-bottom: 5px; transition: all 0.2s ease; }
        .nav-link i { width: 25px; margin-right: 15px; text-align: center; }
        .nav-link:hover, .nav-link.active { background: rgba(255, 255, 255, 0.05); color: #fff; }
        #main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2rem; }
        .main-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .main-header h1 { font-family: var(--font-display); color: #fff; font-size: 2rem; }
        .btn-glow { background: linear-gradient(45deg, var(--accent-glow-2), var(--accent-glow-1)); border: none; color: #fff; transition: all 0.3s; box-shadow: 0 0 15px rgba(0, 242, 255, 0.3); }
        .btn-glow:hover { box-shadow: 0 0 25px rgba(0, 242, 255, 0.6); transform: translateY(-2px); }
        .card { background: var(--bg-dark-secondary); border-color: var(--border-color); }
        .form-control, .form-select { background: rgba(0,0,0,0.3); border-color: var(--border-color); color: var(--text-primary); }
        .form-control:focus, .form-select:focus { background: rgba(0,0,0,0.4); color: #fff; box-shadow: 0 0 0 0.25rem rgba(0, 242, 255, 0.25); border-color: var(--accent-glow-1); }
        .form-select option { background-color: var(--bg-dark-primary); }
        .table-dark-galaxy { background-color: transparent; }
        .table-dark-galaxy th, .table-dark-galaxy td { background-color: var(--bg-dark-secondary); border-color: var(--border-color); vertical-align: middle; }
    </style>
</head>
<body>
<div class="page-wrapper">
    <!-- Barre Latérale -->
    <aside id="sidebar">
        <div class="sidebar-header"><a href="../dashboard.php" class="logo"><i class="fas fa-meteor"></i> GestiSchool</a></div>
        <!-- ... (votre menu de sidebar admin) ... -->
    </aside>

    <!-- Contenu Principal -->
    <main id="main-content">
        <header class="main-header">
            <h1><i class="fas fa-satellite-dish me-3"></i>Centre de Notifications</h1>
        </header>

        <!-- Notifications de succès/erreur -->
        <?php if ($success_message): ?><div class="alert alert-success"><?= $success_message ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><?= $error_message ?></div><?php endif; ?>
        
        <!-- Formulaire d'envoi -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Envoyer une nouvelle notification</h5></div>
            <div class="card-body">
                <form method="post" action="notifications.php">
                    <input type="hidden" name="action" value="send_notification">
                    <div class="mb-3">
                        <label for="message" class="form-label">Message *</label>
                        <textarea class="form-control" name="message" id="message" rows="3" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label">Type de notification *</label>
                            <select class="form-select" name="type" id="type" required>
                                <option value="info">Information</option>
                                <option value="devoir">Nouveau Devoir</option>
                                <option value="note">Nouvelle Note</option>
                                <option value="annonce">Annonce</option>
                                <option value="absence">Absence</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lien" class="form-label">Lien (optionnel)</label>
                            <input type="text" class="form-control" name="lien" id="lien" placeholder="/etudiant/devoirs.php">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cible" class="form-label">Envoyer à *</label>
                            <select class="form-select" name="cible" id="cible-select" required>
                                <option value="tous">Tous les utilisateurs</option>
                                <option value="tous_etudiants">Tous les étudiants</option>
                                <option value="tous_enseignants">Tous les enseignants</option>
                                <option value="classe">Une classe spécifique</option>
                                <option value="etudiant_specifique">Un étudiant spécifique</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="cible-id-wrapper" style="display: none;">
                            <label for="cible_id" class="form-label">Préciser la cible</label>
                            <select class="form-select" name="cible_id" id="cible-id-select">
                                <!-- Options chargées par JS -->
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-glow"><i class="fas fa-paper-plane me-2"></i>Envoyer</button>
                </form>
            </div>
        </div>

        <!-- Historique des notifications -->
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Historique des notifications envoyées</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-dark-galaxy">
                        <thead>
                            <tr>
                                <th>Message</th>
                                <th>Type</th>
                                <th>Destinataires</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $notif): ?>
                            <tr>
                                <td><?= htmlspecialchars($notif['message']) ?></td>
                                <td><span class="badge bg-info"><?= ucfirst($notif['type']) ?></span></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($notif['recipient_count']) ?></span></td>
                                <td><?= date('d/m/Y H:i', strtotime($notif['date_creation'])) ?></td>
                                <td>
                                    <a href="?action=delete_notification&id=<?= $notif['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer cette entrée de l\'historique ?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cibleSelect = document.getElementById('cible-select');
    const cibleIdWrapper = document.getElementById('cible-id-wrapper');
    const cibleIdSelect = document.getElementById('cible-id-select');

    // On pré-charge les données des classes et étudiants en JS
    const classesData = <?= json_encode($classes) ?>;
    const etudiantsData = <?= json_encode($etudiants) ?>;

    function updateCibleIdOptions() {
        const selectedCible = cibleSelect.value;
        cibleIdSelect.innerHTML = ''; // Vider les options

        if (selectedCible === 'classe') {
            cibleIdWrapper.style.display = 'block';
            classesData.forEach(c => {
                cibleIdSelect.innerHTML += `<option value="${c.id}">${c.niveau} - ${c.nom}</option>`;
            });
        } else if (selectedCible === 'etudiant_specifique') {
            cibleIdWrapper.style.display = 'block';
            etudiantsData.forEach(e => {
                cibleIdSelect.innerHTML += `<option value="${e.id}">${e.prenom} ${e.nom}</option>`;
            });
        } else {
            cibleIdWrapper.style.display = 'none';
        }
    }

    cibleSelect.addEventListener('change', updateCibleIdOptions);
});
</script>
</body>
</html>