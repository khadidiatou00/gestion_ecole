<?php
session_start();
require_once '../../config/db.php';

// --- SÉCURITÉ ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

// --- GESTION DES REQUÊTES AJAX (JUSTIFIER UNE ABSENCE) ---
if (isset($_POST['action']) && $_POST['action'] === 'justifier_absence') {
    header('Content-Type: application/json');
    $absence_id = filter_input(INPUT_POST, 'absence_id', FILTER_VALIDATE_INT);
    
    if (!$absence_id) {
        echo json_encode(['success' => false, 'message' => 'ID d\'absence invalide.']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE absences SET justifiee = 1 WHERE id = ?");
        $stmt->execute([$absence_id]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Absence justifiée avec succès.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Aucune absence trouvée avec cet ID.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur de base de données.']);
    }
    exit();
}

// --- LOGIQUE D'AFFICHAGE ---
try {
    // Filtres
    $selected_annee_id = $_GET['annee_id'] ?? $pdo->query("SELECT id FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetchColumn();
    $selected_classe_id = $_GET['classe_id'] ?? null;
    $selected_etudiant_id = $_GET['etudiant_id'] ?? null;
    $date_debut = $_GET['date_debut'] ?? date('Y-m-01');
    $date_fin = $_GET['date_fin'] ?? date('Y-m-t');

    // Listes pour les filtres
    $annees = $pdo->query("SELECT id, annee FROM annees_scolaires ORDER BY annee DESC")->fetchAll();
    $classes = $pdo->query("SELECT id, nom FROM classes ORDER BY nom ASC")->fetchAll();
    
    $etudiants = [];
    if ($selected_classe_id) {
        $stmt_etudiants = $pdo->prepare("SELECT u.id, u.nom, u.prenom FROM utilisateurs u JOIN inscriptions i ON u.id = i.etudiant_id WHERE i.classe_id = ? ORDER BY u.nom");
        $stmt_etudiants->execute([$selected_classe_id]);
        $etudiants = $stmt_etudiants->fetchAll();
    }

    // Construction de la requête principale
    $sql = "
        SELECT a.*, 
               CONCAT(u_etu.prenom, ' ', u_etu.nom) as etudiant_nom,
               m.nom as matiere_nom,
               CONCAT(u_ens.prenom, ' ', u_ens.nom) as enseignant_nom
        FROM absences a
        JOIN utilisateurs u_etu ON a.etudiant_id = u_etu.id
        LEFT JOIN matieres m ON a.matiere_id = m.id
        LEFT JOIN utilisateurs u_ens ON a.enseignant_id = u_ens.id
        WHERE a.annee_id = :annee_id AND a.date_absence BETWEEN :date_debut AND :date_fin
    ";
    $params = [
        ':annee_id' => $selected_annee_id,
        ':date_debut' => $date_debut,
        ':date_fin' => $date_fin
    ];

    if ($selected_classe_id) {
        $sql .= " AND a.classe_id = :classe_id";
        $params[':classe_id'] = $selected_classe_id;
    }
    if ($selected_etudiant_id) {
        $sql .= " AND a.etudiant_id = :etudiant_id";
        $params[':etudiant_id'] = $selected_etudiant_id;
    }

    $sql .= " ORDER BY a.date_absence DESC, a.heure_absence DESC";
    
    $stmt_absences = $pdo->prepare($sql);
    $stmt_absences->execute($params);
    $absences = $stmt_absences->fetchAll();

} catch (PDOException $e) {
    $error_db = "Erreur de connexion : " . $e->getMessage();
    $annees = $classes = $etudiants = $absences = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi des Absences - GestiSchool Galaxy</title>
    
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
        .form-control, .form-select { background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: var(--text-primary); }
        .form-control:focus, .form-select:focus { background: rgba(0,0,0,0.4); border-color: var(--accent-glow-1); box-shadow: 0 0 10px var(--accent-glow-1); color: #fff; }
        .form-select option { background-color: var(--bg-dark-secondary); }
        .btn-glow { border: 1px solid var(--accent-glow-1); color: var(--accent-glow-1); background-color: transparent; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; }
        .btn-glow:hover { background-color: var(--accent-glow-1); color: var(--bg-dark-primary); box-shadow: 0 0 15px var(--accent-glow-1); }
        .galaxy-table { width: 100%; color: var(--text-primary); }
        .galaxy-table th { background-color: rgba(255, 255, 255, 0.05); padding: 1rem; border-bottom: 1px solid var(--border-color); }
        .galaxy-table td { padding: 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .galaxy-table tbody tr:hover { background-color: rgba(255, 255, 255, 0.02); }
        .status-badge { padding: 0.25em 0.6em; font-size: 0.8em; border-radius: 10px; color: #fff; }
        .status-justifiee { background-color: var(--success); }
        .status-non-justifiee { background-color: var(--danger); }
        .toast-container { z-index: 1056; }
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
                            <li><a class="nav-link active" href="absences.php">Suivi des Absences</a></li>
                            <li><a class="nav-link" href="sanctions.php">Gestion des Sanctions</a></li>
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
            <h1 class="font-display"><i class="fas fa-user-clock me-3"></i>Suivi des Absences</h1>
            <button class="btn" id="sidebar-toggle"><i class="fas fa-bars"></i></button>
        </header>

        <div class="content-card">
            <!-- Filtres -->
            <form method="GET" action="absences.php" id="filter-form" class="row g-3 align-items-end mb-4">
                <div class="col-md-3"><label class="form-label">Année</label><select class="form-select" name="annee_id"><?php foreach($annees as $annee): ?><option value="<?= $annee['id'] ?>" <?= ($annee['id'] == $selected_annee_id) ? 'selected' : '' ?>><?= htmlspecialchars($annee['annee']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Classe</label><select class="form-select" id="classe-filter" name="classe_id"><option value="">Toutes</option><?php foreach($classes as $classe): ?><option value="<?= $classe['id'] ?>" <?= ($classe['id'] == $selected_classe_id) ? 'selected' : '' ?>><?= htmlspecialchars($classe['nom']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Étudiant</label><select class="form-select" name="etudiant_id"><option value="">Tous</option><?php foreach($etudiants as $etudiant): ?><option value="<?= $etudiant['id'] ?>" <?= ($etudiant['id'] == $selected_etudiant_id) ? 'selected' : '' ?>><?= htmlspecialchars($etudiant['nom'].' '.$etudiant['prenom']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Date début</label><input type="date" class="form-control" name="date_debut" value="<?= $date_debut ?>"></div>
                <div class="col-md-3"><label class="form-label">Date fin</label><input type="date" class="form-control" name="date_fin" value="<?= $date_fin ?>"></div>
                <div class="col-md-3"><button type="submit" class="btn btn-glow w-100">Filtrer</button></div>
            </form>
            <hr style="border-color: var(--border-color);">
            
            <div class="table-responsive mt-4">
                <table class="galaxy-table">
                    <thead>
                        <tr>
                            <th>Date & Heure</th>
                            <th>Étudiant</th>
                            <th>Matière</th>
                            <th>Signalé par</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($absences)): ?>
                            <tr><td colspan="6" class="text-center py-4">Aucune absence trouvée pour les critères sélectionnés.</td></tr>
                        <?php else: ?>
                            <?php foreach ($absences as $absence): ?>
                                <tr id="absence-row-<?= $absence['id'] ?>">
                                    <td><?= date('d/m/Y', strtotime($absence['date_absence'])) ?> à <?= date('H:i', strtotime($absence['heure_absence'])) ?></td>
                                    <td><?= htmlspecialchars($absence['etudiant_nom']) ?></td>
                                    <td><?= htmlspecialchars($absence['matiere_nom'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars($absence['enseignant_nom'] ?: 'Administration') ?></td>
                                    <td class="text-center status-cell">
                                        <?php if ($absence['justifiee']): ?>
                                            <span class="status-badge status-justifiee">Justifiée</span>
                                        <?php else: ?>
                                            <span class="status-badge status-non-justifiee">Non justifiée</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center action-cell">
                                        <?php if (!$absence['justifiee']): ?>
                                            <button class="btn btn-sm btn-outline-success justify-btn" data-id="<?= $absence['id'] ?>">Justifier</button>
                                        <?php endif; ?>
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

<!-- Toast Container pour les notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));
    }

    // Recharge la page quand on change la classe pour mettre à jour la liste des étudiants
    const classeFilter = document.getElementById('classe-filter');
    if (classeFilter) {
        classeFilter.addEventListener('change', () => document.getElementById('filter-form').submit());
    }

    const toastContainer = document.querySelector('.toast-container');
    function showToast(message, type = 'success') {
        const toastId = 'toast-' + Date.now();
        const toastHTML = `<div id="${toastId}" class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert" aria-live="assertive" aria-atomic="true"><div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div>`;
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toast = new bootstrap.Toast(document.getElementById(toastId));
        toast.show();
    }

    // Gestion de la justification d'absence via AJAX
    document.querySelectorAll('.justify-btn').forEach(button => {
        button.addEventListener('click', function() {
            const absenceId = this.dataset.id;
            if (confirm('Voulez-vous vraiment marquer cette absence comme justifiée ?')) {
                const formData = new FormData();
                formData.append('action', 'justifier_absence');
                formData.append('absence_id', absenceId);

                fetch('absences.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        const row = document.getElementById(`absence-row-${absenceId}`);
                        if(row) {
                            row.querySelector('.status-cell').innerHTML = '<span class="status-badge status-justifiee">Justifiée</span>';
                            row.querySelector('.action-cell').innerHTML = ''; // Vide la cellule d'action
                        }
                    } else {
                        showToast(data.message, 'danger');
                    }
                })
                .catch(error => showToast('Erreur de communication.', 'danger'));
            }
        });
    });
});
</script>

</body>
</html>