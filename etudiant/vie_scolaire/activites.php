<?php
session_start();
require_once '../../config/db.php';

// Vérifier que l'utilisateur est un étudiant connecté
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'etudiant') {
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
    $stmt_annee = $pdo->query("SELECT * FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1");
    $annee_en_cours = $stmt_annee->fetch();

    $activites = [];
    $classe = null;

    if ($annee_en_cours) {
        // Récupérer la classe de l'étudiant
        $stmt_classe = $pdo->prepare("
            SELECT c.* FROM classes c 
            JOIN inscriptions i ON c.id = i.classe_id 
            WHERE i.etudiant_id = ? AND i.annee_id = ? AND i.statut = 'actif'
        ");
        $stmt_classe->execute([$etudiant_id, $annee_en_cours['id']]);
        $classe = $stmt_classe->fetch();

        // Récupérer TOUTES les activités de l'année scolaire en cours
        // Peu importe la classe ou l'organisateur
        $stmt_activites = $pdo->prepare("
            SELECT a.*, u.nom AS organisateur_nom, u.prenom AS organisateur_prenom,
                   c.nom AS classe_nom, c.niveau AS classe_niveau
            FROM activites_scolaires a
            JOIN utilisateurs u ON a.organisateur_id = u.id
            LEFT JOIN classes c ON a.classe_id = c.id
            WHERE a.annee_id = ?
            ORDER BY a.date_debut DESC
        ");
        $stmt_activites->execute([$annee_en_cours['id']]);
        $activites = $stmt_activites->fetchAll();
    }

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activités Scolaires | Gestion École</title>
    
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
        
        /* Activity Cards */
        .activity-card {
            border-left: 4px solid var(--primary);
            border-radius: 8px;
            margin-bottom: 20px;
            transition: all 0.3s;
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .activity-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .activity-card.upcoming {
            border-left-color: var(--success);
        }
        
        .activity-card.ongoing {
            border-left-color: var(--info);
        }
        
        .activity-card.past {
            border-left-color: var(--secondary);
        }
        
        .activity-date {
            font-weight: 600;
            color: var(--primary);
        }
        
        .activity-date.upcoming {
            color: var(--success);
        }
        
        .activity-date.ongoing {
            color: var(--info);
        }
        
        .activity-date.past {
            color: var(--secondary);
        }
        
        .activity-time {
            background: rgba(108, 92, 231, 0.1);
            color: var(--primary);
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-block;
        }
        
        .activity-time.upcoming {
            background: rgba(0, 184, 148, 0.1);
            color: var(--success);
        }
        
        .activity-time.ongoing {
            background: rgba(9, 132, 227, 0.1);
            color: var(--info);
        }
        
        .activity-time.past {
            background: rgba(0, 206, 201, 0.1);
            color: var(--secondary);
        }
        
        .activity-organizer {
            font-size: 0.9rem;
            color: var(--dark);
        }
        
        .activity-organizer i {
            margin-right: 5px;
            color: var(--accent);
        }
        
        .activity-target {
            font-size: 0.85rem;
            color: var(--info);
            background: rgba(9, 132, 227, 0.1);
            padding: 2px 8px;
            border-radius: 15px;
            display: inline-block;
            margin-top: 5px;
        }
        
        /* Badges */
        .activity-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .badge-sport { background: #fd79a8; color: white; }
        .badge-culture { background: #a29bfe; color: white; }
        .badge-scientifique { background: #74b9ff; color: white; }
        .badge-educatif { background: #55efc4; color: #2d3436; }
        .badge-sortie { background: #ffeaa7; color: #2d3436; }
        
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
        
        /* Floating Button */
        .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            z-index: 100;
            transition: all 0.3s;
            border: none;
        }
        
        .floating-btn:hover {
            transform: scale(1.1);
            color: white;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
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
        
        /* Animations */
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
        .delay-4 { animation-delay: 0.4s; }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 5rem;
            color: #e0e0e0;
            margin-bottom: 20px;
        }
        
        .empty-state h4 {
            color: #9e9e9e;
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: #bdbdbd;
            max-width: 500px;
            margin: 0 auto 25px;
        }
        
        /* Stats */
        .stats-row {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
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
                <a href="../ressources/cours.php" class="nav-link">
                    <i class="fas fa-file-pdf"></i> Cours & Documents
                </a>
            </li>
            <li class="nav-item">
                <a href="../ressources/bibliotheque.php" class="nav-link">
                    <i class="fas fa-book-open"></i> Bibliothèque
                </a>
            </li>
            
            <li class="nav-category">Vie Scolaire</li>
            <li class="nav-item">
                <a href="absences.php" class="nav-link">
                    <i class="fas fa-user-clock"></i> Mes Absences
                </a>
            </li>
            <li class="nav-item">
                <a href="activites.php" class="nav-link active">
                    <i class="fas fa-running"></i> Activités
                </a>
            </li>
        </ul>
        
        <div class="user-profile">
            <img src="../../../assets/img/profiles/<?= htmlspecialchars($etudiant['photo_profil'] ?? 'default.png') ?>" alt="Photo de profil">
            <div class="user-info">
                <h6><?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?></h6>
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
                <i class="fas fa-running text-primary me-2"></i> Activités Scolaires
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
        
        <!-- Statistiques des activités -->
        <?php if (!empty($activites)): 
            $now = new DateTime();
            $upcoming = $ongoing = $past = 0;
            foreach ($activites as $activite) {
                $date_debut = new DateTime($activite['date_debut']);
                $date_fin = new DateTime($activite['date_fin']);
                
                if ($date_debut > $now) {
                    $upcoming++;
                } elseif ($date_fin < $now) {
                    $past++;
                } else {
                    $ongoing++;
                }
            }
        ?>
        <div class="stats-row animate-fade-in">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-item">
                        <span class="stat-number"><?= count($activites) ?></span>
                        <span class="stat-label">Total Activités</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <span class="stat-number"><?= $upcoming ?></span>
                        <span class="stat-label">À Venir</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <span class="stat-number"><?= $ongoing ?></span>
                        <span class="stat-label">En Cours</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <span class="stat-number"><?= $past ?></span>
                        <span class="stat-label">Terminées</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Filtres -->
        <div class="dashboard-card animate-fade-in mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0">Filtrer les activités</h5>
                        <small class="text-muted">Toutes les activités de l'école sont affichées</small>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-end">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-primary active" data-filter="all">Toutes</button>
                                <button type="button" class="btn btn-outline-primary" data-filter="upcoming">À venir</button>
                                <button type="button" class="btn btn-outline-primary" data-filter="ongoing">En cours</button>
                                <button type="button" class="btn btn-outline-primary" data-filter="past">Passées</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Liste des activités -->
        <div class="dashboard-card animate-fade-in delay-1">
            <div class="card-header">
                <h5><i class="fas fa-calendar-check me-2"></i> Toutes les Activités de l'École</h5>
                <i class="fas fa-running"></i>
            </div>
            <div class="card-body">
                <?php if (!empty($activites)): ?>
                    <div class="row" id="activities-container">
                        <?php 
                        $now = new DateTime();
                        foreach ($activites as $activite): 
                            $date_debut = new DateTime($activite['date_debut']);
                            $date_fin = new DateTime($activite['date_fin']);
                            
                            $status = '';
                            if ($date_debut > $now) {
                                $status = 'upcoming';
                            } elseif ($date_fin < $now) {
                                $status = 'past';
                            } else {
                                $status = 'ongoing';
                            }
                        ?>
                            <div class="col-md-6 mb-4 activity-item" data-status="<?= $status ?>">
                                <div class="activity-card <?= $status ?> p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="mb-1"><?= htmlspecialchars($activite['titre']) ?></h5>
                                            <span class="activity-badge badge-<?= htmlspecialchars($activite['type']) ?>">
                                                <?= ucfirst(htmlspecialchars($activite['type'])) ?>
                                            </span>
                                            <?php if ($activite['classe_id']): ?>
                                                <div class="activity-target">
                                                    <i class="fas fa-users"></i> <?= htmlspecialchars($activite['classe_niveau'] . ' - ' . $activite['classe_nom']) ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="activity-target">
                                                    <i class="fas fa-globe"></i> Toute l'école
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <span class="activity-date <?= $status ?>">
                                            <?= $date_debut->format('d/m/Y') ?>
                                        </span>
                                    </div>
                                    
                                    <p class="text-muted mb-3"><?= nl2br(htmlspecialchars($activite['description'])) ?></p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="activity-time <?= $status ?> me-2">
                                                <i class="fas fa-clock"></i> 
                                                <?= $date_debut->format('H:i') ?> - <?= $date_fin->format('H:i') ?>
                                            </span>
                                            <?php if (!empty($activite['lieu'])): ?>
                                                <span class="activity-time">
                                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($activite['lieu']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="activity-organizer">
                                            <i class="fas fa-user-tie"></i>
                                            <?= htmlspecialchars($activite['organisateur_prenom'] . ' ' . $activite['organisateur_nom']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h4>Aucune activité programmée</h4>
                        <p>Les activités scolaires (sorties, compétitions, événements) apparaîtront ici lorsqu'elles seront planifiées par l'administration.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Floating Button -->
    <button class="floating-btn" data-bs-toggle="modal" data-bs-target="#quickActionsModal">
        <i class="fas fa-bolt"></i>
    </button>
    
    <!-- Quick Actions Modal -->
    <div class="modal fade" id="quickActionsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Actions Rapides</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row text-center">
                        <div class="col-4 mb-4">
                            <a href="../scolarite/bulletin.php" class="btn btn-icon btn-primary rounded-circle">
                                <i class="fas fa-file-alt"></i>
                            </a>
                            <p class="mt-2 mb-0 small">Notes</p>
                        </div>
                        <div class="col-4 mb-4">
                            <a href="../scolarite/emploi.php" class="btn btn-icon btn-primary rounded-circle">
                                <i class="fas fa-calendar-alt"></i>
                            </a>
                            <p class="mt-2 mb-0 small">EDT</p>
                        </div>
                        <div class="col-4 mb-4">
                            <a href="../ressources/cours.php" class="btn btn-icon btn-primary rounded-circle">
                                <i class="fas fa-book"></i>
                            </a>
                            <p class="mt-2 mb-0 small">Cours</p>
                        </div>
                        <div class="col-4 mb-4">
                            <a href="absences.php" class="btn btn-icon btn-primary rounded-circle">
                                <i class="fas fa-user-clock"></i>
                            </a>
                            <p class="mt-2 mb-0 small">Absences</p>
                        </div>
                        <div class="col-4 mb-4">
                            <a href="../scolarite/cahier_texte.php" class="btn btn-icon btn-primary rounded-circle">
                                <i class="fas fa-tasks"></i>
                            </a>
                            <p class="mt-2 mb-0 small">Devoirs</p>
                        </div>
                        <div class="col-4 mb-4">
                            <a href="activites.php" class="btn btn-icon btn-primary rounded-circle">
                                <i class="fas fa-running"></i>
                            </a>
                            <p class="mt-2 mb-0 small">Activités</p>
                        </div>
                    </div>
                </div>
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
            
            // Filtrage des activités
            const filterButtons = document.querySelectorAll('[data-filter]');
            const activityItems = document.querySelectorAll('.activity-item');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Active le bouton cliqué
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    const filter = this.getAttribute('data-filter');
                    
                    activityItems.forEach(item => {
                        if (filter === 'all' || item.getAttribute('data-status') === filter) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>