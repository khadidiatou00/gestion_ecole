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
// AJOUTER ou MODIFIER un cours
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $titre = trim($_POST['titre']);
    $matiere_id = filter_input(INPUT_POST, 'matiere_id', FILTER_VALIDATE_INT);
    $classe_id = filter_input(INPUT_POST, 'classe_id', FILTER_VALIDATE_INT);
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    try {
        $fichier_path = $_POST['existing_file'] ?? null;

        if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/cours/pdf/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            if ($fichier_path && file_exists('../../' . $fichier_path)) {
                unlink('../../' . $fichier_path);
            }
            
            $file_name = uniqid('cours_') . '_' . basename($_FILES['fichier']['name']);
            $target_file = $upload_dir . $file_name;
            move_uploaded_file($_FILES['fichier']['tmp_name'], $target_file);
            $fichier_path = 'uploads/cours/pdf/' . $file_name;
        }

        if ($_POST['action'] === 'add') {
            $sql = "INSERT INTO cours (titre, matiere_id, classe_id, enseignant_id, fichier_path, annee_id) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$titre, $matiere_id, $classe_id, $enseignant_id, $fichier_path, $annee_en_cours_id]);
            $message = "Le cours a été ajouté avec succès !";
            $message_type = 'success';
        } elseif ($_POST['action'] === 'edit') {
            $sql = "UPDATE cours SET titre = ?, matiere_id = ?, classe_id = ?, fichier_path = ? WHERE id = ? AND enseignant_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$titre, $matiere_id, $classe_id, $fichier_path, $id, $enseignant_id]);
            $message = "Le cours a été modifié avec succès !";
            $message_type = 'success';
        }
    } catch (PDOException $e) {
        $message = "Erreur lors de l'opération : " . $e->getMessage();
        $message_type = 'danger';
    }
}

// SUPPRIMER un cours
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete'])) {
    try {
        $id = filter_input(INPUT_POST, 'id_delete', FILTER_VALIDATE_INT);
        
        $stmt_file = $pdo->prepare("SELECT fichier_path FROM cours WHERE id = ? AND enseignant_id = ?");
        $stmt_file->execute([$id, $enseignant_id]);
        $fichier_path = $stmt_file->fetchColumn();

        if ($fichier_path && file_exists('../../' . $fichier_path)) {
            unlink('../../' . $fichier_path);
        }

        $stmt = $pdo->prepare("DELETE FROM cours WHERE id = ? AND enseignant_id = ?");
        $stmt->execute([$id, $enseignant_id]);
        $message = "Le cours a été supprimé avec succès.";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "Erreur lors de la suppression.";
        $message_type = 'danger';
    }
}

// --- RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE ---
try {
    // Listes pour les formulaires (uniquement les classes/matières de l'enseignant)
    $stmt_affectations = $pdo->prepare("SELECT DISTINCT c.id as classe_id, c.nom as classe_nom, m.id as matiere_id, m.nom as matiere_nom FROM enseignant_matieres em JOIN classes c ON em.classe_id = c.id JOIN matieres m ON em.matiere_id = m.id WHERE em.enseignant_id = ? AND em.annee_id = ? ORDER BY c.nom, m.nom");
    $stmt_affectations->execute([$enseignant_id, $annee_en_cours_id]);
    $affectations = $stmt_affectations->fetchAll();
    
    $classes_enseignant = array_unique(array_column($affectations, 'classe_nom', 'classe_id'));
    $matieres_enseignant = array_unique(array_column($affectations, 'matiere_nom', 'matiere_id'));

    // Liste des cours
    $sql_cours = "SELECT c.*, cl.nom as classe_nom, m.nom as matiere_nom FROM cours c JOIN classes cl ON c.classe_id = cl.id JOIN matieres m ON c.matiere_id = m.id WHERE c.enseignant_id = ? AND c.annee_id = ? ";
    $params = [$enseignant_id, $annee_en_cours_id];
    if (!empty($_GET['classe_id'])) {
        $sql_cours .= " AND c.classe_id = ?";
        $params[] = $_GET['classe_id'];
    }
    if (!empty($_GET['matiere_id'])) {
        $sql_cours .= " AND c.matiere_id = ?";
        $params[] = $_GET['matiere_id'];
    }
    $sql_cours .= " ORDER BY c.date_publication DESC";
    
    $stmt_cours = $pdo->prepare($sql_cours);
    $stmt_cours->execute($params);
    $cours = $stmt_cours->fetchAll();

} catch (PDOException $e) {
    $error_db = "Erreur : " . $e->getMessage();
    $cours = $affectations = $classes_enseignant = $matieres_enseignant = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Cours - GestiSchool Vibrant</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-main: #f4f7fc; --sidebar-bg: #ffffff; --card-bg: #ffffff;
            --primary: #4f46e5; --secondary: #64748b; --accent: #ec4899;
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
        .modal-content { border-radius: 16px; border: none; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); }
        .cours-card {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 12px; padding: 1.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .cours-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
        .cours-card h5 { font-family: var(--font-title); font-weight: 600; }
        .cours-card .badge { font-weight: 500; }
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
                <li class="nav-item"><a class="nav-link active" href="cours.php"><i class="fas fa-book-open"></i> Mes Cours</a></li>
                <li class="nav-item"><a class="nav-link" href="devoirs.php"><i class="fas fa-file-signature"></i> Devoirs</a></li>
                <li class="nav-item"><a class="nav-link" href="projets.php"><i class="fas fa-project-diagram"></i> Projets</a></li>
                <li class="nav-item"><a class="nav-link" href="ressources.php"><i class="fas fa-folder-open"></i> Ressources</a></li>
                <!-- ... autres menus ... -->
            </ul>
        </nav>
    </aside>

    <!-- Contenu Principal -->
    <main id="main-content">
        <header class="main-header">
            <h1 class="d-flex align-items-center"><i class="fas fa-book-open me-3 text-primary"></i> Mes Cours</h1>
            <button class="btn btn-primary-glow" data-bs-toggle="modal" data-bs-target="#coursModal" id="add-cours-btn"><i class="fas fa-plus me-2"></i> Ajouter un cours</button>
        </header>

        <?php if ($message): ?><div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if (isset($error_db)): ?><div class="alert alert-danger"><?= htmlspecialchars($error_db) ?></div><?php endif; ?>
        
        <div class="content-card mb-4">
            <form method="GET" action="cours.php" class="row g-3">
                <div class="col-md-5"><label class="form-label">Filtrer par classe</label><select class="form-select" name="classe_id" onchange="this.form.submit()"><option value="">Toutes mes classes</option><?php foreach ($classes_enseignant as $id => $nom): ?><option value="<?= $id ?>" <?= (isset($_GET['classe_id']) && $_GET['classe_id'] == $id) ? 'selected' : '' ?>><?= htmlspecialchars($nom) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-5"><label class="form-label">Filtrer par matière</label><select class="form-select" name="matiere_id" onchange="this.form.submit()"><option value="">Toutes mes matières</option><?php foreach ($matieres_enseignant as $id => $nom): ?><option value="<?= $id ?>" <?= (isset($_GET['matiere_id']) && $_GET['matiere_id'] == $id) ? 'selected' : '' ?>><?= htmlspecialchars($nom) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2 d-flex align-items-end"><a href="cours.php" class="btn btn-secondary w-100">Réinitialiser</a></div>
            </form>
        </div>

        <div class="row g-4">
            <?php if (empty($cours)): ?>
                <div class="col-12"><p class="text-center text-muted mt-5">Vous n'avez ajouté aucun cours pour cette sélection.</p></div>
            <?php else: ?>
                <?php foreach ($cours as $c): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="cours-card">
                        <h5><?= htmlspecialchars($c['titre']) ?></h5>
                        <div class="mb-3">
                            <span class="badge bg-primary bg-opacity-10 text-primary me-2"><?= htmlspecialchars($c['classe_nom']) ?></span>
                            <span class="badge bg-info bg-opacity-10 text-info"><?= htmlspecialchars($c['matiere_nom']) ?></span>
                        </div>
                        <p class="small text-muted">Publié le: <?= date('d/m/Y H:i', strtotime($c['date_publication'])) ?></p>
                        <div class="d-flex justify-content-end gap-2">
                             <?php if ($c['fichier_path']): ?><a href="../../<?= htmlspecialchars($c['fichier_path']) ?>" class="btn btn-sm btn-outline-success" target="_blank"><i class="fas fa-download"></i></a><?php endif; ?>
                            <button class="btn btn-sm btn-outline-primary edit-btn" data-bs-toggle="modal" data-bs-target="#coursModal" data-cours='<?= json_encode($c) ?>'><i class="fas fa-pencil-alt"></i></button>
                            <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?= $c['id'] ?>"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modale pour Ajouter/Modifier un cours -->
<div class="modal fade" id="coursModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="cours.php" enctype="multipart/form-data">
                <div class="modal-header"><h5 class="modal-title" id="coursModalLabel">Nouveau Cours</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="form-action" value="add"><input type="hidden" name="id" id="form-id"><input type="hidden" name="existing_file" id="form-existing_file">
                    <div class="mb-3"><label class="form-label">Titre du cours</label><input type="text" class="form-control" id="form-titre" name="titre" required></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Classe</label><select class="form-select" name="classe_id" id="form-classe_id" required><option value="">-- Choisir --</option><?php foreach ($classes_enseignant as $id => $nom): ?><option value="<?= $id ?>"><?= htmlspecialchars($nom) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Matière</label><select class="form-select" name="matiere_id" id="form-matiere_id" required><option value="">-- Choisir --</option><?php foreach ($matieres_enseignant as $id => $nom): ?><option value="<?= $id ?>"><?= htmlspecialchars($nom) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Fichier du cours (PDF, DOCX, etc.)</label><input type="file" class="form-control" name="fichier" id="form-fichier"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary-glow" id="form-submit-btn">Ajouter</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Formulaire caché pour la suppression -->
<form method="POST" action="cours.php" id="delete-form" class="d-none">
    <input type="hidden" name="action_delete" value="1"><input type="hidden" name="id_delete" id="id-to-delete">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) sidebarToggle.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));

    const modal = document.getElementById('coursModal');
    const modalTitle = document.getElementById('coursModalLabel');
    const form = modal.querySelector('form');
    const formAction = document.getElementById('form-action');
    const formId = document.getElementById('form-id');
    const formSubmitBtn = document.getElementById('form-submit-btn');

    document.getElementById('add-cours-btn').addEventListener('click', function () {
        modalTitle.textContent = 'Nouveau Cours';
        form.reset();
        formAction.value = 'add';
        formId.value = '';
        document.getElementById('form-existing_file').value = '';
        document.getElementById('form-fichier').required = true;
        formSubmitBtn.textContent = 'Ajouter le cours';
    });
    
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            const data = JSON.parse(this.dataset.cours);
            modalTitle.textContent = 'Modifier le Cours';
            form.reset();
            formAction.value = 'edit';
            formId.value = data.id;
            
            document.getElementById('form-titre').value = data.titre;
            document.getElementById('form-classe_id').value = data.classe_id;
            document.getElementById('form-matiere_id').value = data.matiere_id;
            document.getElementById('form-existing_file').value = data.fichier_path;
            document.getElementById('form-fichier').required = false; // Le fichier n'est pas obligatoire en modification
            
            formSubmitBtn.textContent = 'Enregistrer les modifications';
        });
    });

    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function () {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce cours ? Cette action est irréversible.')) {
                document.getElementById('id-to-delete').value = this.dataset.id;
                document.getElementById('delete-form').submit();
            }
        });
    });
});
</script>

</body>
</html>