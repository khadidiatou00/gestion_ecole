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

    $absences = [];
    $retards = [];
    $classe = null;
    $stats_absences = [
        'total' => 0,
        'justifiees' => 0,
        'non_justifiees' => 0
    ];

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
            // Récupérer les absences de l'étudiant
            $stmt_absences = $pdo->prepare("
                SELECT a.*, m.nom AS matiere, u.nom AS enseignant_nom, u.prenom AS enseignant_prenom
                FROM absences a
                LEFT JOIN matieres m ON a.matiere_id = m.id
                LEFT JOIN utilisateurs u ON a.enseignant_id = u.id
                WHERE a.etudiant_id = ? AND a.annee_id = ?
                ORDER BY a.date_absence DESC, a.heure_absence DESC
            ");
            $stmt_absences->execute([$etudiant_id, $annee_en_cours['id']]);
            $absences = $stmt_absences->fetchAll();

            // Récupérer les retards de l'étudiant
            $stmt_retards = $pdo->prepare("
                SELECT r.*, m.nom AS matiere
                FROM retards r
                LEFT JOIN matieres m ON r.matiere_id = m.id
                WHERE r.etudiant_id = ? AND r.annee_id = ?
                ORDER BY r.date_retard DESC, r.heure_retard DESC
            ");
            $stmt_retards->execute([$etudiant_id, $annee_en_cours['id']]);
            $retards = $stmt_retards->fetchAll();

            // Calculer les statistiques des absences
            $stats_absences['total'] = count($absences);
            $stats_absences['justifiees'] = count(array_filter($absences, function($a) { return $a['justifiee']; }));
            $stats_absences['non_justifiees'] = $stats_absences['total'] - $stats_absences['justifiees'];
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
    <title>Mes Absences | Gestion École</title>
    
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
        
        .stat-card-total { background: linear-gradient(135deg, var(--dark), #636e72); }
        .stat-card-justifiees { background: linear-gradient(135deg, var(--success), #55efc4); }
        .stat-card-non-justifiees { background: linear-gradient(135deg, var(--danger), #ff7675); }
        .stat-card-retards { background: linear-gradient(135deg, var(--warning), #ffeaa7); }
        
        /* Table */
        .absence-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }
        
        .absence-table th {
            background: var(--light);
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
        }
        
        .absence-table td {
            padding: 15px;
            background: white;
            border-bottom: 2px solid var(--light);
            vertical-align: middle;
        }
        
        .absence-table tr:first-child td {
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        
        .absence-table tr:last-child td {
            border-bottom-left-radius: 10px;
            border-bottom-right-radius: 10px;
        }
        
        .absence-table tr:hover td {
            background: #f8f9fa;
        }
        
        /* Badges */
        .badge-absence {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .badge-justifiee { background: #00b894; color: white; }
        .badge-non-justifiee { background: #d63031; color: white; }
        .badge-retard { background: #fdcb6e; color: #2d3436; }
        
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
        
        /* Tabs */
        .nav-tabs .nav-link {
            color: var(--dark);
            border: none;
            padding: 12px 20px;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
            background: transparent;
        }
        
        .nav-tabs .nav-link:hover:not(.active) {
            color: var(--primary-light);
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
                <a href="absences.php" class="nav-link active">
                    <i class="fas fa-user-clock"></i> Mes Absences
                </a>
            </li>
            <li class="nav-item">
                <a href="activites.php" class="nav-link">
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
                <i class="fas fa-user-clock text-primary me-2"></i> Mes Absences
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
        
        <!-- Statistiques -->
        <div class="row animate-fade-in">
            <div class="col-md-3">
                <div class="stat-card stat-card-total">
                    <i class="fas fa-user-times"></i>
                    <div class="stat-value"><?= $stats_absences['total'] ?></div>
                    <div class="stat-label">Absences totales</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-card-justifiees">
                    <i class="fas fa-check-circle"></i>
                    <div class="stat-value"><?= $stats_absences['justifiees'] ?></div>
                    <div class="stat-label">Justifiées</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-card-non-justifiees">
                    <i class="fas fa-times-circle"></i>
                    <div class="stat-value"><?= $stats_absences['non_justifiees'] ?></div>
                    <div class="stat-label">Non justifiées</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-card-retards">
                    <i class="fas fa-clock"></i>
                    <div class="stat-value"><?= count($retards) ?></div>
                    <div class="stat-label">Retards</div>
                </div>
            </div>
        </div>
        
        <!-- Onglets -->
        <div class="dashboard-card animate-fade-in delay-1">
            <ul class="nav nav-tabs" id="absencesTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="absences-tab" data-bs-toggle="tab" data-bs-target="#absences" type="button" role="tab">
                        <i class="fas fa-user-times me-2"></i> Absences
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="retards-tab" data-bs-toggle="tab" data-bs-target="#retards" type="button" role="tab">
                        <i class="fas fa-clock me-2"></i> Retards
                    </button>
                </li>
            </ul>
            
            <div class="card-body">
                <div class="tab-content" id="absencesTabsContent">
                    <!-- Onglet Absences -->
                    <div class="tab-pane fade show active" id="absences" role="tabpanel">
                        <?php if (!empty($absences)): ?>
                            <div class="table-responsive">
                                <table class="absence-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Matière</th>
                                            <th>Heure</th>
                                            <th>Enseignant</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($absences as $absence): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($absence['date_absence'])) ?></td>
                                                <td><?= htmlspecialchars($absence['matiere'] ?? 'Non spécifiée') ?></td>
                                                <td><?= date('H:i', strtotime($absence['heure_absence'])) ?></td>
                                                <td>
                                                    <?php if ($absence['enseignant_nom']): ?>
                                                        <?= htmlspecialchars($absence['enseignant_prenom'] . ' ' . $absence['enseignant_nom']) ?>
                                                    <?php else: ?>
                                                        Non spécifié
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge-absence <?= $absence['justifiee'] ? 'badge-justifiee' : 'badge-non-justifiee' ?>">
                                                        <?= $absence['justifiee'] ? 'Justifiée' : 'Non justifiée' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-check"></i>
                                <h4>Aucune absence enregistrée</h4>
                                <p>Vos absences apparaîtront ici si vous en avez.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Onglet Retards -->
                    <div class="tab-pane fade" id="retards" role="tabpanel">
                        <?php if (!empty($retards)): ?>
                            <div class="table-responsive">
                                <table class="absence-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Matière</th>
                                            <th>Heure</th>
                                            <th>Durée</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($retards as $retard): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($retard['date_retard'])) ?></td>
                                                <td><?= htmlspecialchars($retard['matiere'] ?? 'Non spécifiée') ?></td>
                                                <td><?= date('H:i', strtotime($retard['heure_retard'])) ?></td>
                                                <td>
                                                    <span class="badge-absence badge-retard">
                                                        <?= $retard['duree'] ?> minutes
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-clock"></i>
                                <h4>Aucun retard enregistré</h4>
                                <p>Vos retards apparaîtront ici si vous en avez.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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
        });
    </script>
</body>
</html>