<?php
session_start();
require_once '../config/db.php';

// Vérifier que l'utilisateur est un étudiant connecté
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'etudiant') {
       header('Location: ../auth/login.php');
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

    if ($annee_en_cours) {
        // Récupérer la classe de l'étudiant
        $stmt_classe = $pdo->prepare("
            SELECT c.* FROM classes c 
            JOIN inscriptions i ON c.id = i.classe_id 
            WHERE i.etudiant_id = ? AND i.annee_id = ? AND i.statut = 'actif'
        ");
        $stmt_classe->execute([$etudiant_id, $annee_en_cours['id']]);
        $classe = $stmt_classe->fetch();

        if ($classe) {
            // Statistiques
            $stats = [];
            
            

            // Notes
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notes WHERE etudiant_id = ? AND annee_id = ?");
            $stmt->execute([$etudiant_id, $annee_en_cours['id']]);
            $stats['notes'] = $stmt->fetchColumn();

            // Absences
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM absences WHERE etudiant_id = ? AND annee_id = ?");
            $stmt->execute([$etudiant_id, $annee_en_cours['id']]);
            $stats['absences'] = $stmt->fetchColumn();

            // Retards
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM retards WHERE etudiant_id = ? AND annee_id = ?");
            $stmt->execute([$etudiant_id, $annee_en_cours['id']]);
            $stats['retards'] = $stmt->fetchColumn();

            // Devoirs
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM devoirs d 
                WHERE d.classe_id = ? AND d.annee_id = ?
            ");
            $stmt->execute([$classe['id'], $annee_en_cours['id']]);
            $stats['devoirs'] = $stmt->fetchColumn();

            // Prochains cours (emploi du temps du jour)
            $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
            $jour_actuel = $jours[date('w')];

            $stmt_edt = $pdo->prepare("
                SELECT e.*, m.nom AS matiere, u.nom AS enseignant_nom, u.prenom AS enseignant_prenom, s.nom AS salle
                FROM emploi_temps e
                JOIN matieres m ON e.matiere_id = m.id
                JOIN utilisateurs u ON e.enseignant_id = u.id
                JOIN salles s ON e.salle_id = s.id
                WHERE e.classe_id = ? AND e.jour = ? AND e.annee_id = ?
                ORDER BY e.heure_debut
            ");
            $stmt_edt->execute([$classe['id'], $jour_actuel, $annee_en_cours['id']]);
            $emploi_du_temps = $stmt_edt->fetchAll();

            // Dernières notes
            $stmt_notes = $pdo->prepare("
                SELECT n.*, m.nom AS matiere 
                FROM notes n
                JOIN matieres m ON n.matiere_id = m.id
                WHERE n.etudiant_id = ? AND n.annee_id = ?
                ORDER BY n.date_note DESC
                LIMIT 5
            ");
            $stmt_notes->execute([$etudiant_id, $annee_en_cours['id']]);
            $dernieres_notes = $stmt_notes->fetchAll();

            // Dernières annonces
            $stmt_annonces = $pdo->prepare("
                SELECT a.*, u.nom AS enseignant_nom, u.prenom AS enseignant_prenom 
                FROM annonces a
                JOIN utilisateurs u ON a.enseignant_id = u.id
                WHERE (a.classe_id = ? OR a.classe_id IS NULL) AND a.annee_id = ?
                ORDER BY a.date_publication DESC
                LIMIT 3
            ");
            $stmt_annonces->execute([$classe['id'], $annee_en_cours['id']]);
            $annonces = $stmt_annonces->fetchAll();




            

// Connexion à la base (ex: $pdo déjà connecté)

// Récupérer les devoirs à venir (date limite future uniquement)
$stmt_devoirs = $pdo->prepare("
    SELECT d.*, 
           m.nom AS matiere, 
           u.prenom AS enseignant_prenom, 
           u.nom AS enseignant_nom
    FROM devoirs d
    LEFT JOIN matieres m ON d.matiere_id = m.id
    LEFT JOIN utilisateurs u ON d.enseignant_id = u.id
    WHERE d.date_limite >= NOW()
    ORDER BY d.date_limite ASC
");
$stmt_devoirs->execute();
$prochains_devoirs = $stmt_devoirs->fetchAll();


// Fonction pour déterminer la classe CSS d'urgence
function getUrgenceClass($date_limite) {
    $now = new DateTime();
    $date = new DateTime($date_limite);
    $diff = $now->diff($date);
    
    if ($diff->days <= 1) return 'devoir-urgent';
    if ($diff->days <= 3) return 'devoir-proche';
    return 'devoir-normal';
}

            // Dernières absences
            $stmt_absences = $pdo->prepare("
                SELECT a.*, m.nom AS matiere, u.nom AS enseignant_nom, u.prenom AS enseignant_prenom
                FROM absences a
                JOIN matieres m ON a.matiere_id = m.id
                JOIN utilisateurs u ON a.enseignant_id = u.id
                WHERE a.etudiant_id = ? AND a.annee_id = ?
                ORDER BY a.date_absence DESC
                LIMIT 5
            ");
            $stmt_absences->execute([$etudiant_id, $annee_en_cours['id']]);
            $dernieres_absences = $stmt_absences->fetchAll();

            // Derniers retards
            $stmt_retards = $pdo->prepare("
                SELECT r.*
                FROM retards r
                WHERE r.etudiant_id = ? AND r.annee_id = ?
                ORDER BY r.date_retard DESC
                LIMIT 5
            ");
            $stmt_retards->execute([$etudiant_id, $annee_en_cours['id']]);
            $derniers_retards = $stmt_retards->fetchAll();

        } else {
            $classe = null;
            $emploi_du_temps = $dernieres_notes = $annonces = $activites = $prochains_devoirs = $dernieres_absences = $derniers_retards = [];
            $stats = ['notes' => 0, 'absences' => 0, 'retards' => 0, 'devoirs' => 0];
        }
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
    <title>Tableau de Bord Étudiant | Gestion École</title>
    
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
        
        .stat-card-notes { background: linear-gradient(135deg, var(--success), #55efc4); }
        .stat-card-absences { background: linear-gradient(135deg, var(--danger), #ff7675); }
        .stat-card-retards { background: linear-gradient(135deg, var(--warning), #ffeaa7); }
        .stat-card-devoirs { background: linear-gradient(135deg, var(--info), #74b9ff); }
        
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
        
        /* EDT Table */
        .edt-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }
        
        .edt-table th {
            background: var(--light);
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
        }
        
        .edt-table td {
            padding: 15px;
            background: white;
            border-bottom: 2px solid var(--light);
            vertical-align: middle;
        }
        
        .edt-table tr:first-child td {
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        
        .edt-table tr:last-child td {
            border-bottom-left-radius: 10px;
            border-bottom-right-radius: 10px;
        }
        
        .edt-table tr:hover td {
            background: #f8f9fa;
        }
        
        /* Notes */
        .note-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .note-excellent { background: #00b894; color: white; }
        .note-bien { background: #00cec9; color: white; }
        .note-moyen { background: #fdcb6e; color: #2d3436; }
        .note-insuffisant { background: #ff7675; color: white; }
        
        /* Activity Cards */
        .activity-card {
            border-left: 4px solid var(--primary);
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s;
            background: white;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            padding: 15px;
        }
        
        .activity-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .activity-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 5px;
        }
        
        .badge-sport { background: #fd79a8; color: white; }
        .badge-culture { background: #a29bfe; color: white; }
        .badge-scientifique { background: #74b9ff; color: white; }
        .badge-educatif { background: #55efc4; color: #2d3436; }
        .badge-sortie { background: #ffeaa7; color: #2d3436; }
        
        /* Devoir Cards */
        .devoir-card {
            border-left: 4px solid var(--warning);
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s;
            background: white;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            padding: 15px;
        }
        
        .devoir-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .devoir-urgent {
            border-left-color: var(--danger);
        }
        
        .devoir-proche {
            border-left-color: var(--warning);
        }
        
        .devoir-normal {
            border-left-color: var(--success);
        }
        
        /* Absence/Retard Cards */
        .absence-card, .retard-card {
            border-left: 4px solid var(--danger);
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s;
            background: white;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            padding: 15px;
        }
        
        .retard-card {
            border-left-color: var(--warning);
        }
        
        .absence-card:hover, .retard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .absence-justifiee {
            border-left-color: var(--success);
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

        .badge-sport { background-color: #28a745; }
.badge-culture { background-color: #17a2b8; }
.badge-scientifique { background-color: #6610f2; }
.badge-educatif { background-color: #ffc107; }
.badge-sortie { background-color: #dc3545; }

        
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-graduation-cap"></i> GestionÉcole
            </a>
        </div>
        
        <ul class="list-unstyled sidebar-nav">
            <li class="nav-category">Navigation</li>
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-home"></i> Tableau de Bord
                </a>
            </li>
            
            <li class="nav-category">Scolarité</li>
            <li class="nav-item">
                <a href="scolarite/bulletin.php" class="nav-link">
                    <i class="fas fa-file-alt"></i> Mes Notes
                </a>
            </li>
            <li class="nav-item">
                <a href="scolarite/emploi.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i> Emploi du Temps
                </a>
            </li>
            <li class="nav-item">
                <a href="scolarite/cahier_texte.php" class="nav-link">
                    <i class="fas fa-book"></i> Cahier de Texte
                </a>
            </li>
            
            <li class="nav-category">Ressources</li>
            <li class="nav-item">
                <a href="ressources/cours.php" class="nav-link">
                    <i class="fas fa-file-pdf"></i> Cours & Documents
                </a>
            </li>
            <li class="nav-item">
                <a href="ressources/bibliotheque.php" class="nav-link">
                    <i class="fas fa-book-open"></i> Bibliothèque
                </a>
            </li>
            
            <li class="nav-category">Vie Scolaire</li>
            <li class="nav-item">
                <a href="vie_scolaire/absences.php" class="nav-link">
                    <i class="fas fa-user-clock"></i> Mes Absences
                </a>
            </li>
            <li class="nav-item">
                <a href="vie_scolaire/activites.php" class="nav-link">
                    <i class="fas fa-running"></i> Activités
                </a>
            </li>
        </ul>
        
        <div class="user-profile">
            <img src="../../upload/profiles/<?= htmlspecialchars($etudiant['photo_profil'] ?? 'default.png') ?>" alt="Photo de profil">
            <div class="user-info">
                <h6><?= htmlspecialchars($etudiant['prenom'] . ' ' . htmlspecialchars($etudiant['nom']) )?></h6>
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
                <i class="fas fa-home text-primary me-2"></i> Tableau de Bord
            </h2>
            
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                    <li><a class="dropdown-item" href="profiles.php"><i class="fas fa-user me-2"></i> Profil</a></li>
                    <li><a class="dropdown-item" href="notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Déconnexion</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Bienvenue -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3 class="mb-3">Bonjour, <?= htmlspecialchars($etudiant['prenom']) ?> !</h3>
                        <p class="text-muted mb-0">
                            <?php if ($classe): ?>
                                Vous êtes en <strong><?= htmlspecialchars($classe['niveau']) ?> - <?= htmlspecialchars($classe['nom']) ?></strong>
                            <?php else: ?>
                                Vous n'êtes pas encore affecté à une classe pour cette année.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-center">
                        <img src="https://images.pexels.com/photos/5212345/pexels-photo-5212345.jpeg?auto=compress&cs=tinysrgb&w=150" alt="Étudiant" style="max-width: 150px; border-radius: 10px;">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistiques -->
        <div class="row animate-fade-in delay-1">
            <div class="col-md-3">
                <div class="stat-card stat-card-notes">
                    <i class="fas fa-star"></i>
                    <div class="stat-value"><?= $stats['notes'] ?? 0 ?></div>
                    <div class="stat-label">Notes</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-card-absences">
                    <i class="fas fa-user-times"></i>
                    <div class="stat-value"><?= $stats['absences'] ?? 0 ?></div>
                    <div class="stat-label">Absences</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-card-retards">
                    <i class="fas fa-clock"></i>
                    <div class="stat-value"><?= $stats['retards'] ?? 0 ?></div>
                    <div class="stat-label">Retards</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-card-devoirs">
                    <i class="fas fa-tasks"></i>
                    <div class="stat-value"><?= $stats['devoirs'] ?? 0 ?></div>
                    <div class="stat-label">Devoirs</div>
                </div>
            </div>
        </div>
        
        <!-- Emploi du temps du jour et Dernières notes -->
        <div class="row animate-fade-in delay-2">
            <div class="col-lg-8">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-day me-2"></i> Emploi du Temps - Aujourd'hui</h5>
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($emploi_du_temps)): ?>
                            <div class="table-responsive">
                                <table class="edt-table">
                                    <thead>
                                        <tr>
                                            <th>Heure</th>
                                            <th>Matière</th>
                                            <th>Enseignant</th>
                                            <th>Salle</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($emploi_du_temps as $cours): ?>
                                            <tr>
                                                <td>
                                                    <?= date('H:i', strtotime($cours['heure_debut'])) ?> - <?= date('H:i', strtotime($cours['heure_fin'])) ?>
                                                </td>
                                                <td><?= htmlspecialchars($cours['matiere']) ?></td>
                                                <td><?= htmlspecialchars($cours['enseignant_prenom'] . ' ' . htmlspecialchars($cours['enseignant_nom'])) ?></td>
                                                <td><?= htmlspecialchars($cours['salle']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Aucun cours aujourd'hui</h5>
                                <p class="text-muted">Profitez-en pour réviser !</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Dernières notes -->
            <div class="col-lg-4">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h5><i class="fas fa-star me-2"></i> Dernières Notes</h5>
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($dernieres_notes)): ?>
                            <ul class="list-unstyled">
                                <?php foreach ($dernieres_notes as $note): 
                                    $note_class = '';
                                    if ($note['note'] >= 16) $note_class = 'note-excellent';
                                    elseif ($note['note'] >= 14) $note_class = 'note-bien';
                                    elseif ($note['note'] >= 10) $note_class = 'note-moyen';
                                    else $note_class = 'note-insuffisant';
                                ?>
                                    <li class="mb-3 pb-2 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong><?= htmlspecialchars($note['matiere']) ?></strong>
                                            <span class="note-badge <?= $note_class ?>"><?= number_format($note['note'], 1) ?>/20</span>
                                        </div>
                                        <small class="text-muted">
                                            <?= date('d/m/Y', strtotime($note['date_note'])) ?>
                                        </small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="scolarite/bulletin.php" class="btn btn-sm btn-outline-primary w-100">
                                Voir toutes mes notes <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-star fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Aucune note récente</h5>
                                <p class="text-muted">Vos notes apparaîtront ici</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Prochains devoirs et Dernières absences -->
         
        <div class="row animate-fade-in delay-3">
            <div class="col-lg-6">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h5><i class="fas fa-tasks me-2"></i> Prochains Devoirs</h5>
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($prochains_devoirs)): ?>
                            <?php foreach ($prochains_devoirs as $devoir): 
                                $date_limite = new DateTime($devoir['date_limite']);
                                $now = new DateTime();
                                $diff = $now->diff($date_limite);
                                
                                $urgence_class = 'devoir-normal';
                                if ($diff->days <= 1) $urgence_class = 'devoir-urgent';
                                elseif ($diff->days <= 3) $urgence_class = 'devoir-proche';
                            ?>
                                <div class="devoir-card <?= $urgence_class ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-1"><?= htmlspecialchars($devoir['titre']) ?></h6>
                                        <small class="text-muted"><?= $date_limite->format('d/m/Y') ?></small>
                                    </div>
                                    <p class="mb-2 text-muted"><?= htmlspecialchars($devoir['matiere']) ?></p>
                                    <small class="text-primary">
                                        Par <?= htmlspecialchars($devoir['enseignant_prenom'] . ' ' . $devoir['enseignant_nom']) ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                            <a href="scolarite/cahier_texte.php" class="btn btn-sm btn-outline-primary w-100">
                                Voir tous les devoirs <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                   <div class="col-4 mb-4">
                            <a href="scolarite/devoirs.php" class="btn btn-icon btn-primary rounded-circle">
                                <i class="fas fa-tasks"></i>
                            </a>
                            <p class="mt-2 mb-0 small">Devoirs</p>
                        </div>
                                <h5 class="text-muted">Aucun devoir à venir</h5>
                                <p class="text-muted">Les prochains devoirs apparaîtront ici</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Dernières absences -->
            <div class="col-lg-6">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h5><i class="fas fa-user-times me-2"></i> Dernières Absences</h5>
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($dernieres_absences)): ?>
                            <?php foreach ($dernieres_absences as $absence): ?>
                                <div class="absence-card <?= $absence['justifiee'] ? 'absence-justifiee' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-1"><?= htmlspecialchars($absence['matiere']) ?></h6>
                                        <small class="text-muted"><?= date('d/m/Y', strtotime($absence['date_absence'])) ?></small>
                                    </div>
                                    <p class="mb-2 text-muted">
                                        <?= date('H:i', strtotime($absence['heure_absence'])) ?> - 
                                        <?= htmlspecialchars($absence['enseignant_prenom'] . ' ' . $absence['enseignant_nom']) ?>
                                    </p>
                                    <span class="badge <?= $absence['justifiee'] ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $absence['justifiee'] ? 'Justifiée' : 'Non justifiée' ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                            <a href="vie_scolaire/absences.php" class="btn btn-sm btn-outline-primary w-100">
                                Voir toutes mes absences <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-check fa-3x text-success mb-3"></i>
                                <h5 class="text-success">Aucune absence récente</h5>
                                <p class="text-muted">Continuez comme ça !</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
       <!-- Prochaines activités -->
<div class="col-lg-6">
    <div class="dashboard-card">
        <div class="card-header">
            <h5><i class="fas fa-running me-2"></i> Prochaines Activités</h5>
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="card-body">
            <?php 
            // Récupérer TOUTES les activités de l'année scolaire en cours
            if ($annee_en_cours) {
                $stmt_activites = $pdo->prepare("
                    SELECT a.*, u.nom AS organisateur_nom, u.prenom AS organisateur_prenom,
                           c.nom AS classe_nom, c.niveau AS classe_niveau
                    FROM activites_scolaires a
                    JOIN utilisateurs u ON a.organisateur_id = u.id
                    LEFT JOIN classes c ON a.classe_id = c.id
                    WHERE a.annee_id = ?
                    ORDER BY a.date_debut DESC
                    LIMIT 3
                ");
                $stmt_activites->execute([$annee_en_cours['id']]);
                $activites = $stmt_activites->fetchAll();
            } else {
                $activites = [];
            }
            
            if (!empty($activites)): ?>
                <?php foreach ($activites as $activite): 
                    $date_debut = new DateTime($activite['date_debut']);
                    $date_fin = new DateTime($activite['date_fin']);
                    $now = new DateTime();
                    
                    // Déterminer le statut de l'activité
                    if ($date_debut > $now) {
                        $status = 'upcoming';
                    } elseif ($date_fin < $now) {
                        $status = 'past';
                    } else {
                        $status = 'ongoing';
                    }
                ?>
                    <div class="activity-card mb-3 p-3 <?= $status ?>" style="border-left: 4px solid; 
                        <?= $status === 'upcoming' ? 'border-color: var(--success)' : '' ?>
                        <?= $status === 'ongoing' ? 'border-color: var(--info)' : '' ?>
                        <?= $status === 'past' ? 'border-color: var(--secondary)' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="mb-1"><?= htmlspecialchars($activite['titre']) ?></h6>
                            <small class="text-muted">
                                <?= $date_debut->format('d/m/Y') ?>
                                <?php if ($date_debut->format('d/m/Y') !== $date_fin->format('d/m/Y')): ?>
                                    - <?= $date_fin->format('d/m/Y') ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        
                        <p class="mb-2 text-muted">
                            <?= htmlspecialchars(substr($activite['description'], 0, 80)) ?>...
                        </p>
                        
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <?php if (!empty($activite['lieu'])): ?>
                                <span class="text-muted">
                                    <i class="fas fa-map-marker-alt me-1"></i> 
                                    <?= htmlspecialchars($activite['lieu']) ?>
                                </span>
                            <?php endif; ?>
                            
                            <span class="activity-time <?= $status ?>">
                                <i class="fas fa-clock"></i> 
                                <?= $date_debut->format('H:i') ?> - <?= $date_fin->format('H:i') ?>
                            </span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="activity-badge badge-<?= htmlspecialchars($activite['type']) ?>">
                                <?= ucfirst(htmlspecialchars($activite['type'])) ?>
                            </span>
                            
                            <small class="text-primary">
                                <i class="fas fa-user-tie"></i>
                                <?= htmlspecialchars($activite['organisateur_prenom'] . ' ' . $activite['organisateur_nom']) ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <a href="vie_scolaire/activites.php" class="btn btn-sm btn-outline-primary w-100 mt-2">
                    Voir toutes les activités <i class="fas fa-arrow-right ms-2"></i>
                </a>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-running fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucune activité prévue</h5>
                    <p class="text-muted">Les prochaines activités scolaires apparaîtront ici</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
        
        <!-- Derniers retards (si il y en a) -->
        <?php if (!empty($derniers_retards)): ?>
        <div class="row animate-fade-in delay-4">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock me-2"></i> Derniers Retards</h5>
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($derniers_retards as $retard): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="retard-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-1">Retard du <?= date('d/m/Y', strtotime($retard['date_retard'])) ?></h6>
                                            <small class="text-muted"><?= date('H:i', strtotime($retard['heure_retard'])) ?></small>
                                        </div>
                                        <p class="mb-0 text-muted">Durée: <?= $retard['duree'] ?> minutes</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
                            <a href="scolarite/bulletin.php" class="btn btn-icon btn-primary rounded-circle">
                                <i class="fas fa-file-alt"></i>
                            </a>
                            <p class="mt-2 mb-0 small">Notes</p>
                        </div>
                        <div class="col-4 mb-4">
                            <a href="scolarite/emploi.php" class="btn btn-icon btn-primary rounded-circle">
                                <i class="fas fa-calendar-alt"></i>
                            </a>
                            <p class="mt-2 mb-0 small">EDT</p>
                        </div>
                        <div class="col-4 mb-4">
                            <a href="ressources/cours.php" class="btn btn-icon btn-primary rounded-circle">
                                <i class="fas fa-book"></i>
                            </a>
                            <p class="mt-2 mb-0 small">Cours</p>
                        </div>
                        <div class="col-4 mb-4">
                            <a href="vie_scolaire/absences.php" class="btn btn-icon btn-primary rounded-circle">
                                <i class="fas fa-user-clock"></i>
                            </a>
                            <p class="mt-2 mb-0 small">Absences</p>
                        </div>
                        <div class="col-4 mb-4">
                            <a href="scolarite/devoirs.php" class="btn btn-icon btn-primary rounded-circle">
                                <i class="fas fa-tasks"></i>
                            </a>
                            <p class="mt-2 mb-0 small">Devoirs</p>
                        </div>
                        <div class="col-4 mb-4">
                            <a href="vie_scolaire/activites.php" class="btn btn-icon btn-primary rounded-circle">
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
    
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