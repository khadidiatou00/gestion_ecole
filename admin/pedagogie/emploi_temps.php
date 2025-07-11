<?php
session_start();
require_once '../../config/db.php';

// --- SÉCURITÉ ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

$message = '';
$message_type = '';

// --- LOGIQUE CRUD ---

// AJOUTER ou MODIFIER un créneau
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $classe_id = filter_input(INPUT_POST, 'classe_id', FILTER_VALIDATE_INT);
    $matiere_id = filter_input(INPUT_POST, 'matiere_id', FILTER_VALIDATE_INT);
    $enseignant_id = filter_input(INPUT_POST, 'enseignant_id', FILTER_VALIDATE_INT);
    $salle_id = filter_input(INPUT_POST, 'salle_id', FILTER_VALIDATE_INT);
    $jour = $_POST['jour'];
    $heure_debut = $_POST['heure_debut'];
    $heure_fin = $_POST['heure_fin'];
    $annee_id = filter_input(INPUT_POST, 'annee_id_form', FILTER_VALIDATE_INT);
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    try {
        if ($_POST['action'] === 'add') {
            $sql = "INSERT INTO emploi_temps (classe_id, matiere_id, enseignant_id, salle_id, jour, heure_debut, heure_fin, annee_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$classe_id, $matiere_id, $enseignant_id, $salle_id, $jour, $heure_debut, $heure_fin, $annee_id]);
            $message = "Le créneau a été ajouté avec succès !";
            $message_type = 'success';
        } elseif ($_POST['action'] === 'edit') {
            $sql = "UPDATE emploi_temps SET classe_id=?, matiere_id=?, enseignant_id=?, salle_id=?, jour=?, heure_debut=?, heure_fin=?, annee_id=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$classe_id, $matiere_id, $enseignant_id, $salle_id, $jour, $heure_debut, $heure_fin, $annee_id, $id]);
            $message = "Le créneau a été modifié avec succès !";
            $message_type = 'success';
        }
    } catch (PDOException $e) {
        $message = "Erreur lors de l'opération : " . $e->getMessage();
        $message_type = 'danger';
    }
}

// SUPPRIMER un créneau
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete'])) {
    try {
        $id = filter_input(INPUT_POST, 'id_delete', FILTER_VALIDATE_INT);
        $sql = "DELETE FROM emploi_temps WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $message = "Le créneau a été supprimé avec succès.";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "Erreur lors de la suppression : " . $e->getMessage();
        $message_type = 'danger';
    }
}


// --- RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE ---
try {
    // Filtres
    $selected_annee_id = $_GET['annee_id'] ?? $pdo->query("SELECT id FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetchColumn();
    $selected_classe_id = $_GET['classe_id'] ?? null;

    // Listes pour les filtres et formulaires
    $annees = $pdo->query("SELECT id, annee FROM annees_scolaires ORDER BY annee DESC")->fetchAll();
    $classes = $pdo->query("SELECT id, nom FROM classes ORDER BY nom ASC")->fetchAll();
    $matieres = $pdo->query("SELECT id, nom FROM matieres ORDER BY nom ASC")->fetchAll();
    $enseignants = $pdo->query("SELECT id, CONCAT(prenom, ' ', nom) as full_name FROM utilisateurs WHERE role = 'enseignant' ORDER BY full_name")->fetchAll();
    $salles = $pdo->query("SELECT id, nom FROM salles ORDER BY nom ASC")->fetchAll();

    $emploi_temps = [];
    if ($selected_classe_id && $selected_annee_id) {
        $stmt = $pdo->prepare("
            SELECT edt.*, m.nom as matiere_nom, CONCAT(u.prenom, ' ', u.nom) as enseignant_nom, s.nom as salle_nom
            FROM emploi_temps edt
            JOIN matieres m ON edt.matiere_id = m.id
            JOIN utilisateurs u ON edt.enseignant_id = u.id
            JOIN salles s ON edt.salle_id = s.id
            WHERE edt.classe_id = ? AND edt.annee_id = ?
        ");
        $stmt->execute([$selected_classe_id, $selected_annee_id]);
        $creneaux = $stmt->fetchAll();
        // Organiser les créneaux pour la grille
        foreach ($creneaux as $creneau) {
            $emploi_temps[$creneau['jour']][substr($creneau['heure_debut'], 0, 5)] = $creneau;
        }
    }
} catch (PDOException $e) {
    $error_db = "Erreur de connexion à la base de données : " . $e->getMessage();
    $annees = $classes = $matieres = $enseignants = $salles = $emploi_temps = [];
}

$jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$heures = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00', '00:00'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de l'Emploi du Temps - GestiSchool Galaxy</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-dark-primary: #0d1117; --bg-dark-secondary: #161b22; --border-color: rgba(255, 255, 255, 0.1);
            --text-primary: #c9d1d9; --text-secondary: #8b949e; --accent-glow-1: #00f2ff;
            --accent-glow-2: #da00ff; --font-primary: 'Poppins', sans-serif; --font-display: 'Orbitron', sans-serif;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        body { font-family: var(--font-primary); background-color: var(--bg-dark-primary); color: var(--text-primary);
            background-image: radial-gradient(circle at 1px 1px, rgba(255, 255, 255, 0.05) 1px, transparent 0); background-size: 20px 20px;
        }
        .page-wrapper { display: flex; min-height: 100vh; }
        #sidebar { width: 260px; position: fixed; top: 0; left: 0; height: 100vh; z-index: 1000; background: rgba(16, 19, 26, 0.6); backdrop-filter: blur(10px); border-right: 1px solid var(--border-color); transition: all 0.3s ease; display: flex; flex-direction: column; }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid var(--border-color); }
        .sidebar-header .logo { font-family: var(--font-display); font-size: 1.5rem; color: #fff; text-shadow: 0 0 5px var(--accent-glow-1), 0 0 10px var(--accent-glow-2); }
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
        .content-card { background: var(--bg-dark-secondary); border-radius: 12px; padding: 2rem; border: 1px solid var(--border-color); animation: fadeIn 0.5s ease; }
        .btn-glow { border: 1px solid var(--accent-glow-1); color: var(--accent-glow-1); background-color: transparent; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; }
        .btn-glow:hover { background-color: var(--accent-glow-1); color: var(--bg-dark-primary); box-shadow: 0 0 15px var(--accent-glow-1); }
        .form-control, .form-select { background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: var(--text-primary); }
        .form-control:focus, .form-select:focus { background: rgba(0,0,0,0.4); border-color: var(--accent-glow-1); box-shadow: 0 0 10px var(--accent-glow-1); color: #fff; }
        .form-select option { background-color: var(--bg-dark-secondary); }
        .modal-content { background: rgba(16, 19, 26, 0.8); backdrop-filter: blur(10px); border: 1px solid var(--border-color); color: var(--text-primary); }
        .modal-header, .modal-footer { border-color: var(--border-color); }
        .edt-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .edt-table th, .edt-table td { border: 1px solid var(--border-color); padding: 0.5rem; text-align: center; height: 100px; }
        .edt-table th { background-color: rgba(255, 255, 255, 0.05); }
        .edt-creneau { background: rgba(0, 242, 255, 0.1); border-radius: 8px; padding: 8px; height: 100%; display: flex; flex-direction: column; justify-content: center; cursor: pointer; transition: all 0.2s ease; }
        .edt-creneau:hover { background: rgba(0, 242, 255, 0.2); transform: scale(1.05); }
        .edt-creneau .matiere { font-weight: 600; color: #fff; }
        .edt-creneau .enseignant, .edt-creneau .salle { font-size: 0.8em; }
        @media (max-width: 992px) { #sidebar { left: -260px; } #sidebar.active { left: 0; } #main-content { margin-left: 0; width: 100%; } #sidebar-toggle { display: block; background: transparent; color: var(--text-primary); border: none; font-size: 1.2rem; } }
        #sidebar-toggle { display: none; }
    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- ============================================================== -->
    <!-- Barre Latérale -->
    <!-- ============================================================== -->
    <aside id="sidebar">
        <div class="sidebar-header">
            <a href="../dashboard.php" class="logo"><i class="fas fa-meteor"></i> GestiSchool</a>
        </div>
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
                <li class="nav-category">Pédagogie</li>
                 <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#pedagogieCollapse" role="button" aria-expanded="true" aria-controls="pedagogieCollapse">
                        <i class="fas fa-book-reader"></i> Organisation <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="collapse show" id="pedagogieCollapse">
                        <ul class="nav flex-column ps-4">
                            <li><a class="nav-link active" href="emploi_temps.php">Emploi du Temps</a></li>
                            <li><a class="nav-link" href="affectations.php">Affectations</a></li>
                            <li><a class="nav-link" href="programmes.php">Programmes</a></li>
                        </ul>
                    </div>
                </li>
                <!-- ... autres menus ... -->
            </ul>
        </nav>
    </aside>

    <!-- ============================================================== -->
    <!-- Contenu Principal -->
    <!-- ============================================================== -->
    <main id="main-content">
        <header class="main-header">
            <div>
                <button class="btn" id="sidebar-toggle"><i class="fas fa-bars"></i></button>
                <h1>Emploi du Temps</h1>
            </div>
            <button class="btn btn-glow" data-bs-toggle="modal" data-bs-target="#creneauModal" id="add-creneau-btn">
                <i class="fas fa-plus-circle me-2"></i> Ajouter un créneau
            </button>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type === 'success' ? 'info' : 'danger' ?>" role="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (isset($error_db)): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error_db) ?></div>
        <?php endif; ?>

        <div class="content-card">
            <!-- Filtres -->
            <form method="GET" action="emploi_temps.php" class="row g-3 align-items-end mb-4">
                <div class="col-md-5">
                    <label for="annee_id" class="form-label">Année Scolaire</label>
                    <select class="form-select" name="annee_id" id="annee_id">
                        <?php foreach($annees as $annee): ?>
                            <option value="<?= $annee['id'] ?>" <?= ($annee['id'] == $selected_annee_id) ? 'selected' : '' ?>><?= htmlspecialchars($annee['annee']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="classe_id" class="form-label">Classe</label>
                    <select class="form-select" name="classe_id" id="classe_id">
                        <option value="">-- Choisir une classe --</option>
                        <?php foreach($classes as $classe): ?>
                            <option value="<?= $classe['id'] ?>" <?= ($classe['id'] == $selected_classe_id) ? 'selected' : '' ?>><?= htmlspecialchars($classe['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-glow w-100">Afficher</button>
                </div>
            </form>
            <hr style="border-color: var(--border-color);">
            
            <!-- Grille de l'emploi du temps -->
            <div class="table-responsive">
                <?php if($selected_classe_id && $selected_annee_id): ?>
                <table class="edt-table">
                    <thead>
                        <tr>
                            <th>Heure</th>
                            <?php foreach ($jours as $jour): ?>
                                <th><?= ucfirst($jour) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($heures as $heure): ?>
                            <tr>
                                <td><?= $heure ?> - <?= date('H:i', strtotime($heure . ' +1 hour')) ?></td>
                                <?php foreach ($jours as $jour): ?>
                                    <td>
                                        <?php if (isset($emploi_temps[$jour][$heure])): 
                                            $creneau = $emploi_temps[$jour][$heure];
                                        ?>
                                            <div class="edt-creneau" data-bs-toggle="modal" data-bs-target="#creneauModal"
                                                data-id="<?= $creneau['id'] ?>"
                                                data-classe_id="<?= $creneau['classe_id'] ?>"
                                                data-matiere_id="<?= $creneau['matiere_id'] ?>"
                                                data-enseignant_id="<?= $creneau['enseignant_id'] ?>"
                                                data-salle_id="<?= $creneau['salle_id'] ?>"
                                                data-jour="<?= $creneau['jour'] ?>"
                                                data-heure_debut="<?= $creneau['heure_debut'] ?>"
                                                data-heure_fin="<?= $creneau['heure_fin'] ?>"
                                                data-annee_id="<?= $creneau['annee_id'] ?>">
                                                <div class="matiere"><?= htmlspecialchars($creneau['matiere_nom']) ?></div>
                                                <div class="enseignant"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($creneau['enseignant_nom']) ?></div>
                                                <div class="salle"><i class="fas fa-door-open"></i> <?= htmlspecialchars($creneau['salle_nom']) ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="text-center text-secondary">Veuillez sélectionner une année scolaire et une classe pour afficher l'emploi du temps.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Modale pour Ajouter/Modifier un créneau -->
<div class="modal fade" id="creneauModal" tabindex="-1" aria-labelledby="creneauModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="emploi_temps.php?annee_id=<?= $selected_annee_id ?>&classe_id=<?= $selected_classe_id ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="creneauModalLabel">Ajouter un créneau</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background-color: #fff;"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="id" id="form-id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Année Scolaire</label><select class="form-select" name="annee_id_form" id="form-annee_id" required><option value="">Choisir...</option><?php foreach ($annees as $annee): ?><option value="<?= $annee['id'] ?>"><?= htmlspecialchars($annee['annee']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Classe</label><select class="form-select" name="classe_id" id="form-classe_id" required><option value="">Choisir...</option><?php foreach ($classes as $classe): ?><option value="<?= $classe['id'] ?>"><?= htmlspecialchars($classe['nom']) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Matière</label><select class="form-select" name="matiere_id" id="form-matiere_id" required><option value="">Choisir...</option><?php foreach ($matieres as $matiere): ?><option value="<?= $matiere['id'] ?>"><?= htmlspecialchars($matiere['nom']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Enseignant</label><select class="form-select" name="enseignant_id" id="form-enseignant_id" required><option value="">Choisir...</option><?php foreach ($enseignants as $enseignant): ?><option value="<?= $enseignant['id'] ?>"><?= htmlspecialchars($enseignant['full_name']) ?></option><?php endforeach; ?></select></div>
                    </div>
                     <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Salle</label><select class="form-select" name="salle_id" id="form-salle_id" required><option value="">Choisir...</option><?php foreach ($salles as $salle): ?><option value="<?= $salle['id'] ?>"><?= htmlspecialchars($salle['nom']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Jour</label><select class="form-select" name="jour" id="form-jour" required><option value="">Choisir...</option><?php foreach ($jours as $j): ?><option value="<?= $j ?>"><?= ucfirst($j) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Heure de début</label><input type="time" class="form-control" name="heure_debut" id="form-heure_debut" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Heure de fin</label><input type="time" class="form-control" name="heure_fin" id="form-heure_fin" required></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger me-auto" id="delete-btn-modal" style="display:none;">Supprimer</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-glow" id="form-submit-btn">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Formulaire caché pour la suppression -->
<form method="POST" action="emploi_temps.php?annee_id=<?= $selected_annee_id ?>&classe_id=<?= $selected_classe_id ?>" id="delete-form" class="d-none">
    <input type="hidden" name="action_delete" value="1">
    <input type="hidden" name="id_delete" id="id-to-delete">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('active'));
    }

    const modal = document.getElementById('creneauModal');
    const modalTitle = document.getElementById('creneauModalLabel');
    const form = modal.querySelector('form');
    const formAction = document.getElementById('form-action');
    const formId = document.getElementById('form-id');
    const formSubmitBtn = document.getElementById('form-submit-btn');
    const deleteBtnModal = document.getElementById('delete-btn-modal');
    
    // Mode Ajout
    document.getElementById('add-creneau-btn').addEventListener('click', function () {
        modalTitle.textContent = 'Ajouter un créneau';
        form.reset();
        formAction.value = 'add';
        formId.value = '';
        formSubmitBtn.textContent = 'Ajouter';
        deleteBtnModal.style.display = 'none';
        // Pré-remplir avec les filtres actuels
        document.getElementById('form-annee_id').value = '<?= $selected_annee_id ?>';
        document.getElementById('form-classe_id').value = '<?= $selected_classe_id ?>';
    });
    
    // Mode Modification (en cliquant sur un créneau)
    document.querySelectorAll('.edt-creneau').forEach(creneau => {
        creneau.addEventListener('click', function () {
            const data = this.dataset;
            modalTitle.textContent = 'Modifier le créneau';
            form.reset();
            formAction.value = 'edit';
            formId.value = data.id;
            
            // Remplissage des champs
            document.getElementById('form-annee_id').value = data.annee_id;
            document.getElementById('form-classe_id').value = data.classe_id;
            document.getElementById('form-matiere_id').value = data.matiere_id;
            document.getElementById('form-enseignant_id').value = data.enseignant_id;
            document.getElementById('form-salle_id').value = data.salle_id;
            document.getElementById('form-jour').value = data.jour;
            document.getElementById('form-heure_debut').value = data.heure_debut;
            document.getElementById('form-heure_fin').value = data.heure_fin;
            
            formSubmitBtn.textContent = 'Enregistrer';
            deleteBtnModal.style.display = 'block';
        });
    });

    // Logique pour le bouton de suppression dans la modale
    deleteBtnModal.addEventListener('click', function() {
        const creneauId = formId.value;
        if (confirm('Êtes-vous sûr de vouloir supprimer ce créneau ?')) {
            document.getElementById('id-to-delete').value = creneauId;
            document.getElementById('delete-form').submit();
        }
    });

});
</script>

</body>
</html>