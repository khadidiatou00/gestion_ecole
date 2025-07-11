<?php
session_start();
require_once '../../config/db.php';

// Vérifier que l'utilisateur est un étudiant
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'etudiant') {
    header('Location: ../../auth/login.php');
    exit();
}

$etudiant_id = $_SESSION['user_id'];
$emploi_du_temps = [];
$cours_aujourdhui = [];
$toutes_les_classes = [];
$classe_selectionnee_info = null;

try {
    // Récupérer les infos de l'étudiant
    $stmt_etudiant = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt_etudiant->execute([$etudiant_id]);
    $etudiant = $stmt_etudiant->fetch();

    // Récupérer l'année scolaire en cours
    $annee_en_cours = $pdo->query("SELECT * FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetch();

    if ($annee_en_cours) {
        // Récupérer TOUTES les classes pour le filtre
        $stmt_toutes_classes = $pdo->prepare("SELECT id, nom, niveau FROM classes WHERE annee_id = ? ORDER BY nom");
        $stmt_toutes_classes->execute([$annee_en_cours['id']]);
        $toutes_les_classes = $stmt_toutes_classes->fetchAll();
        
        // Déterminer la classe à afficher
        $classe_a_afficher_id = $_GET['classe_id'] ?? null;
        
        // Si aucune classe n'est sélectionnée dans le filtre, on essaie de prendre celle de l'étudiant
        if (!$classe_a_afficher_id) {
            $stmt_classe_etudiant = $pdo->prepare("SELECT c.id FROM classes c JOIN inscriptions i ON c.id = i.classe_id WHERE i.etudiant_id = ? AND i.annee_id = ? AND i.statut = 'actif'");
            $stmt_classe_etudiant->execute([$etudiant_id, $annee_en_cours['id']]);
            $classe_a_afficher_id = $stmt_classe_etudiant->fetchColumn();
        }

        if ($classe_a_afficher_id) {
            // Récupérer les infos de la classe sélectionnée
            $stmt_classe_selectionnee = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
            $stmt_classe_selectionnee->execute([$classe_a_afficher_id]);
            $classe_selectionnee_info = $stmt_classe_selectionnee->fetch();

            // Récupérer l'emploi du temps complet
            $stmt_edt = $pdo->prepare("
                SELECT e.*, m.nom AS matiere, u.nom AS enseignant_nom, u.prenom AS enseignant_prenom, 
                       s.nom AS salle, m.code AS matiere_code
                FROM emploi_temps e
                JOIN matieres m ON e.matiere_id = m.id
                JOIN utilisateurs u ON e.enseignant_id = u.id
                JOIN salles s ON e.salle_id = s.id
                WHERE e.classe_id = ? AND e.annee_id = ?
                ORDER BY e.heure_debut
            ");
            $stmt_edt->execute([$classe_a_afficher_id, $annee_en_cours['id']]);
            $all_cours = $stmt_edt->fetchAll();

            // Organiser les données par jour
            foreach ($all_cours as $cours) {
                $emploi_du_temps[$cours['jour']][] = $cours;
            }
        }
    }

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

// Définition des jours et du jour actuel
$jours_semaine = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$jours_fr_map = [
    'monday' => 'lundi', 'tuesday' => 'mardi', 'wednesday' => 'mercredi',
    'thursday' => 'jeudi', 'friday' => 'vendredi', 'saturday' => 'samedi', 'sunday' => 'dimanche'
];
$jour_actuel_en = strtolower(date('l'));
$jour_actuel_fr = $jours_fr_map[$jour_actuel_en] ?? null;

// Récupérer les cours du jour actuel à partir des données déjà chargées
$cours_aujourdhui = $jour_actuel_fr && isset($emploi_du_temps[$jour_actuel_fr])
    ? $emploi_du_temps[$jour_actuel_fr]
    : [];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emploi du Temps | Gestion École</title>
    
    <!-- CSS (identique à votre code) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #6c5ce7; --primary-light: #a29bfe; --secondary: #00cec9; --accent: #fd79a8;
            --dark: #2d3436; --light: #f5f6fa; --success: #00b894; --warning: #fdcb6e; --danger: #d63031; --info: #0984e3;
        }
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; color: var(--dark); overflow-x: hidden; }
        #sidebar { width: 280px; height: 100vh; position: fixed; left: 0; top: 0; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; transition: all 0.3s; z-index: 1000; box-shadow: 5px 0 15px rgba(0,0,0,0.1); }
        .sidebar-header { padding: 20px; background: rgba(0,0,0,0.1); text-align: center; }
        .sidebar-header .logo { font-size: 1.8rem; font-weight: 700; color: white; text-decoration: none; }
        .sidebar-nav { padding: 20px 0; }
        .nav-item { position: relative; margin-bottom: 5px; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; display: flex; align-items: center; transition: all 0.3s; border-left: 3px solid transparent; }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(255,255,255,0.1); border-left: 3px solid var(--accent); text-decoration: none; }
        .nav-link i { margin-right: 15px; font-size: 1.1rem; width: 20px; text-align: center; }
        .nav-link.active i { color: var(--accent); }
        .nav-category { padding: 10px 20px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.5); margin-top: 20px; }
        #main-content { margin-left: 280px; padding: 30px; transition: all 0.3s; min-height: 100vh; }
        .dashboard-card { background: white; border-radius: 15px; box-shadow: 0 10px 20px rgba(0,0,0,0.05); margin-bottom: 30px; transition: all 0.3s ease; border: none; overflow: hidden; position: relative; }
        .card-header { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; padding: 20px; border-bottom: none; position: relative; overflow: hidden; }
        .card-header::after { content: ''; position: absolute; top: -50%; right: -50%; width: 100%; height: 200%; background: rgba(255,255,255,0.1); transform: rotate(30deg); pointer-events: none; }
        .card-header h5 { font-weight: 600; margin: 0; position: relative; z-index: 1; }
        .card-header i { font-size: 1.8rem; opacity: 0.3; position: absolute; right: 20px; top: 50%; transform: translateY(-50%); z-index: 0; }
        .card-body { padding: 25px; }
        .edt-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .edt-table th { background: var(--light); padding: 12px 15px; text-align: center; font-weight: 500; }
        .edt-table td { padding: 15px; background: white; border-bottom: 2px solid var(--light); vertical-align: middle; text-align: center; }
        .edt-table tr:hover td { background: #f8f9fa; }
        .cours-cell { background: rgba(108,92,231,0.1); border-radius: 8px; padding: 10px; margin: 2px 0; transition: all 0.3s; }
        .cours-cell:hover { background: rgba(108,92,231,0.2); transform: scale(1.02); }
        .cours-cell .matiere { font-weight: 600; color: var(--primary); }
        .user-profile { display: flex; align-items: center; padding: 20px; background: rgba(0,0,0,0.1); margin-top: auto; }
        .user-profile img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255,255,255,0.2); margin-right: 15px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.6s ease-out forwards; }
        .stat-card { text-align: center; padding: 20px; border-radius: 10px; color: white; margin-bottom: 20px; position: relative; overflow: hidden; transition: all 0.3s; }
        .stat-card:hover { transform: scale(1.03); }
        .stat-card i { font-size: 2.5rem; margin-bottom: 15px; opacity: 0.8; }
        .stat-card .stat-value { font-size: 2rem; font-weight: 700; margin-bottom: 5px; }
        .stat-card .stat-label { font-size: 0.9rem; opacity: 0.9; }
        .stat-card-primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
        .nav-tabs .nav-link { color: var(--dark); border: none; padding: 12px 20px; font-weight: 500; }
        .nav-tabs .nav-link.active { color: var(--primary); border-bottom: 3px solid var(--primary); background: transparent; }
        .heure-cell { font-weight: 500; color: var(--dark); white-space: nowrap; }
        .aujourdhui-card { border-left: 4px solid var(--accent); }
        .jour-actuel { background: rgba(253,121,168,0.1) !important; font-weight: 600; }
    </style>
</head>
<body>
    <nav id="sidebar">
        <div class="sidebar-header">
            <a href="../dashboard.php" class="logo"><i class="fas fa-graduation-cap"></i> GestionÉcole</a>
        </div>
        <ul class="list-unstyled sidebar-nav">
            <li class="nav-category">Navigation</li>
            <li class="nav-item"><a href="../dashboard.php" class="nav-link"><i class="fas fa-home"></i> Tableau de Bord</a></li>
            <li class="nav-category">Scolarité</li>
            <li class="nav-item"><a href="bulletin.php" class="nav-link"><i class="fas fa-file-alt"></i> Mes Notes</a></li>
            <li class="nav-item"><a href="emploi.php" class="nav-link active"><i class="fas fa-calendar-alt"></i> Emploi du Temps</a></li>
            <li class="nav-item"><a href="cahier_texte.php" class="nav-link"><i class="fas fa-book"></i> Cahier de Texte</a></li>
        </ul>
        <div class="user-profile">
            <img src="../../assets/img/profiles/<?= htmlspecialchars($etudiant['photo_profil'] ?? 'default.png') ?>" alt="Photo de profil">
            <div class="user-info"><h6><?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?></h6><small>Étudiant</small></div>
        </div>
    </nav>
    
    <div id="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="fas fa-calendar-alt text-primary me-2"></i> Emploi du Temps</h2>
            <!-- Filtre de sélection de classe -->
            <form method="GET" class="d-flex align-items-center">
                <label for="classe_id" class="form-label me-2 mb-0 fw-bold">Classe :</label>
                <select name="classe_id" id="classe_id" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($toutes_les_classes as $classe): ?>
                        <option value="<?= $classe['id'] ?>" <?= ($classe_a_afficher_id == $classe['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($classe['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        
        <?php if ($classe_a_afficher_id): ?>
        <!-- Bloc d'information sur la classe sélectionnée -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-3">Emploi du temps de la classe</h4>
                        <p class="mb-1"><strong>Classe :</strong> <?= htmlspecialchars($classe_selectionnee_info['niveau'] . ' - ' . $classe_selectionnee_info['nom']) ?></p>
                        <p class="mb-0"><strong>Année scolaire :</strong> <?= $annee_en_cours ? htmlspecialchars($annee_en_cours['annee']) : 'Non définie' ?></p>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="stat-card stat-card-primary">
                            <i class="fas fa-calendar-day"></i>
                            <div class="stat-value"><?= date('d/m/Y') ?></div>
                            <div class="stat-label">Aujourd'hui</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($cours_aujourdhui)): ?>
        <div class="dashboard-card animate-fade-in aujourdhui-card">
            <div class="card-header">
                <h5><i class="fas fa-calendar-day me-2"></i> Cours aujourd'hui - <?= ucfirst($jour_actuel_fr) ?> <?= date('d/m/Y') ?></h5>
                <i class="fas fa-clock"></i>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="edt-table">
                        <thead><tr><th>Heure</th><th>Matière</th><th>Enseignant</th><th>Salle</th></tr></thead>
                        <tbody>
                            <?php foreach ($cours_aujourdhui as $cours): ?>
                                <tr>
                                    <td class="heure-cell"><?= date('H:i', strtotime($cours['heure_debut'])) ?> - <?= date('H:i', strtotime($cours['heure_fin'])) ?></td>
                                    <td><div class="matiere"><?= htmlspecialchars($cours['matiere']) ?></div><small class="text-muted"><?= htmlspecialchars($cours['matiere_code']) ?></small></td>
                                    <td><?= htmlspecialchars($cours['enseignant_prenom'] . ' ' . $cours['enseignant_nom']) ?></td>
                                    <td><span class="badge bg-light text-dark"><?= htmlspecialchars($cours['salle']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="dashboard-card animate-fade-in">
            <div class="card-header"><h5><i class="fas fa-calendar-week me-2"></i> Emploi du Temps Complet</h5><i class="fas fa-table"></i></div>
            <div class="card-body">
                <ul class="nav nav-tabs mb-4" id="edtTabs" role="tablist">
                    <?php foreach ($jours_semaine as $jour): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $jour === $jour_actuel_fr ? 'active' : '' ?>" id="<?= $jour ?>-tab" data-bs-toggle="tab" data-bs-target="#<?= $jour ?>-pane" type="button"><?= ucfirst($jour) ?></button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="tab-content" id="edtTabsContent">
                    <?php foreach ($jours_semaine as $jour): ?>
                        <div class="tab-pane fade <?= $jour === $jour_actuel_fr ? 'show active' : '' ?>" id="<?= $jour ?>-pane" role="tabpanel">
                            <?php if (!empty($emploi_du_temps[$jour])): ?>
                                <div class="table-responsive">
                                    <table class="edt-table">
                                        <thead><tr><th>Heure</th><th>Matière</th><th>Enseignant</th><th>Salle</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($emploi_du_temps[$jour] as $c): ?>
                                                <tr class="<?= $jour === $jour_actuel_fr ? 'jour-actuel' : '' ?>">
                                                    <td class="heure-cell"><?= date('H:i', strtotime($c['heure_debut'])) ?> - <?= date('H:i', strtotime($c['heure_fin'])) ?></td>
                                                    <td><div class="cours-cell"><div class="matiere"><?= htmlspecialchars($c['matiere']) ?></div><small class="text-muted"><?= htmlspecialchars($c['matiere_code']) ?></small></div></td>
                                                    <td><?= htmlspecialchars($c['enseignant_prenom'] . ' ' . $c['enseignant_nom']) ?></td>
                                                    <td><span class="badge bg-light text-dark"><?= htmlspecialchars($c['salle']) ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4"><i class="fas fa-calendar-times fa-3x text-muted mb-3"></i><h5 class="text-muted">Aucun cours ce jour</h5></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
            <div class="alert alert-info">Veuillez sélectionner une classe dans le menu déroulant ci-dessus pour afficher son emploi du temps.</div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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