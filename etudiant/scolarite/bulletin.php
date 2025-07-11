<?php
session_start();
require_once '../../config/db.php';

// Vérifier que l'utilisateur est un étudiant
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
            // Récupérer toutes les matières de la classe
            $stmt_matieres = $pdo->prepare("
                SELECT m.* FROM matieres m
                JOIN enseignant_matieres em ON m.id = em.matiere_id
                WHERE em.classe_id = ? AND em.annee_id = ?
                GROUP BY m.id
            ");
            $stmt_matieres->execute([$classe['id'], $annee_en_cours['id']]);
            $matieres = $stmt_matieres->fetchAll();

            // Récupérer toutes les notes de l'étudiant
            $stmt_notes = $pdo->prepare("
                SELECT n.*, m.nom as matiere, m.coefficient 
                FROM notes n
                JOIN matieres m ON n.matiere_id = m.id
                WHERE n.etudiant_id = ? AND n.annee_id = ?
                ORDER BY m.nom, n.date_note
            ");
            $stmt_notes->execute([$etudiant_id, $annee_en_cours['id']]);
            $notes = $stmt_notes->fetchAll();

            // Calculer les moyennes par matière
            $moyennes = [];
            foreach ($matieres as $matiere) {
                $notes_matiere = array_filter($notes, function($note) use ($matiere) {
                    return $note['matiere_id'] == $matiere['id'];
                });

                if (!empty($notes_matiere)) {
                    $total = 0;
                    $count = 0;
                    foreach ($notes_matiere as $note) {
                        $total += $note['note'];
                        $count++;
                    }
                    $moyennes[$matiere['id']] = [
                        'moyenne' => $total / $count,
                        'coefficient' => $matiere['coefficient']
                    ];
                }
            }

            // Calculer la moyenne générale
            $total_general = 0;
            $total_coefficients = 0;
            foreach ($moyennes as $data) {
                $total_general += $data['moyenne'] * $data['coefficient'];
                $total_coefficients += $data['coefficient'];
            }
            $moyenne_generale = $total_coefficients > 0 ? $total_general / $total_coefficients : 0;
        } else {
            $matieres = $notes = $moyennes = [];
            $moyenne_generale = 0;
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
    <title>Bulletin de Notes | Gestion École</title>
    
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
        
        /* Table */
        .notes-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .notes-table th {
            background: var(--light);
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
        }
        
        .notes-table td {
            padding: 15px;
            background: white;
            border-bottom: 2px solid var(--light);
            vertical-align: middle;
        }
        
        .notes-table tr:first-child th {
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        
        .notes-table tr:last-child td {
            border-bottom-left-radius: 10px;
            border-bottom-right-radius: 10px;
        }
        
        .notes-table tr:hover td {
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
        
        /* Stats Card */
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
                <a href="bulletin.php" class="nav-link active">
                    <i class="fas fa-file-alt"></i> Mes Notes
                </a>
            </li>
            <li class="nav-item">
                <a href="emploi.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i> Emploi du Temps
                </a>
            </li>
            <li class="nav-item">
                <a href="cahier_texte.php" class="nav-link">
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
                <i class="fas fa-file-alt text-primary me-2"></i> Bulletin de Notes
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
                            <div class="col-6">
                                <div class="stat-card stat-card-primary">
                                    <i class="fas fa-star"></i>
                                    <div class="stat-value"><?= count($notes) ?></div>
                                    <div class="stat-label">Notes</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-card stat-card-primary">
                                    <i class="fas fa-chart-line"></i>
                                    <div class="stat-value"><?= number_format($moyenne_generale, 2) ?></div>
                                    <div class="stat-label">Moyenne</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notes par matière -->
        <div class="dashboard-card animate-fade-in delay-1">
            <div class="card-header">
                <h5><i class="fas fa-list-ol me-2"></i> Détail des Notes par Matière</h5>
                <i class="fas fa-table"></i>
            </div>
            <div class="card-body">
                <?php if (!empty($matieres)): ?>
                    <div class="table-responsive">
                        <table class="notes-table">
                            <thead>
                                <tr>
                                    <th>Matière</th>
                                    <th>Coefficient</th>
                                    <th>Notes</th>
                                    <th>Moyenne</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($matieres as $matiere): 
                                    $notes_matiere = array_filter($notes, function($note) use ($matiere) {
                                        return $note['matiere_id'] == $matiere['id'];
                                    });
                                    
                                    $moyenne_matiere = isset($moyennes[$matiere['id']]) ? $moyennes[$matiere['id']]['moyenne'] : null;
                                    
                                    $note_class = '';
                                    if ($moyenne_matiere >= 16) $note_class = 'note-excellent';
                                    elseif ($moyenne_matiere >= 14) $note_class = 'note-bien';
                                    elseif ($moyenne_matiere >= 10) $note_class = 'note-moyen';
                                    elseif ($moyenne_matiere !== null) $note_class = 'note-insuffisant';
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($matiere['nom']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($matiere['coefficient']) ?></td>
                                        <td>
                                            <?php if (!empty($notes_matiere)): ?>
                                                <?php foreach ($notes_matiere as $note): 
                                                    $note_item_class = '';
                                                    if ($note['note'] >= 16) $note_item_class = 'note-excellent';
                                                    elseif ($note['note'] >= 14) $note_item_class = 'note-bien';
                                                    elseif ($note['note'] >= 10) $note_item_class = 'note-moyen';
                                                    else $note_item_class = 'note-insuffisant';
                                                ?>
                                                    <span class="note-badge <?= $note_item_class ?> me-1 mb-1">
                                                        <?= number_format($note['note'], 1) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Aucune note</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($moyenne_matiere !== null): ?>
                                                <span class="note-badge <?= $note_class ?>">
                                                    <?= number_format($moyenne_matiere, 2) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucune matière trouvée</h5>
                        <p class="text-muted">Vos matières apparaîtront ici</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Graphique d'évolution -->
        <div class="row animate-fade-in delay-2">
            <div class="col-md-6">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line me-2"></i> Évolution des Notes</h5>
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="card-body">
                        <canvas id="evolutionChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Résumé -->
            <div class="col-md-6">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i> Résumé</h5>
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6>Moyenne Générale</h6>
                            <div class="progress" style="height: 30px;">
                                <div class="progress-bar bg-primary" 
                                     role="progressbar" 
                                     style="width: <?= min(100, ($moyenne_generale / 20) * 100) ?>%" 
                                     aria-valuenow="<?= $moyenne_generale ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="20">
                                    <strong><?= number_format($moyenne_generale, 2) ?> / 20</strong>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <h6>Meilleure Note</h6>
                                    <?php if (!empty($notes)): 
                                        $max_note = max(array_column($notes, 'note'));
                                        $max_note_matiere = $notes[array_search($max_note, array_column($notes, 'note'))]['matiere'];
                                    ?>
                                        <div class="note-badge note-excellent">
                                            <?= number_format($max_note, 1) ?>
                                        </div>
                                        <small class="text-muted">en <?= htmlspecialchars($max_note_matiere) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <h6>Plus faible note</h6>
                                    <?php if (!empty($notes)): 
                                        $min_note = min(array_column($notes, 'note'));
                                        $min_note_matiere = $notes[array_search($min_note, array_column($notes, 'note'))]['matiere'];
                                    ?>
                                        <div class="note-badge note-insuffisant">
                                            <?= number_format($min_note, 1) ?>
                                        </div>
                                        <small class="text-muted">en <?= htmlspecialchars($min_note_matiere) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6>Commentaire</h6>
                            <div class="alert alert-light">
                                <?php if ($moyenne_generale >= 16): ?>
                                    <i class="fas fa-check-circle text-success me-2"></i> Excellent travail ! Continuez ainsi.
                                <?php elseif ($moyenne_generale >= 14): ?>
                                    <i class="fas fa-thumbs-up text-primary me-2"></i> Bonnes performances, vous pouvez encore progresser.
                                <?php elseif ($moyenne_generale >= 10): ?>
                                    <i class="fas fa-info-circle text-warning me-2"></i> Résultats corrects, mais des efforts sont nécessaires.
                                <?php elseif ($moyenne_generale > 0): ?>
                                    <i class="fas fa-exclamation-triangle text-danger me-2"></i> Résultats insuffisants, un travail sérieux s'impose.
                                <?php else: ?>
                                    <i class="fas fa-info-circle text-muted me-2"></i> Aucune note disponible pour le moment.
                                <?php endif; ?>
                            </div>
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
            
            // Graphique d'évolution
            const ctx = document.getElementById('evolutionChart').getContext('2d');
            
            // Données factices pour l'exemple - à remplacer par des données réelles
            const labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
            const data = [12, 11, 13, 14, 12, 13];
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Votre moyenne',
                        data: data,
                        borderColor: '#6c5ce7',
                        backgroundColor: 'rgba(108, 92, 231, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y.toFixed(2) + '/20';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: 8,
                            max: 20,
                            ticks: {
                                callback: function(value) {
                                    return value + '/20';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>