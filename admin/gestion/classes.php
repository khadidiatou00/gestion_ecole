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

// AJOUTER ou MODIFIER une classe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $nom = trim($_POST['nom']);
    $niveau = trim($_POST['niveau']);
    $capacite_max = filter_input(INPUT_POST, 'capacite_max', FILTER_VALIDATE_INT);
    $cycle_id = filter_input(INPUT_POST, 'cycle_id', FILTER_VALIDATE_INT);
    $annee_id = filter_input(INPUT_POST, 'annee_id', FILTER_VALIDATE_INT);
    $responsable_id = filter_input(INPUT_POST, 'responsable_id', FILTER_VALIDATE_INT) ?: null;

    try {
        if ($_POST['action'] === 'add') {
            $sql = "INSERT INTO classes (nom, niveau, capacite_max, cycle_id, annee_id, responsable_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nom, $niveau, $capacite_max, $cycle_id, $annee_id, $responsable_id]);
            $message = "La classe a été ajoutée avec succès !";
            $message_type = 'success';
        } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $sql = "UPDATE classes SET nom = ?, niveau = ?, capacite_max = ?, cycle_id = ?, annee_id = ?, responsable_id = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nom, $niveau, $capacite_max, $cycle_id, $annee_id, $responsable_id, $id]);
            $message = "La classe a été modifiée avec succès !";
            $message_type = 'success';
        }
    } catch (PDOException $e) {
        $message = "Erreur lors de l'opération : " . $e->getMessage();
        $message_type = 'danger';
    }
}

// SUPPRIMER une classe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete'])) {
    try {
        $id = filter_input(INPUT_POST, 'id_delete', FILTER_VALIDATE_INT);
        $sql = "DELETE FROM classes WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $message = "La classe a été supprimée avec succès.";
        $message_type = 'success';
    } catch (PDOException $e) {
        // Gérer les contraintes de clé étrangère
        if ($e->getCode() == '23000') {
             $message = "Erreur : Impossible de supprimer cette classe car elle est liée à d'autres enregistrements (inscriptions, emplois du temps, etc.).";
        } else {
            $message = "Erreur lors de la suppression : " . $e->getMessage();
        }
        $message_type = 'danger';
    }
}


// --- RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE ---
try {
    // Récupérer toutes les classes avec les noms associés (JOIN)
    $stmt_classes = $pdo->query("
        SELECT c.*, cy.nom as cycle_nom, a.annee as annee_nom, CONCAT(u.prenom, ' ', u.nom) as responsable_nom
        FROM classes c
        LEFT JOIN cycles cy ON c.cycle_id = cy.id
        LEFT JOIN annees_scolaires a ON c.annee_id = a.id
        LEFT JOIN utilisateurs u ON c.responsable_id = u.id
        ORDER BY c.nom ASC
    ");
    $classes = $stmt_classes->fetchAll();

    // Récupérer les listes pour les formulaires
    $cycles = $pdo->query("SELECT id, nom FROM cycles ORDER BY nom")->fetchAll();
    $annees = $pdo->query("SELECT id, annee FROM annees_scolaires ORDER BY annee DESC")->fetchAll();
    $enseignants = $pdo->query("SELECT id, CONCAT(prenom, ' ', nom) as full_name FROM utilisateurs WHERE role = 'enseignant' ORDER BY full_name")->fetchAll();

} catch (PDOException $e) {
    $error_db = "Erreur lors de la récupération des données : " . $e->getMessage();
    $classes = $cycles = $annees = $enseignants = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Classes - GestiSchool Galaxy</title>
    
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
        .alert-glow { border: 1px solid; box-shadow: 0 0 10px; }
        .alert-success { background-color: rgba(40, 167, 69, 0.2); border-color: var(--success-glow); box-shadow-color: var(--success-glow); }
        .alert-danger { background-color: rgba(220, 53, 69, 0.2); border-color: var(--danger-glow); box-shadow-color: var(--danger-glow); }
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
                    <a class="nav-link" data-bs-toggle="collapse" href="#gestionCollapse" role="button" aria-expanded="true" aria-controls="gestionCollapse">
                        <i class="fas fa-university"></i> Scolarité <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="collapse show" id="gestionCollapse">
                        <ul class="nav flex-column ps-4">
                            <li><a class="nav-link active" href="classes.php">Classes</a></li>
                            <li><a class="nav-link" href="matieres.php">Matières</a></li>
                            <li><a class="nav-link" href="salles.php">Salles</a></li>
                            <li><a class="nav-link" href="annees.php">Années Scolaires</a></li>
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
                <h1>Gestion des Classes</h1>
            </div>
            <button class="btn btn-glow" data-bs-toggle="modal" data-bs-target="#classModal" id="add-class-btn">
                <i class="fas fa-plus-circle me-2"></i> Ajouter une classe
            </button>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-glow" role="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (isset($error_db)): ?>
            <div class="alert alert-danger alert-glow" role="alert"><?= htmlspecialchars($error_db) ?></div>
        <?php endif; ?>

        <div class="content-card">
            <div class="table-responsive">
                <table class="galaxy-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Niveau</th>
                            <th>Capacité</th>
                            <th>Responsable</th>
                            <th>Cycle</th>
                            <th>Année Scolaire</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($classes)): ?>
                            <tr><td colspan="7" class="text-center">Aucune classe trouvée.</td></tr>
                        <?php else: ?>
                            <?php foreach ($classes as $classe): ?>
                                <tr>
                                    <td><?= htmlspecialchars($classe['nom']) ?></td>
                                    <td><?= htmlspecialchars($classe['niveau']) ?></td>
                                    <td><?= htmlspecialchars($classe['capacite_max']) ?></td>
                                    <td><?= htmlspecialchars($classe['responsable_nom'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars($classe['cycle_nom'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars($classe['annee_nom'] ?: 'N/A') ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-action btn-action-edit edit-btn"
                                            data-bs-toggle="modal" data-bs-target="#classModal"
                                            data-id="<?= $classe['id'] ?>"
                                            data-nom="<?= htmlspecialchars($classe['nom']) ?>"
                                            data-niveau="<?= htmlspecialchars($classe['niveau']) ?>"
                                            data-capacite_max="<?= $classe['capacite_max'] ?>"
                                            data-cycle_id="<?= $classe['cycle_id'] ?>"
                                            data-annee_id="<?= $classe['annee_id'] ?>"
                                            data-responsable_id="<?= $classe['responsable_id'] ?>">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <button class="btn btn-action btn-action-delete delete-btn" data-id="<?= $classe['id'] ?>" data-nom="<?= htmlspecialchars($classe['nom']) ?>">
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

<!-- Modale pour Ajouter/Modifier une classe -->
<div class="modal fade" id="classModal" tabindex="-1" aria-labelledby="classModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="classes.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="classModalLabel">Ajouter une classe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background-color: #fff;"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="id" id="form-id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom" class="form-label">Nom de la classe</label>
                            <input type="text" class="form-control" id="form-nom" name="nom" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="niveau" class="form-label">Niveau</label>
                            <input type="text" class="form-control" id="form-niveau" name="niveau" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="capacite_max" class="form-label">Capacité maximale</label>
                        <input type="number" class="form-control" id="form-capacite_max" name="capacite_max" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cycle_id" class="form-label">Cycle</label>
                            <select class="form-select" id="form-cycle_id" name="cycle_id" required>
                                <option value="">Choisir un cycle...</option>
                                <?php foreach ($cycles as $cycle): ?>
                                    <option value="<?= $cycle['id'] ?>"><?= htmlspecialchars($cycle['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="annee_id" class="form-label">Année Scolaire</label>
                            <select class="form-select" id="form-annee_id" name="annee_id" required>
                                <option value="">Choisir une année...</option>
                                <?php foreach ($annees as $annee): ?>
                                    <option value="<?= $annee['id'] ?>"><?= htmlspecialchars($annee['annee']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="responsable_id" class="form-label">Enseignant Responsable</label>
                        <select class="form-select" id="form-responsable_id" name="responsable_id">
                            <option value="">Aucun responsable...</option>
                            <?php foreach ($enseignants as $enseignant): ?>
                                <option value="<?= $enseignant['id'] ?>"><?= htmlspecialchars($enseignant['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-glow" id="form-submit-btn">Ajouter la classe</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Formulaire caché pour la suppression -->
<form method="POST" action="classes.php" id="delete-form" class="d-none">
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

    const classModal = document.getElementById('classModal');
    const modalTitle = document.getElementById('classModalLabel');
    const formAction = document.getElementById('form-action');
    const formId = document.getElementById('form-id');
    const formSubmitBtn = document.getElementById('form-submit-btn');
    
    // Logique pour l'ouverture de la modale en mode "Ajout"
    document.getElementById('add-class-btn').addEventListener('click', function () {
        modalTitle.textContent = 'Ajouter une classe';
        formAction.value = 'add';
        formId.value = '';
        document.getElementById('classModal').querySelector('form').reset();
        formSubmitBtn.textContent = 'Ajouter la classe';
    });
    
    // Logique pour l'ouverture de la modale en mode "Modification"
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            const data = this.dataset;
            modalTitle.textContent = 'Modifier la classe';
            formAction.value = 'edit';
            formId.value = data.id;
            document.getElementById('form-nom').value = data.nom;
            document.getElementById('form-niveau').value = data.niveau;
            document.getElementById('form-capacite_max').value = data.capacite_max;
            document.getElementById('form-cycle_id').value = data.cycle_id;
            document.getElementById('form-annee_id').value = data.annee_id;
            document.getElementById('form-responsable_id').value = data.responsable_id;
            formSubmitBtn.textContent = 'Enregistrer les modifications';
        });
    });

    // Logique pour la suppression
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function () {
            const className = this.dataset.nom;
            const classId = this.dataset.id;
            if (confirm(`Êtes-vous sûr de vouloir supprimer la classe "${className}" ? Cette action est irréversible.`)) {
                document.getElementById('id-to-delete').value = classId;
                document.getElementById('delete-form').submit();
            }
        });
    });
});
</script>

</body>
</html>