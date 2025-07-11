<?php
session_start();
require_once '../../config/db.php';

// --- SÉCURITÉ ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

// --- LOGIQUE D'AFFICHAGE ---
try {
    // Filtres
    $selected_annee_id = $_GET['annee_id'] ?? $pdo->query("SELECT id FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetchColumn();
    $selected_classe_id = $_GET['classe_id'] ?? null;

    // Listes pour les filtres
    $annees = $pdo->query("SELECT id, annee FROM annees_scolaires ORDER BY annee DESC")->fetchAll();
    $classes = $pdo->query("SELECT id, nom FROM classes ORDER BY nom ASC")->fetchAll();

    $deliberation_data = [];
    $matieres_header = [];
    $classe_info = null;

    if ($selected_classe_id && $selected_annee_id) {
        $classe_info = $pdo->query("SELECT nom FROM classes WHERE id = $selected_classe_id")->fetch();

        // 1. Obtenir la liste des matières du programme de la classe
        $stmt_matieres = $pdo->prepare("
            SELECT m.id, m.nom, m.coefficient
            FROM matieres m
            JOIN enseignant_matieres em ON m.id = em.matiere_id
            WHERE em.classe_id = ? AND em.annee_id = ?
            ORDER BY m.nom
        ");
        $stmt_matieres->execute([$selected_classe_id, $selected_annee_id]);
        $matieres_header = $stmt_matieres->fetchAll(PDO::FETCH_ASSOC);

        // 2. Obtenir la liste des étudiants inscrits
        $stmt_etudiants = $pdo->prepare("
            SELECT u.id, u.nom, u.prenom
            FROM utilisateurs u
            JOIN inscriptions i ON u.id = i.etudiant_id
            WHERE i.classe_id = ? AND i.annee_id = ? AND i.statut = 'actif'
            ORDER BY u.nom, u.prenom
        ");
        $stmt_etudiants->execute([$selected_classe_id, $selected_annee_id]);
        $etudiants = $stmt_etudiants->fetchAll(PDO::FETCH_ASSOC);
        
        // 3. Pour chaque étudiant, calculer sa moyenne dans chaque matière
        foreach ($etudiants as $etudiant) {
            $moyennes_matieres = [];
            $total_points = 0;
            $total_coeffs = 0;

            foreach ($matieres_header as $matiere) {
                $stmt_moyenne = $pdo->prepare("
                    SELECT AVG(note) as moyenne
                    FROM notes
                    WHERE etudiant_id = ? AND matiere_id = ? AND annee_id = ?
                ");
                $stmt_moyenne->execute([$etudiant['id'], $matiere['id'], $selected_annee_id]);
                $result = $stmt_moyenne->fetch();
                $moyenne = $result['moyenne'] ?? null;
                $moyennes_matieres[$matiere['id']] = $moyenne;
                
                if ($moyenne !== null && $matiere['coefficient'] > 0) {
                    $total_points += $moyenne * $matiere['coefficient'];
                    $total_coeffs += $matiere['coefficient'];
                }
            }

            $moyenne_generale = ($total_coeffs > 0) ? $total_points / $total_coeffs : 0;

            $deliberation_data[] = [
                'etudiant_id' => $etudiant['id'],
                'etudiant_nom' => $etudiant['nom'] . ' ' . $etudiant['prenom'],
                'moyennes' => $moyennes_matieres,
                'moyenne_generale' => $moyenne_generale
            ];
        }
    }
} catch (PDOException $e) {
    $error_db = "Erreur de connexion : " . $e->getMessage();
    $annees = $classes = $deliberation_data = $matieres_header = [];
    $classe_info = null;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Délibérations - GestiSchool Galaxy</title>
    
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
        .form-control, .form-select { background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: var(--text-primary); }
        .form-control:focus, .form-select:focus { background: rgba(0,0,0,0.4); border-color: var(--accent-glow-1); box-shadow: 0 0 10px var(--accent-glow-1); color: #fff; }
        .form-select option { background-color: var(--bg-dark-secondary); }
        .btn-glow { border: 1px solid var(--accent-glow-1); color: var(--accent-glow-1); background-color: transparent; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; }
        .btn-glow:hover { background-color: var(--accent-glow-1); color: var(--bg-dark-primary); box-shadow: 0 0 15px var(--accent-glow-1); }
        .deliberation-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .deliberation-table th, .deliberation-table td { padding: 0.75rem; border: 1px solid var(--border-color); text-align: center; vertical-align: middle; }
        .deliberation-table thead th { background-color: rgba(255, 255, 255, 0.05); position: sticky; top: 0; z-index: 1; }
        .deliberation-table td.etudiant-nom { text-align: left; font-weight: 500; }
        .moyenne-generale { font-weight: bold; background: rgba(0, 242, 255, 0.1); }
        .moyenne-ok { color: var(--success); }
        .moyenne-moyen { color: var(--warning); }
        .moyenne-ko { color: var(--danger); }
        @media print {
            body, .content-card, .deliberation-table { background: #fff !important; color: #000 !important; }
            body * { visibility: hidden; }
            #deliberation-card, #deliberation-card * { visibility: visible; }
            #sidebar, .main-header, .content-card > form, #print-btn { display: none !important; }
            #main-content { margin-left: 0 !important; padding: 0 !important; }
            .content-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
            .deliberation-table th, .deliberation-table td { border-color: #ccc !important; }
        }
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
                <li class="nav-category">Évaluation</li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#evaluationCollapse" role="button" aria-expanded="true" aria-controls="evaluationCollapse">
                        <i class="fas fa-graduation-cap"></i> Évaluation <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="collapse show" id="evaluationCollapse">
                        <ul class="nav flex-column ps-4">
                            <li><a class="nav-link" href="notes.php">Gestion des Notes</a></li>
                            <li><a class="nav-link" href="bulletins.php">Bulletins</a></li>
                            <li><a class="nav-link active" href="deliberations.php">Délibérations</a></li>
                        </ul>
                    </div>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Contenu Principal -->
    <main id="main-content">
        <header class="main-header">
            <h1 class="font-display"><i class="fas fa-gavel me-3"></i>Délibérations</h1>
            <button class="btn" id="sidebar-toggle"><i class="fas fa-bars"></i></button>
        </header>

        <div class="content-card">
            <!-- Filtres -->
            <form method="GET" action="deliberations.php" id="filter-form" class="row g-3 align-items-end mb-4">
                <div class="col-md-5"><label class="form-label">Année Scolaire</label><select class="form-select" name="annee_id" onchange="this.form.submit()"><?php foreach($annees as $annee): ?><option value="<?= $annee['id'] ?>" <?= ($annee['id'] == $selected_annee_id) ? 'selected' : '' ?>><?= htmlspecialchars($annee['annee']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-5"><label class="form-label">Classe</label><select class="form-select" name="classe_id" onchange="this.form.submit()"><option value="">-- Choisir une classe --</option><?php foreach($classes as $classe): ?><option value="<?= $classe['id'] ?>" <?= ($classe['id'] == $selected_classe_id) ? 'selected' : '' ?>><?= htmlspecialchars($classe['nom']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><button type="submit" class="btn btn-glow w-100">Afficher</button></div>
            </form>
            <hr style="border-color: var(--border-color);">
            
            <!-- Tableau de délibération -->
            <div id="deliberation-card" class="mt-4">
            <?php if (!empty($deliberation_data)): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Procès-Verbal de Délibération - Classe : <span class="text-white"><?= htmlspecialchars($classe_info['nom']) ?></span></h3>
                    <button id="print-btn" class="btn btn-glow"><i class="fas fa-print me-2"></i> Exporter / Imprimer</button>
                </div>
                <div class="table-responsive">
                    <table class="deliberation-table">
                        <thead>
                            <tr>
                                <th>Étudiant</th>
                                <?php foreach ($matieres_header as $matiere): ?>
                                    <th><?= htmlspecialchars($matiere['nom']) ?></th>
                                <?php endforeach; ?>
                                <th>Moy. Générale</th>
                                <th>Décision</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deliberation_data as $data): ?>
                                <tr>
                                    <td class="etudiant-nom"><?= htmlspecialchars($data['etudiant_nom']) ?></td>
                                    <?php foreach ($matieres_header as $matiere): 
                                        $moyenne = $data['moyennes'][$matiere['id']];
                                        $color_class = '';
                                        if ($moyenne !== null) {
                                            if ($moyenne >= 12) $color_class = 'moyenne-ok';
                                            elseif ($moyenne >= 10) $color_class = 'moyenne-moyen';
                                            else $color_class = 'moyenne-ko';
                                        }
                                    ?>
                                        <td class="<?= $color_class ?>">
                                            <?= $moyenne !== null ? number_format($moyenne, 2, ',', ' ') : '-' ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="moyenne-generale"><?= number_format($data['moyenne_generale'], 2, ',', ' ') ?></td>
                                    <td><!-- TODO: Décision --></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center p-5"><i class="fas fa-filter fa-3x text-secondary mb-3"></i><p class="text-secondary">Veuillez sélectionner une année et une classe pour afficher le tableau de délibération.</p></div>
            <?php endif; ?>
            </div>
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