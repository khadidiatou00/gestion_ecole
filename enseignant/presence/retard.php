<?php
session_start();
require_once '../../config/db.php';

// --- SÉCURITÉ ET INITIALISATION ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'enseignant') {
    header('Location: ../../auth/login.php');
    exit();
}

$enseignant_id = $_SESSION['user_id'];
$notification = null;
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']);
}

// --- LOGIQUE PHP (CRUD) ---
try {
    $annee_en_cours_id = $pdo->query("SELECT id FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetchColumn();
    if (!$annee_en_cours_id) { throw new Exception("Aucune année scolaire active n'a été trouvée."); }

    // --- CRÉATION D'UN RETARD (POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_retard'])) {
        $etudiant_id = $_POST['etudiant_id'];
        $classe_id = $_POST['classe_id'];
        $date_retard = $_POST['date_retard'];
        $heure_retard = $_POST['heure_retard'];
        $duree = $_POST['duree'];

        if (empty($etudiant_id) || empty($classe_id) || empty($date_retard) || empty($heure_retard) || !is_numeric($duree) || $duree <= 0) {
            throw new Exception("Tous les champs sont obligatoires et la durée doit être un nombre positif.");
        }

        $sql = "INSERT INTO retards (etudiant_id, classe_id, date_retard, heure_retard, duree, annee_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$etudiant_id, $classe_id, $date_retard, $heure_retard, $duree, $annee_en_cours_id]);
        
        $_SESSION['notification'] = ['type' => 'success', 'message' => 'Le retard a été enregistré avec succès.'];
        header('Location: retard.php');
        exit();
    }

    // --- SUPPRESSION D'UN RETARD (POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_retard'])) {
        $retard_id = $_POST['retard_id'];
        $stmt = $pdo->prepare("DELETE FROM retards WHERE id = ?");
        $stmt->execute([$retard_id]);
        
        $_SESSION['notification'] = ['type' => 'info', 'message' => 'Le retard a été supprimé.'];
        header('Location: retard.php');
        exit();
    }

    // --- LOGIQUE D'AFFICHAGE ---
    $stmt_enseignant = $pdo->prepare("SELECT nom, prenom, photo_profil FROM utilisateurs WHERE id = ?");
    $stmt_enseignant->execute([$enseignant_id]);
    $enseignant_info = $stmt_enseignant->fetch();
    
    $stmt_retards = $pdo->prepare(
        "SELECT r.id, r.date_retard, r.heure_retard, r.duree, u.nom as etudiant_nom, u.prenom as etudiant_prenom, u.photo_profil, c.nom as classe_nom
         FROM retards r
         JOIN utilisateurs u ON r.etudiant_id = u.id
         JOIN classes c ON r.classe_id = c.id
         WHERE r.annee_id = ?
         ORDER BY r.date_retard DESC, r.heure_retard DESC LIMIT 50"
    );
    $stmt_retards->execute([$annee_en_cours_id]);
    $retards_list = $stmt_retards->fetchAll();

    // Récupérer toutes les classes et étudiants de l'enseignant pour la modal
    $stmt_eleves = $pdo->prepare(
        "SELECT u.id, u.nom, u.prenom, em.classe_id, c.nom as classe_nom
         FROM utilisateurs u
         JOIN inscriptions i ON u.id = i.etudiant_id
         JOIN enseignant_matieres em ON i.classe_id = em.classe_id
         JOIN classes c ON c.id = em.classe_id
         WHERE em.enseignant_id = ? AND i.annee_id = ? AND u.role = 'etudiant' AND i.statut = 'actif'
         GROUP BY u.id, em.classe_id ORDER BY c.nom, u.nom, u.prenom"
    );
    $stmt_eleves->execute([$enseignant_id, $annee_en_cours_id]);
    $eleves_par_classe = [];
    $classes_list_modal = [];
    foreach ($stmt_eleves->fetchAll() as $eleve) {
        if (!isset($classes_list_modal[$eleve['classe_id']])) {
            $classes_list_modal[$eleve['classe_id']] = $eleve['classe_nom'];
        }
        $eleves_par_classe[$eleve['classe_id']][] = $eleve;
    }

} catch (PDOException $e) {
    $notification = ['type' => 'danger', 'message' => "Erreur de base de données : " . $e->getMessage()];
} catch (Exception $e) {
    $notification = ['type' => 'danger', 'message' => "Erreur : " . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Retards - GestiSchool</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Le CSS est identique, pas besoin de le changer -->
    <style>
        :root {
            --bg-main: #f4f7fc; --sidebar-bg: #ffffff; --card-bg: #ffffff; --primary: #4f46e5;
            --secondary: #64748b; --accent: #ec4899; --text-dark: #1e293b; --text-light: #64748b;
            --border-color: #e2e8f0; --font-body: 'Poppins', sans-serif; --font-title: 'Montserrat', sans-serif;
            --warning-light: #fef3c7; --warning-dark: #b45309;
        }
        @keyframes fadeInScale { from { opacity: 0; transform: translateY(20px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        body { font-family: var(--font-body); background-color: var(--bg-main); color: var(--text-dark); }
        .page-wrapper { display: flex; min-height: 100vh; }
        #sidebar { width: 260px; position: fixed; top: 0; left: 0; height: 100vh; z-index: 1000; background: var(--sidebar-bg); border-right: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.05); display: flex; flex-direction: column; transition: all 0.3s ease; }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid var(--border-color); }
        .sidebar-header .logo { font-family: var(--font-title); font-size: 1.6rem; color: var(--primary); font-weight: 700; text-decoration: none; }
        .sidebar-nav { padding: 1rem; flex-grow: 1; overflow-y: auto; }
        .nav-category { font-size: 0.75rem; color: var(--text-light); text-transform: uppercase; padding: 1rem; font-weight: 600; letter-spacing: 0.5px; }
        .nav-link { display: flex; align-items: center; padding: 0.8rem 1rem; color: var(--secondary); text-decoration: none; border-radius: 8px; margin-bottom: 5px; font-weight: 500; transition: all 0.3s ease; }
        .nav-link i { width: 25px; margin-right: 15px; text-align: center; font-size: 1.2rem; }
        .nav-link:hover { background-color: #eef2ff; color: var(--primary); }
        .nav-link.active { background: var(--primary); color: #fff; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
        .sidebar-footer { padding: 1rem; border-top: 1px solid var(--border-color); }
        .user-info { display: flex; align-items: center; }
        .user-info img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; margin-right: 12px; border: 2px solid var(--primary); }
        .user-info .username { font-weight: 600; color: var(--text-dark); }
        #main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2.5rem; }
        .main-header h1 { font-family: var(--font-title); font-weight: 700; }
        .dashboard-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem 2rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.07), 0 4px 6px -2px rgba(0,0,0,0.05); animation: fadeInScale 0.6s ease-out forwards; opacity: 0; }
        .card-header-custom { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
        .card-header-custom .icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: grid; place-items: center; margin-right: 1rem; background: linear-gradient(135deg, var(--primary), var(--accent)); color: #fff; font-size: 1.5rem; box-shadow: 0 4px 8px rgba(79, 70, 229, 0.3); }
        .card-header-custom h5 { font-family: var(--font-title); font-size: 1.3rem; margin: 0; font-weight: 600; }
        .btn-primary { background-color: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background-color: #4338ca; border-color: #4338ca; }
        .student-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 1rem; border: 2px solid var(--border-color); }
        .modal-content { border-radius: 16px; border: none; }
        @media (max-width: 992px) { #sidebar { left: -260px; } #sidebar.active { left: 0; } #main-content { margin-left: 0; width: 100%; } #sidebar-toggle { display: block; } }
        #sidebar-toggle { display: none; background: transparent; border: none; font-size: 1.5rem; }
    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- Barre Latérale -->
    <aside id="sidebar">
        <!-- Contenu de la sidebar inchangé -->
        <div class="sidebar-header"><a href="../dashboard.php" class="logo"><i class="fas fa-graduation-cap"></i> GestiSchool</a></div>
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
                <li class="nav-category">Évaluation</li>
                <li class="nav-item"><a class="nav-link" href="../evaluation/notes.php"><i class="fas fa-marker"></i> Saisie des Notes</a></li>
                <li class="nav-item"><a class="nav-link" href="../evaluation/competences.php"><i class="fas fa-tasks"></i> Compétences</a></li>
                <li class="nav-category">Vie de Classe</li>
                <li class="nav-item"><a class="nav-link" href="absences.php"><i class="fas fa-user-check"></i> Appel & Absences</a></li>
                <li class="nav-item"><a class="nav-link active" href="retard.php"><i class="fas fa-user-clock"></i> Retards</a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <img src="../../<?= htmlspecialchars($enseignant_info['photo_profil'] ?? 'assets/img/profiles/default.png') ?>" alt="Photo de profil">
                <div>
                    <div class="username"><?= htmlspecialchars($enseignant_info['prenom'] . ' ' . $enseignant_info['nom']) ?></div>
                    <a href="../../auth/logout.php" class="text-danger small">Déconnexion</a>
                </div>
            </div>
        </div>
    </aside>

    <!-- Contenu Principal -->
    <main id="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <button id="sidebar-toggle" class="me-3"><i class="fas fa-bars"></i></button>
                <h1 class="d-inline-block align-middle"><i class="fas fa-user-clock me-2" style="color: var(--primary);"></i>Gestion des Retards</h1>
            </div>
        </header>

        <?php if ($notification): ?>
            <div class="alert alert-<?= $notification['type'] ?> alert-dismissible fade show" role="alert" style="animation: fadeInScale 0.4s ease-out;">
                <i class="fas <?= $notification['type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i><?= htmlspecialchars($notification['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="dashboard-card" style="animation-delay: 0.1s;">
            <div class="card-header-custom">
                <div class="d-flex align-items-center">
                    <div class="icon-wrapper"><i class="fas fa-history"></i></div>
                    <h5>Derniers retards enregistrés</h5>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRetardModal"><i class="fas fa-plus me-2"></i>Signaler un retard</button>
            </div>
            
            <div class="table-responsive">
                <!-- Tableau d'affichage inchangé -->
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Étudiant</th>
                            <th>Classe</th>
                            <th class="text-center">Durée</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($retards_list)): ?>
                            <tr><td colspan="5" class="text-center text-muted p-5"><i class="fas fa-thumbs-up fa-3x mb-3"></i><br>Aucun retard enregistré récemment.</td></tr>
                        <?php else: ?>
                            <?php foreach ($retards_list as $retard): ?>
                                <tr>
                                    <td>
                                        <div><?= date('d/m/Y', strtotime($retard['date_retard'])) ?></div>
                                        <small class="text-muted"><?= date('H:i', strtotime($retard['heure_retard'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="../../<?= htmlspecialchars($retard['photo_profil'] ?: 'assets/img/profiles/default.png') ?>" alt="avatar" class="student-avatar">
                                            <span><?= htmlspecialchars($retard['etudiant_prenom'] . ' ' . $retard['etudiant_nom']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($retard['classe_nom']) ?></td>
                                    <td class="text-center"><span class="badge rounded-pill" style="background-color: var(--warning-light); color: var(--warning-dark);"><?= $retard['duree'] ?> min</span></td>
                                    <td class="text-center">
                                        <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce retard ?');">
                                            <input type="hidden" name="retard_id" value="<?= $retard['id'] ?>">
                                            <button type="submit" name="delete_retard" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal pour ajouter un retard -->
<div class="modal fade" id="addRetardModal" tabindex="-1" aria-labelledby="addRetardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRetardModalLabel">Signaler un nouveau retard</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="retard.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modalClasseId" class="form-label">Classe</label>
                        <select class="form-select" id="modalClasseId" name="classe_id" required>
                            <option value="">-- D'abord choisir la classe --</option>
                            <?php foreach ($classes_list_modal as $id => $nom): ?>
                                <option value="<?= $id ?>"><?= htmlspecialchars($nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="modalEtudiantId" class="form-label">Étudiant</label>
                        <select class="form-select" id="modalEtudiantId" name="etudiant_id" required disabled>
                            <option value="">-- Attente de la classe --</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_retard" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date_retard" name="date_retard" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="heure_retard" class="form-label">Heure</label>
                            <input type="time" class="form-control" id="heure_retard" name="heure_retard" value="<?= date('H:i') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="duree" class="form-label">Durée du retard (en minutes)</label>
                        <input type="number" class="form-control" id="duree" name="duree" min="1" placeholder="Ex: 10" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="save_retard" class="btn btn-primary"><i class="fas fa-save me-2"></i>Sauvegarder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- ========================================================= -->
<!-- NOUVEAU SCRIPT CORRIGÉ -->
<!-- ========================================================= -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));
    }

    // Préparation des données pour le JavaScript
    // On passe les données PHP à JavaScript dans un format facile à utiliser (JSON)
    const elevesData = <?= json_encode($eleves_par_classe, JSON_UNESCAPED_UNICODE); ?>;
    
    const classeSelect = document.getElementById('modalClasseId');
    const etudiantSelect = document.getElementById('modalEtudiantId');

    classeSelect.addEventListener('change', function() {
        const selectedClasseId = this.value;

        // 1. Vider la liste actuelle des étudiants
        etudiantSelect.innerHTML = ''; 

        // 2. Si aucune classe n'est sélectionnée, afficher le message par défaut et désactiver
        if (!selectedClasseId) {
            etudiantSelect.innerHTML = '<option value="">-- Attente de la classe --</option>';
            etudiantSelect.disabled = true;
            return;
        }

        // 3. Activer la liste et ajouter le placeholder
        etudiantSelect.disabled = false;
        etudiantSelect.innerHTML = '<option value="">-- Choisissez un étudiant --</option>';

        // 4. Ajouter les étudiants de la classe sélectionnée
        if (elevesData[selectedClasseId]) {
            elevesData[selectedClasseId].forEach(eleve => {
                const option = document.createElement('option');
                option.value = eleve.id;
                option.textContent = `${eleve.prenom} ${eleve.nom}`;
                etudiantSelect.appendChild(option);
            });
        }
    });
});
</script>

</body>
</html>