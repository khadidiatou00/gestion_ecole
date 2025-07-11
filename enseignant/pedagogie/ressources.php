<?php
session_start();
require_once '../../config/db.php';

// --- SÉCURITÉ ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'enseignant') {
    header('Location: ../../auth/login.php');
    exit();
}

$enseignant_id = $_SESSION['user_id'];
$annee_en_cours_id = $pdo->query("SELECT id FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetchColumn();
$message = '';
$message_type = '';

// --- LOGIQUE CRUD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $titre = trim($_POST['titre']);
    $type_fichier = $_POST['type_fichier'];
    $matiere_id = filter_input(INPUT_POST, 'matiere_id', FILTER_VALIDATE_INT);
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $fichier_path = $_POST['fichier_path'] ?? null; // Pour les liens

    try {
        if ($type_fichier !== 'lien') {
            $fichier_path = $_POST['existing_file'] ?? null;
            if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/ressources/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                if ($fichier_path && file_exists('../../' . $fichier_path)) {
                    unlink('../../' . $fichier_path);
                }
                
                $file_name = uniqid('res_') . '_' . basename($_FILES['fichier']['name']);
                $target_file = $upload_dir . $file_name;
                move_uploaded_file($_FILES['fichier']['tmp_name'], $target_file);
                $fichier_path = 'uploads/ressources/' . $file_name;
            }
        }

        if ($_POST['action'] === 'add') {
            $sql = "INSERT INTO cours (titre, matiere_id, enseignant_id, fichier_path, type_fichier, annee_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$titre, $matiere_id, $enseignant_id, $fichier_path, $type_fichier, $annee_en_cours_id]);
            $message = "La ressource a été ajoutée avec succès !";
            $message_type = 'success';
        } elseif ($_POST['action'] === 'edit') {
            $sql = "UPDATE cours SET titre = ?, matiere_id = ?, fichier_path = ?, type_fichier = ? WHERE id = ? AND enseignant_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$titre, $matiere_id, $fichier_path, $type_fichier, $id, $enseignant_id]);
            $message = "La ressource a été modifiée avec succès !";
            $message_type = 'success';
        }
    } catch (PDOException $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = 'danger';
    }
}

// SUPPRIMER une ressource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete'])) {
    try {
        $id = filter_input(INPUT_POST, 'id_delete', FILTER_VALIDATE_INT);
        $stmt = $pdo->prepare("SELECT fichier_path, type_fichier FROM cours WHERE id = ? AND enseignant_id = ?");
        $stmt->execute([$id, $enseignant_id]);
        $res = $stmt->fetch();

        if ($res) {
            if ($res['type_fichier'] !== 'lien' && $res['fichier_path'] && file_exists('../../' . $res['fichier_path'])) {
                unlink('../../' . $res['fichier_path']);
            }
            $stmt_delete = $pdo->prepare("DELETE FROM cours WHERE id = ?");
            $stmt_delete->execute([$id]);
            $message = "La ressource a été supprimée.";
            $message_type = 'success';
        }
    } catch (PDOException $e) {
        $message = "Erreur lors de la suppression.";
        $message_type = 'danger';
    }
}

// --- RÉCUPÉRATION DES DONNÉES ---
try {
    $matieres = $pdo->query("SELECT id, nom FROM matieres ORDER BY nom")->fetchAll();

    $sql_ressources = "SELECT c.*, m.nom as matiere_nom, CONCAT(u.prenom, ' ', u.nom) as enseignant_nom FROM cours c JOIN matieres m ON c.matiere_id = m.id JOIN utilisateurs u ON c.enseignant_id = u.id";
    $params = [];
    if (!empty($_GET['matiere_id'])) {
        $sql_ressources .= " WHERE c.matiere_id = ?";
        $params[] = $_GET['matiere_id'];
    }
    $sql_ressources .= " ORDER BY c.date_publication DESC";
    
    $stmt_ressources = $pdo->prepare($sql_ressources);
    $stmt_ressources->execute($params);
    $ressources = $stmt_ressources->fetchAll();

} catch (PDOException $e) {
    $error_db = "Erreur : " . $e->getMessage();
    $ressources = $matieres = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ressources Pédagogiques - GestiSchool Vibrant</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-main: #f4f7fc; --sidebar-bg: #ffffff; --card-bg: #ffffff;
            --primary: #4f46e5; --secondary: #64748b; --accent: #10b981;
            --text-dark: #1e293b; --text-light: #64748b; --border-color: #e2e8f0;
            --font-body: 'Poppins', sans-serif; --font-title: 'Montserrat', sans-serif;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        body { font-family: var(--font-body); background-color: var(--bg-main); color: var(--text-dark); }
        .page-wrapper { display: flex; min-height: 100vh; }
        #sidebar { width: 260px; position: fixed; top: 0; left: 0; height: 100vh; z-index: 1000; background: var(--sidebar-bg); border-right: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.05); display: flex; flex-direction: column; transition: all 0.3s ease; }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid var(--border-color); }
        .sidebar-header .logo { font-family: var(--font-title); font-size: 1.6rem; color: var(--primary); font-weight: 700; text-decoration: none; }
        .sidebar-nav { padding: 1rem; flex-grow: 1; overflow-y: auto; }
        .nav-category { font-size: 0.75rem; color: var(--text-light); text-transform: uppercase; padding: 1rem; font-weight: 600; letter-spacing: 0.5px; }
        .nav-link { display: flex; align-items: center; padding: 0.8rem 1rem; color: var(--secondary); text-decoration: none; border-radius: 8px; margin-bottom: 5px; font-weight: 500; transition: all 0.3s ease; }
        .nav-link i { width: 25px; margin-right: 15px; text-align: center; font-size: 1.2rem; transition: all 0.3s ease; }
        .nav-link:hover { background-color: #eef2ff; color: var(--primary); }
        .nav-link.active { background: var(--primary); color: #fff; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
        #main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2.5rem; }
        .main-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .main-header h1 { font-family: var(--font-title); font-weight: 700; }
        .content-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem 2rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.07), 0 4px 6px -2px rgba(0,0,0,0.05); animation: fadeIn 0.6s ease-out forwards; }
        .btn-primary-glow { background-color: var(--primary); color: #fff; border: none; border-radius: 8px; padding: 0.6rem 1.2rem; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4); }
        .btn-primary-glow:hover { transform: translateY(-2px); box-shadow: 0 7px 20px rgba(79, 70, 229, 0.5); }
        .form-control, .form-select { border-radius: 8px; border-color: var(--border-color); }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
        .modal-content { border-radius: 16px; border: none; }
        .resource-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; transition: all 0.3s ease; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .resource-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
        .resource-icon { font-size: 2rem; width: 50px; height: 50px; border-radius: 50%; display: grid; place-items: center; background: var(--primary); color: #fff; flex-shrink: 0; }
        .resource-card h5 { font-family: var(--font-title); font-weight: 600; }
        @media (max-width: 992px) { #sidebar { left: -260px; } #sidebar.active { left: 0; } #main-content { margin-left: 0; width: 100%; } #sidebar-toggle { display: block; } }
        #sidebar-toggle { display: none; background: transparent; border: none; font-size: 1.5rem; }
    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- Barre Latérale -->
    <aside id="sidebar">
        <div class="sidebar-header"><a href="../dashboard.php" class="logo"><i class="fas fa-graduation-cap"></i> GestiSchool</a></div>
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
                <li class="nav-category">Pédagogie</li>
                <li class="nav-item"><a class="nav-link" href="cours.php"><i class="fas fa-book-open"></i> Mes Cours</a></li>
                <li class="nav-item"><a class="nav-link" href="devoirs.php"><i class="fas fa-file-signature"></i> Devoirs</a></li>
                <li class="nav-item"><a class="nav-link" href="projets.php"><i class="fas fa-project-diagram"></i> Projets</a></li>
                <li class="nav-item"><a class="nav-link active" href="ressources.php"><i class="fas fa-folder-tree"></i> Ressources</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Contenu Principal -->
    <main id="main-content">
        <header class="main-header">
            <h1 class="d-flex align-items-center"><i class="fas fa-folder-tree me-3 text-primary"></i> Ressources Pédagogiques</h1>
            <button class="btn btn-primary-glow" data-bs-toggle="modal" data-bs-target="#resourceModal" id="add-resource-btn"><i class="fas fa-plus me-2"></i> Partager une ressource</button>
        </header>

        <?php if ($message): ?><div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if (isset($error_db)): ?><div class="alert alert-danger"><?= htmlspecialchars($error_db) ?></div><?php endif; ?>
        
        <div class="content-card mb-4">
            <form method="GET" action="ressources.php" class="row g-3">
                <div class="col-md-10"><label class="form-label">Filtrer par matière</label><select class="form-select" name="matiere_id" onchange="this.form.submit()"><option value="">Toutes les matières</option><?php foreach ($matieres as $matiere): ?><option value="<?= $matiere['id'] ?>" <?= (isset($_GET['matiere_id']) && $_GET['matiere_id'] == $matiere['id']) ? 'selected' : '' ?>><?= htmlspecialchars($matiere['nom']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2 d-flex align-items-end"><a href="ressources.php" class="btn btn-secondary w-100">Réinitialiser</a></div>
            </form>
        </div>

        <div class="row g-4">
            <?php if (empty($ressources)): ?>
                <div class="col-12"><p class="text-center text-muted mt-5">Aucune ressource partagée pour cette sélection.</p></div>
            <?php else: ?>
                <?php foreach ($ressources as $res): 
                    $icon = 'fa-file-alt'; $link = '#'; $target = '';
                    if ($res['type_fichier'] === 'lien') { $icon = 'fa-link'; $link = htmlspecialchars($res['fichier_path']); $target = '_blank'; }
                    elseif ($res['type_fichier'] === 'video') { $icon = 'fa-video'; $link = htmlspecialchars($res['fichier_path']); $target = '_blank'; }
                    elseif (in_array($res['type_fichier'], ['pdf', 'doc', 'ppt'])) {
                         $icon = 'fa-file-' . $res['type_fichier'];
                         $link = '../../' . htmlspecialchars($res['fichier_path']);
                         $target = '_blank';
                    } else { $link = '../../' . htmlspecialchars($res['fichier_path']); $target = '_blank'; }
                ?>
                <div class="col-md-6">
                    <div class="resource-card">
                        <div class="d-flex align-items-start">
                            <div class="resource-icon me-3"><i class="fas <?= $icon ?>"></i></div>
                            <div class="flex-grow-1">
                                <h5><?= htmlspecialchars($res['titre']) ?></h5>
                                <p class="text-muted small mb-2">Partagé par <strong><?= htmlspecialchars($res['enseignant_nom']) ?></strong> | Matière: <strong><?= htmlspecialchars($res['matiere_nom']) ?></strong></p>
                                <a href="<?= $link ?>" class="btn btn-sm btn-outline-primary" target="<?= $target ?>">Voir la ressource <i class="fas fa-external-link-alt ms-1"></i></a>
                            </div>
                            <?php if ($res['enseignant_id'] == $enseignant_id): ?>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-light edit-btn" data-bs-toggle="modal" data-bs-target="#resourceModal" data-resource='<?= json_encode($res) ?>'><i class="fas fa-pencil-alt"></i></button>
                                <button class="btn btn-sm btn-light delete-btn" data-id="<?= $res['id'] ?>"><i class="fas fa-trash-alt"></i></button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modale -->
<div class="modal fade" id="resourceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="ressources.php" enctype="multipart/form-data">
                <div class="modal-header"><h5 class="modal-title" id="resourceModalLabel">Nouvelle Ressource</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="form-action" value="add"><input type="hidden" name="id" id="form-id"><input type="hidden" name="existing_file" id="form-existing_file">
                    <div class="mb-3"><label class="form-label">Titre</label><input type="text" class="form-control" name="titre" id="form-titre" required></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Type de ressource</label><select class="form-select" name="type_fichier" id="form-type_fichier" required><option value="pdf">PDF</option><option value="doc">Document Word</option><option value="ppt">Présentation</option><option value="video">Vidéo (lien)</option><option value="lien">Lien web</option><option value="autre">Autre fichier</option></select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Matière</label><select class="form-select" name="matiere_id" id="form-matiere_id" required><option value="">-- Choisir --</option><?php foreach ($matieres as $matiere): ?><option value="<?= $matiere['id'] ?>"><?= htmlspecialchars($matiere['nom']) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="mb-3" id="field-fichier_path"><label class="form-label">Lien (URL)</label><input type="url" class="form-control" name="fichier_path" id="form-fichier_path" placeholder="https://..."></div>
                    <div class="mb-3" id="field-fichier"><label class="form-label">Fichier</label><input type="file" class="form-control" name="fichier" id="form-fichier"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary-glow" id="form-submit-btn">Partager</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Formulaire caché pour la suppression -->
<form method="POST" action="ressources.php" id="delete-form" class="d-none">
    <input type="hidden" name="action_delete" value="1"><input type="hidden" name="id_delete" id="id-to-delete">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) sidebarToggle.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));

    const modal = document.getElementById('resourceModal');
    const typeSelect = document.getElementById('form-type_fichier');
    const urlField = document.getElementById('field-fichier_path');
    const fileField = document.getElementById('field-fichier');

    function toggleFields() {
        if (typeSelect.value === 'lien' || typeSelect.value === 'video') {
            urlField.style.display = 'block';
            fileField.style.display = 'none';
        } else {
            urlField.style.display = 'none';
            fileField.style.display = 'block';
        }
    }
    
    typeSelect.addEventListener('change', toggleFields);
    toggleFields(); // Init on load

    document.getElementById('add-resource-btn').addEventListener('click', function () {
        modal.querySelector('.modal-title').textContent = 'Nouvelle Ressource';
        modal.querySelector('form').reset();
        document.getElementById('form-action').value = 'add';
        document.getElementById('form-id').value = '';
        document.getElementById('form-submit-btn').textContent = 'Partager';
        toggleFields();
    });
    
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            const data = JSON.parse(this.dataset.resource);
            modal.querySelector('.modal-title').textContent = 'Modifier la Ressource';
            modal.querySelector('form').reset();
            document.getElementById('form-action').value = 'edit';
            document.getElementById('form-id').value = data.id;
            document.getElementById('form-titre').value = data.titre;
            document.getElementById('form-type_fichier').value = data.type_fichier;
            document.getElementById('form-matiere_id').value = data.matiere_id;
            if (data.type_fichier === 'lien' || data.type_fichier === 'video') {
                document.getElementById('form-fichier_path').value = data.fichier_path;
            }
            document.getElementById('form-existing_file').value = data.fichier_path;
            document.getElementById('form-submit-btn').textContent = 'Enregistrer';
            toggleFields();
        });
    });

    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function () {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette ressource ?')) {
                document.getElementById('id-to-delete').value = this.dataset.id;
                document.getElementById('delete-form').submit();
            }
        });
    });
});
</script>

</body>
</html>