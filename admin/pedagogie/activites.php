<?php
session_start();

// --- SÉCURITÉ ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

// --- CONNEXION À LA BASE DE DONNÉES ---
require_once '../../config/db.php';

// --- LOGIQUE CRUD (CREATE, UPDATE, DELETE) ---
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = $_POST['id'] ?? null;
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'];
    $lieu = trim($_POST['lieu']);
    $organisateur_id = $_POST['organisateur_id'];
    $classe_id = !empty($_POST['classe_id']) ? $_POST['classe_id'] : null;
    $annee_id = $_POST['annee_id'];

    if (empty($titre) || empty($type) || empty($date_debut) || empty($date_fin) || empty($organisateur_id) || empty($annee_id)) {
        $error = "Tous les champs marqués d'un * sont obligatoires.";
    } elseif ($date_fin < $date_debut) {
        $error = "La date de fin ne peut pas être antérieure à la date de début.";
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE activites_scolaires SET titre = ?, description = ?, type = ?, date_debut = ?, date_fin = ?, lieu = ?, organisateur_id = ?, classe_id = ?, annee_id = ? WHERE id = ?");
                $stmt->execute([$titre, $description, $type, $date_debut, $date_fin, $lieu, $organisateur_id, $classe_id, $annee_id, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO activites_scolaires (titre, description, type, date_debut, date_fin, lieu, organisateur_id, classe_id, annee_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$titre, $description, $type, $date_debut, $date_fin, $lieu, $organisateur_id, $classe_id, $annee_id]);
            }
            header("Location: activites.php?status=success");
            exit();
        } catch (PDOException $e) {
            $error = "Erreur lors de l'opération : " . $e->getMessage();
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_to_delete = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM activites_scolaires WHERE id = ?");
        $stmt->execute([$id_to_delete]);
        header("Location: activites.php?status=deleted");
        exit();
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}
if(isset($_GET['status'])){
    if($_GET['status'] == 'success') $message = "Opération réussie !";
    if($_GET['status'] == 'deleted') $message = "Activité supprimée avec succès.";
}

// --- LECTURE (READ) ---
try {
    $stmt_activites = $pdo->query("
        SELECT a.*, u.nom AS organisateur_nom, u.prenom AS organisateur_prenom, c.nom AS classe_nom, an.annee AS annee_scolaire
        FROM activites_scolaires a
        LEFT JOIN utilisateurs u ON a.organisateur_id = u.id LEFT JOIN classes c ON a.classe_id = c.id
        LEFT JOIN annees_scolaires an ON a.annee_id = an.id ORDER BY a.date_debut DESC
    ");
    $activites = $stmt_activites->fetchAll();
    $organisateurs = $pdo->query("SELECT id, nom, prenom FROM utilisateurs WHERE role IN ('admin', 'enseignant') ORDER BY nom, prenom")->fetchAll();
    $classes = $pdo->query("SELECT id, nom FROM classes ORDER BY nom")->fetchAll();
    $annees_scolaires = $pdo->query("SELECT id, annee FROM annees_scolaires ORDER BY annee DESC")->fetchAll();
} catch (PDOException $e) {
    $error = "Erreur de connexion : " . $e->getMessage();
    $activites = $organisateurs = $classes = $annees_scolaires = [];
}

$type_icons = ['sport' => 'fas fa-futbol', 'culture' => 'fas fa-palette', 'scientifique' => 'fas fa-flask', 'educatif' => 'fas fa-book-open', 'sortie' => 'fas fa-bus-alt'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Activités - GestiSchool Galaxy</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        :root {
            --bg-dark-primary: #0d1117;
            --bg-dark-secondary: #161b22;
            --border-color: rgba(255, 255, 255, 0.1);
            --text-primary: #c9d1d9;
            --text-secondary: #8b949e;
            --accent-glow-1: #00f2ff;
            --accent-glow-2: #da00ff;
            --font-primary: 'Poppins', sans-serif;
            --font-display: 'Orbitron', sans-serif;
            --success-color: #28a745;
            --danger-color: #dc3545;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        body { font-family: var(--font-primary); background-color: var(--bg-dark-primary); color: var(--text-primary); background-image: radial-gradient(circle at 1px 1px, rgba(255, 255, 255, 0.05) 1px, transparent 0); background-size: 20px 20px; }
        .page-wrapper { display: flex; min-height: 100vh; }
        #sidebar { width: 260px; position: fixed; top: 0; left: 0; height: 100vh; z-index: 1000; background: rgba(16, 19, 26, 0.6); backdrop-filter: blur(10px); border-right: 1px solid var(--border-color); transition: all 0.3s ease; display: flex; flex-direction: column; }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid var(--border-color); }
        .sidebar-header .logo { font-family: var(--font-display); font-size: 1.5rem; color: #fff; text-shadow: 0 0 5px var(--accent-glow-1), 0 0 10px var(--accent-glow-2); text-decoration: none; }
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
        .user-menu .dropdown-menu { background-color: var(--bg-dark-secondary); border: 1px solid var(--border-color); }
        .user-menu .dropdown-item { color: var(--text-primary); }
        .user-menu .dropdown-item:hover { background-color: rgba(255, 255, 255, 0.05); }
        .crud-container { background: var(--bg-dark-secondary); padding: 1.5rem 2rem; border-radius: 12px; border: 1px solid var(--border-color); animation: fadeIn 0.5s ease forwards; }
        .table-responsive { max-height: 65vh; }
        .table { color: var(--text-primary); }
        .table thead { color: var(--text-secondary); border-color: var(--border-color); }
        .table tbody tr { border-color: var(--border-color); transition: background-color 0.2s; }
        .table-hover tbody tr:hover { background-color: rgba(255, 255, 255, 0.03); }
        .table th, .table td { vertical-align: middle; }
        .btn-glow { background: linear-gradient(45deg, var(--accent-glow-2), var(--accent-glow-1)); color: #fff; border: none; border-radius: 8px; padding: 0.5rem 1.2rem; font-weight: 600; text-shadow: 0 0 5px rgba(0,0,0,0.5); transition: all 0.3s ease; }
        .btn-glow:hover { box-shadow: 0 0 15px var(--accent-glow-1); transform: translateY(-2px); }
        .modal-content { background-color: var(--bg-dark-secondary); border: 1px solid var(--border-color); color: var(--text-primary); }
        .modal-header { border-bottom: 1px solid var(--border-color); }
        .modal-title { font-family: var(--font-display); color: #fff; }
        .form-control, .form-select { background-color: var(--bg-dark-primary); color: var(--text-primary); border: 1px solid var(--border-color); }
        .form-control::placeholder { color: var(--text-secondary); opacity: 0.7; }
        .form-control:focus, .form-select:focus { background-color: var(--bg-dark-primary); color: var(--text-primary); border-color: var(--accent-glow-1); box-shadow: 0 0 10px rgba(0, 242, 255, 0.3); }
        .form-label { color: var(--text-secondary); }
        .btn-action { background: transparent; border: 1px solid var(--border-color); color: var(--text-secondary); transition: all 0.2s ease; }
        .btn-action.edit:hover { color: var(--accent-glow-1); border-color: var(--accent-glow-1); }
        .btn-action.delete:hover { color: var(--danger-color); border-color: var(--danger-color); }
        .alert-custom-success { background-color: rgba(40, 167, 69, 0.2); border-color: var(--success-color); color: #c3e6cb; }
        .alert-custom-danger { background-color: rgba(220, 53, 69, 0.2); border-color: var(--danger-color); color: #f5c6cb; }
        .flatpickr-calendar { background: var(--bg-dark-secondary); border: 1px solid var(--border-color); box-shadow: 0 5px 15px rgba(0,0,0,0.3); font-family: var(--font-primary); }
        .flatpickr-months .flatpickr-month, .flatpickr-current-month, .flatpickr-time input, .flatpickr-time .flatpickr-am-pm { color: #fff; fill: #fff; }
        .flatpickr-weekdays, .flatpickr-weekday { color: var(--text-secondary); }
        .flatpickr-day { color: var(--text-primary); }
        .flatpickr-day:hover, .flatpickr-day:focus { background: rgba(255, 255, 255, 0.05); border-color: transparent; }
        .flatpickr-day.today { border-color: var(--accent-glow-2); }
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange { background: var(--accent-glow-1); color: var(--bg-dark-primary); border-color: var(--accent-glow-1); }
        .flatpickr-day.disabled, .flatpickr-day.disabled:hover { color: rgba(255, 255, 255, 0.2); }
        .numInputWrapper span:hover { background: rgba(255, 255, 255, 0.1); }
        .flatpickr-time .numInput { color: inherit; background: transparent; }
        .popover { background-color: var(--bg-dark-primary); border: 1px solid var(--accent-glow-1); max-width: 400px; }
        .popover-header { background-color: var(--bg-dark-secondary); color: #fff; border-bottom: 1px solid var(--border-color); font-family: var(--font-display); }
        .popover-body { color: var(--text-primary); }
        .bs-popover-auto[data-popper-placement^=top]>.popover-arrow::before, .bs-popover-top>.popover-arrow::before { border-top-color: var(--accent-glow-1); }
        #sidebar-toggle { display: none; }
        @media (max-width: 992px) { #sidebar { left: -260px; } #sidebar.active { left: 0; } #main-content { margin-left: 0; width: 100%; } #sidebar-toggle { display: block; background: transparent; color: var(--text-primary); border: none; font-size: 1.2rem; } }
    </style>
</head>
<body>

<div class="page-wrapper">
    <aside id="sidebar">
        <div class="sidebar-header"><a href="../dashboard.php" class="logo"><i class="fas fa-meteor"></i> GestiSchool</a></div>
        <nav class="sidebar-nav">
             <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
                <li class="nav-category">Pédagogie</li>
                 <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#pedagogieCollapse" role="button" aria-expanded="true"><i class="fas fa-book-reader"></i> Organisation <i class="fas fa-chevron-right arrow"></i></a>
                    <div class="collapse show" id="pedagogieCollapse"><ul class="nav flex-column ps-4">
                        <li><a class="nav-link" href="emploi_temps.php">Emploi du Temps</a></li>
                        <li><a class="nav-link" href="affectations.php">Affectations</a></li>
                        <li><a class="nav-link" href="programmes.php">Programmes</a></li>
                        <li><a class="nav-link active" href="activites.php">Activités</a></li>
                    </ul></div>
                </li>
             </ul>
        </nav>
    </aside>

    <main id="main-content">
        <header class="main-header">
            <div>
                <button class="btn" id="sidebar-toggle"><i class="fas fa-bars"></i></button>
                <h1>Gestion des Activités Scolaires</h1>
            </div>
            <div class="header-actions d-flex align-items-center">
                 <button class="btn btn-glow" data-bs-toggle="modal" data-bs-target="#activityModal"><i class="fas fa-plus me-2"></i> Ajouter une Activité</button>
                <div class="dropdown user-menu ms-3">
                    <a class="btn dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="fas fa-user-astronaut fs-4"></i></a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#">Mon Profil</a></li>
                        <li><hr class="dropdown-divider" style="border-color: var(--border-color);"></li>
                        <li><a class="dropdown-item" href="../../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <div class="crud-container">
            <?php if ($message): ?> <div class="alert alert-custom-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php endif; ?>
            <?php if ($error): ?> <div class="alert alert-custom-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Période</th>
                            <th>Classe</th>
                            <th>Organisateur</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activites)): ?>
                            <tr><td colspan="7" class="text-center text-secondary py-4"><i class="fas fa-ghost fa-2x mb-2 d-block"></i>Aucune activité planifiée.</td></tr>
                        <?php else: foreach ($activites as $activite): ?>
                            <tr class="align-middle">
                                <td class="fw-bold"><?= htmlspecialchars($activite['titre']) ?></td>
                                <td>
                                    <?php
                                    $description = $activite['description'] ?? '';
                                    if (!empty($description)) {
                                        $short_desc = strlen($description) > 50 ? substr($description, 0, 50) . '...' : $description;
                                        echo htmlspecialchars($short_desc);
                                        if (strlen($description) > 50) {
                                            echo ' <i class="fas fa-info-circle text-info" style="cursor: pointer;"
                                                      data-bs-toggle="popover" data-bs-trigger="hover"
                                                      data-bs-title="Description complète"
                                                      data-bs-content="' . htmlspecialchars($description, ENT_QUOTES) . '"></i>';
                                        }
                                    } else {
                                        echo '<span class="text-secondary">N/A</span>';
                                    }
                                    ?>
                                </td>
                                <td><span class="badge rounded-pill" style="background-color: var(--bg-dark-primary); border: 1px solid var(--border-color);"><i class="<?= $type_icons[$activite['type']] ?? 'fas fa-star' ?> me-1"></i><?= htmlspecialchars(ucfirst($activite['type'])) ?></span></td>
                                <td><?= (new DateTime($activite['date_debut']))->format('d/m/Y H:i') ?><br><span class="text-secondary">à</span> <?= (new DateTime($activite['date_fin']))->format('d/m/Y H:i') ?></td>
                                <td><?= htmlspecialchars($activite['classe_nom'] ?? 'Toutes') ?></td>
                                <td><?= htmlspecialchars($activite['organisateur_prenom'] . ' ' . $activite['organisateur_nom']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-action edit" data-bs-toggle="modal" data-bs-target="#activityModal" data-id="<?= $activite['id'] ?>" data-titre="<?= htmlspecialchars($activite['titre'], ENT_QUOTES) ?>" data-description="<?= htmlspecialchars($activite['description'], ENT_QUOTES) ?>" data-type="<?= $activite['type'] ?>" data-date_debut="<?= (new DateTime($activite['date_debut']))->format('Y-m-d H:i') ?>" data-date_fin="<?= (new DateTime($activite['date_fin']))->format('Y-m-d H:i') ?>" data-lieu="<?= htmlspecialchars($activite['lieu'], ENT_QUOTES) ?>" data-organisateur_id="<?= $activite['organisateur_id'] ?>" data-classe_id="<?= $activite['classe_id'] ?>" data-annee_id="<?= $activite['annee_id'] ?>"><i class="fas fa-pencil-alt"></i></button>
                                    <a href="activites.php?action=delete&id=<?= $activite['id'] ?>" class="btn btn-sm btn-action delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette activité ?');"><i class="fas fa-trash-alt"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal -->
<div class="modal fade" id="activityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="activityForm" method="POST" action="activites.php">
                <input type="hidden" name="action" value="save"><input type="hidden" name="id" id="activity_id">
                <div class="modal-header"><h5 class="modal-title" id="activityModalLabel">Ajouter une activité</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8"><label for="titre" class="form-label">Titre *</label><input type="text" class="form-control" id="titre" name="titre" required></div>
                        <div class="col-md-4"><label for="type" class="form-label">Type *</label><select class="form-select" id="type" name="type" required><option value="sport">Sport</option><option value="culture">Culture</option><option value="scientifique">Scientifique</option><option value="educatif">Éducatif</option><option value="sortie">Sortie</option></select></div>
                        <div class="col-12"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="3"></textarea></div>
                        <div class="col-md-6"><label for="date_debut" class="form-label">Date et Heure de Début *</label><input type="text" class="form-control" id="date_debut" name="date_debut" required placeholder="Cliquez pour choisir..."></div>
                        <div class="col-md-6"><label for="date_fin" class="form-label">Date et Heure de Fin *</label><input type="text" class="form-control" id="date_fin" name="date_fin" required placeholder="Cliquez pour choisir..."></div>
                        <div class="col-md-12"><label for="lieu" class="form-label">Lieu</label><input type="text" class="form-control" id="lieu" name="lieu"></div>
                        <div class="col-md-4"><label for="organisateur_id" class="form-label">Organisateur *</label><select class="form-select" id="organisateur_id" name="organisateur_id" required><option value="">Choisir...</option><?php foreach ($organisateurs as $org): ?><option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['prenom'] . ' ' . $org['nom']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label for="classe_id" class="form-label">Classe</label><select class="form-select" id="classe_id" name="classe_id"><option value="">Toutes</option><?php foreach ($classes as $classe): ?><option value="<?= $classe['id'] ?>"><?= htmlspecialchars($classe['nom']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label for="annee_id" class="form-label">Année Scolaire *</label><select class="form-select" id="annee_id" name="annee_id" required><option value="">Choisir...</option><?php foreach ($annees_scolaires as $annee): ?><option value="<?= $annee['id'] ?>"><?= htmlspecialchars($annee['annee']) ?></option><?php endforeach; ?></select></div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-glow" id="modalSubmitButton">Ajouter</button></div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Initialisation des popovers pour les descriptions longues
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl, {
        html: true,
        sanitize: false // Important pour que les sauts de ligne soient interprétés
    }));

    // Configuration et initialisation des calendriers Flatpickr
    const flatpickrConfig = {
        enableTime: true, dateFormat: "Y-m-d H:i", locale: "fr", time_24hr: true
    };
    const dateDebutPicker = flatpickr("#date_debut", flatpickrConfig);
    const dateFinPicker = flatpickr("#date_fin", flatpickrConfig);
    
    // Gestion de la modale pour l'ajout et la modification
    const activityModal = document.getElementById('activityModal');
    const form = document.getElementById('activityForm');
    
    activityModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const modalTitle = activityModal.querySelector('.modal-title');
        const modalSubmitButton = activityModal.querySelector('#modalSubmitButton');
        const id = button.getAttribute('data-id');

        if (id) {
            modalTitle.textContent = 'Modifier l\'activité';
            modalSubmitButton.textContent = 'Mettre à jour';
            form.querySelector('#activity_id').value = id;
            form.querySelector('#titre').value = button.getAttribute('data-titre');
            form.querySelector('#description').value = button.getAttribute('data-description');
            form.querySelector('#type').value = button.getAttribute('data-type');
            dateDebutPicker.setDate(button.getAttribute('data-date_debut'), true);
            dateFinPicker.setDate(button.getAttribute('data-date_fin'), true);
            form.querySelector('#lieu').value = button.getAttribute('data-lieu');
            form.querySelector('#organisateur_id').value = button.getAttribute('data-organisateur_id');
            form.querySelector('#classe_id').value = button.getAttribute('data-classe_id');
            form.querySelector('#annee_id').value = button.getAttribute('data-annee_id');
        } else {
            form.reset();
            modalTitle.textContent = 'Ajouter une nouvelle activité';
            modalSubmitButton.textContent = 'Ajouter l\'activité';
            form.querySelector('#activity_id').value = '';
            dateDebutPicker.clear();
            dateFinPicker.clear();
        }
    });
});
</script>
</body>
</html>