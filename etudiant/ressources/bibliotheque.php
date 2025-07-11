<?php
session_start();
require_once '../../config/db.php';


// Vérifier que l'utilisateur est un étudiant
if ($_SESSION['user_role'] !== 'etudiant') {
    header('Location: ../../auth/login.php');
    exit();
}

$etudiant_id = $_SESSION['user_id'];

try {
    // Récupérer les informations de l'étudiant
    $stmt_etudiant = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt_etudiant->execute([$etudiant_id]);
    $etudiant = $stmt_etudiant->fetch();

    // Récupérer l'année scolaire en cours
    $annee_en_cours = $pdo->query("SELECT * FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetch();
    
    // Récupérer les ressources de la bibliothèque
    $ressources = $pdo->query(
        "SELECT r.*, c.nom as categorie_nom 
         FROM ressources_bibliotheque r
         JOIN categories_ressources c ON r.categorie_id = c.id
         WHERE r.statut = 'actif'
         ORDER BY r.date_ajout DESC"
    )->fetchAll();

    // Récupérer les catégories de ressources
    $categories = $pdo->query(
        "SELECT c.*, COUNT(r.id) as nb_ressources
         FROM categories_ressources c
         LEFT JOIN ressources_bibliotheque r ON c.id = r.categorie_id AND r.statut = 'actif'
         GROUP BY c.id
         ORDER BY c.nom"
    )->fetchAll();

    // Statistiques
    $stats = [
        'total' => count($ressources),
        'livres' => $pdo->query("SELECT COUNT(*) FROM ressources_bibliotheque WHERE type = 'livre' AND statut = 'actif'")->fetchColumn(),
        'numerique' => $pdo->query("SELECT COUNT(*) FROM ressources_bibliotheque WHERE type = 'numerique' AND statut = 'actif'")->fetchColumn()
    ];

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

// Fonction pour obtenir l'icône selon le type de ressource
function getRessourceIcon($type) {
    $icons = [
        'livre' => 'book',
        'numerique' => 'laptop',
        'article' => 'file-alt',
        'video' => 'film',
        'audio' => 'music',
        'lien' => 'link'
    ];
    return $icons[$type] ?? 'book';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bibliothèque | Gestion École</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS personnalisé -->
    <style>
        :root {
            --primary: #6c5ce7;
            --primary-light: #a29bfe;
            --secondary: #00cec9;
            --accent: #fd79a8;
            --dark: #2d3436;
            --light: #f5f6fa;
            --success: #00b894;
            --warning: #fdcb6e;
            --danger: #d63031;
            --info: #0984e3;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: var(--dark);
            overflow-x: hidden;
        }
        
        /* Sidebar */
        #sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            padding: 20px;
            background: rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .sidebar-header h3 {
            color: white;
            margin: 0;
            font-weight: 600;
        }
        
        .sidebar-header .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }
        
        .sidebar-nav {
            padding: 20px 0;
        }
        
        .nav-item {
            position: relative;
            margin-bottom: 5px;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid var(--accent);
            text-decoration: none;
        }
        
        .nav-link i {
            margin-right: 15px;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        
        .nav-link.active i {
            color: var(--accent);
        }
        
        .nav-category {
            padding: 10px 20px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 20px;
        }
        
        /* Main Content */
        #main-content {
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s;
            min-height: 100vh;
        }
        
        /* Cards */
        .dashboard-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
            position: relative;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 20px;
            border-bottom: none;
            position: relative;
            overflow: hidden;
        }
        
        .card-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(30deg);
            pointer-events: none;
        }
        
        .card-header h5 {
            font-weight: 600;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .card-header i {
            font-size: 1.8rem;
            opacity: 0.3;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 0;
        }
        
        .card-body {
            padding: 25px;
        }
        
        /* Ressources */
        .ressource-card {
            border-left: 4px solid var(--primary);
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .ressource-card:hover {
            transform: translateX(5px);
        }
        
        .ressource-card .ressource-titre {
            font-weight: 600;
            color: var(--primary);
        }
        
        .ressource-card .ressource-date {
            font-size: 0.9rem;
            color: var(--dark);
        }
        
        .ressource-card .ressource-categorie {
            font-size: 0.9rem;
            color: var(--secondary);
        }
        
        .ressource-card .ressource-description {
            margin-top: 10px;
            color: var(--dark);
        }
        
        .ressource-card .ressource-actions {
            margin-top: 15px;
        }
        
        .ressource-icon {
            font-size: 1.5rem;
            margin-right: 10px;
            color: var(--primary);
        }
        
        .ressource-icon.livre { color: #6c5ce7; }
        .ressource-icon.numerique { color: #0984e3; }
        .ressource-icon.article { color: #00b894; }
        .ressource-icon.video { color: #d63031; }
        .ressource-icon.audio { color: #fd79a8; }
        .ressource-icon.lien { color: #fdcb6e; }
        
        /* Catégories */
        .categorie-card {
            display: block;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            text-decoration: none;
            color: var(--dark);
        }
        
        .categorie-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            color: var(--primary);
        }
        
        .categorie-card .categorie-nom {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .categorie-card .categorie-count {
            font-size: 0.8rem;
            color: var(--secondary);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            #sidebar {
                left: -280px;
            }
            
            #sidebar.active {
                left: 0;
            }
            
            #main-content {
                margin-left: 0;
            }
            
            #sidebarCollapse {
                display: block;
            }
        }
        
        /* User Profile */
        .user-profile {
            display: flex;
            align-items: center;
            padding: 20px;
            background: rgba(0, 0, 0, 0.1);
            margin-top: auto;
        }
        
        .user-profile img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.2);
            margin-right: 15px;
        }
        
        .user-profile .user-info h6 {
            margin: 0;
            font-weight: 600;
            color: white;
        }
        
        .user-profile .user-info small {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.8rem;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out forwards;
        }
        
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        
        /* Stats Cards */
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            color: white;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: scale(1.03);
        }
        
        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.8;
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .stat-card-primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
        .stat-card-info { background: linear-gradient(135deg, var(--info), #74b9ff); }
        .stat-card-success { background: linear-gradient(135deg, var(--success), #55efc4); }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header">
            <a href="../dashboard.php" class="logo">
                <i class="fas fa-graduation-cap"></i> GestionÉcole
            </a>
        </div>
        
        <ul class="list-unstyled sidebar-nav">
            <li class="nav-category">Navigation</li>
            <li class="nav-item">
                <a href="../dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Tableau de Bord
                </a>
            </li>
            
            <li class="nav-category">Scolarité</li>
            <li class="nav-item">
                <a href="../scolarite/bulletin.php" class="nav-link">
                    <i class="fas fa-file-alt"></i> Mes Notes
                </a>
            </li>
            <li class="nav-item">
                <a href="../scolarite/emploi.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i> Emploi du Temps
                </a>
            </li>
            <li class="nav-item">
                <a href="../scolarite/cahier_texte.php" class="nav-link">
                    <i class="fas fa-book"></i> Cahier de Texte
                </a>
            </li>
            
            <li class="nav-category">Ressources</li>
            <li class="nav-item">
                <a href="cours.php" class="nav-link">
                    <i class="fas fa-file-pdf"></i> Cours & Documents
                </a>
            </li>
            <li class="nav-item">
                <a href="bibliotheque.php" class="nav-link active">
                    <i class="fas fa-book-open"></i> Bibliothèque
                </a>
            </li>
            
            <li class="nav-category">Vie Scolaire</li>
            <li class="nav-item">
                <a href="../vie_scolaire/absences.php" class="nav-link">
                    <i class="fas fa-user-clock"></i> Mes Absences
                </a>
            </li>
            <li class="nav-item">
                <a href="../vie_scolaire/activites.php" class="nav-link">
                    <i class="fas fa-running"></i> Activités
                </a>
            </li>
        </ul>
        
        <div class="user-profile">
            <img src="../../assets/img/profiles/<?= htmlspecialchars($etudiant['photo_profil'] ?? 'default.png') ?>" alt="Photo de profil">
            <div class="user-info">
                <h6><?= htmlspecialchars($etudiant['prenom'] . ' ' . htmlspecialchars($etudiant['nom'])) ?></h6>
                <small>Étudiant</small>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div id="main-content">
        <!-- Top Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <button id="sidebarCollapse" class="btn btn-primary d-lg-none">
                <i class="fas fa-bars"></i>
            </button>
            
            <h2 class="mb-0">
                <i class="fas fa-book-open text-primary me-2"></i> Bibliothèque
            </h2>
            
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                    <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i> Profil</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-bell me-2"></i> Notifications</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Déconnexion</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Informations étudiant -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h4 class="mb-3"><?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?></h4>
                        <p class="mb-1">
                            <strong>Classe :</strong> 
                            <?= isset($classe) ? htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']) : 'Non affecté' ?>
                        </p>
                        <p class="mb-0">
                            <strong>Année scolaire :</strong> 
                            <?= $annee_en_cours ? htmlspecialchars($annee_en_cours['annee']) : 'Non définie' ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="stat-card stat-card-primary">
                                    <i class="fas fa-book"></i>
                                    <div class="stat-value"><?= $stats['total'] ?></div>
                                    <div class="stat-label">Ressources</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card stat-card-info">
                                    <i class="fas fa-book-open"></i>
                                    <div class="stat-value"><?= $stats['livres'] ?></div>
                                    <div class="stat-label">Livres</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card stat-card-success">
                                    <i class="fas fa-laptop"></i>
                                    <div class="stat-value"><?= $stats['numerique'] ?></div>
                                    <div class="stat-label">Numériques</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Catégories -->
        <div class="dashboard-card animate-fade-in delay-1">
            <div class="card-header">
                <h5><i class="fas fa-tags me-2"></i> Catégories</h5>
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="card-body">
                <?php if (!empty($categories)): ?>
                    <div class="row">
                        <?php foreach ($categories as $categorie): ?>
                            <div class="col-md-3 col-sm-6">
                                <a href="#categorie-<?= $categorie['id'] ?>" class="categorie-card">
                                    <div class="categorie-nom">
                                        <i class="fas fa-tag me-2" style="color: <?= $categorie['couleur'] ?? '#000' ?>"></i>
                                        <?= htmlspecialchars($categorie['nom']) ?>
                                    </div>
                                    <div class="categorie-count">
                                        <?= $categorie['nb_ressources'] ?> ressource<?= $categorie['nb_ressources'] > 1 ? 's' : '' ?>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucune catégorie disponible</h5>
                        <p class="text-muted">Les catégories de ressources apparaîtront ici</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Toutes les ressources -->
        <div class="dashboard-card animate-fade-in delay-2">
            <div class="card-header">
                <h5><i class="fas fa-list-ul me-2"></i> Toutes les ressources</h5>
                <i class="fas fa-book"></i>
            </div>
            <div class="card-body">
                <?php if (!empty($ressources)): ?>
                    <div class="row">
                        <?php foreach ($ressources as $ressource): 
                            $icon = getRessourceIcon($ressource['type']);
                            $icon_class = $ressource['type'];
                        ?>
                            <div class="col-md-6 mb-4">
                                <div class="ressource-card p-4">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-<?= $icon ?> ressource-icon <?= $icon_class ?> me-3"></i>
                                        <div>
                                            <h5 class="ressource-titre"><?= htmlspecialchars($ressource['titre']) ?></h5>
                                            <div class="ressource-categorie">
                                                <i class="fas fa-tag me-2" style="color: <?= $categorie['couleur'] ?? '#000' ?>"></i>
                                                    <?= htmlspecialchars($ressource['categorie_nom']) ?>
                                                </span>
                                            </div>
                                            <div class="ressource-date mt-2">
                                                <i class="far fa-calendar-alt me-1"></i>
                                                Ajouté le <?= date('d/m/Y', strtotime($ressource['date_ajout'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($ressource['description'])): ?>
                                        <div class="ressource-description mt-3">
                                            <?= nl2br(htmlspecialchars($ressource['description'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="ressource-actions mt-3">
                                        <?php if ($ressource['type'] !== 'lien'): ?>
                                            <a href="../../../uploads/bibliotheque/<?= htmlspecialchars($ressource['fichier']) ?>" 
                                               class="btn btn-sm btn-outline-primary me-2" download>
                                                <i class="fas fa-download me-1"></i> Télécharger
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= htmlspecialchars($ressource['lien']) ?>" 
                                               class="btn btn-sm btn-outline-primary me-2" target="_blank">
                                                <i class="fas fa-external-link-alt me-1"></i> Accéder
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($ressource['type'] === 'livre'): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-user-edit me-1"></i> <?= htmlspecialchars($ressource['auteur']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucune ressource disponible</h5>
                        <p class="text-muted">Les ressources de la bibliothèque apparaîtront ici</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle Sidebar
        document.getElementById('sidebarCollapse').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Animation on scroll
        document.addEventListener('DOMContentLoaded', function() {
            const animatedElements = document.querySelectorAll('.animate-fade-in');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = 1;
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });
            
            animatedElements.forEach(element => {
                element.style.opacity = 0;
                element.style.transform = 'translateY(20px)';
                element.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
                observer.observe(element);
            });
        });
    </script>
</body>
</html>