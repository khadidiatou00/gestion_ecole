<?php
session_start();
require_once '../../config/db.php';

// --- SÉCURITÉ ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

$message = '';
$message_type = '';

// --- LOGIQUE CRUD ---

// AJOUTER ou MODIFIER un enseignant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Récupération des données du formulaire
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $telephone = $_POST['telephone'] ?? null;
    $adresse = trim($_POST['adresse']) ?? null;
    $sexe = $_POST['sexe'] ?? null;
    $specialite = trim($_POST['specialite']) ?? null;
    $grade = trim($_POST['grade']) ?? null;
    $contrat = $_POST['contrat'] ?? null;
    $statut = filter_input(INPUT_POST, 'statut', FILTER_VALIDATE_INT);
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    try {
        $photo_path = $_POST['existing_photo'] ?? null;

        // Gestion de l'upload de la photo de profil
        if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            // Supprimer l'ancienne photo si elle existe
            if ($photo_path && file_exists('../../' . $photo_path)) {
                unlink('../../' . $photo_path);
            }
            
            $file_name = uniqid('teacher_') . '_' . basename($_FILES['photo_profil']['name']);
            $target_file = $upload_dir . $file_name;
            move_uploaded_file($_FILES['photo_profil']['tmp_name'], $target_file);
            $photo_path = 'uploads/profiles/' . $file_name;
        }

        if ($_POST['action'] === 'add') {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql = "INSERT INTO utilisateurs (nom, prenom, email, password, role, telephone, adresse, sexe, photo_profil, specialite, grade, contrat, statut) 
                    VALUES (?, ?, ?, ?, 'enseignant', ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nom, $prenom, $email, $password, $telephone, $adresse, $sexe, $photo_path, $specialite, $grade, $contrat, $statut]);
            $message = "L'enseignant a été ajouté avec succès !";
            $message_type = 'success';

        } elseif ($_POST['action'] === 'edit') {
            $sql = "UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, telephone = ?, adresse = ?, sexe = ?, photo_profil = ?, specialite = ?, grade = ?, contrat = ?, statut = ? WHERE id = ? AND role = 'enseignant'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nom, $prenom, $email, $telephone, $adresse, $sexe, $photo_path, $specialite, $grade, $contrat, $statut, $id]);
            $message = "Les informations de l'enseignant ont été modifiées avec succès !";
            $message_type = 'success';
        }
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            $message = "Erreur : L'adresse e-mail '" . htmlspecialchars($email) . "' est déjà utilisée.";
        } else {
            $message = "Erreur lors de l'opération : " . $e->getMessage();
        }
        $message_type = 'danger';
    }
}

// SUPPRIMER un enseignant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete'])) {
    try {
        $id = filter_input(INPUT_POST, 'id_delete', FILTER_VALIDATE_INT);
        
        // Récupérer le chemin de la photo pour la supprimer du serveur
        $stmt_photo = $pdo->prepare("SELECT photo_profil FROM utilisateurs WHERE id = ?");
        $stmt_photo->execute([$id]);
        $photo_path = $stmt_photo->fetchColumn();

        if ($photo_path && file_exists('../../' . $photo_path)) {
            unlink('../../' . $photo_path);
        }

        $sql = "DELETE FROM utilisateurs WHERE id = ? AND role = 'enseignant'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $message = "L'enseignant a été supprimé avec succès.";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "Erreur lors de la suppression. Il est possible que cet enseignant soit lié à d'autres données.";
        $message_type = 'danger';
    }
}

// --- RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE ---
try {
    $enseignants = $pdo->query("SELECT * FROM utilisateurs WHERE role = 'enseignant' ORDER BY nom, prenom ASC")->fetchAll();
} catch (PDOException $e) {
    $error_db = "Erreur lors de la récupération des enseignants : " . $e->getMessage();
    $enseignants = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Enseignants - GestiSchool Galaxy</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-dark-primary: #0d1117; --bg-dark-secondary: #161b22; --border-color: rgba(255, 255, 255, 0.1);
            --text-primary: #c9d1d9; --text-secondary: #8b949e; --accent-glow-1: #00f2ff;
            --accent-glow-2: #da00ff; --font-primary: 'Poppins', sans-serif; --font-display: 'Orbitron', sans-serif;
            --success: #28a745; --danger: #dc3545;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        body { font-family: var(--font-primary); background-color: var(--bg-dark-primary); color: var(--text-primary);
            background-image: radial-gradient(circle at 1px 1px, rgba(255, 255, 255, 0.05) 1px, transparent 0); background-size: 20px 20px;
        }
        .page-wrapper { display: flex; min-height: 100vh; }
        #sidebar {
            width: 260px; position: fixed; top: 0; left: 0; height: 100vh; z-index: 1000; background: rgba(16, 19, 26, 0.6);
            backdrop-filter: blur(10px); border-right: 1px solid var(--border-color); transition: all 0.3s ease;
            display: flex; flex-direction: column;
        }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid var(--border-color); }
        .sidebar-header .logo { font-family: var(--font-display); font-size: 1.5rem; color: #fff; text-shadow: 0 0 5px var(--accent-glow-1), 0 0 10px var(--accent-glow-2); }
        .sidebar-header .logo i { margin-right: 10px; }
        .sidebar-nav { padding: 1rem; flex-grow: 1; overflow-y: auto; }
        .nav-category { font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; padding: 0.5rem 1rem; font-weight: 600; }
        .nav-link { display: flex; align-items: center; padding: 0.75rem 1rem; color: var(--text-primary); text-decoration: none; border-radius: 8px; margin-bottom: 5px; transition: all 0.2s ease; }
        .nav-link i { width: 25px; margin-right: 15px; text-align: center; font-size: 1.1rem; }
        .nav-link:hover, .nav-link.active { background: rgba(255, 255, 255, 0.05); color: #fff; box-shadow: 0 0 10px rgba(0, 242, 255, 0.2); }
        .nav-link .arrow { margin-left: auto; transition: transform 0.3s ease; }
        .nav-link[aria-expanded="true"] .arrow { transform: rotate(90deg); }
        #main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2rem; transition: all 0.3s ease; }
        .main-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .main-header h1 { font-family: var(--font-display); color: #fff; font-size: 2rem; }
        .content-card { background: var(--bg-dark-secondary); border-radius: 12px; padding: 2rem; border: 1px solid var(--border-color); animation: fadeIn 0.5s ease; }
        .btn-glow {
            border: 1px solid var(--accent-glow-1); color: var(--accent-glow-1); background-color: transparent;
            padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; transition: all 0.3s ease;
        }
        .btn-glow:hover { background-color: var(--accent-glow-1); color: var(--bg-dark-primary); box-shadow: 0 0 15px var(--accent-glow-1); }
        .galaxy-table { width: 100%; color: var(--text-primary); }
        .galaxy-table th { background-color: rgba(255, 255, 255, 0.05); padding: 1rem; border-bottom: 1px solid var(--border-color); }
        .galaxy-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .galaxy-table tbody tr:hover { background-color: rgba(255, 255, 255, 0.02); }
        .profile-pic { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); }
        .profile-pic-default { width: 40px; height: 40px; border-radius: 50%; background-color: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .status-badge { padding: 0.25em 0.6em; font-size: 0.8em; border-radius: 10px; color: #fff; }
        .status-1 { background-color: var(--success); box-shadow: 0 0 8px var(--success); }
        .status-0 { background-color: var(--danger); box-shadow: 0 0 8px var(--danger); }
        .btn-action { background: transparent; border: 1px solid; border-radius: 5px; padding: 5px 10px; margin: 0 2px; transition: all 0.2s; }
        .btn-action-edit { color: var(--accent-glow-1); border-color: var(--accent-glow-1); }
        .btn-action-edit:hover { background: var(--accent-glow-1); color: var(--bg-dark-primary); }
        .btn-action-delete { color: #dc3545; border-color: #dc3545; }
        .btn-action-delete:hover { background: #dc3545; color: #fff; }
        .modal-content { background: rgba(16, 19, 26, 0.8); backdrop-filter: blur(10px); border: 1px solid var(--border-color); color: var(--text-primary); }
        .modal-header, .modal-footer { border-color: var(--border-color); }
        .form-control, .form-select { background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: var(--text-primary); }
        .form-control:focus, .form-select:focus { background: rgba(0,0,0,0.4); border-color: var(--accent-glow-1); box-shadow: 0 0 10px var(--accent-glow-1); color: #fff; }
        .form-select option { background-color: var(--bg-dark-secondary); }
        .alert { background-color: rgba(255, 255, 255, 0.1); border: 1px solid var(--border-color); }
        @media (max-width: 992px) {
            #sidebar { left: -260px; } #sidebar.active { left: 0; }
            #main-content { margin-left: 0; width: 100%; }
            #sidebar-toggle { display: block; background: transparent; color: var(--text-primary); border: none; font-size: 1.2rem; }
        }
        #sidebar-toggle { display: none; }
    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- ============================================================== -->
    <!-- Barre Latérale -->
    <!-- ============================================================== -->
    <aside id="sidebar">
        <div class="sidebar-header">
            <a href="../dashboard.php" class="logo"><i class="fas fa-meteor"></i> GestiSchool</a>
        </div>
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
                <li class="nav-category">Gestion Globale</li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#gestionCollapse" role="button" aria-expanded="false" aria-controls="gestionCollapse">
                        <i class="fas fa-university"></i> Scolarité <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="collapse" id="gestionCollapse">
                        <ul class="nav flex-column ps-4">
                            <li><a class="nav-link" href="../gestion/classes.php">Classes</a></li>
                            <li><a class="nav-link" href="../gestion/matieres.php">Matières</a></li>
                            <li><a class="nav-link" href="../gestion/salles.php">Salles</a></li>
                            <li><a class="nav-link" href="../gestion/annees.php">Années Scolaires</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#personnelCollapse" role="button" aria-expanded="true" aria-controls="personnelCollapse">
                        <i class="fas fa-users-cog"></i> Personnel <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="collapse show" id="personnelCollapse">
                        <ul class="nav flex-column ps-4">
                            <li><a class="nav-link active" href="enseignants.php">Enseignants</a></li>
                            <li><a class="nav-link" href="etudiants.php">Étudiants</a></li>
                        </ul>
                    </div>
                </li>
                <!-- ... autres menus ... -->
            </ul>
        </nav>
    </aside>

    <!-- ============================================================== -->
    <!-- Contenu Principal -->
    <!-- ============================================================== -->
    <main id="main-content">
        <header class="main-header">
            <div>
                <button class="btn" id="sidebar-toggle"><i class="fas fa-bars"></i></button>
                <h1>Gestion des Enseignants</h1>
            </div>
            <button class="btn btn-glow" data-bs-toggle="modal" data-bs-target="#enseignantModal" id="add-enseignant-btn">
                <i class="fas fa-user-plus me-2"></i> Ajouter un Enseignant
            </button>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type === 'success' ? 'info' : 'danger' ?>" role="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (isset($error_db)): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error_db) ?></div>
        <?php endif; ?>

        <div class="content-card">
            <div class="table-responsive">
                <table class="galaxy-table">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Nom & Prénom</th>
                            <th>Email</th>
                            <th>Spécialité</th>
                            <th>Contrat</th>
                            <th class="text-center">Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enseignants)): ?>
                            <tr><td colspan="7" class="text-center">Aucun enseignant trouvé.</td></tr>
                        <?php else: ?>
                            <?php foreach ($enseignants as $ens): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($ens['photo_profil'])): ?>
                                            <img src="../../<?= htmlspecialchars($ens['photo_profil']) ?>" alt="Photo de profil" class="profile-pic">
                                        <?php else: ?>
                                            <div class="profile-pic-default"><i class="fas fa-user-astronaut"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($ens['nom'] . ' ' . $ens['prenom']) ?></td>
                                    <td><?= htmlspecialchars($ens['email']) ?></td>
                                    <td><?= htmlspecialchars($ens['specialite'] ?: 'N/A') ?></td>
                                    <td><?= ucfirst(htmlspecialchars($ens['contrat'] ?: 'N/A')) ?></td>
                                    <td class="text-center">
                                        <span class="status-badge status-<?= $ens['statut'] ?>">
                                            <?= $ens['statut'] ? 'Actif' : 'Inactif' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-action btn-action-edit edit-btn"
                                            data-bs-toggle="modal" data-bs-target="#enseignantModal"
                                            data-id="<?= $ens['id'] ?>"
                                            data-nom="<?= htmlspecialchars($ens['nom']) ?>"
                                            data-prenom="<?= htmlspecialchars($ens['prenom']) ?>"
                                            data-email="<?= htmlspecialchars($ens['email']) ?>"
                                            data-telephone="<?= htmlspecialchars($ens['telephone']) ?>"
                                            data-adresse="<?= htmlspecialchars($ens['adresse']) ?>"
                                            data-sexe="<?= htmlspecialchars($ens['sexe']) ?>"
                                            data-specialite="<?= htmlspecialchars($ens['specialite']) ?>"
                                            data-grade="<?= htmlspecialchars($ens['grade']) ?>"
                                            data-contrat="<?= htmlspecialchars($ens['contrat']) ?>"
                                            data-statut="<?= $ens['statut'] ?>"
                                            data-photo_profil="<?= htmlspecialchars($ens['photo_profil']) ?>">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <button class="btn btn-action btn-action-delete delete-btn" data-id="<?= $ens['id'] ?>" data-nom="<?= htmlspecialchars($ens['prenom'] . ' ' . $ens['nom']) ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modale pour Ajouter/Modifier un enseignant -->
<div class="modal fade" id="enseignantModal" tabindex="-1" aria-labelledby="enseignantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="enseignants.php" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="enseignantModalLabel">Ajouter un Enseignant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background-color: #fff;"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="id" id="form-id">
                    <input type="hidden" name="existing_photo" id="form-existing_photo">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Nom</label><input type="text" class="form-control" id="form-nom" name="nom" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Prénom</label><input type="text" class="form-control" id="form-prenom" name="prenom"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" class="form-control" id="form-email" name="email" required></div>
                        <div class="col-md-6 mb-3" id="password-field"><label class="form-label">Mot de passe initial</label><input type="password" class="form-control" name="password"></div>
                    </div>
                     <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Téléphone</label><input type="tel" class="form-control" id="form-telephone" name="telephone"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Sexe</label><select class="form-select" id="form-sexe" name="sexe"><option value="">Non spécifié</option><option value="M">Masculin</option><option value="F">Féminin</option></select></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Adresse</label><textarea class="form-control" id="form-adresse" name="adresse" rows="2"></textarea></div>
                    <hr style="border-color: var(--border-color);">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Spécialité</label><input type="text" class="form-control" id="form-specialite" name="specialite"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Grade</label><input type="text" class="form-control" id="form-grade" name="grade"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Type de contrat</label><select class="form-select" id="form-contrat" name="contrat"><option value="">Non spécifié</option><option value="titulaire">Titulaire</option><option value="contractuel">Contractuel</option><option value="vacataire">Vacataire</option></select></div>
                         <div class="col-md-6 mb-3"><label class="form-label">Statut</label><select class="form-select" id="form-statut" name="statut" required><option value="1">Actif</option><option value="0">Inactif</option></select></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Photo de profil</label><input type="file" class="form-control" name="photo_profil" accept="image/*"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-glow" id="form-submit-btn">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Formulaire caché pour la suppression -->
<form method="POST" action="enseignants.php" id="delete-form" class="d-none">
    <input type="hidden" name="action_delete" value="1">
    <input type="hidden" name="id_delete" id="id-to-delete">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('active'));
    }

    const modal = document.getElementById('enseignantModal');
    const modalTitle = document.getElementById('enseignantModalLabel');
    const form = modal.querySelector('form');
    const formAction = document.getElementById('form-action');
    const formId = document.getElementById('form-id');
    const formSubmitBtn = document.getElementById('form-submit-btn');
    const passwordField = document.getElementById('password-field');
    const passwordInput = passwordField.querySelector('input');
    
    document.getElementById('add-enseignant-btn').addEventListener('click', function () {
        modalTitle.textContent = 'Ajouter un Enseignant';
        form.reset();
        formAction.value = 'add';
        formId.value = '';
        formSubmitBtn.textContent = 'Ajouter';
        passwordField.style.display = 'block';
        passwordInput.required = true;
    });
    
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            const data = this.dataset;
            modalTitle.textContent = 'Modifier l\'Enseignant';
            form.reset();
            formAction.value = 'edit';
            formId.value = data.id;
            
            // Remplissage des champs
            document.getElementById('form-nom').value = data.nom;
            document.getElementById('form-prenom').value = data.prenom;
            document.getElementById('form-email').value = data.email;
            document.getElementById('form-telephone').value = data.telephone;
            document.getElementById('form-adresse').value = data.adresse;
            document.getElementById('form-sexe').value = data.sexe;
            document.getElementById('form-specialite').value = data.specialite;
            document.getElementById('form-grade').value = data.grade;
            document.getElementById('form-contrat').value = data.contrat;
            document.getElementById('form-statut').value = data.statut;
            document.getElementById('form-existing_photo').value = data.photo_profil;
            
            formSubmitBtn.textContent = 'Enregistrer les modifications';
            passwordField.style.display = 'none';
            passwordInput.required = false;
        });
    });

    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function () {
            const enseignantName = this.dataset.nom;
            const enseignantId = this.dataset.id;
            if (confirm(`Êtes-vous sûr de vouloir supprimer l'enseignant "${enseignantName}" ? Cette action est irréversible.`)) {
                document.getElementById('id-to-delete').value = enseignantId;
                document.getElementById('delete-form').submit();
            }
        });
    });
});
</script>

</body>
</html>