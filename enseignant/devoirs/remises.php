<?php
session_start();
require_once '../../config/db.php';

// --- AUTHENTIFICATION & RÔLE ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['enseignant', 'admin'])) {
    header('Location: ../../auth/login.php');
    exit();
}
$enseignant_id = $_SESSION['user_id'];

// --- VARIABLES DE RETOUR ---
$success_message = '';
$error_message = '';

// --- LOGIQUE DE NOTATION (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'noter_remise') {
    $remise_id = filter_input(INPUT_POST, 'remise_id', FILTER_VALIDATE_INT);
    $note = filter_input(INPUT_POST, 'note', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0, 'max_range' => 20]]);
    $commentaire = trim($_POST['commentaire_enseignant']);

    if ($note === false) {
        $error_message = "La note doit être un nombre valide entre 0 et 20.";
    } elseif ($remise_id) {
        try {
            $pdo->beginTransaction();
            // 1. Mettre à jour la remise avec la note et le commentaire
            $stmt_update = $pdo->prepare("UPDATE remises_devoirs SET note = ?, commentaire_enseignant = ? WHERE id = ?");
            $stmt_update->execute([$note, $commentaire, $remise_id]);
            
            // 2. Envoyer une notification à l'étudiant
            $stmt_info = $pdo->prepare("SELECT r.etudiant_id, d.titre, d.id as devoir_id FROM remises_devoirs r JOIN devoirs d ON r.devoir_id = d.id WHERE r.id = ?");
            $stmt_info->execute([$remise_id]);
            $info = $stmt_info->fetch();

            if ($info) {
                $notif_message = "Votre devoir \"{$info['titre']}\" a été noté : $note/20.";
                $notif_lien = "etudiant/scolarite/repondre_devoir.php?id=" . $info['devoir_id'];
                $stmt_notif = $pdo->prepare("INSERT INTO notifications (utilisateur_id, type, message, lien) VALUES (?, 'note', ?, ?)");
                $stmt_notif->execute([$info['etudiant_id'], $notif_message, $notif_lien]);
            }
            
            $pdo->commit();
            $success_message = "La note a été enregistrée et l'étudiant notifié.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Erreur de base de données : " . $e->getMessage();
        }
    }
}

// --- RÉCUPÉRATION DES REMISES À CORRIGER ---
try {
    $stmt = $pdo->prepare("
        SELECT 
            r.id AS remise_id, r.date_remise, r.fichier_remis_path, r.reponse_texte,
            d.titre AS devoir_titre,
            u.nom AS etudiant_nom, u.prenom AS etudiant_prenom,
            c.nom AS classe_nom, c.niveau AS classe_niveau
        FROM remises_devoirs r
        JOIN devoirs d ON r.devoir_id = d.id
        JOIN utilisateurs u ON r.etudiant_id = u.id
        JOIN classes c ON d.classe_id = c.id
        WHERE d.enseignant_id = :enseignant_id AND r.note IS NULL
        ORDER BY r.date_remise ASC
    ");
    $stmt->execute([':enseignant_id' => $enseignant_id]);
    $remises_a_corriger = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centre de Correction - GestiSchool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6a11cb; --secondary: #2575fc; --light-bg: #f4f7f9; --white: #fff;
            --dark: #343a40; --border-color: #dee2e6; --success: #198754; --info: #0dcaf0;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        body { font-family: 'Poppins', sans-serif; background-color: var(--light-bg); }
        .main-header { padding: 2rem 0; }
        .main-header h1 { font-weight: 700; color: var(--dark); }
        .remise-card {
            background: var(--white); border: 0; border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            animation: fadeIn 0.5s ease-out forwards;
            display: flex; flex-direction: column; height: 100%;
        }
        .remise-header {
            padding: 1.25rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
            border-radius: 15px 15px 0 0;
        }
        .remise-header h5 { margin: 0; font-weight: 600; }
        .remise-body { padding: 1.25rem; flex-grow: 1; }
        .travail-remis {
            background: #fafafa; border: 1px solid var(--border-color);
            padding: 1rem; border-radius: 10px; margin-bottom: 1rem;
        }
        .fichier-link {
            display: flex; align-items: center; gap: 10px;
            padding: 0.75rem 1rem; background-color: #e9ecef;
            border-radius: 8px; text-decoration: none; color: var(--dark);
            transition: all 0.3s;
        }
        .fichier-link:hover { background-color: #dbe2e9; }
        .reponse-texte-display {
            font-style: italic; color: #6c757d;
            border-left: 3px solid var(--primary);
            padding-left: 1rem;
            margin-top: 1rem;
        }
        .form-notation { border-top: 1px solid var(--border-color); padding-top: 1rem; }
        .empty-state {
            text-align: center; padding: 4rem; background: var(--white); border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        .empty-state .icon {
            font-size: 4rem; color: var(--success);
            background: linear-gradient(135deg, #e0f7ea, #c8e6c9);
            width: 100px; height: 100px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <main class="container">
        <header class="main-header">
            <h1><i class="fas fa-marker text-primary"></i> Centre de Correction</h1>
            <p class="text-muted">Vous avez <?= count($remises_a_corriger) ?> devoir(s) à corriger.</p>
        </header>

        <?php if ($success_message): ?><div class="alert alert-success"><?= $success_message ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><?= $error_message ?></div><?php endif; ?>

        <?php if (empty($remises_a_corriger)): ?>
            <div class="empty-state">
                <div class="icon"><i class="fas fa-check-double"></i></div>
                <h3 class="fw-bold">Félicitations !</h3>
                <p class="text-muted">Vous êtes à jour. Il n'y a aucune nouvelle remise à corriger pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($remises_a_corriger as $remise): ?>
                <div class="col-lg-6">
                    <div class="remise-card">
                        <div class="remise-header">
                            <h5><?= htmlspecialchars($remise['etudiant_prenom'] . ' ' . $remise['etudiant_nom']) ?></h5>
                            <small>Classe: <?= htmlspecialchars($remise['classe_niveau'] . ' ' . $remise['classe_nom']) ?></small>
                        </div>
                        <div class="remise-body">
                            <p class="mb-2"><strong>Devoir :</strong> <?= htmlspecialchars($remise['devoir_titre']) ?></p>
                            <p class="text-muted mb-3"><small>Remis le : <?= (new DateTime($remise['date_remise']))->format('d/m/Y à H:i') ?></small></p>

                            <div class="travail-remis">
                                <h6 class="mb-3">Travail soumis par l'étudiant :</h6>
                                
                                <?php if (!empty($remise['fichier_remis_path'])): ?>
                                    <a href="../../<?= htmlspecialchars($remise['fichier_remis_path']) ?>" class="fichier-link" download>
                                        <i class="fas fa-file-download fa-lg text-primary"></i>
                                        <span class="fw-bold text-truncate"><?= htmlspecialchars(basename($remise['fichier_remis_path'])) ?></span>
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($remise['reponse_texte'])): ?>
                                    <div class="reponse-texte-display">
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($remise['reponse_texte'])) ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($remise['fichier_remis_path']) && empty($remise['reponse_texte'])): ?>
                                    <p class="text-muted fst-italic">Aucun travail n'a été soumis.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent form-notation">
                            <form method="post" action="corrections.php" class="row g-2 align-items-center">
                                <input type="hidden" name="action" value="noter_remise">
                                <input type="hidden" name="remise_id" value="<?= $remise['remise_id'] ?>">
                                <div class="col-4">
                                    <input type="number" step="0.25" min="0" max="20" class="form-control" name="note" placeholder="Note /20" required>
                                </div>
                                <div class="col-5">
                                    <input type="text" class="form-control" name="commentaire_enseignant" placeholder="Commentaire (optionnel)">
                                </div>
                                <div class="col-3 d-grid">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Noter</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>