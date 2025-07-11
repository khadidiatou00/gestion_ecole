<?php
session_start();
require_once '../config/db.php';

// --- SÉCURITÉ ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'enseignant') {
    header('Location: ../auth/login.php');
    exit();
}

$enseignant_id = $_SESSION['user_id'];
$annee_en_cours_id = $pdo->query("SELECT id FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetchColumn();

// --- LOGIQUE PHP (INCHANGÉE) ---
try {
    $stmt_enseignant = $pdo->prepare("SELECT nom, prenom, photo_profil FROM utilisateurs WHERE id = ?");
    $stmt_enseignant->execute([$enseignant_id]);
    $enseignant_info = $stmt_enseignant->fetch();

    $jour_actuel = strtolower(date('l'));
    $heure_actuelle = date('H:i:s');
    $stmt_prochain_cours = $pdo->prepare("SELECT edt.heure_debut, m.nom as matiere_nom, c.nom as classe_nom, s.nom as salle_nom FROM emploi_temps edt JOIN matieres m ON edt.matiere_id = m.id JOIN classes c ON edt.classe_id = c.id JOIN salles s ON edt.salle_id = s.id WHERE edt.enseignant_id = ? AND edt.jour = ? AND edt.heure_debut > ? AND edt.annee_id = ? ORDER BY edt.heure_debut ASC LIMIT 1");
    $stmt_prochain_cours->execute([$enseignant_id, $jour_actuel, $heure_actuelle, $annee_en_cours_id]);
    $prochain_cours = $stmt_prochain_cours->fetch();

    $stmt_devoirs = $pdo->prepare("SELECT d.titre, c.nom as classe_nom, d.date_limite FROM devoirs d JOIN classes c ON d.classe_id = c.id WHERE d.enseignant_id = ? AND d.date_limite >= CURDATE() ORDER BY d.date_limite ASC LIMIT 5");
    $stmt_devoirs->execute([$enseignant_id]);
    $devoirs = $stmt_devoirs->fetchAll();

    $stmt_chart = $pdo->prepare("SELECT m.nom as matiere, COUNT(edt.id) as heures FROM emploi_temps edt JOIN matieres m ON edt.matiere_id = m.id WHERE edt.enseignant_id = ? AND edt.annee_id = ? GROUP BY m.nom");
    $stmt_chart->execute([$enseignant_id, $annee_en_cours_id]);
    $data_chart = $stmt_chart->fetchAll();
    $chart_labels = array_column($data_chart, 'matiere');
    $chart_values = array_column($data_chart, 'heures');
} catch (PDOException $e) {
    $error_db = "Erreur de récupération des données : " . $e->getMessage();
    $enseignant_info = $prochain_cours = $devoirs = [];
    $chart_labels = $chart_values = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - GestiSchool Vibrant</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- ========================================================= -->
    <!-- NOUVEAU CSS "VIBRANT" INTÉGRÉ -->
    <!-- ========================================================= -->
    <style>
        :root {
            --bg-main: #f4f7fc;
            --sidebar-bg: #ffffff;
            --card-bg: #ffffff;
            --primary: #4f46e5;
            --secondary: #64748b;
            --accent: #ec4899;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border-color: #e2e8f0;
            --font-body: 'Poppins', sans-serif;
            --font-title: 'Montserrat', sans-serif;
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: translateY(20px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        @keyframes pulse-glow {
            0% { box-shadow: 0 0 10px rgba(79, 70, 229, 0.2), 0 0 20px rgba(79, 70, 229, 0.1); }
            50% { box-shadow: 0 0 20px rgba(79, 70, 229, 0.4), 0 0 40px rgba(79, 70, 229, 0.2); }
            100% { box-shadow: 0 0 10px rgba(79, 70, 229, 0.2), 0 0 20px rgba(79, 70, 229, 0.1); }
        }

        body {
            font-family: var(--font-body);
            background-color: var(--bg-main);
            color: var(--text-dark);
        }

        .page-wrapper { display: flex; min-height: 100vh; }
        
        /* Barre latérale */
        #sidebar {
            width: 260px; position: fixed; top: 0; left: 0; height: 100vh;
            z-index: 1000; background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            display: flex; flex-direction: column;
            transition: all 0.3s ease;
        }
        .sidebar-header {
            padding: 1.5rem; text-align: center; border-bottom: 1px solid var(--border-color);
        }
        .sidebar-header .logo {
            font-family: var(--font-title); font-size: 1.6rem; color: var(--primary);
            font-weight: 700; text-decoration: none;
        }
        .sidebar-nav { padding: 1rem; flex-grow: 1; overflow-y: auto; }
        .nav-category { font-size: 0.75rem; color: var(--text-light); text-transform: uppercase; padding: 1rem; font-weight: 600; letter-spacing: 0.5px; }
        .nav-link {
            display: flex; align-items: center; padding: 0.8rem 1rem;
            color: var(--secondary); text-decoration: none; border-radius: 8px;
            margin-bottom: 5px; font-weight: 500; transition: all 0.3s ease;
        }
        .nav-link i {
            width: 25px; margin-right: 15px; text-align: center;
            font-size: 1.2rem; transition: all 0.3s ease;
        }
        .nav-link:hover {
            background-color: #eef2ff; color: var(--primary);
        }
        .nav-link.active {
            background: var(--primary); color: #fff;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
        }
        .nav-link.active i { transform: scale(1.1); }
        
        /* Pied de la barre latérale */
        .sidebar-footer { padding: 1rem; border-top: 1px solid var(--border-color); }
        .user-info { display: flex; align-items: center; }
        .user-info img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; margin-right: 12px; border: 2px solid var(--primary); }
        .user-info .username { font-weight: 600; color: var(--text-dark); }
        
        /* Contenu principal */
        #main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2.5rem; }
        .main-header h1 { font-family: var(--font-title); font-weight: 700; }
        
        .dashboard-card {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 16px; padding: 1.5rem 2rem;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.07), 0 4px 6px -2px rgba(0,0,0,0.05);
            animation: fadeInScale 0.6s ease-out forwards;
            opacity: 0;
            transform-origin: center;
        }
        .card-header-custom {
            display: flex; align-items: center; margin-bottom: 1.5rem;
            padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);
        }
        .card-header-custom .icon-wrapper {
            width: 48px; height: 48px; border-radius: 12px;
            display: grid; place-items: center; margin-right: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff; font-size: 1.5rem; box-shadow: 0 4px 8px rgba(79, 70, 229, 0.3);
        }
        .card-header-custom h5 { font-family: var(--font-title); font-size: 1.3rem; margin: 0; font-weight: 600; }
        
        .quick-access-btn {
            text-align: center; background: var(--card-bg);
            border: 1px solid var(--border-color); border-radius: 16px;
            padding: 2rem 1.5rem; text-decoration: none; color: var(--text-dark);
            font-weight: 600; font-family: var(--font-title);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.07), 0 4px 6px -2px rgba(0,0,0,0.05);
            animation: fadeInScale 0.6s ease-out forwards;
            opacity: 0;
        }
        .quick-access-btn i {
            font-size: 2.5rem; margin-bottom: 1rem;
            background: -webkit-linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            transition: all 0.3s ease;
        }
        .quick-access-btn:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
        }

        .list-group-item { background: transparent; border-color: transparent; border-bottom: 1px solid var(--border-color); padding: 1rem 0; }
        .list-group-item:last-child { border-bottom: none; }
        .list-group-item strong { color: var(--text-dark); }
        .badge { font-weight: 600; }
        
        @media (max-width: 992px) { #sidebar { left: -260px; } #sidebar.active { left: 0; } #main-content { margin-left: 0; width: 100%; } #sidebar-toggle { display: block; } }
        #sidebar-toggle { display: none; background: transparent; border: none; font-size: 1.5rem; }
    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- Barre Latérale -->
    <aside id="sidebar">
        <div class="sidebar-header"><a href="dashboard.php" class="logo"><i class="fas fa-graduation-cap"></i> GestiSchool</a></div>
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
                <li class="nav-category">Pédagogie</li>
                <li class="nav-item"><a class="nav-link" href="pedagogie/cours.php"><i class="fas fa-book-open"></i> Mes Cours</a></li>
                <li class="nav-item"><a class="nav-link" href="pedagogie/devoirs.php"><i class="fas fa-file-signature"></i> Devoirs</a></li>
                <li class="nav-item"><a class="nav-link" href="pedagogie/projets.php"><i class="fas fa-project-diagram"></i> Projets</a></li>
                <li class="nav-item"><a class="nav-link" href="pedagogie/ressources.php"><i class="fas fa-folder-open"></i> Ressources</a></li>
                <li class="nav-category">Évaluation</li>
                <li class="nav-item"><a class="nav-link" href="evaluation/notes.php"><i class="fas fa-marker"></i> Saisie des Notes</a></li>
                <li class="nav-item"><a class="nav-link" href="evaluation/competences.php"><i class="fas fa-tasks"></i> Compétences</a></li>
                <li class="nav-category">Devoirs remise </li>
                <li class="nav-item"><a class="nav-link" href="devoirs/remises.php"><i class="fas fa-envelope"></i>Devoir remise</a></li>
                
                <li class="nav-category">Vie de Classe</li>
                <li class="nav-item"><a class="nav-link" href="presence/absences.php"><i class="fas fa-user-check"></i> Appel & Absences</a></li>
                <li class="nav-item"><a class="nav-link" href="presence/retard.php"><i class="fas fa-user-clock"></i> Retards</a></li>
                <li class="nav-category">Communication</li>
                <li class="nav-item"><a class="nav-link" href="communication/messagerie.php"><i class="fas fa-envelope"></i> Messagerie</a></li>
                <li class="nav-item"><a class="nav-link" href="communication/annonces.php"><i class="fas fa-bullhorn"></i> Annonces</a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <img src="../<?= htmlspecialchars($enseignant_info['photo_profil'] ?: 'assets/img/profiles/default.png') ?>" alt="Photo de profil">
                <div>
                    <div class="username"><?= htmlspecialchars($enseignant_info['prenom'] . ' ' . $enseignant_info['nom']) ?></div>
                    <a href="../auth/logout.php" class="text-danger small">Déconnexion</a>
                </div>
            </div>
        </div>
    </aside>

    <!-- Contenu Principal -->
    <main id="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <button id="sidebar-toggle" class="me-3"><i class="fas fa-bars"></i></button>
                <h1 class="d-inline-block align-middle">Bienvenue, <span style="color: var(--primary);"><?= htmlspecialchars($enseignant_info['prenom']) ?></span> !</h1>
            </div>
        </header>

        <!-- Accès rapides -->
        <div class="row g-4 mb-5">
            <div class="col-md-4"><a href="evaluation/notes.php" class="quick-access-btn" style="animation-delay: 0.1s;"><i class="fas fa-marker"></i> Saisir les notes</a></div>
            <div class="col-md-4"><a href="presence/absences.php" class="quick-access-btn" style="animation-delay: 0.2s;"><i class="fas fa-user-check"></i> Faire l'appel</a></div>
            <div class="col-md-4"><a href="pedagogie/devoirs.php" class="quick-access-btn" style="animation-delay: 0.3s;"><i class="fas fa-file-signature"></i> Gérer les devoirs</a></div>
        </div>

        <div class="row g-4">
            <!-- Colonne de gauche -->
            <div class="col-lg-8">
                <!-- Prochain cours -->
                <div class="dashboard-card mb-4" style="animation-delay: 0.4s;">
                    <div class="card-header-custom"><div class="icon-wrapper"><i class="fas fa-chalkboard"></i></div><h5>Mon Prochain Cours</h5></div>
                    <?php if ($prochain_cours): ?>
                    <ul class="list-group list-group-flush"><li class="list-group-item d-flex justify-content-between"><strong>Heure :</strong> <span class="fw-bold fs-5 text-primary"><?= date('H:i', strtotime($prochain_cours['heure_debut'])) ?></span></li><li class="list-group-item d-flex justify-content-between"><strong>Matière :</strong> <span><?= htmlspecialchars($prochain_cours['matiere_nom']) ?></span></li><li class="list-group-item d-flex justify-content-between"><strong>Classe :</strong> <span><?= htmlspecialchars($prochain_cours['classe_nom']) ?></span></li><li class="list-group-item d-flex justify-content-between"><strong>Salle :</strong> <span><?= htmlspecialchars($prochain_cours['salle_nom']) ?></span></li></ul>
                    <?php else: ?><p class="text-center text-muted p-4">Aucun autre cours prévu pour aujourd'hui. Profitez de votre temps !</p><?php endif; ?>
                </div>
                <!-- Devoirs à rendre -->
                <div class="dashboard-card" style="animation-delay: 0.5s;">
                    <div class="card-header-custom"><div class="icon-wrapper"><i class="fas fa-hourglass-half"></i></div><h5>Devoirs à Rendre</h5></div>
                     <?php if ($devoirs): ?><ul class="list-group list-group-flush"><?php foreach($devoirs as $devoir): ?><li class="list-group-item d-flex justify-content-between align-items-center"><div><strong><?= htmlspecialchars($devoir['titre']) ?></strong><small class="d-block text-muted">Classe : <?= htmlspecialchars($devoir['classe_nom']) ?></small></div><span class="badge rounded-pill" style="background-color:var(--accent); color:#fff;"><?= date('d/m/Y', strtotime($devoir['date_limite'])) ?></span></li><?php endforeach; ?></ul><?php else: ?><p class="text-center text-muted p-4">Aucun devoir programmé.</p><?php endif; ?>
                </div>
            </div>

            <!-- Colonne de droite -->
            <div class="col-lg-4">
                <!-- Répartition des heures -->
                <div class="dashboard-card" style="animation-delay: 0.6s;">
                    <div class="card-header-custom"><div class="icon-wrapper"><i class="fas fa-chart-pie"></i></div><h5>Ma Charge Horaire</h5></div>
                    <canvas id="heuresChart"></canvas>
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

    const heuresCtx = document.getElementById('heuresChart')?.getContext('2d');
    if (heuresCtx) {
        new Chart(heuresCtx, {
            type: 'radar',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Heures de cours',
                    data: <?= json_encode($chart_values) ?>,
                    fill: true,
                    backgroundColor: 'rgba(79, 70, 229, 0.2)',
                    borderColor: 'rgb(79, 70, 229)',
                    pointBackgroundColor: 'rgb(79, 70, 229)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgb(79, 70, 229)'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: true,
                elements: { line: { tension: 0.1, borderWidth: 3 } },
                scales: { r: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, angleLines: { color: 'rgba(0,0,0,0.05)' }, pointLabels: { font: { size: 12, weight: '500' } }, ticks: { backdropColor: 'transparent', color: '#64748b' } } },
                plugins: { legend: { display: false } }
            }
        });
    }
});
</script>

</body>
</html>