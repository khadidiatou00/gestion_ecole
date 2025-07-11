<?php
session_start();
require_once '../../config/db.php';

// --- Vérification de l'authentification et rôle étudiant ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'etudiant') {
   header('Location: ../../auth/login.php');
    exit();
}

$etudiant_id = $_SESSION['user_id'];

// --- Vérification de l'ID du devoir ---
$devoir_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($devoir_id <= 0) {
    echo "Erreur : identifiant de devoir invalide.";
    exit();
}

$success_message = '';
$error_message = '';

// --- Traitement du formulaire ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reponse_texte = trim($_POST['reponse_texte']);
    $fichier_path = null;

    // Gestion de l'upload du fichier
    if (isset($_FILES['fichier_remis']) && $_FILES['fichier_remis']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/remises/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_ext = pathinfo($_FILES['fichier_remis']['name'], PATHINFO_EXTENSION);
        $file_name = "remise_{$devoir_id}_{$etudiant_id}_" . time() . "." . $file_ext;

        if (move_uploaded_file($_FILES['fichier_remis']['tmp_name'], $upload_dir . $file_name)) {
            $fichier_path = 'uploads/remises/' . $file_name;
        } else {
            $error_message = "Erreur lors du téléchargement du fichier.";
        }
    }

    if (empty($error_message)) {
        try {
            // Vérifier si une remise existe déjà
            $stmt_check = $pdo->prepare("SELECT id, fichier_remis_path FROM remises_devoirs WHERE devoir_id = ? AND etudiant_id = ?");
            $stmt_check->execute([$devoir_id, $etudiant_id]);
            $remise_existante = $stmt_check->fetch();

            // Garde du chemin existant si aucun nouveau fichier n'est soumis
            $final_fichier_path = $fichier_path ?? $remise_existante['fichier_remis_path'] ?? null;

            if ($remise_existante) {
                // Mise à jour
                $stmt = $pdo->prepare("UPDATE remises_devoirs SET reponse_texte = ?, fichier_remis_path = ?, date_remise = NOW() WHERE id = ?");
                $stmt->execute([$reponse_texte, $final_fichier_path, $remise_existante['id']]);
                $success_message = "Votre devoir a été mis à jour avec succès.";
            } else {
                // Insertion
                $stmt = $pdo->prepare("INSERT INTO remises_devoirs (devoir_id, etudiant_id, reponse_texte, fichier_remis_path) VALUES (?, ?, ?, ?)");
                $stmt->execute([$devoir_id, $etudiant_id, $reponse_texte, $final_fichier_path]);
                $success_message = "Votre devoir a été soumis avec succès.";
            }
        } catch (PDOException $e) {
            $error_message = "Erreur base de données : " . $e->getMessage();
        }
    }
}

// --- Récupération des informations du devoir ---
try {
    $stmt_devoir = $pdo->prepare("
        SELECT d.*, m.nom AS matiere_nom, u.prenom AS enseignant_prenom, u.nom AS enseignant_nom
        FROM devoirs d
        JOIN matieres m ON d.matiere_id = m.id
        JOIN utilisateurs u ON d.enseignant_id = u.id
        WHERE d.id = ?
    ");
    $stmt_devoir->execute([$devoir_id]);
    $devoir = $stmt_devoir->fetch(PDO::FETCH_ASSOC);

    if (!$devoir) {
        echo "Erreur : devoir introuvable.";
        exit();
    }

    // Récupération de la remise (s'il y en a une)
    $stmt_remise = $pdo->prepare("SELECT * FROM remises_devoirs WHERE devoir_id = ? AND etudiant_id = ?");
    $stmt_remise->execute([$devoir_id, $etudiant_id]);
    $remise = $stmt_remise->fetch(PDO::FETCH_ASSOC);

    $date_limite = new DateTime($devoir['date_limite']);
    $now = new DateTime();
    $is_late = $now > $date_limite;

} catch (PDOException $e) {
    echo "Erreur base de données : " . $e->getMessage();
    exit();
}

// --- Fonction pour l'affichage du temps restant ---
function getTimeRemainingInfo($date_limite_str) {
    $date_limite = new DateTime($date_limite_str);
    $now = new DateTime();
    if ($now > $date_limite) return ['class' => 'danger', 'text' => 'Date limite dépassée'];
    $interval = $now->diff($date_limite);
    if ($interval->days == 0) return ['class' => 'warning', 'text' => 'Se termine aujourd\'hui'];
    return ['class' => 'success', 'text' => $interval->format('%a jours restants')];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Répondre au Devoir - GestiSchool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6c5ce7; --secondary: #00cec9; --light-bg: #f8f9fa; --white: #fff;
            --dark: #2d3436; --border-color: #e9ecef; --success: #28a745; --danger: #dc3545; --warning: #ffc107;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--light-bg); }
        #main-content { padding: 30px; /* À adapter si vous avez une sidebar */ }
        .card-custom {
            background-color: var(--white); border: 0; border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.07); transition: all 0.3s;
        }
        .card-header-custom {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white); padding: 1.5rem; border-radius: 15px 15px 0 0;
            border-bottom: 0;
        }
        .info-pills { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 1rem; }
        .info-pills .badge { font-size: 0.9rem; padding: 0.6em 1em; }
        .file-download-link {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 10px 20px; border: 2px dashed var(--secondary); border-radius: 10px;
            color: var(--primary); font-weight: 600; text-decoration: none; transition: all 0.3s;
        }
        .file-download-link:hover { background-color: var(--secondary); color: var(--white); border-style: solid; }
        .submission-card { border-left: 5px solid var(--primary); }
        .form-label { font-weight: 500; }
        .file-input-wrapper {
            position: relative; border: 2px dashed var(--border-color); border-radius: 10px;
            padding: 2rem; text-align: center; cursor: pointer; transition: all 0.3s;
        }
        .file-input-wrapper:hover { border-color: var(--primary); background-color: #fcfcff; }
        .file-input-wrapper input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .submission-status { border-radius: 10px; padding: 1.5rem; text-align: center; }
    </style>
</head>
<body>
    <main id="main-content">
        <a href="devoirs.php" class="btn btn-light mb-4"><i class="fas fa-arrow-left me-2"></i>Retour à la liste des devoirs</a>

        <?php if ($success_message): ?><div class="alert alert-success"><?= $success_message ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><?= $error_message ?></div><?php endif; ?>

        <div class="row g-4">
            <!-- Colonne de gauche: Détails du devoir -->
            <div class="col-lg-7">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h1 class="h3 mb-0 text-white"><?= htmlspecialchars($devoir['titre']) ?></h1>
                        <div class="info-pills">
                            <span class="badge bg-white text-dark"><i class="fas fa-book me-2"></i><?= htmlspecialchars($devoir['matiere_nom']) ?></span>
                            <span class="badge bg-white text-dark"><i class="fas fa-user-tie me-2"></i><?= htmlspecialchars($devoir['enseignant_prenom'] . ' ' . $devoir['enseignant_nom']) ?></span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="mb-3">Instructions</h5>
                        <p class="text-muted">
                            <?= !empty($devoir['description']) ? nl2br(htmlspecialchars($devoir['description'])) : "Aucune instruction particulière." ?>
                        </p>
                        
                        <?php if (!empty($devoir['fichier_path'])): ?>
                            <hr class="my-4">
                            <h5 class="mb-3">Fichier joint</h5>
                            <a href="../../<?= htmlspecialchars($devoir['fichier_path']) ?>" class="file-download-link" download>
                                <i class="fas fa-download fa-lg"></i>
                                <span>Télécharger le sujet</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Colonne de droite: Soumission -->
            <div class="col-lg-5">
                <div class="card-custom submission-card">
                    <div class="card-body p-4">
                        <h4 class="mb-3">Ma Réponse</h4>
                        <div class="alert alert-<?= getTimeRemainingInfo($devoir['date_limite'])['class'] ?> text-center fw-bold">
                            <i class="fas fa-calendar-alt me-2"></i>Date limite : <?= $date_limite->format('d/m/Y à H:i') ?>
                            <br>
                            <small><?= getTimeRemainingInfo($devoir['date_limite'])['text'] ?></small>
                        </div>
                        
                        <?php if ($is_late && !$remise): ?>
                            <div class="alert alert-danger text-center">
                                <i class="fas fa-times-circle me-2"></i>La date limite de soumission est dépassée.
                            </div>
                        <?php else: ?>
                            <form method="post" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="reponse_texte" class="form-label">Réponse écrite (optionnel)</label>
                                    <textarea name="reponse_texte" class="form-control" rows="5" placeholder="Saisissez votre réponse ici..."><?= htmlspecialchars($remise['reponse_texte'] ?? '') ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Fichier à remettre</label>
                                    <div class="file-input-wrapper">
                                        <input type="file" name="fichier_remis" id="file-input">
                                        <i class="fas fa-cloud-upload-alt fa-2x text-primary mb-2"></i>
                                        <p class="mb-0" id="file-name">Cliquez ou glissez-déposez votre fichier</p>
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i> <?= $remise ? 'Mettre à jour ma remise' : 'Remettre mon devoir' ?>
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($remise): ?>
                <div class="card-custom mt-4">
                    <div class="card-body p-4">
                         <h5 class="mb-3 text-success"><i class="fas fa-check-circle me-2"></i>Travail Remis</h5>
                         <p><strong>Date de remise :</strong> <?= (new DateTime($remise['date_remise']))->format('d/m/Y à H:i') ?></p>
                         <?php if (!empty($remise['fichier_remis_path'])): ?>
                             <a href="../../../<?= htmlspecialchars($remise['fichier_remis_path']) ?>" download class="btn btn-outline-success btn-sm">
                                <i class="fas fa-download me-2"></i>Voir ma remise
                             </a>
                         <?php endif; ?>
                         <?php if ($remise['note']): ?>
                            <div class="alert alert-info mt-3"><strong>Note obtenue : <?= htmlspecialchars($remise['note']) ?>/20</strong></div>
                            <p><strong>Commentaire :</strong> <?= nl2br(htmlspecialchars($remise['commentaire_enseignant'])) ?></p>
                         <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script>
        document.getElementById('file-input').addEventListener('change', function() {
            const fileName = this.files.length > 0 ? this.files[0].name : 'Cliquez ou glissez-déposez votre fichier';
            document.getElementById('file-name').textContent = fileName;
        });
    </script>
</body>
</html>