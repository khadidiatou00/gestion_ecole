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

// AJOUTER ou MODIFIER une affectation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $enseignant_id = filter_input(INPUT_POST, 'enseignant_id', FILTER_VALIDATE_INT);
    $matiere_id = filter_input(INPUT_POST, 'matiere_id', FILTER_VALIDATE_INT);
    $classe_id = filter_input(INPUT_POST, 'classe_id', FILTER_VALIDATE_INT);
    $annee_id = filter_input(INPUT_POST, 'annee_id', FILTER_VALIDATE_INT);
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    try {
        // Vérifier si l'affectation existe déjà
        $sql_check = "SELECT id FROM enseignant_matieres WHERE enseignant_id = ? AND matiere_id = ? AND classe_id = ? AND annee_id = ?";
        $params_check = [$enseignant_id, $matiere_id, $classe_id, $annee_id];
        if ($_POST['action'] === 'edit') {
            $sql_check .= " AND id != ?";
            $params_check[] = $id;
        }
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute($params_check);

        if ($stmt_check->fetch()) {
            $message = "Erreur : Cette affectation existe déjà.";
            $message_type = 'danger';
        } else {
            if ($_POST['action'] === 'add') {
                $sql = "INSERT INTO enseignant_matieres (enseignant_id, matiere_id, classe_id, annee_id) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$enseignant_id, $matiere_id, $classe_id, $annee_id]);
                $message = "L'affectation a été créée avec succès !";
                $message_type = 'success';
            } elseif ($_POST['action'] === 'edit') {
                $sql = "UPDATE enseignant_matieres SET enseignant_id = ?, matiere_id = ?, classe_id = ?, annee_id = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$enseignant_id, $matiere_id, $classe_id, $annee_id, $id]);
                $message = "L'affectation a été modifiée avec succès !";
                $message_type = 'success';
            }
        }
    } catch (PDOException $e) {
        $message = "Erreur lors de l'opération : " . $e->getMessage();
        $message_type = 'danger';
    }
}

// SUPPRIMER une affectation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete'])) {
    try {
        $id = filter_input(INPUT_POST, 'id_delete', FILTER_VALIDATE_INT);
        $sql = "DELETE FROM enseignant_matieres WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $message = "L'affectation a été supprimée avec succès.";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "Erreur lors de la suppression : " . $e->getMessage();
        $message_type = 'danger';
    }
}

// --- RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE ---
try {
    // Récupérer toutes les affectations avec les noms associés
    $stmt_affectations = $pdo->query("
        SELECT em.id, 
               CONCAT(u.prenom, ' ', u.nom) as enseignant_nom, 
               m.nom as matiere_nom, 
               c.nom as classe_nom, 
               a.annee as annee_nom,
               em.enseignant_id, em.matiere_id, em.classe_id, em.annee_id
        FROM enseignant_matieres em
        JOIN utilisateurs u ON em.enseignant_id = u.id
        JOIN matieres m ON em.matiere_id = m.id
        JOIN classes c ON em.classe_id = c.id
        JOIN annees_scolaires a ON em.annee_id = a.id
        ORDER BY a.annee DESC, c.nom, m.nom
    ");
    $affectations = $stmt_affectations->fetchAll();

    // Récupérer les listes pour les formulaires
    $enseignants = $pdo->query("SELECT id, CONCAT(prenom, ' ', nom) as full_name FROM utilisateurs WHERE role = 'enseignant' ORDER BY full_name")->fetchAll();
    $matieres = $pdo->query("SELECT id, nom FROM matieres ORDER BY nom")->fetchAll();
    $classes = $pdo->query("SELECT id, nom FROM classes ORDER BY nom")->fetchAll();
    $annees = $pdo->query("SELECT id, annee FROM annees_scolaires ORDER BY annee DESC")->fetchAll();

} catch (PDOException $e) {
    $error_db = "Erreur lors de la récupération des données : " . $e->getMessage();
    $affectations = $enseignants = $matieres = $classes = $annees = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Affectations - GestiSchool Galaxy</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-dark-primary: #0d1117; --bg-dark-secondary: #161b22; --border-color: rgba(255, 255, 255, 0.1);
            --text-primary: #c9d1d9; --text-secondary: #8b949e; --accent-glow-1: #00f2ff;
            --accent-glow-2: #da00ff; --font-primary: 'Poppins', sans-serif; --font-display: 'Orbitron', sans-serif;
            --success-glow: rgba(40, 167, 69, 0.5); --danger-glow: rgba(220, 53, 69, 0.5);
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        body { font-family: var(--font-primary); background-color: var(--bg-dark-primary); color: var(--text-primary);
            background-image: radial-gradient(circle at 1px 1px, rgba(255, 255, 255, 0.05) 1px, transparent 0); background-size: 20px 20px;
        }
        .page-wrapper { display: flex; min-height: 100vh; }
        #sidebar { width: 260px; position: fixed; top: 0; left: 0; height: 100vh; z-index: 1000; background: rgba(16, 19, 26, 0.6); backdrop-filter: blur(10px); border-right: 1px solid var(--border-color); transition: all 0.3s ease; display: flex; flex-direction: column; }
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
        .btn-glow { border: 1px solid var(--accent-glow-1); color: var(--accent-glow-1); background-color: transparent; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; }
        .btn-glow:hover { background-color: var(--accent-glow-1); color: var(--bg-dark-primary); box-shadow: 0 0 15px var(--accent-glow-1); }
        .galaxy-table { width: 100%; color: var(--text-primary); }
        .galaxy-table th { background-color: rgba(255, 255, 255, 0.05); padding: 1rem; border-bottom: 1px solid var(--border-color); }
        .galaxy-table td { padding: 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .galaxy-table tbody tr:hover { background-color: rgba(255, 255, 255, 0.02); }
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
        @media (max-width: 992px) { #sidebar { left: -260px; } #sidebar.active { left: 0; } #main-content { margin-left: 0; width: 100%; } #sidebar-toggle { display: block; background: transparent; color: var(--text-primary); border: none; font-size: 1.2rem; } }
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
                <li class="nav-category">Pédagogie</li>
                 <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#pedagogieCollapse" role="button" aria-expanded="true" aria-controls="pedagogieCollapse">
                        <i class="fas fa-book-reader"></i> Organisation <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="collapse show" id="pedagogieCollapse">
                        <ul class="nav flex-column ps-4">
                            <li><a class="nav-link" href="emploi_temps.php">Emploi du Temps</a></li>
                            <li><a class="nav-link active" href="affectations.php">Affectations</a></li>
                            <li><a class="nav-link" href="programmes.php">Programmes</a></li>
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
                <h1>Gestion des Affectations</h1>
            </div>
            <button class="btn btn-glow" data-bs-toggle="modal" data-bs-target="#affectationModal" id="add-affectation-btn">
                <i class="fas fa-link me-2"></i> Nouvelle Affectation
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
                            <th>Enseignant</th>
                            <th>Matière</th>
                            <th>Classe</th>
                            <th>Année Scolaire</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($affectations)): ?>
                            <tr><td colspan="5" class="text-center">Aucune affectation trouvée.</td></tr>
                        <?php else: ?>
                            <?php foreach ($affectations as $aff): ?>
                                <tr>
                                    <td><?= htmlspecialchars($aff['enseignant_nom']) ?></td>
                                    <td><?= htmlspecialchars($aff['matiere_nom']) ?></td>
                                    <td><?= htmlspecialchars($aff['classe_nom']) ?></td>
                                    <td><?= htmlspecialchars($aff['annee_nom']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-action btn-action-edit edit-btn"
                                            data-bs-toggle="modal" data-bs-target="#affectationModal"
                                            data-id="<?= $aff['id'] ?>"
                                            data-enseignant_id="<?= $aff['enseignant_id'] ?>"
                                            data-matiere_id="<?= $aff['matiere_id'] ?>"
                                            data-classe_id="<?= $aff['classe_id'] ?>"
                                            data-annee_id="<?= $aff['annee_id'] ?>">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <button class="btn btn-action btn-action-delete delete-btn" data-id="<?= $aff['id'] ?>" data-info="Affectation de <?= htmlspecialchars($aff['enseignant_nom']) ?> à la classe <?= htmlspecialchars($aff['classe_nom']) ?>">
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

<!-- Modale pour Ajouter/Modifier une affectation -->
<div class="modal fade" id="affectationModal" tabindex="-1" aria-labelledby="affectationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="affectations.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="affectationModalLabel">Nouvelle Affectation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background-color: #fff;"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="id" id="form-id">
                    
                    <div class="mb-3">
                        <label class="form-label">Année Scolaire</label>
                        <select class="form-select" name="annee_id" id="form-annee_id" required>
                            <option value="">-- Choisir une année scolaire --</option>
                            <?php foreach ($annees as $annee): ?>
                                <option value="<?= $annee['id'] ?>"><?= htmlspecialchars($annee['annee']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="mb-3">
                        <label class="form-label">Classe</label>
                        <select class="form-select" name="classe_id" id="form-classe_id" required>
                            <option value="">-- Choisir une classe --</option>
                             <?php foreach ($classes as $classe): ?>
                                <option value="<?= $classe['id'] ?>"><?= htmlspecialchars($classe['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="mb-3">
                        <label class="form-label">Matière</label>
                        <select class="form-select" name="matiere_id" id="form-matiere_id" required>
                            <option value="">-- Choisir une matière --</option>
                             <?php foreach ($matieres as $matiere): ?>
                                <option value="<?= $matiere['id'] ?>"><?= htmlspecialchars($matiere['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="mb-3">
                        <label class="form-label">Enseignant</label>
                        <select class="form-select" name="enseignant_id" id="form-enseignant_id" required>
                            <option value="">-- Choisir un enseignant --</option>
                             <?php foreach ($enseignants as $enseignant): ?>
                                <option value="<?= $enseignant['id'] ?>"><?= htmlspecialchars($enseignant['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
<form method="POST" action="affectations.php" id="delete-form" class="d-none">
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

    const modalTitle = document.getElementById('affectationModalLabel');
    const form = document.getElementById('affectationModal').querySelector('form');
    const formAction = document.getElementById('form-action');
    const formId = document.getElementById('form-id');
    const formSubmitBtn = document.getElementById('form-submit-btn');
    
    document.getElementById('add-affectation-btn').addEventListener('click', function () {
        modalTitle.textContent = 'Nouvelle Affectation';
        form.reset();
        formAction.value = 'add';
        formId.value = '';
        formSubmitBtn.textContent = 'Ajouter';
    });
    
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            const data = this.dataset;
            modalTitle.textContent = 'Modifier l\'Affectation';
            form.reset();
            formAction.value = 'edit';
            formId.value = data.id;
            
            document.getElementById('form-annee_id').value = data.annee_id;
            document.getElementById('form-classe_id').value = data.classe_id;
            document.getElementById('form-matiere_id').value = data.matiere_id;
            document.getElementById('form-enseignant_id').value = data.enseignant_id;
            
            formSubmitBtn.textContent = 'Enregistrer les modifications';
        });
    });

    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function () {
            const info = this.dataset.info;
            const id = this.dataset.id;
            if (confirm(`Êtes-vous sûr de vouloir supprimer cette affectation ?\n(${info})`)) {
                document.getElementById('id-to-delete').value = id;
                document.getElementById('delete-form').submit();
            }
        });
    });
});
</script>

</body>
</html>