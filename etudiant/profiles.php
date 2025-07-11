<?php
session_start();
require_once '../config/db.php';

// --- SÉCURITÉ & RÉCUPÉRATION DES DONNÉES ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'etudiant') {
    header('Location: ../auth/login.php');
    exit();
}

$etudiant_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// --- LOGIQUE DE MISE À JOUR DU PROFIL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mise à jour du mot de passe
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        $stmt = $pdo->prepare("SELECT password FROM utilisateurs WHERE id = ?");
        $stmt->execute([$etudiant_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password && strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE utilisateurs SET password = ? WHERE id = ?");
                $update_stmt->execute([$hashed_password, $etudiant_id]);
                $success_message = "Mot de passe mis à jour avec succès !";
            } else {
                $error_message = "Le nouveau mot de passe est invalide ou ne correspond pas.";
            }
        } else {
            $error_message = "Le mot de passe actuel est incorrect.";
        }
    }

    // Mise à jour de la photo de profil
    if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = 'user_' . $etudiant_id . '_' . time() . '.' . pathinfo($_FILES['photo_profil']['name'], PATHINFO_EXTENSION);
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['photo_profil']['tmp_name'], $target_file)) {
            $photo_path = 'uploads/profiles/' . $file_name;
            $stmt = $pdo->prepare("UPDATE utilisateurs SET photo_profil = ? WHERE id = ?");
            $stmt->execute([$photo_path, $etudiant_id]);
            $success_message = "Photo de profil mise à jour avec succès.";
        } else {
            $error_message = "Erreur lors du téléchargement de la photo.";
        }
    }
}


// --- RÉCUPÉRATION DES INFORMATIONS COMPLÈTES DE L'ÉTUDIANT ---
try {
    $stmt = $pdo->prepare("
        SELECT u.*, c.nom AS classe_nom, c.niveau AS classe_niveau, a.annee AS annee_scolaire
        FROM utilisateurs u
        LEFT JOIN inscriptions i ON u.id = i.etudiant_id AND i.statut = 'actif'
        LEFT JOIN classes c ON i.classe_id = c.id
        LEFT JOIN annees_scolaires a ON i.annee_id = a.id AND a.statut = 'en_cours'
        WHERE u.id = ?
    ");
    $stmt->execute([$etudiant_id]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$etudiant) {
        die("Erreur: Impossible de trouver les informations de l'étudiant.");
    }

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - GestiSchool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6c5ce7; --primary-light: #a29bfe; --secondary: #00cec9;
            --dark: #2d3436; --light-bg: #f8f9fa; --white: #fff;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--light-bg); }
        #sidebar { /* ... (votre style de sidebar habituel) ... */ }
        #main-content { margin-left: 280px; padding: 30px; }
        .profile-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white);
            border-radius: 15px;
            padding: 2.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(108, 92, 231, 0.3);
            position: relative;
        }
        .profile-picture-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: -95px auto 20px;
            border: 5px solid var(--white);
            border-radius: 50%;
            background-color: var(--light-bg);
        }
        .profile-picture { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .upload-button {
            position: absolute; bottom: 5px; right: 5px;
            width: 35px; height: 35px;
            background-color: var(--secondary); color: var(--white);
            border-radius: 50%; border: 2px solid var(--white);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.3s;
        }
        .upload-button:hover { transform: scale(1.1); }
        .profile-card {
            background-color: var(--white);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-top: 2rem;
        }
        .info-group { margin-bottom: 1.5rem; }
        .info-group .info-label { font-size: 0.9rem; color: #888; margin-bottom: 5px; }
        .info-group .info-value { font-weight: 500; color: var(--dark); }
        .info-group i { color: var(--primary); margin-right: 10px; width: 20px; text-align: center; }
        .section-title { font-weight: 600; color: var(--dark); margin-bottom: 1.5rem; }
        @media (max-width: 992px) { #main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <!-- (Inclure votre sidebar ici, avec le lien "Profil" actif) -->
    <main id="main-content">
        <header class="profile-header">
            <!-- Contenu du header -->
        </header>
        <div class="profile-picture-container">
            <img src="../<?= htmlspecialchars($etudiant['photo_profil'] ?? 'assets/img/profiles/default.png') ?>" alt="Photo de profil" class="profile-picture">
            <label for="photo_upload" class="upload-button"><i class="fas fa-camera"></i></label>
            <form method="post" enctype="multipart/form-data" class="d-none">
                <input type="file" name="photo_profil" id="photo_upload" onchange="this.form.submit()">
            </form>
        </div>
        <div class="text-center">
            <h2 class="fw-bold"><?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?></h2>
            <p class="text-muted">Étudiant</p>
        </div>

        <?php if ($success_message): ?><div class="alert alert-success mt-3"><?= $success_message ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger mt-3"><?= $error_message ?></div><?php endif; ?>

        <div class="row mt-4">
            <div class="col-lg-7">
                <div class="profile-card">
                    <h4 class="section-title">Informations Personnelles</h4>
                    <div class="row">
                        <div class="col-md-6 info-group"><i class="fas fa-envelope"></i> <span class="info-label">Email</span><div class="info-value ps-4 ms-2"><?= htmlspecialchars($etudiant['email']) ?></div></div>
                        <div class="col-md-6 info-group"><i class="fas fa-phone"></i> <span class="info-label">Téléphone</span><div class="info-value ps-4 ms-2"><?= htmlspecialchars($etudiant['telephone'] ?? 'N/A') ?></div></div>
                        <div class="col-md-6 info-group"><i class="fas fa-venus-mars"></i> <span class="info-label">Sexe</span><div class="info-value ps-4 ms-2"><?= $etudiant['sexe'] === 'M' ? 'Masculin' : 'Féminin' ?></div></div>
                        <div class="col-md-6 info-group"><i class="fas fa-birthday-cake"></i> <span class="info-label">Date de Naissance</span><div class="info-value ps-4 ms-2"><?= date('d/m/Y', strtotime($etudiant['date_naissance'])) ?></div></div>
                        <div class="col-12 info-group"><i class="fas fa-map-marker-alt"></i> <span class="info-label">Adresse</span><div class="info-value ps-4 ms-2"><?= htmlspecialchars($etudiant['adresse'] ?? 'N/A') ?></div></div>
                    </div>
                     <hr>
                     <h4 class="section-title mt-4">Informations Scolaires</h4>
                     <div class="row">
                        <div class="col-md-6 info-group"><i class="fas fa-graduation-cap"></i> <span class="info-label">Classe</span><div class="info-value ps-4 ms-2"><?= htmlspecialchars($etudiant['classe_niveau'] . ' - ' . $etudiant['classe_nom'] ?? 'Non affecté') ?></div></div>
                        <div class="col-md-6 info-group"><i class="fas fa-calendar-alt"></i> <span class="info-label">Année Scolaire</span><div class="info-value ps-4 ms-2"><?= htmlspecialchars($etudiant['annee_scolaire'] ?? 'N/A') ?></div></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="profile-card">
                    <h4 class="section-title">Sécurité</h4>
                    <form method="post">
                        <input type="hidden" name="update_password">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mot de passe actuel</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" name="new_password" required>
                        </div>
                         <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-2">Changer le mot de passe</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>