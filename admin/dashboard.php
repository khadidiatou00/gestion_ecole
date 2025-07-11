<?php
session_start();

// --- SÉCURITÉ ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// --- CONNEXION À LA BASE DE DONNÉES ---
require_once '../config/db.php';

// --- RÉCUPÉRATION DES DONNÉES DYNAMIQUES POUR LE DASHBOARD ---
try {
    // 1. Statistiques pour les cartes
    $totalEtudiants = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'etudiant'")->fetchColumn();
    $totalEnseignants = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'enseignant'")->fetchColumn();
    $totalClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $anneeEnCours = $pdo->query("SELECT annee FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetchColumn() ?: 'N/A';

    // 2. Données pour le graphique de répartition des utilisateurs
    $stmtRoles = $pdo->query("SELECT role, COUNT(*) as count FROM utilisateurs GROUP BY role");
    $rolesData = $stmtRoles->fetchAll();
    $chartRolesLabels = [];
    $chartRolesValues = [];
    foreach ($rolesData as $row) {
        $chartRolesLabels[] = ucfirst($row['role']);
        $chartRolesValues[] = $row['count'];
    }

    // 3. Données pour le graphique des inscriptions par classe
    $stmtClasses = $pdo->query("
        SELECT c.nom, COUNT(i.id) as student_count
        FROM classes c
        LEFT JOIN inscriptions i ON c.id = i.classe_id
        GROUP BY c.id, c.nom
        ORDER BY c.nom
    ");
    $classesData = $stmtClasses->fetchAll();
    $chartClassesLabels = [];
    $chartClassesValues = [];
    foreach ($classesData as $row) {
        $chartClassesLabels[] = $row['nom'];
        $chartClassesValues[] = $row['student_count'];
    }

} catch (PDOException $e) {
    $error_db = "Erreur de connexion à la base de données : " . $e->getMessage();
    $totalEtudiants = $totalEnseignants = $totalClasses = 0;
    $anneeEnCours = 'Erreur';
    $chartRolesLabels = $chartRolesValues = $chartClassesLabels = $chartClassesValues = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - GestiSchool Galaxy</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS Intégré - Thème "Galaxy" (Version Corrigée) -->
    <style>
        :root {
            --bg-dark-primary: #0d1117;
            --bg-dark-secondary: #161b22;
            --border-color: rgba(255, 255, 255, 0.1);
            --text-primary: #c9d1d9;
            --text-secondary: #8b949e;
            --accent-glow-1: #00f2ff;
            --accent-glow-2: #da00ff;
            --font-primary: 'Poppins', sans-serif;
            --font-display: 'Orbitron', sans-serif;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- CORRECTION 1 : Page stabilisée --- */
        /* L'animation "background-pan" a été supprimée du body pour empêcher tout mouvement de la page */
        body {
            font-family: var(--font-primary);
            background-color: var(--bg-dark-primary);
            color: var(--text-primary);
            /* Le fond "étoilé" est conservé mais il est maintenant statique */
            background-image: radial-gradient(circle at 1px 1px, rgba(255, 255, 255, 0.05) 1px, transparent 0);
            background-size: 20px 20px;
        }

        .page-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* --- Barre Latérale (inchangée) --- */
        #sidebar {
            width: 260px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            background: rgba(16, 19, 26, 0.6);
            backdrop-filter: blur(10px);
            border-right: 1px solid var(--border-color);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }
        .sidebar-header .logo {
            font-family: var(--font-display);
            font-size: 1.5rem;
            color: #fff;
            text-shadow: 0 0 5px var(--accent-glow-1), 0 0 10px var(--accent-glow-2);
        }
        .sidebar-header .logo i { margin-right: 10px; }
        .sidebar-nav { padding: 1rem; flex-grow: 1; overflow-y: auto; }
        .nav-category {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            padding: 0.5rem 1rem;
            font-weight: 600;
        }
        .nav-link {
            display: flex; align-items: center; padding: 0.75rem 1rem;
            color: var(--text-primary); text-decoration: none; border-radius: 8px;
            margin-bottom: 5px; transition: all 0.2s ease;
        }
        .nav-link i { width: 25px; margin-right: 15px; text-align: center; font-size: 1.1rem; }
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.05); color: #fff;
            box-shadow: 0 0 10px rgba(0, 242, 255, 0.2);
        }
        .nav-link .arrow { margin-left: auto; transition: transform 0.3s ease; }
        .nav-link[aria-expanded="true"] .arrow { transform: rotate(90deg); }

        /* --- Contenu Principal (inchangé) --- */
        #main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            padding: 2rem;
            transition: all 0.3s ease;
        }
        .main-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 2rem;
        }
        .main-header h1 { font-family: var(--font-display); color: #fff; font-size: 2rem; }
        .user-menu .dropdown-menu {
            background-color: var(--bg-dark-secondary); border: 1px solid var(--border-color);
        }
        .user-menu .dropdown-item { color: var(--text-primary); }
        .user-menu .dropdown-item:hover { background-color: rgba(255, 255, 255, 0.05); }


        /* --- Cartes Statistiques "Nébuleuse" (inchangées) --- */
        .stat-card {
            position: relative; background: var(--bg-dark-secondary); border-radius: 12px;
            padding: 1.5rem; overflow: hidden; border: 1px solid transparent;
            animation: fadeIn 0.5s ease forwards; animation-delay: var(--delay, 0s); opacity: 0;
        }
        .stat-card::before {
            content: ''; position: absolute; top: 0; right: 0; bottom: 0; left: 0;
            z-index: -1; margin: -1px; border-radius: inherit;
            background: conic-gradient(from 180deg at 50% 50%, var(--accent-glow-2) 0%, var(--accent-glow-1) 50%, var(--accent-glow-2) 100%);
            animation: rotate 4s linear infinite;
        }
        @keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .stat-card-icon { font-size: 2.5rem; color: var(--accent-glow-1); margin-bottom: 1rem; text-shadow: 0 0 15px var(--accent-glow-1); }
        .stat-card-title { color: var(--text-secondary); font-size: 1rem; }
        .stat-card-value { font-family: var(--font-display); font-size: 2.5rem; color: #fff; }

        /* --- CORRECTION 2 : Hauteur des graphiques contrôlée --- */
        .chart-container {
            background: var(--bg-dark-secondary); padding: 1.5rem;
            border-radius: 12px; border: 1px solid var(--border-color);
            animation: fadeIn 0.5s ease forwards; animation-delay: 0.4s;
            opacity: 0;
            height: 400px; /* Hauteur fixe pour le conteneur */
            display: flex;
            flex-direction: column;
        }
        .chart-container h5 {
            font-family: var(--font-display); color: #fff;
            margin-bottom: 1.5rem; flex-shrink: 0; /* Empêche le titre de se réduire */
        }
        .chart-container canvas {
            width: 100% !important; /* !important pour outrepasser les styles de Chart.js */
            height: 100% !important;
        }
        
        /* --- Responsive (inchangé) --- */
        #sidebar-toggle { display: none; }
        @media (max-width: 992px) {
            #sidebar { left: -260px; }
            #sidebar.active { left: 0; }
            #main-content { margin-left: 0; width: 100%; }
            #sidebar-toggle { display: block; background: transparent; color: var(--text-primary); border: none; font-size: 1.2rem; }
        }

    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- ============================================================== -->
    <!-- Barre Latérale -->
    <!-- ============================================================== -->
    <aside id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="logo"><i class="fas fa-meteor"></i> GestiSchool</a>
        </div>
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a>
                </li>
                
                <li class="nav-category">Gestion Globale</li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#gestionCollapse" role="button" aria-expanded="false" aria-controls="gestionCollapse">
                        <i class="fas fa-university"></i> Scolarité <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="collapse" id="gestionCollapse">
                        <ul class="nav flex-column ps-4">
                            <li><a class="nav-link" href="gestion/classes.php">Classes</a></li>
                            <li><a class="nav-link" href="gestion/matieres.php">Matières</a></li>
                            <li><a class="nav-link" href="gestion/salles.php">Salles</a></li>
                            <li><a class="nav-link" href="gestion/annees.php">Années Scolaires</a></li>
                            <li><a class="nav-link" href="gestion/cycles.php">Cycles</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#personnelCollapse" role="button" aria-expanded="false" aria-controls="personnelCollapse">
                        <i class="fas fa-users-cog"></i> Personnel <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="collapse" id="personnelCollapse">
                        <ul class="nav flex-column ps-4">
                            <li><a class="nav-link" href="personnel/enseignants.php">Enseignants</a></li>
                            <li><a class="nav-link" href="personnel/etudiants.php">Étudiants</a></li>
                        </ul>
                    </div>
                </li>

                <li class="nav-category">Pédagogie</li>
                 <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#pedagogieCollapse" role="button" aria-expanded="false" aria-controls="pedagogieCollapse">
                        <i class="fas fa-book-reader"></i> Organisation <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="collapse" id="pedagogieCollapse">
                        <ul class="nav flex-column ps-4">
                            <li><a class="nav-link" href="pedagogie/emploi_temps.php">Emploi du Temps</a></li>
                            <li><a class="nav-link" href="pedagogie/affectations.php">Affectations</a></li>
                            <li><a class="nav-link" href="pedagogie/programmes.php">Programmes</a></li>
                            <li><a class="nav-link" href="pedagogie/activites.php">Activites </a></li>
                            <li><a class="nav-link" href="pedagogie/bibliotheque.php">Bibliotheque</a></li>
                            <li><a class="nav-link" href="pedagogie/notifications.php">Notificatios</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#evaluationCollapse" role="button" aria-expanded="false" aria-controls="evaluationCollapse">
                        <i class="fas fa-graduation-cap"></i> Évaluation <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="collapse" id="evaluationCollapse">
                        <ul class="nav flex-column ps-4">
                            <li><a class="nav-link" href="evaluation/notes.php">Gestion des Notes</a></li>
                            <li><a class="nav-link" href="evaluation/bulletins.php">Bulletins</a></li>
                            <li><a class="nav-link" href="evaluation/deliberations.php">Délibérations</a></li>
                        </ul>
                    </div>
                </li>

                <li class="nav-category">Vie Scolaire</li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#surveillanceCollapse" role="button" aria-expanded="false" aria-controls="surveillanceCollapse">
                        <i class="fas fa-user-shield"></i> Surveillance <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="collapse" id="surveillanceCollapse">
                        <ul class="nav flex-column ps-4">
                            <li><a class="nav-link" href="surveillance/absences.php">Suivi des Absences</a></li>
                            <li><a class="nav-link" href="surveillance/sanctions.php">Gestion des Sanctions</a></li>
                            <li><a class="nav-link" href="surveillance/statistiques.php">Statistiques</a></li>
                        </ul>
                    </div>
                </li>
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
                <h1>Tableau de bord</h1>
            </div>
            <div class="header-actions d-flex align-items-center">
                <div class="dropdown user-menu">
                    <a class="btn dropdown-toggle" href="#" role="button" id="userMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-astronaut fs-4"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuLink">
                        <li><a class="dropdown-item" href="#">Mon Profil</a></li>
                        <li><hr class="dropdown-divider" style="border-color: var(--border-color);"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Cartes de statistiques -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6"><div class="stat-card" style="--delay: 0.1s;"><div class="stat-card-icon"><i class="fas fa-user-graduate"></i></div><div class="stat-card-title">Étudiants Inscrits</div><div class="stat-card-value"><?= htmlspecialchars($totalEtudiants) ?></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="stat-card" style="--delay: 0.2s;"><div class="stat-card-icon"><i class="fas fa-chalkboard-teacher"></i></div><div class="stat-card-title">Enseignants Actifs</div><div class="stat-card-value"><?= htmlspecialchars($totalEnseignants) ?></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="stat-card" style="--delay: 0.3s;"><div class="stat-card-icon"><i class="fas fa-school"></i></div><div class="stat-card-title">Classes Ouvertes</div><div class="stat-card-value"><?= htmlspecialchars($totalClasses) ?></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="stat-card" style="--delay: 0.4s;"><div class="stat-card-icon"><i class="far fa-calendar-alt"></i></div><div class="stat-card-title">Année Scolaire</div><div class="stat-card-value" style="font-size: 1.8rem;"><?= htmlspecialchars($anneeEnCours) ?></div></div></div>
        </div>
        
        <?php if (isset($error_db)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_db) ?></div>
        <?php endif; ?>

        <!-- Graphiques -->
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-bar me-2"></i>Inscriptions par Classe</h5>
                    <canvas id="classChart"></canvas>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-pie me-2"></i>Répartition des Utilisateurs</h5>
                    <canvas id="roleChart"></canvas>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Scripts JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('active'));
    }

    Chart.defaults.color = 'rgba(255, 255, 255, 0.7)';
    Chart.defaults.font.family = "'Poppins', sans-serif";

    const classCtx = document.getElementById('classChart')?.getContext('2d');
    if (classCtx) {
        new Chart(classCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartClassesLabels) ?>,
                datasets: [{
                    label: "Nombre d'étudiants",
                    data: <?= json_encode($chartClassesValues) ?>,
                    backgroundColor: 'rgba(0, 242, 255, 0.5)',
                    borderColor: 'rgba(0, 242, 255, 1)',
                    borderWidth: 1, borderRadius: 5,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.1)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    const roleCtx = document.getElementById('roleChart')?.getContext('2d');
    if (roleCtx) {
        new Chart(roleCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($chartRolesLabels) ?>,
                datasets: [{
                    data: <?= json_encode($chartRolesValues) ?>,
                    backgroundColor: ['rgba(218, 0, 255, 0.7)', 'rgba(0, 242, 255, 0.7)', 'rgba(0, 255, 135, 0.7)'],
                    borderColor: ['#da00ff', '#00f2ff', '#00ff87'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 20 } } }
            }
        });
    }
});
</script>
</body>
</html>