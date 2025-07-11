<?php
session_start();

// --- SÉCURITÉ ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

// --- CONNEXION À LA BASE DE DONNÉES ---
require_once '../../config/db.php';

$notification = null;
$cycles = [];

// --- LOGIQUE CRUD ---
try {
    // --- GESTION DU FORMULAIRE (POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // --- CRÉER OU MODIFIER UN CYCLE ---
        if (isset($_POST['action']) && in_array($_POST['action'], ['creer', 'modifier'])) {
            $nom = trim($_POST['nom']);
            $code = trim($_POST['code']);
            $duree = filter_input(INPUT_POST, 'duree_annees', FILTER_VALIDATE_INT);

            if (empty($nom) || empty($code) || $duree === false || $duree <= 0) {
                throw new Exception("Tous les champs sont obligatoires et la durée doit être un nombre positif.");
            }

            if ($_POST['action'] === 'creer') {
                $stmt = $pdo->prepare("INSERT INTO cycles (nom, code, duree_annees) VALUES (?, ?, ?)");
                $stmt->execute([$nom, $code, $duree]);
                $_SESSION['notification'] = ['type' => 'success', 'message' => 'Le cycle a été créé avec succès.'];
            } else { // Modifier
                $id = $_POST['id'];
                $stmt = $pdo->prepare("UPDATE cycles SET nom = ?, code = ?, duree_annees = ? WHERE id = ?");
                $stmt->execute([$nom, $code, $duree, $id]);
                $_SESSION['notification'] = ['type' => 'success', 'message' => 'Le cycle a été modifié avec succès.'];
            }
            header('Location: cycles.php');
            exit();
        }

        // --- SUPPRIMER UN CYCLE ---
        if (isset($_POST['action']) && $_POST['action'] === 'supprimer') {
            $id = $_POST['id'];
            // On vérifie si le cycle est utilisé
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE cycle_id = ?");
            $stmt_check->execute([$id]);
            if ($stmt_check->fetchColumn() > 0) {
                throw new Exception("Impossible de supprimer ce cycle car il est associé à une ou plusieurs classes.");
            }
            
            $stmt = $pdo->prepare("DELETE FROM cycles WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['notification'] = ['type' => 'info', 'message' => 'Le cycle a été supprimé.'];
            header('Location: cycles.php');
            exit();
        }
    }

    // --- RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE ---
    $cycles = $pdo->query("SELECT * FROM cycles ORDER BY nom ASC")->fetchAll();

    if (isset($_SESSION['notification'])) {
        $notification = $_SESSION['notification'];
        unset($_SESSION['notification']);
    }

} catch (PDOException $e) {
    if ($e->errorInfo[1] == 1062) {
        $notification = ['type' => 'danger', 'message' => 'Erreur : Le code du cycle doit être unique.'];
    } else {
        $notification = ['type' => 'danger', 'message' => 'Erreur de base de données : ' . $e->getMessage()];
    }
} catch (Exception $e) {
    $notification = ['type' => 'danger', 'message' => $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Cycles - GestiSchool</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS "Galaxy" (copié de votre dashboard) -->
    <style>
        :root {
            --bg-dark-primary: #0d1117; --bg-dark-secondary: #161b22; --border-color: rgba(255, 255, 255, 0.1);
            --text-primary: #c9d1d9; --text-secondary: #8b949e; --accent-glow-1: #00f2ff; --accent-glow-2: #da00ff;
            --font-primary: 'Poppins', sans-serif; --font-display: 'Orbitron', sans-serif;
        }
        body { font-family: var(--font-primary); background-color: var(--bg-dark-primary); color: var(--text-primary); background-image: radial-gradient(circle at 1px 1px, rgba(255, 255, 255, 0.05) 1px, transparent 0); background-size: 20px 20px; }
        .page-wrapper { display: flex; min-height: 100vh; }
        #sidebar {
            width: 260px; position: fixed; top: 0; left: 0; height: 100vh; z-index: 1000;
            background: rgba(16, 19, 26, 0.6); backdrop-filter: blur(10px); border-right: 1px solid var(--border-color);
            display: flex; flex-direction: column; transition: all 0.3s ease;
        }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid var(--border-color); }
        .sidebar-header .logo { font-family: var(--font-display); font-size: 1.5rem; color: #fff; text-decoration: none; text-shadow: 0 0 5px var(--accent-glow-1), 0 0 10px var(--accent-glow-2); }
        .sidebar-nav { padding: 1rem; flex-grow: 1; overflow-y: auto; }
        .nav-category { font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; padding: 0.5rem 1rem; font-weight: 600; }
        .nav-link { display: flex; align-items: center; padding: 0.75rem 1rem; color: var(--text-primary); text-decoration: none; border-radius: 8px; margin-bottom: 5px; transition: all 0.2s ease; }
        .nav-link i { width: 25px; margin-right: 15px; text-align: center; font-size: 1.1rem; }
        .nav-link:hover, .nav-link.active { background: rgba(255, 255, 255, 0.05); color: #fff; box-shadow: 0 0 10px rgba(0, 242, 255, 0.2); }
        .nav-link .arrow { margin-left: auto; transition: transform 0.3s ease; }
        .nav-link[aria-expanded="true"] { background: rgba(255, 255, 255, 0.05); }
        .nav-link[aria-expanded="true"] .arrow { transform: rotate(90deg); }
        #main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2rem; animation: fadeIn 0.5s ease forwards; }
        .main-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .main-header h1 { font-family: var(--font-display); color: #fff; font-size: 2rem; }
        .content-card { background: var(--bg-dark-secondary); padding: 2rem; border-radius: 12px; border: 1px solid var(--border-color); }
        .table { color: var(--text-primary); border-color: var(--border-color); }
        .table th { color: var(--text-secondary); text-transform: uppercase; font-size: 0.8rem; border-bottom-width: 2px; }
        .table tbody tr:hover { background-color: rgba(255, 255, 255, 0.03); }
        .btn-glow { background: var(--accent-glow-1); color: var(--bg-dark-primary); border: none; font-weight: 600; text-shadow: none; box-shadow: 0 0 15px rgba(0, 242, 255, 0.4); transition: all 0.3s ease;}
        .btn-glow:hover { box-shadow: 0 0 25px rgba(0, 242, 255, 0.7); transform: translateY(-2px); }
        .modal-content { background-color: var(--bg-dark-secondary); border: 1px solid var(--border-color); color: var(--text-primary); }
        .modal-header, .modal-footer { border-color: var(--border-color); }
        .form-control, .form-select { background-color: #0d1117; border: 1px solid var(--border-color); color: var(--text-primary); }
        .form-control:focus, .form-select:focus { background-color: #0d1117; border-color: var(--accent-glow-1); box-shadow: 0 0 10px rgba(0, 242, 255, 0.3); color: var(--text-primary); }
        @media (max-width: 992px) { #sidebar { left: -260px; } #sidebar.active { left: 0; } #main-content { margin-left: 0; width: 100%; } #sidebar-toggle { display: block; } }
        #sidebar-toggle { display: none; background: transparent; color: var(--text-primary); border: none; font-size: 1.2rem; }
    </style>
</head>
<body>

<div class="page-wrapper">
    <aside id="sidebar">
        <div class="sidebar-header"><a href="../dashboard.php" class="logo"><i class="fas fa-meteor"></i> GestiSchool</a></div>
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
                <li class="nav-category">Gestion Globale</li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#gestionCollapse" role="button" aria-expanded="true">
                        <i class="fas fa-university"></i> Scolarité <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="collapse show" id="gestionCollapse">
                        <ul class="nav flex-column ps-4">
                            <li><a class="nav-link" href="classes.php">Classes</a></li>
                            <li><a class="nav-link" href="matieres.php">Matières</a></li>
                            <li><a class="nav-link" href="salles.php">Salles</a></li>
                            <li><a class="nav-link" href="annees.php">Années Scolaires</a></li>
                            <li><a class="nav-link active" href="cycles.php">Cycles</a></li>
                        </ul>
                    </div>
                </li>
            </ul>
        </nav>
    </aside>

    <main id="main-content">
        <header class="main-header">
            <div>
                <button class="btn" id="sidebar-toggle"><i class="fas fa-bars"></i></button>
                <h1 class="d-inline-block align-middle ms-3">Gestion des Cycles</h1>
            </div>
            <button class="btn btn-glow" data-bs-toggle="modal" data-bs-target="#cycleModal"><i class="fas fa-plus me-2"></i>Nouveau Cycle</button>
        </header>

        <?php if ($notification): ?>
            <div class="alert alert-<?= $notification['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert" style="background-color: var(--bg-dark-secondary); border-color: var(--border-color); color: var(--text-primary);">
                <?= htmlspecialchars($notification['message']) ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nom du Cycle</th>
                            <th>Code</th>
                            <th>Durée (années)</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cycles)): ?>
                            <tr><td colspan="4" class="text-center text-secondary py-4">Aucun cycle trouvé.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($cycles as $cycle): ?>
                            <tr>
                                <td><?= htmlspecialchars($cycle['nom']) ?></td>
                                <td><span class="badge" style="background-color: var(--accent-glow-2);"><?= htmlspecialchars($cycle['code']) ?></span></td>
                                <td><?= htmlspecialchars($cycle['duree_annees']) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-outline-info btn-sm btn-edit" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#cycleModal"
                                            data-id="<?= $cycle['id'] ?>"
                                            data-nom="<?= htmlspecialchars($cycle['nom']) ?>"
                                            data-code="<?= htmlspecialchars($cycle['code']) ?>"
                                            data-duree="<?= $cycle['duree_annees'] ?>">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                    <form action="cycles.php" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce cycle ?\nCette action est irréversible.');">
                                        <input type="hidden" name="action" value="supprimer">
                                        <input type="hidden" name="id" value="<?= $cycle['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal pour Créer/Modifier un Cycle -->
<div class="modal fade" id="cycleModal" tabindex="-1" aria-labelledby="cycleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="cycleForm" action="cycles.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="cycleModalLabel">Nouveau Cycle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="creer">
                    <input type="hidden" name="id" id="formId">
                    <div class="mb-3">
                        <label for="formNom" class="form-label">Nom du Cycle</label>
                        <input type="text" class="form-control" id="formNom" name="nom" required>
                    </div>
                    <div class="mb-3">
                        <label for="formCode" class="form-label">Code (Unique)</label>
                        <input type="text" class="form-control" id="formCode" name="code" required>
                    </div>
                    <div class="mb-3">
                        <label for="formDuree" class="form-label">Durée en années</label>
                        <input type="number" class="form-control" id="formDuree" name="duree_annees" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-glow">Sauvegarder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));
    }

    const cycleModal = document.getElementById('cycleModal');
    cycleModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const form = document.getElementById('cycleForm');
        const modalTitle = document.getElementById('cycleModalLabel');
        
        const actionInput = form.querySelector('#formAction');
        const idInput = form.querySelector('#formId');
        const nomInput = form.querySelector('#formNom');
        const codeInput = form.querySelector('#formCode');
        const dureeInput = form.querySelector('#formDuree');

        if (button.classList.contains('btn-edit')) {
            // Mode Modification
            modalTitle.textContent = 'Modifier le Cycle';
            actionInput.value = 'modifier';
            idInput.value = button.dataset.id;
            nomInput.value = button.dataset.nom;
            codeInput.value = button.dataset.code;
            dureeInput.value = button.dataset.duree;
        } else {
            // Mode Création
            modalTitle.textContent = 'Nouveau Cycle';
            actionInput.value = 'creer';
            form.reset();
            idInput.value = '';
        }
    });
});
</script>
</body>
</html>