<?php
session_start();
require_once '../../config/db.php';

// --- SÉCURITÉ ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

// ===================================================================
// --- SECTION AJAX : GESTION DE LA RÉCUPÉRATION DES ÉTUDIANTS ---
// ===================================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_etudiants') {
    header('Content-Type: application/json');
    $classe_id = $_GET['classe_id'] ?? 0;
    $annee_id = $_GET['annee_id'] ?? 0;
    $etudiants = [];

    if ($classe_id && $annee_id) {
        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.nom, u.prenom 
                FROM utilisateurs u
                JOIN inscriptions i ON u.id = i.etudiant_id
                WHERE i.classe_id = ? AND i.annee_id = ? AND i.statut = 'actif'
                ORDER BY u.nom, u.prenom
            ");
            $stmt->execute([$classe_id, $annee_id]);
            $etudiants = $stmt->fetchAll();
        } catch (PDOException $e) {
            // En cas d'erreur, on renvoie un tableau vide
            $etudiants = [];
        }
    }
    echo json_encode($etudiants);
    exit(); // On arrête le script ici pour ne pas envoyer le reste de la page HTML
}
// ===================================================================
// --- FIN DE LA SECTION AJAX ---
// ===================================================================


// --- LOGIQUE D'AFFICHAGE DE LA PAGE (pour le chargement initial) ---
try {
    // Filtres
    $selected_annee_id = $_GET['annee_id'] ?? $pdo->query("SELECT id FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetchColumn();
    $selected_classe_id = $_GET['classe_id'] ?? null;
    $selected_etudiant_id = $_GET['etudiant_id'] ?? null;

    // Listes pour les filtres
    $annees = $pdo->query("SELECT id, annee FROM annees_scolaires ORDER BY annee DESC")->fetchAll();
    $classes = $pdo->query("SELECT id, nom FROM classes ORDER BY nom ASC")->fetchAll();
    
    // La liste des étudiants sera maintenant chargée par AJAX, 
    // mais on la charge ici aussi pour le cas où la page est rechargée avec un étudiant déjà sélectionné.
    $etudiants = [];
    if ($selected_classe_id && $selected_annee_id) {
        $stmt_etudiants = $pdo->prepare("
            SELECT u.id, u.nom, u.prenom 
            FROM utilisateurs u JOIN inscriptions i ON u.id = i.etudiant_id
            WHERE i.classe_id = ? AND i.annee_id = ? AND i.statut = 'actif'
            ORDER BY u.nom, u.prenom
        ");
        $stmt_etudiants->execute([$selected_classe_id, $selected_annee_id]);
        $etudiants = $stmt_etudiants->fetchAll();
    }
    
    $bulletin_data = null;
    if ($selected_etudiant_id && $selected_classe_id && $selected_annee_id) {
        // ... (La logique de génération du bulletin reste la même)
        $etudiant_info = $pdo->query("SELECT * FROM utilisateurs WHERE id = $selected_etudiant_id")->fetch();
        $classe_info = $pdo->query("SELECT * FROM classes WHERE id = $selected_classe_id")->fetch();
        $annee_info = $pdo->query("SELECT * FROM annees_scolaires WHERE id = $selected_annee_id")->fetch();

        $stmt_bulletin = $pdo->prepare("
            SELECT m.id as matiere_id, m.nom as matiere_nom, m.coefficient,
                   (SELECT GROUP_CONCAT(n.note SEPARATOR ', ') FROM notes n WHERE n.matiere_id = m.id AND n.etudiant_id = :etudiant_id AND n.annee_id = :annee_id) as notes_str
            FROM matieres m JOIN enseignant_matieres em ON m.id = em.matiere_id
            WHERE em.classe_id = :classe_id AND em.annee_id = :annee_id_2
            GROUP BY m.id ORDER BY m.nom
        ");
        $stmt_bulletin->execute([':etudiant_id' => $selected_etudiant_id, ':annee_id' => $selected_annee_id, ':classe_id' => $selected_classe_id, ':annee_id_2' => $selected_annee_id]);
        $bulletin_data_raw = $stmt_bulletin->fetchAll();

        $total_points = 0; $total_coeffs = 0; $bulletin_data = [];
        foreach ($bulletin_data_raw as $row) {
            $notes = !empty($row['notes_str']) ? explode(', ', $row['notes_str']) : [];
            $moyenne_matiere = !empty($notes) ? array_sum($notes) / count($notes) : 0;
            $row['moyenne'] = $moyenne_matiere;
            $bulletin_data[] = $row;
            if ($row['coefficient'] > 0) {
                $total_points += $moyenne_matiere * $row['coefficient'];
                $total_coeffs += $row['coefficient'];
            }
        }
        $moyenne_generale = ($total_coeffs > 0) ? $total_points / $total_coeffs : 0;
    }
} catch (PDOException $e) {
    $error_db = "Erreur de connexion : " . $e->getMessage();
    $annees = $classes = $etudiants = []; $bulletin_data = null;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Génération de Bulletins - GestiSchool Galaxy</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-dark-primary: #0d1117; --bg-dark-secondary: #161b22; --border-color: rgba(255, 255, 255, 0.1);
            --text-primary: #c9d1d9; --text-secondary: #8b949e; --accent-glow-1: #00f2ff;
            --accent-glow-2: #da00ff; --font-primary: 'Poppins', sans-serif; --font-display: 'Orbitron', sans-serif;
        }
        /* ... (Le reste du CSS est identique et reste ici) ... */
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
        .bulletin-container { background-color: #fff; color: #333; padding: 2rem; border-radius: 8px; }
        .bulletin-header { text-align: center; border-bottom: 2px solid #ccc; padding-bottom: 1rem; margin-bottom: 2rem; }
        .bulletin-header h2 { font-family: 'Times New Roman', serif; }
        .bulletin-table { width: 100%; }
        .bulletin-table th, .bulletin-table td { padding: 0.75rem; border: 1px solid #ddd; }
        .bulletin-table thead th { background-color: #f2f2f2; }
        @media print {
            body * { visibility: hidden; }
            #bulletin-section, #bulletin-section * { visibility: visible; }
            #sidebar, .main-header, #filter-form-card, #print-btn { display: none !important; }
            #main-content { margin-left: 0 !important; padding: 0 !important; }
            .content-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
            .bulletin-container { box-shadow: none !important; border-radius: 0 !important; }
        }
        @media (max-width: 992px) { #sidebar { left: -260px; } #sidebar.active { left: 0; } #main-content { margin-left: 0; width: 100%; } #sidebar-toggle { display: block; background: transparent; color: var(--text-primary); border: none; font-size: 1.2rem; } }
        #sidebar-toggle { display: none; }
    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- Barre Latérale -->
    <aside id="sidebar">
        <div class="sidebar-header">
            <a href="../dashboard.php" class="logo"><i class="fas fa-meteor"></i> GestiSchool</a>
        </div>
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
                <li class="nav-category">Évaluation</li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#evaluationCollapse" role="button" aria-expanded="true" aria-controls="evaluationCollapse">
                        <i class="fas fa-graduation-cap"></i> Évaluation <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="collapse show" id="evaluationCollapse">
                        <ul class="nav flex-column ps-4">
                            <li><a class="nav-link" href="notes.php">Gestion des Notes</a></li>
                            <li><a class="nav-link active" href="bulletins.php">Bulletins</a></li>
                            <li><a class="nav-link" href="deliberations.php">Délibérations</a></li>
                        </ul>
                    </div>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Contenu Principal -->
    <main id="main-content">
        <header class="main-header">
            <h1 class="font-display"><i class="fas fa-file-invoice me-3"></i>Bulletins de notes</h1>
            <button class="btn" id="sidebar-toggle"><i class="fas fa-bars"></i></button>
        </header>

        <div class="content-card" id="filter-form-card">
            <!-- Filtres -->
            <form id="filter-form" class="row g-3 align-items-end mb-4">
                <div class="col-md-4"><label class="form-label">Année Scolaire</label><select class="form-select" id="annee_id" name="annee_id"><?php foreach($annees as $annee): ?><option value="<?= $annee['id'] ?>" <?= ($annee['id'] == $selected_annee_id) ? 'selected' : '' ?>><?= htmlspecialchars($annee['annee']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Classe</label><select class="form-select" id="classe_id" name="classe_id"><option value="">-- Choisir --</option><?php foreach($classes as $classe): ?><option value="<?= $classe['id'] ?>" <?= ($classe['id'] == $selected_classe_id) ? 'selected' : '' ?>><?= htmlspecialchars($classe['nom']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Étudiant</label><select class="form-select" id="etudiant_id" name="etudiant_id"><option value="">-- Choisir --</option><?php foreach($etudiants as $etudiant): ?><option value="<?= $etudiant['id'] ?>" <?= ($etudiant['id'] == $selected_etudiant_id) ? 'selected' : '' ?>><?= htmlspecialchars($etudiant['nom'].' '.$etudiant['prenom']) ?></option><?php endforeach; ?></select></div>
            </form>
        </div>
            
        <!-- Affichage du bulletin -->
        <div id="bulletin-section" class="mt-4">
        <?php if ($bulletin_data !== null): ?>
            <div class="content-card">
                <div class="d-flex justify-content-end mb-3">
                    <button id="print-btn" class="btn btn-glow"><i class="fas fa-print me-2"></i> Imprimer / PDF</button>
                </div>
                <div class="bulletin-container">
                    <div class="bulletin-header"><h2>BULLETIN DE NOTES</h2><h4>Année Scolaire: <?= htmlspecialchars($annee_info['annee']) ?></h4></div>
                    <div class="my-4"><p><strong>Nom & Prénom :</strong> <?= htmlspecialchars($etudiant_info['nom'] . ' ' . $etudiant_info['prenom']) ?></p><p><strong>Classe :</strong> <?= htmlspecialchars($classe_info['nom']) ?></p><p><strong>Né(e) le :</strong> <?= date('d/m/Y', strtotime($etudiant_info['date_naissance'])) ?></p></div>
                    <table class="bulletin-table">
                        <thead><tr><th>Matières</th><th>Coeff.</th><th>Notes</th><th>Moyenne/20</th><th>Appréciation</th></tr></thead>
                        <tbody><?php foreach($bulletin_data as $data): ?><tr><td><?= htmlspecialchars($data['matiere_nom']) ?></td><td><?= htmlspecialchars($data['coefficient']) ?></td><td><?= htmlspecialchars($data['notes_str']) ?></td><td><strong><?= number_format($data['moyenne'], 2, ',', ' ') ?></strong></td><td></td></tr><?php endforeach; ?></tbody>
                    </table>
                    <div class="row mt-4"><div class="col-md-6"><h4>Appréciation du conseil de classe</h4><p><em>Travail satisfaisant. Continuez vos efforts.</em></p></div><div class="col-md-6 text-end"><h3>Moyenne Générale : <strong><?= number_format($moyenne_generale, 2, ',', ' ') ?> / 20</strong></h3><p><strong>Rang :</strong> X / <?= count($etudiants) ?></p></div></div>
                </div>
            </div>
        <?php else: ?>
            <div class="content-card text-center p-5"><i class="fas fa-search fa-3x text-secondary mb-3"></i><p class="text-secondary">Veuillez sélectionner une année, une classe et un étudiant pour afficher le bulletin.</p></div>
        <?php endif; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('active'));
    }

    const anneeSelect = document.getElementById('annee_id');
    const classeSelect = document.getElementById('classe_id');
    const etudiantSelect = document.getElementById('etudiant_id');

    // Fonction pour charger les étudiants via AJAX
    function chargerEtudiants() {
        const anneeId = anneeSelect.value;
        const classeId = classeSelect.value;
        const currentEtudiantId = '<?= $selected_etudiant_id ?? '' ?>';

        // Vider la liste des étudiants
        etudiantSelect.innerHTML = '<option value="">-- Charger... --</option>';

        if (classeId && anneeId) {
            fetch(`?action=get_etudiants&classe_id=${classeId}&annee_id=${anneeId}`)
                .then(response => response.json())
                .then(data => {
                    etudiantSelect.innerHTML = '<option value="">-- Choisir un étudiant --</option>';
                    data.forEach(etudiant => {
                        const option = document.createElement('option');
                        option.value = etudiant.id;
                        option.textContent = `${etudiant.nom} ${etudiant.prenom}`;
                        if(etudiant.id == currentEtudiantId) {
                            option.selected = true;
                        }
                        etudiantSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des étudiants:', error);
                    etudiantSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                });
        } else {
             etudiantSelect.innerHTML = '<option value="">-- Choisir une classe d\'abord --</option>';
        }
    }

    // Écouteurs d'événements
    anneeSelect.addEventListener('change', chargerEtudiants);
    classeSelect.addEventListener('change', chargerEtudiants);
    etudiantSelect.addEventListener('change', function() {
        // Soumettre le formulaire pour afficher le bulletin de l'étudiant sélectionné
        if(this.value) {
            document.getElementById('filter-form').action = `bulletins.php?annee_id=${anneeSelect.value}&classe_id=${classeSelect.value}&etudiant_id=${this.value}`;
            document.getElementById('filter-form').submit();
        }
    });

    const printBtn = document.getElementById('print-btn');
    if(printBtn) {
        printBtn.addEventListener('click', function() {
            window.print();
        });
    }
});
</script>

</body>
</html>