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

    $programme = [];
    $nom_classe_selectionnee = '';
    $annee_selectionnee = '';

    if ($selected_classe_id && $selected_annee_id) {
        // Récupérer le programme basé sur les affectations
        $stmt_programme = $pdo->prepare("
            SELECT 
                m.nom as matiere_nom,
                m.code as matiere_code,
                m.coefficient,
                m.heures_annuelles,
                CONCAT(u.prenom, ' ', u.nom) as enseignant_nom
            FROM enseignant_matieres em
            JOIN matieres m ON em.matiere_id = m.id
            JOIN utilisateurs u ON em.enseignant_id = u.id
            WHERE em.classe_id = ? AND em.annee_id = ?
            ORDER BY m.nom ASC
        ");
        $stmt_programme->execute([$selected_classe_id, $selected_annee_id]);
        $programme = $stmt_programme->fetchAll();
        
        // Noms pour l'affichage du titre
        $nom_classe_selectionnee = $pdo->query("SELECT nom FROM classes WHERE id = $selected_classe_id")->fetchColumn();
        $annee_selectionnee = $pdo->query("SELECT annee FROM annees_scolaires WHERE id = $selected_annee_id")->fetchColumn();
    }
} catch (PDOException $e) {
    $error_db = "Erreur de connexion à la base de données : " . $e->getMessage();
    $annees = $classes = $programme = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programmes Scolaires - GestiSchool Galaxy</title>
    
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
        .main-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .main-header h1 { font-family: var(--font-display); color: #fff; font-size: 2rem; }
        .content-card { background: var(--bg-dark-secondary); border-radius: 12px; padding: 2rem; border: 1px solid var(--border-color); animation: fadeIn 0.5s ease; }
        .btn-glow { border: 1px solid var(--accent-glow-1); color: var(--accent-glow-1); background-color: transparent; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; }
        .btn-glow:hover { background-color: var(--accent-glow-1); color: var(--bg-dark-primary); box-shadow: 0 0 15px var(--accent-glow-1); }
        .form-control, .form-select { background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: var(--text-primary); }
        .form-control:focus, .form-select:focus { background: rgba(0,0,0,0.4); border-color: var(--accent-glow-1); box-shadow: 0 0 10px var(--accent-glow-1); color: #fff; }
        .form-select option { background-color: var(--bg-dark-secondary); }
        .programme-item {
            background: rgba(255, 255, 255, 0.03);
            border-left: 4px solid var(--accent-glow-1);
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .programme-item:hover { transform: translateX(5px); box-shadow: 5px 0 15px -5px rgba(0, 242, 255, 0.2); }
        .matiere-title { font-size: 1.2rem; font-weight: 600; color: #fff; margin-bottom: 0.5rem; }
        .matiere-code { font-family: monospace; font-size: 0.9rem; color: var(--text-secondary); }
        .matiere-detail { display: flex; align-items: center; color: var(--text-primary); margin-top: 0.5rem; }
        .matiere-detail i { width: 20px; text-align: center; margin-right: 10px; color: var(--accent-glow-1); }
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
                            <li><a class="nav-link" href="affectations.php">Affectations</a></li>
                            <li><a class="nav-link active" href="programmes.php">Programmes</a></li>
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
                <h1>Programmes Scolaires</h1>
            </div>
        </header>

        <?php if (isset($error_db)): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error_db) ?></div>
        <?php endif; ?>

        <div class="content-card">
            <!-- Filtres -->
            <form method="GET" action="programmes.php" class="row g-3 align-items-end mb-4">
                <div class="col-md-5">
                    <label for="annee_id" class="form-label">Année Scolaire</label>
                    <select class="form-select" name="annee_id" id="annee_id">
                        <?php foreach($annees as $annee): ?>
                            <option value="<?= $annee['id'] ?>" <?= ($annee['id'] == $selected_annee_id) ? 'selected' : '' ?>><?= htmlspecialchars($annee['annee']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="classe_id" class="form-label">Classe</label>
                    <select class="form-select" name="classe_id" id="classe_id">
                        <option value="">-- Choisir une classe --</option>
                        <?php foreach($classes as $classe): ?>
                            <option value="<?= $classe['id'] ?>" <?= ($classe['id'] == $selected_classe_id) ? 'selected' : '' ?>><?= htmlspecialchars($classe['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-glow w-100">Voir</button>
                </div>
            </form>
            <hr style="border-color: var(--border-color);">

            <!-- Affichage du programme -->
            <div class="mt-4">
                <?php if ($selected_classe_id && $selected_annee_id): ?>
                    <h3 class="mb-4" style="font-family: var(--font-display);">Programme pour : <span class="text-white"><?= htmlspecialchars($nom_classe_selectionnee) ?></span> | <span class="text-white"><?= htmlspecialchars($annee_selectionnee) ?></span></h3>
                    
                    <?php if (empty($programme)): ?>
                        <div class="text-center p-5">
                            <i class="fas fa-folder-open fa-3x text-secondary mb-3"></i>
                            <p class="text-secondary">Aucun programme n'a été défini pour cette classe pour l'année sélectionnée.<br>Veuillez ajouter des affectations pour construire le programme.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                        <?php foreach ($programme as $item): ?>
                            <div class="col-lg-6">
                                <div class="programme-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="matiere-title"><i class="fas fa-book me-2"></i><?= htmlspecialchars($item['matiere_nom']) ?></div>
                                            <div class="matiere-code"><?= htmlspecialchars($item['matiere_code']) ?></div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="matiere-detail"><i class="fas fa-user-tie"></i>Enseignant : <strong><?= htmlspecialchars($item['enseignant_nom']) ?></strong></div>
                                        <div class="matiere-detail"><i class="fas fa-star"></i>Coefficient : <strong><?= htmlspecialchars($item['coefficient']) ?></strong></div>
                                        <div class="matiere-detail"><i class="fas fa-clock"></i>Volume Horaire : <strong><?= htmlspecialchars($item['heures_annuelles']) ?> h/an</strong></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                
                <?php else: ?>
                    <div class="text-center p-5">
                        <i class="fas fa-search fa-3x text-secondary mb-3"></i>
                        <p class="text-secondary">Veuillez sélectionner une année scolaire et une classe pour consulter le programme.</p>
                    </div>
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
});
</script>

</body>
</html>