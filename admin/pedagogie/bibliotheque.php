<?php
session_start();
require_once '../../config/db.php';

// --- SÉCURITÉ ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

// --- LOGIQUE CRUD (CREATE, READ, UPDATE, DELETE) ---

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$error_message = '';
$success_message = '';

// --- GESTION DES RESSOURCES ---

// AJOUTER ou MODIFIER une ressource
if ($action === 'save_ressource') {
    $id = $_POST['id'] ?? null;
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];
    $categorie_id = $_POST['categorie_id'];
    $lien = ($type === 'lien') ? trim($_POST['lien']) : null;
    $fichier_actuel = $_POST['fichier_actuel'] ?? null;
    
    if (empty($titre) || empty($type) || empty($categorie_id)) {
        $error_message = "Titre, type et catégorie sont obligatoires.";
    } else {
        $fichier_path = $fichier_actuel; // Garder l'ancien fichier par défaut
        if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/bibliotheque/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_name = uniqid('res_') . '_' . basename($_FILES['fichier']['name']);
            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $upload_dir . $file_name)) {
                $fichier_path = 'uploads/bibliotheque/' . $file_name;
            } else {
                $error_message = "Erreur lors du téléchargement du fichier.";
            }
        }
        
        if (empty($error_message)) {
            try {
                if ($id) { // Modification
                    $stmt = $pdo->prepare("UPDATE ressources_bibliotheque SET titre=?, description=?, type=?, categorie_id=?, lien=?, fichier=? WHERE id=?");
                    $stmt->execute([$titre, $description, $type, $categorie_id, $lien, $fichier_path, $id]);
                    $success_message = "Ressource modifiée avec succès.";
                } else { // Ajout
                    $stmt = $pdo->prepare("INSERT INTO ressources_bibliotheque (titre, description, type, categorie_id, lien, fichier) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$titre, $description, $type, $categorie_id, $lien, $fichier_path]);
                    $success_message = "Ressource ajoutée avec succès.";
                }
            } catch (PDOException $e) { $error_message = "Erreur BDD : " . $e->getMessage(); }
        }
    }
}

// SUPPRIMER une ressource
if ($action === 'delete_ressource' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM ressources_bibliotheque WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $success_message = "Ressource supprimée avec succès.";
    } catch (PDOException $e) { $error_message = "Erreur BDD : " . $e->getMessage(); }
}

// --- GESTION DES CATÉGORIES ---

// AJOUTER ou MODIFIER une catégorie
if ($action === 'save_categorie') {
    $id = $_POST['id_cat'] ?? null;
    $nom = trim($_POST['nom_cat']);
    if (!empty($nom)) {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE categories_ressources SET nom=? WHERE id=?");
                $stmt->execute([$nom, $id]);
                $success_message = "Catégorie modifiée.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories_ressources (nom) VALUES (?)");
                $stmt->execute([$nom]);
                $success_message = "Catégorie ajoutée.";
            }
        } catch (PDOException $e) { $error_message = "Erreur BDD : " . $e->getMessage(); }
    } else { $error_message = "Le nom de la catégorie est obligatoire."; }
}

// SUPPRIMER une catégorie
if ($action === 'delete_categorie' && isset($_GET['id'])) {
    try {
        // On ne peut supprimer que si aucune ressource n'utilise cette catégorie
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM ressources_bibliotheque WHERE categorie_id = ?");
        $stmtCheck->execute([$_GET['id']]);
        if ($stmtCheck->fetchColumn() > 0) {
            $error_message = "Impossible de supprimer : cette catégorie est utilisée par des ressources.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories_ressources WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $success_message = "Catégorie supprimée.";
        }
    } catch (PDOException $e) { $error_message = "Erreur BDD : " . $e->getMessage(); }
}

// --- LECTURE (READ) ---
try {
    $ressources = $pdo->query("SELECT r.*, c.nom as categorie_nom FROM ressources_bibliotheque r JOIN categories_ressources c ON r.categorie_id = c.id ORDER BY r.date_ajout DESC")->fetchAll(PDO::FETCH_ASSOC);
    $categories = $pdo->query("SELECT c.*, COUNT(r.id) as count FROM categories_ressources c LEFT JOIN ressources_bibliotheque r ON c.id = r.categorie_id GROUP BY c.id ORDER BY c.nom")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des données : " . $e->getMessage();
    $ressources = [];
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bibliothèque - Admin GestiSchool</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS "Galaxy" du Dashboard Admin -->
    <style>
        :root { /* ... (votre palette de couleurs "Galaxy") ... */
             --bg-dark-primary: #0d1117; --bg-dark-secondary:rgb(120, 151, 196); --border-color: rgba(255, 255, 255, 0.1);
            --text-primary: #c9d1d9; --text-secondary: #8b949e; --accent-glow-1: #00f2ff; --accent-glow-2: #da00ff;
            --font-primary: 'Poppins', sans-serif; --font-display: 'Orbitron', sans-serif;
        }
        body { font-family: var(--font-primary); background-color: var(--bg-dark-primary); color: var(--text-primary); background-image: radial-gradient(circle at 1px 1px, rgba(255, 255, 255, 0.05) 1px, transparent 0); background-size: 20px 20px; }
        .page-wrapper { display: flex; min-height: 100vh; }
        #sidebar { /* ... (styles de la sidebar) ... */ 
            width: 260px; position: fixed; top: 0; left: 0; height: 100vh; z-index: 1000; background: rgba(16, 19, 26, 0.6); backdrop-filter: blur(10px); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; 
        }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid var(--border-color); }
        .sidebar-header .logo { font-family: var(--font-display); font-size: 1.5rem; color: #fff; text-shadow: 0 0 5px var(--accent-glow-1), 0 0 10px var(--accent-glow-2); }
        .sidebar-nav { padding: 1rem; flex-grow: 1; overflow-y: auto; }
        .nav-link { display: flex; align-items: center; padding: 0.75rem 1rem; color: var(--text-primary); text-decoration: none; border-radius: 8px; margin-bottom: 5px; transition: all 0.2s ease; }
        .nav-link i { width: 25px; margin-right: 15px; text-align: center; font-size: 1.1rem; }
        .nav-link:hover, .nav-link.active { background: rgba(255, 255, 255, 0.05); color: #fff; box-shadow: 0 0 10px rgba(0, 242, 255, 0.2); }
        #main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2rem; }
        .main-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .main-header h1 { font-family: var(--font-display); color: #fff; font-size: 2rem; }
        /* Styles pour les formulaires et modales */
        .modal-content { background-color: var(--bg-dark-secondary); border: 1px solid var(--border-color); }
        .modal-header, .modal-footer { border-color: var(--border-color); }
        .form-control, .form-select { background: rgba(0,0,0,0.3); border-color: var(--border-color); color: var(--text-primary); }
        .form-control:focus, .form-select:focus { background: rgba(0,0,0,0.4); color: #fff; box-shadow: 0 0 0 0.25rem rgba(0, 242, 255, 0.25); border-color: var(--accent-glow-1); }
        .form-select option { background-color: var(--bg-dark-primary); }
        .btn-glow { background: linear-gradient(45deg, var(--accent-glow-2), var(--accent-glow-1)); border: none; color: #fff; transition: all 0.3s; box-shadow: 0 0 15px rgba(0, 242, 255, 0.3); }
        .btn-glow:hover { box-shadow: 0 0 25px rgba(0, 242, 255, 0.6); transform: translateY(-2px); }
        .table-dark-galaxy { background-color: transparent; }
        .table-dark-galaxy th, .table-dark-galaxy td { background-color: var(--bg-dark-secondary); border-color: var(--border-color); vertical-align: middle; }
        .table-dark-galaxy thead th { background-color: rgba(0,0,0,0.3); }
        /* Style pour les cartes de catégories */
        .category-card { background: var(--bg-dark-secondary); border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; transition: all 0.2s ease; }
        .category-card:hover { border-color: var(--accent-glow-1); box-shadow: 0 0 10px rgba(0, 242, 255, 0.1); }
    </style>
</head>
<body>
<div class="page-wrapper">
    <!-- Barre Latérale -->
    <aside id="sidebar">
        <div class="sidebar-header"><a href="../dashboard.php" class="logo"><i class="fas fa-meteor"></i> GestiSchool</a></div>
        <nav class="sidebar-nav">
             <!-- ... (Collez ici les liens de navigation de votre dashboard admin) ... -->
             <a class="nav-link" href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a>
             <a class="nav-link active" href="bibliotheque.php"><i class="fas fa-book-reader"></i> Bibliothèque</a>
             <!-- ... etc ... -->
        </nav>
    </aside>

    <!-- Contenu Principal -->
    <main id="main-content">
        <header class="main-header">
            <h1><i class="fas fa-book-reader me-2"></i>Gestion de la Bibliothèque</h1>
            <button class="btn btn-glow" data-bs-toggle="modal" data-bs-target="#modalAddRessource">
                <i class="fas fa-plus-circle me-2"></i>Ajouter une Ressource
            </button>
        </header>

        <!-- Notifications -->
        <?php if ($success_message): ?><div class="alert alert-success"><?= $success_message ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><?= $error_message ?></div><?php endif; ?>

        <!-- Gestion des Catégories -->
        <div class="card mb-4" style="background:var(--bg-dark-secondary); border-color:var(--border-color);">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:transparent; border-color:var(--border-color);">
                <h5 class="mb-0">Catégories</h5>
                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalAddCategorie">
                    <i class="fas fa-plus"></i> Ajouter
                </button>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($categories as $cat): ?>
                    <div class="category-card d-flex align-items-center gap-3">
                        <span><strong><?= htmlspecialchars($cat['nom']) ?></strong> (<?= $cat['count'] ?>)</span>
                        <div class="ms-auto">
                            <a href="?action=delete_categorie&id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Voulez-vous vraiment supprimer cette catégorie ?');"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Tableau des Ressources -->
        <div class="card" style="background:var(--bg-dark-secondary); border-color:var(--border-color);">
            <div class="card-header" style="background:transparent; border-color:var(--border-color);">
                <h5 class="mb-0">Liste des Ressources</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-dark-galaxy">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Type</th>
                                <th>Catégorie</th>
                                <th>Date d'ajout</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ressources as $res): ?>
                            <tr>
                                <td><?= htmlspecialchars($res['titre']) ?></td>
                                <td><span class="badge bg-info"><?= ucfirst($res['type']) ?></span></td>
                                <td><?= htmlspecialchars($res['categorie_nom']) ?></td>
                                <td><?= date('d/m/Y', strtotime($res['date_ajout'])) ?></td>
                              <td>
                                     <button class="btn btn-sm btn-outline-primary edit-ressource-btn"
    data-id="<?= $res['id'] ?>"
    data-titre="<?= htmlspecialchars($res['titre'] ?? '') ?>"
    data-description="<?= htmlspecialchars($res['description'] ?? '') ?>"
    data-type="<?= $res['type'] ?? '' ?>"
    data-categorie_id="<?= $res['categorie_id'] ?? '' ?>"
    data-lien="<?= htmlspecialchars($res['lien'] ?? '') ?>"
    data-fichier="<?= htmlspecialchars($res['fichier'] ?? '') ?>">
    <i class="fas fa-edit"></i>
    </button>

                                    <a href="?action=delete_ressource&id=<?= $res['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Voulez-vous vraiment supprimer cette ressource ?');">
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

<!-- Modale Ajouter/Modifier Ressource -->
<div class="modal fade" id="modalAddRessource" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="bibliotheque.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_ressource">
                <input type="hidden" name="id" id="ressource-id">
                <input type="hidden" name="fichier_actuel" id="ressource-fichier-actuel">
                <div class="modal-header">
                    <h5 class="modal-title" id="ressource-modal-title">Ajouter une Ressource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="titre" class="form-label">Titre *</label>
                        <input type="text" class="form-control" name="titre" id="ressource-titre" required>
                    </div>
                     <div class="mb-3">
                        <label for="categorie_id" class="form-label">Catégorie *</label>
                        <select class="form-select" name="categorie_id" id="ressource-categorie" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">Type *</label>
                        <select class="form-select" name="type" id="ressource-type" required>
                            <option value="livre">Livre</option>
                            <option value="numerique">Fichier Numérique</option>
                            <option value="lien">Lien Externe</option>
                        </select>
                    </div>
                    <div class="mb-3" id="lien-field" style="display:none;">
                        <label for="lien" class="form-label">URL du lien</label>
                        <input type="url" class="form-control" name="lien" id="ressource-lien" placeholder="https://...">
                    </div>
                    <div class="mb-3" id="fichier-field">
                        <label for="fichier" class="form-label">Fichier</label>
                        <input type="file" class="form-control" name="fichier" id="ressource-fichier">
                        <small class="text-muted" id="fichier-info"></small>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="ressource-description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-glow">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modale Ajouter Catégorie -->
<div class="modal fade" id="modalAddCategorie" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="bibliotheque.php">
                <input type="hidden" name="action" value="save_categorie">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter une Catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label for="nom_cat" class="form-label">Nom de la catégorie *</label>
                    <input type="text" class="form-control" name="nom_cat" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-glow">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ressourceTypeSelect = document.getElementById('ressource-type');
    const lienField = document.getElementById('lien-field');
    const fichierField = document.getElementById('fichier-field');

    function toggleFields() {
        if (ressourceTypeSelect.value === 'lien') {
            lienField.style.display = 'block';
            fichierField.style.display = 'none';
        } else {
            lienField.style.display = 'none';
            fichierField.style.display = 'block';
        }
    }
    ressourceTypeSelect.addEventListener('change', toggleFields);
    toggleFields(); // Initial check

    // Gestion de la modification
    const modal = new bootstrap.Modal(document.getElementById('modalAddRessource'));
    document.querySelectorAll('.edit-ressource-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('ressource-modal-title').innerText = 'Modifier la Ressource';
            document.getElementById('ressource-id').value = this.dataset.id;
            document.getElementById('ressource-titre').value = this.dataset.titre;
            document.getElementById('ressource-description').value = this.dataset.description;
            document.getElementById('ressource-type').value = this.dataset.type;
            document.getElementById('ressource-categorie').value = this.dataset.categorie_id;
            document.getElementById('ressource-lien').value = this.dataset.lien;
            document.getElementById('ressource-fichier-actuel').value = this.dataset.fichier;
            if(this.dataset.fichier) {
                document.getElementById('fichier-info').innerText = 'Fichier actuel : ' + this.dataset.fichier.split('/').pop() + '. Laissez vide pour ne pas changer.';
            } else {
                 document.getElementById('fichier-info').innerText = '';
            }
            toggleFields();
            modal.show();
        });
    });

    // Réinitialiser la modale à la fermeture
    document.getElementById('modalAddRessource').addEventListener('hidden.bs.modal', function () {
        document.getElementById('ressource-modal-title').innerText = 'Ajouter une Ressource';
        document.querySelector('#modalAddRessource form').reset();
        document.getElementById('ressource-id').value = '';
        document.getElementById('ressource-fichier-actuel').value = '';
        document.getElementById('fichier-info').innerText = '';
        toggleFields();
    });
});
</script>
</body>
</html>