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
    // Initialiser les tableaux vides pour éviter les erreurs
    $devoirs = [];
    $devoirs_passes = [];
    $stats = [
        'a_venir' => 0,
        'en_retard' => 0,
        'rendus' => 0
    ];

    // Récupérer les informations de l'étudiant
    $stmt_etudiant = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt_etudiant->execute([$etudiant_id]);
    $etudiant = $stmt_etudiant->fetch();

    // Récupérer l'année scolaire en cours
    $annee_en_cours = $pdo->query("SELECT * FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetch();

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
            // Devoirs à venir
            $stmt_devoirs = $pdo->prepare("
                SELECT d.*, m.nom AS matiere, u.nom AS enseignant_nom, u.prenom AS enseignant_prenom
                FROM devoirs d
                JOIN matieres m ON d.matiere_id = m.id
                JOIN utilisateurs u ON d.enseignant_id = u.id
                WHERE d.classe_id = ? AND d.annee_id = ? AND d.date_limite >= CURDATE()
                ORDER BY d.date_limite ASC
            ");
            $stmt_devoirs->execute([$classe['id'], $annee_en_cours['id']]);
            $devoirs = $stmt_devoirs->fetchAll();

            // Devoirs passés (30 derniers jours)
            $stmt_devoirs_passes = $pdo->prepare("
                SELECT d.*, m.nom AS matiere, u.nom AS enseignant_nom, u.prenom AS enseignant_prenom
                FROM devoirs d
                JOIN matieres m ON d.matiere_id = m.id
                JOIN utilisateurs u ON d.enseignant_id = u.id
                WHERE d.classe_id = ? AND d.annee_id = ? 
                  AND d.date_limite < CURDATE()
                  AND d.date_limite >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ORDER BY d.date_limite DESC
            ");
            $stmt_devoirs_passes->execute([$classe['id'], $annee_en_cours['id']]);
            $devoirs_passes = $stmt_devoirs_passes->fetchAll();

            // Statistiques des devoirs
            $stmt_retard = $pdo->prepare("
                SELECT COUNT(*) FROM devoirs 
                WHERE classe_id = ? AND annee_id = ? 
                  AND date_limite < CURDATE() AND rendu = 0
            ");
            $stmt_retard->execute([$classe['id'], $annee_en_cours['id']]);
            $en_retard = $stmt_retard->fetchColumn();

            $stmt_rendus = $pdo->prepare("
                SELECT COUNT(*) FROM devoirs 
                WHERE classe_id = ? AND annee_id = ? 
                  AND rendu = 1
            ");
            $stmt_rendus->execute([$classe['id'], $annee_en_cours['id']]);
            $rendus = $stmt_rendus->fetchColumn();

            // Mettre à jour les statistiques
            $stats = [
                'a_venir' => count($devoirs),
                'en_retard' => $en_retard,
                'rendus' => $rendus
            ];
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
    <title>Cahier de Texte | Gestion École</title>
    
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
        
        /* Devoirs */
        .devoir-card {
            border-left: 4px solid var(--primary);
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .devoir-card:hover {
            transform: translateX(5px);
        }
        
        .devoir-card.en-retard {
            border-left-color: var(--danger);
        }
        
        .devoir-card.rendu {
            border-left-color: var(--success);
        }
        
        .devoir-card .devoir-matiere {
            font-weight: 600;
            color: var(--primary);
        }
        
        .devoir-card.en-retard .devoir-matiere {
            color: var(--danger);
        }
        
        .devoir-card.rendu .devoir-matiere {
            color: var(--success);
        }
        
        .devoir-date {
            font-size: 0.9rem;
            color: var(--dark);
        }
        
        .devoir-date.en-retard {
            color: var(--danger);
        }
        
        .devoir-enseignant {
            font-size: 0.9rem;
            color: var(--secondary);
        }
        
        .devoir-description {
            margin-top: 10px;
            color: var(--dark);
        }
        
        .devoir-actions {
            margin-top: 15px;
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
        
        .stat-card-primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
        .stat-card-danger { background: linear-gradient(135deg, var(--danger), #ff7675); }
        .stat-card-success { background: linear-gradient(135deg, var(--success), #55efc4); }
        
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
        
        /* Onglets */
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
        
        /* Badge */
        .badge-devoir {
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
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
                <a href="bulletin.php" class="nav-link">
                    <i class="fas fa-file-alt"></i> Mes Notes
                </a>
            </li>
            <li class="nav-item">
                <a href="emploi.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i> Emploi du Temps
                </a>
            </li>
            <li class="nav-item">
                <a href="cahier_texte.php" class="nav-link active">
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
            <img src="../../../assets/img/profiles/<?= htmlspecialchars($etudiant['photo_profil'] ?? 'default.png') ?>" alt="Photo de profil">
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
                <i class="fas fa-book text-primary me-2"></i> Cahier de Texte
            </h2>
            
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                    <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i> Profil</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-bell me-2"></i> Notifications</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../../../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Déconnexion</a></li>
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
                            <?= $classe ? htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']) : 'Non affecté' ?>
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
                                    <i class="fas fa-tasks"></i>
                                    <div class="stat-value"><?= $stats['a_venir'] ?></div>
                                    <div class="stat-label">À venir</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card stat-card-danger">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <div class="stat-value"><?= $stats['en_retard'] ?></div>
                                    <div class="stat-label">En retard</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card stat-card-success">
                                    <i class="fas fa-check-circle"></i>
                                    <div class="stat-value"><?= $stats['rendus'] ?></div>
                                    <div class="stat-label">Rendus</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Onglets -->
        <ul class="nav nav-tabs mb-4" id="devoirsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="a-venir-tab" data-bs-toggle="tab" data-bs-target="#a-venir" type="button" role="tab" aria-controls="a-venir" aria-selected="true">
                    <i class="fas fa-calendar-day me-2"></i> À venir
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="passes-tab" data-bs-toggle="tab" data-bs-target="#passes" type="button" role="tab" aria-controls="passes" aria-selected="false">
                    <i class="fas fa-history me-2"></i> Passés
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="devoirsTabsContent">
            <!-- Devoirs à venir -->
            <div class="tab-pane fade show active" id="a-venir" role="tabpanel" aria-labelledby="a-venir-tab">
                <?php if (!empty($devoirs)): ?>
                    <?php foreach ($devoirs as $devoir): 
                        $is_late = strtotime($devoir['date_limite']) < time() && !$devoir['rendu'];
                        $is_done = $devoir['rendu'];
                    ?>
                        <div class="dashboard-card animate-fade-in delay-1 devoir-card <?= $is_late ? 'en-retard' : '' ?> <?= $is_done ? 'rendu' : '' ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="devoir-matiere">
                                            <?= htmlspecialchars($devoir['matiere']) ?>
                                        </div>
                                        <div class="devoir-date <?= $is_late ? 'en-retard' : '' ?>">
                                            <i class="far fa-calendar-alt me-1"></i>
                                            Pour le <?= date('d/m/Y', strtotime($devoir['date_limite'])) ?>
                                            <?php if ($is_late): ?>
                                                <span class="badge badge-devoir bg-danger ms-2">En retard</span>
                                            <?php elseif ($is_done): ?>
                                                <span class="badge badge-devoir bg-success ms-2">Rendu</span>
                                            <?php else: ?>
                                                <span class="badge badge-devoir bg-primary ms-2">À faire</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="devoir-enseignant">
                                            Par <?= htmlspecialchars($devoir['enseignant_prenom'] . ' ' . $devoir['enseignant_nom']) ?>
                                        </div>
                                    </div>
                                    <?php if (!$is_done): ?>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#rendreDevoirModal" data-devoir-id="<?= $devoir['id'] ?>">
                                            <i class="fas fa-paper-plane me-1"></i> Rendre
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($devoir['titre'])): ?>
                                    <h5 class="mt-3"><?= htmlspecialchars($devoir['titre']) ?></h5>
                                <?php endif; ?>
                                
                                <?php if (!empty($devoir['description'])): ?>
                                    <div class="devoir-description">
                                        <?= nl2br(htmlspecialchars($devoir['description'])) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($devoir['fichier_path'])): ?>
                                    <div class="devoir-actions mt-3">
                                        <a href="../../../uploads/devoirs/<?= htmlspecialchars($devoir['fichier_path']) ?>" class="btn btn-sm btn-outline-secondary" download>
                                            <i class="fas fa-download me-1"></i> Télécharger le sujet
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="dashboard-card animate-fade-in delay-1">
                        <div class="card-body text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5 class="text-muted">Aucun devoir à venir</h5>
                            <p class="text-muted">Vous n'avez pas de devoir à rendre pour le moment</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Devoirs passés -->
            <div class="tab-pane fade" id="passes" role="tabpanel" aria-labelledby="passes-tab">
                <?php if (!empty($devoirs_passes)): ?>
                    <?php foreach ($devoirs_passes as $devoir): ?>
                        <div class="dashboard-card animate-fade-in delay-2 devoir-card <?= $devoir['rendu'] ? 'rendu' : 'en-retard' ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="devoir-matiere">
                                            <?= htmlspecialchars($devoir['matiere']) ?>
                                        </div>
                                        <div class="devoir-date <?= !$devoir['rendu'] ? 'en-retard' : '' ?>">
                                            <i class="far fa-calendar-alt me-1"></i>
                                            Pour le <?= date('d/m/Y', strtotime($devoir['date_limite'])) ?>
                                            <?php if (!$devoir['rendu']): ?>
                                                <span class="badge badge-devoir bg-danger ms-2">Non rendu</span>
                                            <?php else: ?>
                                                <span class="badge badge-devoir bg-success ms-2">Rendu</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="devoir-enseignant">
                                            Par <?= htmlspecialchars($devoir['enseignant_prenom'] . ' ' . $devoir['enseignant_nom']) ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($devoir['titre'])): ?>
                                    <h5 class="mt-3"><?= htmlspecialchars($devoir['titre']) ?></h5>
                                <?php endif; ?>
                                
                                <?php if (!empty($devoir['description'])): ?>
                                    <div class="devoir-description">
                                        <?= nl2br(htmlspecialchars($devoir['description'])) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($devoir['fichier_path'])): ?>
                                    <div class="devoir-actions mt-3">
                                        <a href="../../uploads/devoirs/<?= htmlspecialchars($devoir['fichier_path']) ?>" class="btn btn-sm btn-outline-secondary" download>
                                            <i class="fas fa-download me-1"></i> Télécharger le sujet
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="dashboard-card animate-fade-in delay-2">
                        <div class="card-body text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucun devoir passé récent</h5>
                            <p class="text-muted">Vos devoirs passés apparaîtront ici</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal pour rendre un devoir -->
    <div class="modal fade" id="rendreDevoirModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rendre un devoir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="rendreDevoirForm" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="devoir_id" id="modalDevoirId">
                        <div class="mb-3">
                            <label for="fichierDevoir" class="form-label">Fichier du devoir</label>
                            <input class="form-control" type="file" id="fichierDevoir" name="fichier" required>
                            <small class="text-muted">Formats acceptés : PDF, DOC, DOCX, ZIP (max 10MB)</small>
                        </div>
                        <div class="mb-3">
                            <label for="commentaire" class="form-label">Commentaire (optionnel)</label>
                            <textarea class="form-control" id="commentaire" name="commentaire" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i> Envoyer
                        </button>
                    </div>
                </form>
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
            
            // Gérer le modal pour rendre un devoir
            const rendreDevoirModal = document.getElementById('rendreDevoirModal');
            if (rendreDevoirModal) {
                rendreDevoirModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const devoirId = button.getAttribute('data-devoir-id');
                    document.getElementById('modalDevoirId').value = devoirId;
                });
            }
            
            // Gérer la soumission du formulaire
            const form = document.getElementById('rendreDevoirForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Ici, vous devriez ajouter le code pour envoyer le devoir via AJAX
                    // et gérer la réponse du serveur
                    
                    alert('Devoir envoyé avec succès !');
                    const modal = bootstrap.Modal.getInstance(rendreDevoirModal);
                    modal.hide();
                    
                    // Recharger la page pour voir les changements
                    setTimeout(() => location.reload(), 1000);
                });
            }
        });
    </script>
</body>
</html>