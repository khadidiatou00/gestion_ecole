<?php
session_start();
require_once '../../config/db.php';

// --- SÉCURITÉ ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

// --- LOGIQUE DE RÉCUPÉRATION DES STATISTIQUES ---
try {
    // Filtre par année
    $selected_annee_id = $_GET['annee_id'] ?? $pdo->query("SELECT id FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetchColumn();
    
    $annees = $pdo->query("SELECT id, annee FROM annees_scolaires ORDER BY annee DESC")->fetchAll();

    // 1. STATS POUR LES CARTES
    $first_day_of_month = date('Y-m-01');
    $last_day_of_month = date('Y-m-t');

    // Absences ce mois-ci
    $stmt_abs_mois = $pdo->prepare("SELECT COUNT(*) FROM absences WHERE date_absence BETWEEN ? AND ? AND annee_id = ?");
    $stmt_abs_mois->execute([$first_day_of_month, $last_day_of_month, $selected_annee_id]);
    $absences_mois = $stmt_abs_mois->fetchColumn();

    // Taux de justification
    $stmt_abs_total = $pdo->prepare("SELECT COUNT(*) FROM absences WHERE annee_id = ?");
    $stmt_abs_total->execute([$selected_annee_id]);
    $total_absences = $stmt_abs_total->fetchColumn();
    
    $stmt_abs_justifiees = $pdo->prepare("SELECT COUNT(*) FROM absences WHERE justifiee = 1 AND annee_id = ?");
    $stmt_abs_justifiees->execute([$selected_annee_id]);
    $total_absences_justifiees = $stmt_abs_justifiees->fetchColumn();
    $taux_justification = ($total_absences > 0) ? ($total_absences_justifiees / $total_absences) * 100 : 0;

    // Total des sanctions
    $stmt_sanctions = $pdo->prepare("SELECT COUNT(*) FROM sanctions WHERE annee_id = ?");
    $stmt_sanctions->execute([$selected_annee_id]);
    $total_sanctions = $stmt_sanctions->fetchColumn();

    // Total des retards
    $stmt_retards = $pdo->prepare("SELECT COUNT(*) FROM retards WHERE annee_id = ?");
    $stmt_retards->execute([$selected_annee_id]);
    $total_retards = $stmt_retards->fetchColumn();


    // 2. DONNÉES POUR LES GRAPHIQUES
    // Absences par classe
    $stmt_abs_classe = $pdo->prepare("SELECT c.nom, COUNT(a.id) as count FROM absences a JOIN classes c ON a.classe_id = c.id WHERE a.annee_id = ? GROUP BY c.id ORDER BY count DESC");
    $stmt_abs_classe->execute([$selected_annee_id]);
    $data_abs_classe = $stmt_abs_classe->fetchAll();
    $chart_abs_classe_labels = array_column($data_abs_classe, 'nom');
    $chart_abs_classe_values = array_column($data_abs_classe, 'count');

    // Répartition des sanctions
    $stmt_sanctions_type = $pdo->prepare("SELECT type_sanction, COUNT(*) as count FROM sanctions WHERE annee_id = ? GROUP BY type_sanction");
    $stmt_sanctions_type->execute([$selected_annee_id]);
    $data_sanctions_type = $stmt_sanctions_type->fetchAll();
    $chart_sanctions_labels = array_column($data_sanctions_type, 'type_sanction');
    $chart_sanctions_values = array_column($data_sanctions_type, 'count');


    // 3. DONNÉES POUR LES LISTES "TOP 5"
    // Top 5 absences
    $stmt_top_abs = $pdo->prepare("SELECT CONCAT(u.prenom, ' ', u.nom) as nom_complet, COUNT(a.id) as count FROM absences a JOIN utilisateurs u ON a.etudiant_id = u.id WHERE a.annee_id = ? GROUP BY u.id ORDER BY count DESC LIMIT 5");
    $stmt_top_abs->execute([$selected_annee_id]);
    $top_absences = $stmt_top_abs->fetchAll();

    // Top 5 sanctions
    $stmt_top_sanctions = $pdo->prepare("SELECT CONCAT(u.prenom, ' ', u.nom) as nom_complet, COUNT(s.id) as count FROM sanctions s JOIN utilisateurs u ON s.etudiant_id = u.id WHERE s.annee_id = ? GROUP BY u.id ORDER BY count DESC LIMIT 5");
    $stmt_top_sanctions->execute([$selected_annee_id]);
    $top_sanctions = $stmt_top_sanctions->fetchAll();

} catch (PDOException $e) {
    $error_db = "Erreur de connexion : " . $e->getMessage();
    // Initialisation des variables en cas d'erreur
    $absences_mois = $taux_justification = $total_sanctions = $total_retards = 0;
    $chart_abs_classe_labels = $chart_abs_classe_values = $chart_sanctions_labels = $chart_sanctions_values = [];
    $top_absences = $top_sanctions = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques de Surveillance - GestiSchool Galaxy</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-dark-primary: #0d1117; --bg-dark-secondary: #161b22; --border-color: rgba(255, 255, 255, 0.1);
            --text-primary: #c9d1d9; --text-secondary: #8b949e; --accent-glow-1: #00f2ff;
            --accent-glow-2: #da00ff; --font-primary: 'Poppins', sans-serif; --font-display: 'Orbitron', sans-serif;
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
        .form-select { background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: var(--text-primary); }
        .stat-card { position: relative; background: var(--bg-dark-secondary); border-radius: 12px; padding: 1.5rem; overflow: hidden; border: 1px solid transparent; animation: fadeIn 0.5s ease forwards; animation-delay: var(--delay, 0s); opacity: 0; }
        .stat-card::before { content: ''; position: absolute; top: 0; right: 0; bottom: 0; left: 0; z-index: -1; margin: -1px; border-radius: inherit; background: conic-gradient(from 180deg at 50% 50%, var(--accent-glow-2) 0%, var(--accent-glow-1) 50%, var(--accent-glow-2) 100%); animation: rotate 4s linear infinite; }
        @keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .stat-card-icon { font-size: 2.5rem; color: var(--accent-glow-1); margin-bottom: 1rem; text-shadow: 0 0 15px var(--accent-glow-1); }
        .stat-card-title { color: var(--text-secondary); font-size: 1rem; }
        .stat-card-value { font-family: var(--font-display); font-size: 2.5rem; color: #fff; }
        .chart-container { background: var(--bg-dark-secondary); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border-color); height: 400px; display: flex; flex-direction: column; }
        .chart-container h5 { font-family: var(--font-display); color: #fff; margin-bottom: 1.5rem; flex-shrink: 0; }
        .chart-container canvas { width: 100% !important; height: 100% !important; }
        .top-list { list-style: none; padding: 0; }
        .top-list li { display: flex; justify-content: space-between; padding: 0.75rem; border-bottom: 1px solid var(--border-color); transition: background-color 0.2s ease; }
        .top-list li:hover { background-color: rgba(255,255,255,0.03); }
        .top-list li:last-child { border-bottom: none; }
        .top-list .count { font-weight: bold; color: var(--accent-glow-1); }
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
                            <li><a class="nav-link" href="sanctions.php">Gestion des Sanctions</a></li>
                            <li><a class="nav-link active" href="statistiques.php">Statistiques</a></li>
                        </ul>
                    </div>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Contenu Principal -->
    <main id="main-content">
        <header class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="font-display"><i class="fas fa-chart-line me-3"></i>Statistiques de Surveillance</h1>
            <button class="btn" id="sidebar-toggle"><i class="fas fa-bars"></i></button>
        </header>

        <div class="content-card mb-4">
            <form method="GET" action="statistiques.php" class="row">
                <div class="col-md-6"><label class="form-label">Filtrer par Année Scolaire</label><select class="form-select" name="annee_id" onchange="this.form.submit()"><?php foreach($annees as $annee): ?><option value="<?= $annee['id'] ?>" <?= ($annee['id'] == $selected_annee_id) ? 'selected' : '' ?>><?= htmlspecialchars($annee['annee']) ?></option><?php endforeach; ?></select></div>
            </form>
        </div>

        <!-- Cartes de Synthèse -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6"><div class="stat-card" style="--delay: 0.1s;"><div class="stat-card-icon"><i class="fas fa-user-clock"></i></div><div class="stat-card-title">Absences (ce mois)</div><div class="stat-card-value"><?= $absences_mois ?></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="stat-card" style="--delay: 0.2s;"><div class="stat-card-icon"><i class="fas fa-check-double"></i></div><div class="stat-card-title">Taux Justification</div><div class="stat-card-value"><?= number_format($taux_justification, 1) ?>%</div></div></div>
            <div class="col-xl-3 col-md-6"><div class="stat-card" style="--delay: 0.3s;"><div class="stat-card-icon"><i class="fas fa-hourglass-half"></i></div><div class="stat-card-title">Total Retards</div><div class="stat-card-value"><?= $total_retards ?></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="stat-card" style="--delay: 0.4s;"><div class="stat-card-icon"><i class="fas fa-balance-scale"></i></div><div class="stat-card-title">Total Sanctions</div><div class="stat-card-value"><?= $total_sanctions ?></div></div></div>
        </div>
        
        <!-- Graphiques -->
        <div class="row g-4 mb-4">
            <div class="col-lg-7"><div class="chart-container"><h5 class="font-display"><i class="fas fa-chart-bar me-2"></i>Absences par Classe</h5><canvas id="absencesChart"></canvas></div></div>
            <div class="col-lg-5"><div class="chart-container"><h5 class="font-display"><i class="fas fa-chart-pie me-2"></i>Répartition des Sanctions</h5><canvas id="sanctionsChart"></canvas></div></div>
        </div>

        <!-- Listes Top 5 -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="content-card">
                    <h5 class="font-display mb-3"><i class="fas fa-user-slash me-2"></i>Top 5 - Étudiants les plus absents</h5>
                    <ul class="top-list"><?php foreach($top_absences as $item): ?><li><span><?= htmlspecialchars($item['nom_complet']) ?></span> <span class="count"><?= $item['count'] ?> absences</span></li><?php endforeach; ?></ul>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="content-card">
                     <h5 class="font-display mb-3"><i class="fas fa-gavel me-2"></i>Top 5 - Étudiants les plus sanctionnés</h5>
                     <ul class="top-list"><?php foreach($top_sanctions as $item): ?><li><span><?= htmlspecialchars($item['nom_complet']) ?></span> <span class="count"><?= $item['count'] ?> sanctions</span></li><?php endforeach; ?></ul>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) sidebarToggle.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));

    Chart.defaults.color = 'rgba(255, 255, 255, 0.7)';
    Chart.defaults.font.family = "'Poppins', sans-serif";

    // Graphique 1: Absences par classe
    const absencesCtx = document.getElementById('absencesChart')?.getContext('2d');
    if (absencesCtx) {
        new Chart(absencesCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_abs_classe_labels) ?>,
                datasets: [{ label: "Nombre d'absences", data: <?= json_encode($chart_abs_classe_values) ?>, backgroundColor: 'rgba(218, 0, 255, 0.5)', borderColor: 'rgba(218, 0, 255, 1)', borderWidth: 1, borderRadius: 5 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.1)' } }, x: { grid: { display: false } } } }
        });
    }

    // Graphique 2: Répartition des sanctions
    const sanctionsCtx = document.getElementById('sanctionsChart')?.getContext('2d');
    if (sanctionsCtx) {
        new Chart(sanctionsCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_map('ucfirst', $chart_sanctions_labels)) ?>,
                datasets: [{
                    data: <?= json_encode($chart_sanctions_values) ?>,
                    backgroundColor: ['rgba(255, 193, 7, 0.7)', 'rgba(220, 53, 69, 0.7)', 'rgba(0, 242, 255, 0.7)', 'rgba(40, 167, 69, 0.7)'],
                    borderColor: ['#ffc107', '#dc3545', '#00f2ff', '#28a745'],
                    borderWidth: 1
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { padding: 20 } } } }
        });
    }
});
</script>

</body>
</html>