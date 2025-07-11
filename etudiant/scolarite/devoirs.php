<?php
session_start();
require_once '../../config/db.php';

// --- VÉRIFICATION DE LA SESSION ET RÉCUPÉRATION DES INFOS DE BASE ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'etudiant') {
    header('Location: ../../auth/login.php');
    exit();
}

$etudiant_id = $_SESSION['user_id'];

try {
    // --- RÉCUPÉRATION DES DONNÉES ---
    $etudiant = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $etudiant->execute([$etudiant_id]);
    $etudiant = $etudiant->fetch();

    $annee_en_cours = $pdo->query("SELECT * FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetch();

    $devoirs_a_venir = [];
    $devoirs_passes = [];
    $classe = null; // Initialisation

    if ($annee_en_cours) {
        $stmt_classe = $pdo->prepare("
            SELECT c.* FROM classes c 
            JOIN inscriptions i ON c.id = i.classe_id 
            WHERE i.etudiant_id = ? AND i.annee_id = ? AND i.statut = 'actif'
        ");
        $stmt_classe->execute([$etudiant_id, $annee_en_cours['id']]);
        $classe = $stmt_classe->fetch();

        if ($classe) {
            $stmt_devoirs = $pdo->prepare("
                SELECT d.*, m.nom AS matiere_nom, u.prenom AS enseignant_prenom, u.nom AS enseignant_nom
                FROM devoirs d
                JOIN matieres m ON d.matiere_id = m.id
                JOIN utilisateurs u ON d.enseignant_id = u.id
                WHERE d.classe_id = ? AND d.annee_id = ?
                ORDER BY d.date_limite DESC
            ");
            $stmt_devoirs->execute([$classe['id'], $annee_en_cours['id']]);
            $tous_les_devoirs = $stmt_devoirs->fetchAll();

            $now = new DateTime();
            foreach ($tous_les_devoirs as $devoir) {
                $date_limite = new DateTime($devoir['date_limite']);
                if ($date_limite >= $now) {
                    $devoirs_a_venir[] = $devoir;
                } else {
                    $devoirs_passes[] = $devoir;
                }
            }
            usort($devoirs_a_venir, function($a, $b) {
                return new DateTime($a['date_limite']) <=> new DateTime($b['date_limite']);
            });
        }
    }

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

// Fonction pour l'affichage de l'urgence
function getUrgenceInfo($date_limite_str) {
    $date_limite = new DateTime($date_limite_str);
    $now = new DateTime();
    if ($date_limite < $now) return ['class' => 'passe', 'text' => 'Date limite dépassée'];
    $interval = $now->diff($date_limite);
    $jours = $interval->days;
    if ($interval->invert) return ['class' => 'passe', 'text' => 'Date limite dépassée']; // S'assure que c'est bien dans le futur
    if ($jours == 0) return ['class' => 'urgent', 'text' => 'Pour aujourd\'hui !'];
    if ($jours == 1) return ['class' => 'urgent', 'text' => 'Pour demain'];
    if ($jours <= 3) return ['class' => 'proche', 'text' => "Il reste $jours jours"];
    return ['class' => 'normal', 'text' => "Il reste $jours jours"];
}


?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Devoirs | Gestion École</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6c5ce7; --primary-light: #a29bfe; --secondary: #00cec9;
            --accent: #fd79a8; --dark: #2d3436; --light: #f5f6fa;
            --success: #00b894; --warning: #fdcb6e; --danger: #d63031; --info: #0984e3;
        }
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; color: var(--dark); overflow-x: hidden; }
        #sidebar { width: 280px; height: 100vh; position: fixed; left: 0; top: 0; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; transition: all 0.3s; z-index: 1000; box-shadow: 5px 0 15px rgba(0,0,0,0.1); display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; background: rgba(0,0,0,0.1); text-align: center; }
        .sidebar-header .logo { font-size: 1.8rem; font-weight: 700; color: white; text-decoration: none; }
        .sidebar-nav { padding: 20px 0; flex-grow: 1; }
        .nav-item { margin-bottom: 5px; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; display: flex; align-items: center; transition: all 0.3s; border-left: 3px solid transparent; }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(255,255,255,0.1); border-left: 3px solid var(--accent); text-decoration: none; }
        .nav-link i { margin-right: 15px; font-size: 1.1rem; width: 20px; text-align: center; }
        .nav-link.active i { color: var(--accent); }
        .nav-category { padding: 10px 20px; font-size: 0.75rem; text-transform: uppercase; color: rgba(255,255,255,0.5); margin-top: 20px; }
        #main-content { margin-left: 280px; padding: 30px; transition: all 0.3s; min-height: 100vh; }
        .user-profile { display: flex; align-items: center; padding: 20px; background: rgba(0,0,0,0.1); }
        .user-profile img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255,255,255,0.2); margin-right: 15px; }
        .user-profile .user-info h6 { margin: 0; font-weight: 600; color: white; }
        .user-profile .user-info small { color: rgba(255,255,255,0.7); font-size: 0.8rem; }
        .devoir-card { background-color: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; border-left: 5px solid; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .devoir-card.urgent { border-color: var(--danger); }
        .devoir-card.proche { border-color: var(--warning); }
        .devoir-card.normal { border-color: var(--success); }
        .devoir-card.passe { border-color: #bdc3c7; background-color: #f8f9fa; }
        .devoir-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .devoir-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .devoir-titre { font-weight: 600; font-size: 1.1rem; color: var(--dark); }
        .devoir-matiere { font-size: 0.9rem; color: var(--primary); font-weight: 500; margin-bottom: 10px; }
        .devoir-info { display: flex; flex-wrap: wrap; align-items: center; gap: 15px; font-size: 0.85rem; color: #7f8c8d; }
        .devoir-info i { color: var(--primary-light); }
        .badge-urgence { padding: 6px 10px; border-radius: 20px; color: white; font-weight: 500; font-size: 0.8rem; }
        .badge-urgence.urgent { background-color: var(--danger); }
        .badge-urgence.proche { background-color: var(--warning); color: var(--dark); }
        .badge-urgence.normal { background-color: var(--success); }
        .badge-urgence.passe { background-color: #7f8c8d; }
        .empty-state { text-align: center; padding: 50px; background: #fff; border-radius: 15px; }
        .empty-state i { font-size: 4rem; color: #e0e0e0; margin-bottom: 20px; }
        .empty-state h4 { color: #9e9e9e; }
        @media (max-width: 992px) {
            #sidebar { left: -280px; }
            #sidebar.active { left: 0; }
            #main-content { margin-left: 0; }
            #sidebarCollapse { display: block !important; }
        }
    </style>
</head>
<body>
    <nav id="sidebar">
        <!-- Contenu de la sidebar (menus) -->
        <div>
            <div class="sidebar-header">
                <a href="../dashboard.php" class="logo"><i class="fas fa-graduation-cap"></i> GestionÉcole</a>
            </div>
            <ul class="list-unstyled sidebar-nav">
                <li class="nav-category">Navigation</li>
                <li class="nav-item"><a href="../dashboard.php" class="nav-link"><i class="fas fa-home"></i> Tableau de Bord</a></li>
                <li class="nav-category">Scolarité</li>
                <li class="nav-item"><a href="bulletin.php" class="nav-link"><i class="fas fa-file-alt"></i> Mes Notes</a></li>
                <li class="nav-item"><a href="emploi.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Emploi du Temps</a></li>
                <li class="nav-item"><a href="devoirs.php" class="nav-link active"><i class="fas fa-tasks"></i> Mes Devoirs</a></li>
                <li class="nav-item"><a href="cahier_texte.php" class="nav-link"><i class="fas fa-book"></i> Cahier de Texte</a></li>
            </ul>
        </div>
        <!-- Profil utilisateur en bas -->
        <div class="user-profile">
            <img src="../../<?= htmlspecialchars($etudiant['photo_profil'] ?? 'assets/img/profiles/default.png') ?>" alt="Photo de profil">
            <div class="user-info">
                <h6><?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?></h6>
                <small>Étudiant</small>
            </div>
        </div>
    </nav>

    <div id="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <button id="sidebarCollapse" class="btn btn-primary d-lg-none d-block"><i class="fas fa-bars"></i></button>
            <h2 class="mb-0"><i class="fas fa-tasks text-primary me-2"></i> Mes Devoirs</h2>
            <div></div> <!-- Pour l'espacement -->
        </div>

        <?php if (!$classe): ?>
            <div class="alert alert-warning text-center">
                <i class="fas fa-exclamation-triangle me-2"></i> Vous n'êtes affecté à aucune classe pour le moment. Les devoirs ne peuvent pas être affichés.
            </div>
        <?php else: ?>
            <div class="mb-5">
                <h4 class="mb-3"><i class="fas fa-hourglass-half text-success me-2"></i> À Rendre</h4>
                <?php if (!empty($devoirs_a_venir)): ?>
                    <div class="row">
                        <?php foreach ($devoirs_a_venir as $devoir): 
                            $urgence = getUrgenceInfo($devoir['date_limite']);
                        ?>
                            <div class="col-lg-6">
                                <div class="devoir-card <?= $urgence['class'] ?>">
                                    <div class="devoir-header mb-3">
                                        <div>
                                            <div class="devoir-matiere"><?= htmlspecialchars($devoir['matiere_nom']) ?></div>
                                            <div class="devoir-titre"><?= htmlspecialchars($devoir['titre']) ?></div>
                                        </div>
                                        <span class="badge-urgence <?= $urgence['class'] ?>"><?= $urgence['text'] ?></span>
                                    </div>
                                    <div class="devoir-info mb-3">
                                        <span><i class="fas fa-user-tie me-1"></i> <?= htmlspecialchars($devoir['enseignant_prenom'] . ' ' . $devoir['enseignant_nom']) ?></span>
                                        <span><i class="fas fa-calendar-alt me-1"></i> Le <?= (new DateTime($devoir['date_limite']))->format('d/m/Y à H:i') ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-light text-dark p-2">
                                            <i class="fas fa-tag me-1"></i> Type: <?= ucfirst(htmlspecialchars($devoir['type'])) ?>
                                        </span>
                                        <div class="d-flex">
                                            <?php if (!empty($devoir['fichier_path'])): ?>
                                                <a href="../../<?= htmlspecialchars($devoir['fichier_path']) ?>" class="btn btn-primary me-2" download>
                                                    <i class="fas fa-download me-2"></i>Sujet
                                                </a>
                                            <?php endif; ?>
                                            <a href="repondre_devoir.php?id=<?= $devoir['id'] ?>" class="btn btn-success">
                                                <i class="fas fa-pen"></i> Répondre
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-check-circle text-success"></i><h4>Super ! Vous êtes à jour.</h4><p class="text-muted">Aucun devoir à rendre pour le moment.</p></div>
                <?php endif; ?>
            </div>
            <div>
                <h4 class="mb-3"><i class="fas fa-history text-secondary me-2"></i> Devoirs Passés</h4>
                <?php if (!empty($devoirs_passes)): ?>
                    <div class="row">
                        <?php foreach (array_slice($devoirs_passes, 0, 4) as $devoir): ?>
                            <div class="col-lg-6">
                                <div class="devoir-card passe">
                                    <div class="devoir-header">
                                        <div>
                                            <div class="devoir-matiere"><?= htmlspecialchars($devoir['matiere_nom']) ?></div>
                                            <div class="devoir-titre"><?= htmlspecialchars($devoir['titre']) ?></div>
                                        </div>
                                        <span class="badge-urgence passe">Dépassé</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-folder-open"></i><h4>Aucun devoir dans l'historique.</h4></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarCollapse').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>
