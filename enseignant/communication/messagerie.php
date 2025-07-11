<?php
session_start();
require_once '../../config/db.php';

// --- SÉCURITÉ ET INITIALISATION ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'enseignant') {
    header('Location: ../../auth/login.php');
    exit();
}

$enseignant_id = $_SESSION['user_id'];
$notification = null;
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']);
}

// --- LOGIQUE CRUD (POST) ---
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // --- ENVOYER UN MESSAGE ---
        if (isset($_POST['action']) && $_POST['action'] === 'envoyer_message') {
            $destinataire_id = $_POST['destinataire_id'];
            $sujet = trim($_POST['sujet']);
            $message = trim($_POST['message']);

            if (empty($destinataire_id) || empty($sujet) || empty($message)) {
                throw new Exception("Tous les champs sont obligatoires.");
            }

            $sql = "INSERT INTO messages (expediteur_id, destinataire_id, sujet, message) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$enseignant_id, $destinataire_id, $sujet, $message]);

            $_SESSION['notification'] = ['type' => 'success', 'message' => 'Message envoyé avec succès.'];
            header('Location: messagerie.php?view=sent');
            exit();
        }

        // --- SUPPRIMER UN MESSAGE ---
        if (isset($_POST['action']) && $_POST['action'] === 'supprimer_message') {
            $message_id = $_POST['message_id'];
            $view = $_POST['view'];

            // Vérifier à qui appartient le message avant de supprimer
            $stmt_check = $pdo->prepare("SELECT expediteur_id, destinataire_id FROM messages WHERE id = ?");
            $stmt_check->execute([$message_id]);
            $msg = $stmt_check->fetch();

            if ($msg) {
                if ($msg['expediteur_id'] == $enseignant_id) {
                    $stmt_del = $pdo->prepare("UPDATE messages SET supprime_expediteur = 1 WHERE id = ?");
                } elseif ($msg['destinataire_id'] == $enseignant_id) {
                    $stmt_del = $pdo->prepare("UPDATE messages SET supprime_destinataire = 1 WHERE id = ?");
                }
                if (isset($stmt_del)) {
                    $stmt_del->execute([$message_id]);
                    $_SESSION['notification'] = ['type' => 'info', 'message' => 'Message déplacé dans la corbeille.'];
                }
            }
            header('Location: messagerie.php?view=' . urlencode($view));
            exit();
        }
    }

    // --- LOGIQUE D'AFFICHAGE (GET) ---
    $stmt_enseignant = $pdo->prepare("SELECT nom, prenom, photo_profil FROM utilisateurs WHERE id = ?");
    $stmt_enseignant->execute([$enseignant_id]);
    $enseignant_info = $stmt_enseignant->fetch();

    $view = $_GET['view'] ?? 'inbox';
    $selected_message_id = $_GET['id'] ?? null;
    $message_selectionne = null;

    // MARQUER COMME LU
    if ($selected_message_id && $view === 'inbox') {
        $stmt_read = $pdo->prepare("UPDATE messages SET statut_lecture = 'lu' WHERE id = ? AND destinataire_id = ?");
        $stmt_read->execute([$selected_message_id, $enseignant_id]);
    }

    // RÉCUPÉRER LES MESSAGES POUR LA LISTE
    if ($view === 'inbox') {
        $sql_messages = "SELECT m.id, m.sujet, m.date_envoi, m.statut_lecture, u.prenom, u.nom
                         FROM messages m JOIN utilisateurs u ON m.expediteur_id = u.id
                         WHERE m.destinataire_id = ? AND m.supprime_destinataire = 0
                         ORDER BY m.date_envoi DESC";
    } else { // 'sent'
        $sql_messages = "SELECT m.id, m.sujet, m.date_envoi, m.statut_lecture, u.prenom, u.nom
                         FROM messages m JOIN utilisateurs u ON m.destinataire_id = u.id
                         WHERE m.expediteur_id = ? AND m.supprime_expediteur = 0
                         ORDER BY m.date_envoi DESC";
    }
    $stmt_messages = $pdo->prepare($sql_messages);
    $stmt_messages->execute([$enseignant_id]);
    $messages_list = $stmt_messages->fetchAll();

    // AFFICHER LE MESSAGE SÉLECTIONNÉ
    if ($selected_message_id) {
        $stmt_msg = $pdo->prepare(
            "SELECT m.*, u_exp.prenom as exp_prenom, u_exp.nom as exp_nom, u_dest.prenom as dest_prenom, u_dest.nom as dest_nom
             FROM messages m
             JOIN utilisateurs u_exp ON m.expediteur_id = u_exp.id
             JOIN utilisateurs u_dest ON m.destinataire_id = u_dest.id
             WHERE m.id = ? AND (m.expediteur_id = ? OR m.destinataire_id = ?)"
        );
        $stmt_msg->execute([$selected_message_id, $enseignant_id, $enseignant_id]);
        $message_selectionne = $stmt_msg->fetch();
    }

    // Lister les destinataires possibles (tous sauf soi-même)
    $stmt_dest = $pdo->prepare("SELECT id, nom, prenom, role FROM utilisateurs WHERE id != ? ORDER BY role, nom");
    $stmt_dest->execute([$enseignant_id]);
    $destinataires = $stmt_dest->fetchAll();

} catch (Exception $e) {
    $notification = ['type' => 'danger', 'message' => "Erreur : " . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie - GestiSchool</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: #f4f7fc; --sidebar-bg: #ffffff; --card-bg: #ffffff; --primary: #4f46e5;
            --secondary: #64748b; --accent: #ec4899; --text-dark: #1e293b; --text-light: #64748b;
            --border-color: #e2e8f0; --font-body: 'Poppins', sans-serif; --font-title: 'Montserrat', sans-serif;
        }
        @keyframes fadeInScale { from { opacity: 0; transform: translateY(20px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        body { font-family: var(--font-body); background-color: var(--bg-main); color: var(--text-dark); }
        .page-wrapper { display: flex; min-height: 100vh; }
        #sidebar { width: 260px; position: fixed; top: 0; left: 0; height: 100vh; z-index: 1000; background: var(--sidebar-bg); border-right: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.05); display: flex; flex-direction: column; transition: all 0.3s ease; }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid var(--border-color); }
        .sidebar-header .logo { font-family: var(--font-title); font-size: 1.6rem; color: var(--primary); font-weight: 700; text-decoration: none; }
        .sidebar-nav { padding: 1rem; flex-grow: 1; overflow-y: auto; }
        .nav-category { font-size: 0.75rem; color: var(--text-light); text-transform: uppercase; padding: 1rem; font-weight: 600; letter-spacing: 0.5px; }
        .nav-link { display: flex; align-items: center; padding: 0.8rem 1rem; color: var(--secondary); text-decoration: none; border-radius: 8px; margin-bottom: 5px; font-weight: 500; transition: all 0.3s ease; }
        .nav-link:hover { background-color: #eef2ff; color: var(--primary); }
        .nav-link.active { background: var(--primary); color: #fff; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
        .sidebar-footer { padding: 1rem; border-top: 1px solid var(--border-color); }
        .user-info { display: flex; align-items: center; }
        .user-info img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; margin-right: 12px; border: 2px solid var(--primary); }
        #main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2.5rem; }
        .main-header h1 { font-family: var(--font-title); font-weight: 700; }
        .dashboard-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 0; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.07), 0 4px 6px -2px rgba(0,0,0,0.05); animation: fadeInScale 0.6s ease-out forwards; opacity: 0; overflow: hidden; }
        .messagerie-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); }
        .messagerie-body { display: flex; height: 60vh; }
        .messages-list { width: 35%; border-right: 1px solid var(--border-color); overflow-y: auto; }
        .message-view { width: 65%; padding: 2rem; }
        .list-group-item { border-radius: 0; border-left: 0; border-right: 0; border-top: 1px solid var(--border-color); padding: 1rem 1.5rem; }
        .list-group-item:first-child { border-top: none; }
        .list-group-item.active { background-color: #eef2ff; color: var(--text-dark); border-left: 4px solid var(--primary); }
        .list-group-item.unread { font-weight: 700; color: var(--text-dark); }
        .message-subject { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .message-sender { font-size: 0.9rem; }
        .message-date { font-size: 0.8rem; color: var(--text-light); }
        .message-detail-header { border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.5rem; }
        @media (max-width: 992px) { #sidebar { left: -260px; } #sidebar.active { left: 0; } #main-content { margin-left: 0; width: 100%; } #sidebar-toggle { display: block; } }
        #sidebar-toggle { display: none; background: transparent; border: none; font-size: 1.5rem; }
    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- Barre Latérale -->
    <aside id="sidebar">
        <!-- ... Sidebar ... -->
        <div class="sidebar-header"><a href="../dashboard.php" class="logo"><i class="fas fa-graduation-cap"></i> GestiSchool</a></div>
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
                <li class="nav-category">Communication</li>
                <li class="nav-item"><a class="nav-link active" href="messagerie.php"><i class="fas fa-envelope"></i> Messagerie</a></li>
                <li class="nav-item"><a class="nav-link" href="annonces.php"><i class="fas fa-bullhorn"></i> Annonces</a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <img src="../../<?= htmlspecialchars($enseignant_info['photo_profil'] ?? 'assets/img/profiles/default.png') ?>" alt="Photo de profil">
                <div><?= htmlspecialchars($enseignant_info['prenom'] . ' ' . $enseignant_info['nom']) ?> <a href="../../auth/logout.php" class="text-danger small d-block">Déconnexion</a></div>
            </div>
        </div>
    </aside>

    <!-- Contenu Principal -->
    <main id="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
             <h1 class="d-inline-block align-middle"><i class="fas fa-envelope-open-text me-2" style="color: var(--primary);"></i>Messagerie</h1>
        </header>

        <?php if ($notification): ?>
            <div class="alert alert-<?= $notification['type'] ?> alert-dismissible fade show" role="alert"><?= htmlspecialchars($notification['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="dashboard-card">
            <div class="messagerie-header d-flex justify-content-between align-items-center">
                <ul class="nav nav-pills">
                    <li class="nav-item"><a class="nav-link <?= $view === 'inbox' ? 'active' : '' ?>" href="?view=inbox"><i class="fas fa-inbox me-1"></i> Boîte de réception</a></li>
                    <li class="nav-item"><a class="nav-link <?= $view === 'sent' ? 'active' : '' ?>" href="?view=sent"><i class="fas fa-paper-plane me-1"></i> Messages envoyés</a></li>
                </ul>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal"><i class="fas fa-edit me-2"></i>Nouveau message</button>
            </div>
            <div class="messagerie-body">
                <div class="messages-list">
                    <div class="list-group list-group-flush">
                        <?php if (empty($messages_list)): ?>
                            <div class="text-center text-muted p-5">Boîte vide.</div>
                        <?php endif; ?>
                        <?php foreach ($messages_list as $msg): ?>
                            <a href="?view=<?= $view ?>&id=<?= $msg['id'] ?>" class="list-group-item list-group-item-action <?= $selected_message_id == $msg['id'] ? 'active' : '' ?> <?= $view == 'inbox' && $msg['statut_lecture'] == 'non_lu' ? 'unread' : '' ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <p class="mb-1 message-sender"><?= htmlspecialchars($msg['prenom'] . ' ' . $msg['nom']) ?></p>
                                    <small class="message-date"><?= date('d/m/y', strtotime($msg['date_envoi'])) ?></small>
                                </div>
                                <span class="message-subject"><?= htmlspecialchars($msg['sujet']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="message-view">
                    <?php if (!$message_selectionne): ?>
                        <div class="text-center text-muted h-100 d-flex flex-column justify-content-center align-items-center">
                            <i class="fas fa-envelope-open fa-4x mb-3"></i>
                            <h4>Sélectionnez un message pour le lire.</h4>
                        </div>
                    <?php else: ?>
                        <div class="message-detail-header">
                            <h4 class="mb-1"><?= htmlspecialchars($message_selectionne['sujet']) ?></h4>
                            <div class="text-muted small">
                                De : <strong><?= htmlspecialchars($message_selectionne['exp_prenom'] . ' ' . $message_selectionne['exp_nom']) ?></strong><br>
                                À : <strong><?= htmlspecialchars($message_selectionne['dest_prenom'] . ' ' . $message_selectionne['dest_nom']) ?></strong><br>
                                Le : <?= date('d/m/Y à H:i', strtotime($message_selectionne['date_envoi'])) ?>
                            </div>
                        </div>
                        <div class="message-body-content" style="white-space: pre-wrap;"><?= htmlspecialchars($message_selectionne['message']) ?></div>
                        <hr>
                        <div class="text-end">
                            <button class="btn btn-outline-primary btn-reply" 
                                    data-exp-id="<?= $message_selectionne['expediteur_id'] ?>" 
                                    data-sujet="<?= htmlspecialchars($message_selectionne['sujet']) ?>">
                                <i class="fas fa-reply me-1"></i> Répondre
                            </button>
                            <form action="messagerie.php" method="POST" class="d-inline" onsubmit="return confirm('Voulez-vous vraiment supprimer ce message ?');">
                                <input type="hidden" name="action" value="supprimer_message">
                                <input type="hidden" name="message_id" value="<?= $message_selectionne['id'] ?>">
                                <input type="hidden" name="view" value="<?= $view ?>">
                                <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash me-1"></i> Supprimer</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal pour composer un message -->
<div class="modal fade" id="composeModal" tabindex="-1" aria-labelledby="composeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="messagerie.php" method="POST">
                <input type="hidden" name="action" value="envoyer_message">
                <div class="modal-header">
                    <h5 class="modal-title" id="composeModalLabel">Nouveau Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="destinataire_id" class="form-label">Destinataire</label>
                        <select class="form-select" id="destinataire_id" name="destinataire_id" required>
                            <option value="">-- Choisissez un destinataire --</option>
                            <?php foreach ($destinataires as $dest): ?>
                                <option value="<?= $dest['id'] ?>"><?= htmlspecialchars($dest['prenom'] . ' ' . $dest['nom'] . ' (' . $dest['role'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="sujet" class="form-label">Sujet</label>
                        <input type="text" class="form-control" id="sujet" name="sujet" required>
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="8" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Envoyer</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const composeModalEl = document.getElementById('composeModal');
    const composeModal = new bootstrap.Modal(composeModalEl);

    // Gérer la réponse à un message
    document.querySelectorAll('.btn-reply').forEach(button => {
        button.addEventListener('click', function() {
            const expediteurId = this.dataset.expId;
            let sujet = this.dataset.sujet;
            
            // Préfixer le sujet avec "Re: " s'il n'est pas déjà présent
            if (!sujet.toLowerCase().startsWith('re:')) {
                sujet = 'Re: ' + sujet;
            }

            // Pré-remplir le formulaire de la modale
            composeModalEl.querySelector('#destinataire_id').value = expediteurId;
            composeModalEl.querySelector('#sujet').value = sujet;
            composeModalEl.querySelector('#message').value = "\n\n\n--- Message original ---\n";
            composeModalEl.querySelector('#message').focus();

            composeModal.show();
        });
    });

    // Vider le formulaire quand on clique sur "Nouveau Message"
    document.querySelector('button[data-bs-target="#composeModal"]').addEventListener('click', function() {
        composeModalEl.querySelector('form').reset();
    });
});
</script>

</body>
</html>