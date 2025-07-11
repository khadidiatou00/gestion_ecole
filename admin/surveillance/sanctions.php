<?php
session_start();
require_once '../../config/db.php';

// --- SÉCURITÉ ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

// --- GESTION AJAX POUR CHARGER LES ÉTUDIANTS ---
if (isset($_GET['action']) && $_GET['action'] === 'get_etudiants') {
    header('Content-Type: application/json');
    $classe_id = $_GET['classe_id'] ?? 0;
    $etudiants = [];
    if ($classe_id) {
        $stmt = $pdo->prepare("SELECT u.id, u.nom, u.prenom FROM utilisateurs u JOIN inscriptions i ON u.id = i.etudiant_id WHERE i.classe_id = ? ORDER BY u.nom");
        $stmt->execute([$classe_id]);
        $etudiants = $stmt->fetchAll();
    }
    echo json_encode($etudiants);
    exit();
}

$message = '';
$message_type = '';

// --- LOGIQUE CRUD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $etudiant_id = filter_input(INPUT_POST, 'etudiant_id', FILTER_VALIDATE_INT);
    $type_sanction = $_POST['type_sanction'];
    $date_sanction = $_POST['date_sanction'];
    $gravite = $_POST['gravite'];
    $donnee_par = $_SESSION['user_id']; // L'admin connecté
    $annee_id = filter_input(INPUT_POST, 'annee_id', FILTER_VALIDATE_INT);
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    try {
        if ($_POST['action'] === 'add') {
            $sql = "INSERT INTO sanctions (etudiant_id, type_sanction, date_sanction, gravite, donnee_par, annee_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$etudiant_id, $type_sanction, $date_sanction, $gravite, $donnee_par, $annee_id]);
            $message = "La sanction a été enregistrée avec succès !";
            $message_type = 'success';
        } elseif ($_POST['action'] === 'edit') {
            $sql = "UPDATE sanctions SET etudiant_id = ?, type_sanction = ?, date_sanction = ?, gravite = ?, annee_id = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$etudiant_id, $type_sanction, $date_sanction, $gravite, $annee_id, $id]);
            $message = "La sanction a été modifiée avec succès !";
            $message_type = 'success';
        }
    } catch (PDOException $e) {
        $message = "Erreur lors de l'opération : " . $e->getMessage();
        $message_type = 'danger';
    }
}

// SUPPRIMER une sanction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete'])) {
    try {
        $id = filter_input(INPUT_POST, 'id_delete', FILTER_VALIDATE_INT);
        $stmt = $pdo->prepare("DELETE FROM sanctions WHERE id = ?");
        $stmt->execute([$id]);
        $message = "La sanction a été supprimée avec succès.";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "Erreur lors de la suppression : " . $e->getMessage();
        $message_type = 'danger';
    }
}

// --- LOGIQUE D'AFFICHAGE ---
try {
    $selected_annee_id = $_GET['annee_id_filter'] ?? $pdo->query("SELECT id FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetchColumn();
    
    $annees = $pdo->query("SELECT id, annee FROM annees_scolaires ORDER BY annee DESC")->fetchAll();
    $classes = $pdo->query("SELECT id, nom FROM classes ORDER BY nom ASC")->fetchAll();

    $sql = "
        SELECT s.*, 
               CONCAT(u_etu.prenom, ' ', u_etu.nom) as etudiant_nom,
               CONCAT(u_adm.prenom, ' ', u_adm.nom) as admin_nom
        FROM sanctions s
        JOIN utilisateurs u_etu ON s.etudiant_id = u_etu.id
        JOIN utilisateurs u_adm ON s.donnee_par = u_adm.id
        WHERE s.annee_id = :annee_id
        ORDER BY s.date_sanction DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':annee_id' => $selected_annee_id]);
    $sanctions = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_db = "Erreur : " . $e->getMessage();
    $sanctions = $annees = $classes = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Sanctions - GestiSchool Galaxy</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-dark-primary: #0d1117; --bg-dark-secondary: #161b22; --border-color: rgba(255, 255, 255, 0.1);
            --text-primary: #c9d1d9; --text-secondary: #8b949e; --accent-glow-1: #00f2ff;
            --accent-glow-2: #da00ff; --font-primary: 'Poppins', sans-serif; --font-display: 'Orbitron', sans-serif;
            --success: #28a745; --warning: #ffc107; --danger: #dc3545;
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
        .main-header h1 { font-family: var(--font-display); color: #fff; font-size: 2rem; }
        .content-card { background: var(--bg-dark-secondary); border-radius: 12px; padding: 2rem; border: 1px solid var(--border-color); animation: fadeIn 0.5s ease; }
        .btn-glow { border: 1px solid var(--accent-glow-1); color: var(--accent-glow-1); background-color: transparent; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; }
        .btn-glow:hover { background-color: var(--accent-glow-1); color: var(--bg-dark-primary); box-shadow: 0 0 15px var(--accent-glow-1); }
        .galaxy-table { width: 100%; color: var(--text-primary); }
        .galaxy-table th { background-color: rgba(255, 255, 255, 0.05); padding: 1rem; border-bottom: 1px solid var(--border-color); }
        .galaxy-table td { padding: 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .galaxy-table tbody tr:hover { background-color: rgba(255, 255, 255, 0.02); }
        .status-badge { padding: 0.25em 0.6em; font-size: 0.8em; border-radius: 10px; color: #fff; text-shadow: 0 0 5px rgba(0,0,0,0.5); }
        .gravite-leger { background-color: var(--success); }
        .gravite-moyen { background-color: var(--warning); }
        .gravite-grave { background-color: var(--danger); }
        .btn-action { background: transparent; border: 1px solid; border-radius: 5px; padding: 5px 10px; margin: 0 2px; transition: all 0.2s; }
        .btn-action-edit { color: var(--accent-glow-1); border-color: var(--accent-glow-1); }
        .btn-action-edit:hover { background: var(--accent-glow-1); color: var(--bg-dark-primary); }
        .btn-action-delete { color: var(--danger); border-color: var(--danger); }
        .btn-action-delete:hover { background: var(--danger); color: #fff; }
        .modal-content { background: rgba(16, 19, 26, 0.8); backdrop-filter: blur(10px); border: 1px solid var(--border-color); color: var(--text-primary); }
        .modal-header, .modal-footer { border-color: var(--border-color); }
        .form-control, .form-select { background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: var(--text-primary); }
        .form-control:focus, .form-select:focus { background: rgba(0,0,0,0.4); border-color: var(--accent-glow-1); box-shadow: 0 0 10px var(--accent-glow-1); color: #fff; }
        .form-select option { background-color: var(--bg-dark-secondary); }
        @media (max-width: 992px) { #sidebar { left: -260px; } #sidebar.active { left: 0; } #main-content { margin-left: 0; width: 100%; } #sidebar-toggle { display: block; background: transparent; color: var(--text-primary); border: none; font-size: 1.2rem; } }
        #sidebar-toggle { display: none; }
    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- Barre Latérale -->
    <aside id="sidebar">
        <div class="sidebar-header"><a href="../dashboard.php" class="logo"><i class="fas fa-meteor"></i> GestiSchool</a></div>
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
                <li class="nav-category">Vie Scolaire</li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#surveillanceCollapse" role="button" aria-expanded="true" aria-controls="surveillanceCollapse">
                        <i class="fas fa-user-shield"></i> Surveillance <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="collapse show" id="surveillanceCollapse">
                        <ul class="nav flex-column ps-4">
                            <li><a class="nav-link" href="absences.php">Suivi des Absences</a></li>
                            <li><a class="nav-link active" href="sanctions.php">Gestion des Sanctions</a></li>
                            <li><a class="nav-link" href="statistiques.php">Statistiques</a></li>
                        </ul>
                    </div>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Contenu Principal -->
    <main id="main-content">
        <header class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="font-display"><i class="fas fa-balance-scale-right me-3"></i>Gestion des Sanctions</h1>
            <button class="btn" id="sidebar-toggle"><i class="fas fa-bars"></i></button>
        </header>
        
        <div class="d-flex justify-content-end mb-3">
             <button class="btn btn-glow" data-bs-toggle="modal" data-bs-target="#sanctionModal" id="add-sanction-btn">
                <i class="fas fa-plus-circle me-2"></i> Nouvelle Sanction
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type === 'success' ? 'info' : 'danger' ?>" role="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (isset($error_db)): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error_db) ?></div>
        <?php endif; ?>

        <div class="content-card">
            <form method="GET" action="sanctions.php" class="row g-3 mb-4">
                <div class="col-md-8"><label class="form-label">Filtrer par Année Scolaire</label><select class="form-select" name="annee_id_filter" onchange="this.form.submit()"><?php foreach($annees as $annee): ?><option value="<?= $annee['id'] ?>" <?= ($annee['id'] == $selected_annee_id) ? 'selected' : '' ?>><?= htmlspecialchars($annee['annee']) ?></option><?php endforeach; ?></select></div>
            </form>
            <div class="table-responsive mt-4">
                <table class="galaxy-table">
                    <thead>
                        <tr>
                            <th>Date</th><th>Étudiant</th><th>Type</th><th>Gravité</th><th>Donnée par</th><th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sanctions)): ?>
                            <tr><td colspan="6" class="text-center py-4">Aucune sanction trouvée pour cette année.</td></tr>
                        <?php else: ?>
                            <?php foreach ($sanctions as $sanction): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($sanction['date_sanction'])) ?></td>
                                    <td><?= htmlspecialchars($sanction['etudiant_nom']) ?></td>
                                    <td><?= ucfirst(htmlspecialchars($sanction['type_sanction'])) ?></td>
                                    <td><span class="status-badge gravite-<?= strtolower($sanction['gravite']) ?>"><?= ucfirst(htmlspecialchars($sanction['gravite'])) ?></span></td>
                                    <td><?= htmlspecialchars($sanction['admin_nom']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-action btn-action-edit edit-btn" data-bs-toggle="modal" data-bs-target="#sanctionModal" data-sanction='<?= json_encode($sanction) ?>'><i class="fas fa-pencil-alt"></i></button>
                                        <button class="btn btn-action btn-action-delete delete-btn" data-id="<?= $sanction['id'] ?>" data-info="Sanction pour <?= htmlspecialchars($sanction['etudiant_nom']) ?>"><i class="fas fa-trash-alt"></i></button>
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

<!-- Modale pour Ajouter/Modifier -->
<div class="modal fade" id="sanctionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="sanctions.php">
                <div class="modal-header"><h5 class="modal-title" id="sanctionModalLabel">Nouvelle Sanction</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="id" id="form-id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Année Scolaire</label><select class="form-select" name="annee_id" id="form-annee_id" required><?php foreach($annees as $annee): ?><option value="<?= $annee['id'] ?>" <?= ($annee['id'] == $selected_annee_id) ? 'selected' : '' ?>><?= htmlspecialchars($annee['annee']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Classe</label><select class="form-select" id="form-classe_id"><option value="">-- Choisir une classe pour filtrer --</option><?php foreach($classes as $classe): ?><option value="<?= $classe['id'] ?>"><?= htmlspecialchars($classe['nom']) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Étudiant</label><select class="form-select" name="etudiant_id" id="form-etudiant_id" required><option value="">-- Choisir une classe d'abord --</option></select></div>
                    <hr style="border-color: var(--border-color);">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Type de sanction</label><select class="form-select" name="type_sanction" id="form-type_sanction" required><option value="avertissement">Avertissement</option><option value="retenue">Retenue</option><option value="exclusion">Exclusion</option><option value="autre">Autre</option></select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Date</label><input type="date" class="form-control" name="date_sanction" id="form-date_sanction" required value="<?= date('Y-m-d') ?>"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Gravité</label><select class="form-select" name="gravite" id="form-gravite" required><option value="leger">Léger</option><option value="moyen">Moyen</option><option value="grave">Grave</option></select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-glow" id="form-submit-btn">Enregistrer</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Formulaire caché pour la suppression -->
<form method="POST" action="sanctions.php" id="delete-form" class="d-none">
    <input type="hidden" name="action_delete" value="1"><input type="hidden" name="id_delete" id="id-to-delete">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) sidebarToggle.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));

    const modal = document.getElementById('sanctionModal');
    const modalTitle = document.getElementById('sanctionModalLabel');
    const form = modal.querySelector('form');
    const formAction = document.getElementById('form-action');
    const formId = document.getElementById('form-id');
    const formSubmitBtn = document.getElementById('form-submit-btn');
    const classeSelect = document.getElementById('form-classe_id');
    const etudiantSelect = document.getElementById('form-etudiant_id');

    // Charge les étudiants quand une classe est sélectionnée dans la modale
    classeSelect.addEventListener('change', function() {
        const classeId = this.value;
        etudiantSelect.innerHTML = '<option value="">Chargement...</option>';
        if (classeId) {
            fetch(`?action=get_etudiants&classe_id=${classeId}`)
                .then(response => response.json())
                .then(data => {
                    etudiantSelect.innerHTML = '<option value="">-- Choisir un étudiant --</option>';
                    data.forEach(etudiant => {
                        etudiantSelect.innerHTML += `<option value="${etudiant.id}">${etudiant.prenom} ${etudiant.nom}</option>`;
                    });
                });
        } else {
            etudiantSelect.innerHTML = '<option value="">-- Choisir une classe d\'abord --</option>';
        }
    });

    document.getElementById('add-sanction-btn').addEventListener('click', function () {
        modalTitle.textContent = 'Nouvelle Sanction';
        form.reset();
        formAction.value = 'add';
        formId.value = '';
        formSubmitBtn.textContent = 'Enregistrer';
    });
    
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            const data = JSON.parse(this.dataset.sanction);
            modalTitle.textContent = 'Modifier la Sanction';
            form.reset();
            formAction.value = 'edit';
            formId.value = data.id;
            
            document.getElementById('form-annee_id').value = data.annee_id;
            // Note: la classe n'est pas stockée, donc l'utilisateur doit la re-sélectionner pour voir l'étudiant
            etudiantSelect.innerHTML = `<option value="${data.etudiant_id}" selected>${data.etudiant_nom}</option>`;
            document.getElementById('form-type_sanction').value = data.type_sanction;
            document.getElementById('form-date_sanction').value = data.date_sanction;
            document.getElementById('form-gravite').value = data.gravite;
            
            formSubmitBtn.textContent = 'Enregistrer les modifications';
        });
    });

    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function () {
            const info = this.dataset.info;
            if (confirm(`Êtes-vous sûr de vouloir supprimer cette sanction ?\n(${info})`)) {
                document.getElementById('id-to-delete').value = this.dataset.id;
                document.getElementById('delete-form').submit();
            }
        });
    });
});
</script>

</body>
</html>