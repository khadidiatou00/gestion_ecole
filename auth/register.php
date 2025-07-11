<?php
session_start();
require_once '../config/db.php';

// SÉCURITÉ : Décommentez si seul un admin doit y accéder
/*
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
*/

$success_message = '';
$error_message = '';

try {
    // NOUVEAU : On récupère les classes et années pour les menus déroulants
    $classes = $pdo->query("SELECT id, nom, niveau FROM classes ORDER BY niveau, nom")->fetchAll();
    $annees_scolaires = $pdo->query("SELECT id, annee FROM annees_scolaires ORDER BY annee DESC")->fetchAll();
} catch (PDOException $e) {
    // Si on ne peut pas charger les classes/années, on affiche une erreur
    $error_message = "Impossible de charger les classes ou années scolaires. Erreur : " . $e->getMessage();
    $classes = [];
    $annees_scolaires = [];
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Le reste du code utilise directement $pdo
    $nom = trim($_POST['nom']) ?? '';
    $prenom = trim($_POST['prenom']) ?? '';
    $email = trim($_POST['email']) ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    // ... autres champs
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : null;
$adresse = isset($_POST['adresse']) ? trim($_POST['adresse']) : null;
$sexe = $_POST['sexe'] ?? null;
$date_naissance = $_POST['date_naissance'] ?? null;
$groupe_sanguin = $_POST['groupe_sanguin'] ?? null;


    $specialite = ($role === 'enseignant') ? ($_POST['specialite'] ?? null) : null;
    $grade = ($role === 'enseignant') ? ($_POST['grade'] ?? null) : null;
    $contrat = ($role === 'enseignant') ? ($_POST['contrat'] ?? null) : null;

    // NOUVEAU : On récupère les infos d'inscription si le rôle est étudiant
    $classe_id = ($role === 'etudiant') ? ($_POST['classe_id'] ?? null) : null;
    $annee_id = ($role === 'etudiant') ? ($_POST['annee_id'] ?? null) : null;
    
    // --- VALIDATION ---
    if (empty($nom) || empty($email) || empty($password) || empty($role)) {
        $error_message = "Les champs Nom, Email, Mot de passe et Rôle sont obligatoires.";
    } elseif ($role === 'etudiant' && (empty($classe_id) || empty($annee_id))) {
        // NOUVEAU : Validation pour l'étudiant
        $error_message = "Pour un étudiant, la classe et l'année scolaire sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Le format de l'adresse e-mail est invalide.";
    } else {
        $pdo->beginTransaction(); // On commence une transaction pour assurer que les deux insertions se font correctement
        try {
            // Étape 1 : Vérifier si l'email existe
            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error_message = "Cette adresse e-mail est déjà utilisée.";
                $pdo->rollBack(); // On annule la transaction
            } else {
                // Gestion de la photo de profil... (votre code est bon)
                $photo_path = null;
                if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] == UPLOAD_ERR_OK) {
                    // ... votre logique d'upload ...
                     $upload_dir = '../../uploads/profiles/'; // Chemin corrigé pour être à la racine du projet
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    $file_name = uniqid('user_') . '_' . basename($_FILES['photo_profil']['name']);
                    $target_file = $upload_dir . $file_name;
                    $photo_path = 'uploads/profiles/' . $file_name; // Chemin relatif depuis la racine
                    move_uploaded_file($_FILES['photo_profil']['tmp_name'], $target_file);
                }

                // Étape 2 : Insérer dans la table `utilisateurs`
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO utilisateurs (nom, prenom, email, password, role, telephone, adresse, sexe, date_naissance, photo_profil, specialite, grade, contrat, groupe_sanguin, statut) 
                        VALUES (:nom, :prenom, :email, :password, :role, :telephone, :adresse, :sexe, :date_naissance, :photo_profil, :specialite, :grade, :contrat, :groupe_sanguin, 1)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':password' => $hashed_password, ':role' => $role,
                    ':telephone' => $telephone, ':adresse' => $adresse, ':sexe' => $sexe, 
                    ':date_naissance' => empty($date_naissance) ? null : $date_naissance, 
                    ':photo_profil' => $photo_path, ':specialite' => $specialite, ':grade' => $grade, ':contrat' => $contrat, 
                    ':groupe_sanguin' => $groupe_sanguin
                ]);
                
                // --- LOGIQUE D'INSCRIPTION AUTOMATIQUE ---
                if ($role === 'etudiant') {
                    $etudiant_id = $pdo->lastInsertId(); // On récupère l'ID de l'étudiant qu'on vient de créer

                    $sql_inscription = "INSERT INTO inscriptions (etudiant_id, classe_id, annee_id, date_inscription, statut)
                                        VALUES (:etudiant_id, :classe_id, :annee_id, CURDATE(), 'actif')";
                    
                    $stmt_inscription = $pdo->prepare($sql_inscription);
                    $stmt_inscription->execute([
                        ':etudiant_id' => $etudiant_id,
                        ':classe_id' => $classe_id,
                        ':annee_id' => $annee_id
                    ]);
                }
                
                $pdo->commit(); // Tout s'est bien passé, on valide les changements
                $success_message = "L'utilisateur '$prenom $nom' a été créé et inscrit avec succès !";
            }
        } catch (PDOException $e) {
            $pdo->rollBack(); // En cas d'erreur, on annule tout
            $error_message = "Erreur de base de données : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - GestiSchool Aurora</title>
    <!-- Les styles CSS sont identiques, pas besoin de les changer -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #6a11cb; --secondary-color: #2575fc; --accent-color: #30cfd0; --text-color: #e0e0e0; --glass-bg: rgba(255, 255, 255, 0.05); --glass-border: rgba(255, 255, 255, 0.2); }
        @keyframes gradient-animation { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        body { font-family: 'Poppins', sans-serif; margin: 0; padding: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; color: var(--text-color); background: linear-gradient(-45deg, var(--primary-color), var(--secondary-color), #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradient-animation 15s ease infinite; padding-top: 3rem; padding-bottom: 3rem; }
        .auth-card { background: var(--glass-bg); border-radius: 20px; border: 1px solid var(--glass-border); box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); padding: 2.5rem; width: 100%; max-width: 800px; animation: fadeInUp 0.8s ease-out forwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .auth-card .logo { font-size: 3rem; margin-bottom: 0.5rem; background: -webkit-linear-gradient(45deg, var(--accent-color), #fff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 15px rgba(48, 207, 208, 0.4); }
        .form-control, .form-select { background: rgba(255, 255, 255, 0.1); border: 1px solid var(--glass-border); border-radius: 8px; color: var(--text-color); transition: all 0.3s ease; }
        .form-control::placeholder, .form-select { color: rgba(255, 255, 255, 0.6); }
        .form-control:focus, .form-select:focus { background: rgba(255, 255, 255, 0.15); color: white; box-shadow: 0 0 0 0.25rem rgba(48, 207, 208, 0.25); border-color: var(--accent-color); }
        .form-select option { background: #2c2c54; color: white; }
        .btn-aurora { background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); border: none; border-radius: 10px; padding: 0.8rem; font-weight: 600; color: #fff; transition: all 0.4s ease; box-shadow: 0 4px 15px 0 rgba(0, 0, 0, 0.2); background-size: 200% auto; }
        .btn-aurora:hover { background-position: right center; transform: scale(1.05); }
        .form-section-title { font-weight: 500; margin-top: 1.5rem; margin-bottom: 1rem; color: #fff; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem; }
        .conditional-fields { max-height: 0; opacity: 0; overflow: hidden; transition: all 0.7s ease-in-out; margin-top: 0; padding-top: 0; }
        .conditional-fields.show { max-height: 500px; opacity: 1; margin-top: 1rem; padding-top: 1rem; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="text-center mb-4"><i class="fas fa-user-plus logo"></i><h2>Créer un Compte</h2></div>
        <?php if ($success_message): ?><div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

        <form method="POST" action="register.php" enctype="multipart/form-data">
            <!-- Informations Personnelles -->
            <div class="form-section-title"><i class="fas fa-id-card me-2"></i>Informations Personnelles</div>
            <!-- ... (les champs restent les mêmes) ... -->
             <div class="row">
                <div class="col-md-6 mb-3"><input type="text" class="form-control" name="nom" placeholder="Nom *" required></div>
                <div class="col-md-6 mb-3"><input type="text" class="form-control" name="prenom" placeholder="Prénom"></div>
                <!-- ... autres champs personnels ... -->
            </div>


            <!-- Accès & Rôle -->
            <div class="form-section-title"><i class="fas fa-lock me-2"></i>Accès & Rôle</div>
            <div class="row">
                <div class="col-md-6 mb-3"><input type="email" class="form-control" name="email" placeholder="Adresse e-mail *" required></div>
                <div class="col-md-6 mb-3"><input type="password" class="form-control" name="password" placeholder="Mot de passe *" required></div>
                <div class="col-12 mb-3">
                    <select name="role" id="role-select" class="form-select" required>
                        <option value="" disabled selected>Sélectionner un rôle *</option>
                        <option value="admin">Administrateur</option>
                        <option value="enseignant">Enseignant</option>
                        <option value="etudiant">Étudiant</option>
                    </select>
                </div>
            </div>
            
            <!-- CHAMPS CONDITIONNELS POUR L'ENSEIGNANT -->
            <div id="enseignant-fields" class="conditional-fields">
                 <div class="form-section-title"><i class="fas fa-chalkboard-teacher me-2"></i>Détails Enseignant</div>
                 <div class="row">
                    <div class="col-md-4 mb-3"><input type="text" class="form-control" name="specialite" placeholder="Spécialité"></div>
                    <div class="col-md-4 mb-3"><input type="text" class="form-control" name="grade" placeholder="Grade"></div>
                    <div class="col-md-4 mb-3"><select name="contrat" class="form-select"><option value="" disabled selected>Contrat</option><option value="titulaire">Titulaire</option><option value="contractuel">Contractuel</option><option value="vacataire">Vacataire</option></select></div>
                </div>
            </div>

            <!-- NOUVEAU : CHAMPS CONDITIONNELS POUR L'ÉTUDIANT -->
            <div id="etudiant-fields" class="conditional-fields">
                 <div class="form-section-title"><i class="fas fa-user-graduate me-2"></i>Inscription de l'Étudiant</div>
                 <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="annee_id" class="form-label">Année Scolaire *</label>
                        <select name="annee_id" id="annee_id" class="form-select">
                            <option value="" disabled selected>Choisir une année</option>
                            <?php foreach ($annees_scolaires as $annee): ?>
                                <option value="<?= $annee['id'] ?>"><?= htmlspecialchars($annee['annee']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="classe_id" class="form-label">Classe *</label>
                        <select name="classe_id" id="classe_id" class="form-select">
                             <option value="" disabled selected>Choisir une classe</option>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?= $classe['id'] ?>"><?= htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-aurora btn-lg">Inscrire l'Utilisateur</button>
            </div>
        </form>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role-select');
            const enseignantFields = document.getElementById('enseignant-fields');
            // NOUVEAU : On cible les champs de l'étudiant
            const etudiantFields = document.getElementById('etudiant-fields');

            roleSelect.addEventListener('change', function() {
                // On affiche les champs de l'enseignant si le rôle est "enseignant"
                enseignantFields.classList.toggle('show', this.value === 'enseignant');
                // NOUVEAU : On affiche les champs de l'étudiant si le rôle est "etudiant"
                etudiantFields.classList.toggle('show', this.value === 'etudiant');
            });
        });
    </script>
</body>
</html>