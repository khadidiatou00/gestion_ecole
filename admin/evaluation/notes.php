<?php
session_start();
require_once '../../config/db.php';

// --- GESTION DES REQUETES AJAX (CREATE/UPDATE/DELETE) ---
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Action non reconnue.'];

    try {
        if ($_POST['ajax_action'] === 'save_note') {
            $note_id = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT);
            $etudiant_id = filter_input(INPUT_POST, 'etudiant_id', FILTER_VALIDATE_INT);
            $matiere_id = filter_input(INPUT_POST, 'matiere_id', FILTER_VALIDATE_INT);
            $classe_id = filter_input(INPUT_POST, 'classe_id', FILTER_VALIDATE_INT);
            $annee_id = filter_input(INPUT_POST, 'annee_id', FILTER_VALIDATE_INT);
            $type_note = $_POST['type_note'];
            $note_valeur = filter_input(INPUT_POST, 'note', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0, 'max_range' => 20]]);
            $date_note = $_POST['date_note'];
            $enseignant_id = $_SESSION['user_id']; // L'admin qui saisit

            if ($note_valeur === false) {
                 throw new Exception("La note doit être un nombre entre 0 et 20.");
            }

            if ($note_id) { // UPDATE
                $sql = "UPDATE notes SET note = ?, type_note = ?, date_note = ?, enseignant_id = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$note_valeur, $type_note, $date_note, $enseignant_id, $note_id]);
                $response = ['success' => true, 'message' => 'Note mise à jour !', 'note_id' => $note_id];
            } else { // INSERT
                $sql = "INSERT INTO notes (etudiant_id, matiere_id, enseignant_id, classe_id, note, type_note, date_note, annee_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$etudiant_id, $matiere_id, $enseignant_id, $classe_id, $note_valeur, $type_note, $date_note, $annee_id]);
                $new_note_id = $pdo->lastInsertId();
                $response = ['success' => true, 'message' => 'Note enregistrée !', 'note_id' => $new_note_id];
            }
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit(); // Termine le script après la réponse AJAX
}


// --- LOGIQUE D'AFFICHAGE DE LA PAGE ---
$selected_annee_id = $_GET['annee_id'] ?? $pdo->query("SELECT id FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetchColumn();
$selected_classe_id = $_GET['classe_id'] ?? null;
$selected_matiere_id = $_GET['matiere_id'] ?? null;

try {
    $annees = $pdo->query("SELECT id, annee FROM annees_scolaires ORDER BY annee DESC")->fetchAll();
    $classes = $pdo->query("SELECT id, nom FROM classes ORDER BY nom ASC")->fetchAll();
    
    // Charger les matières en fonction de la classe si elle est sélectionnée
    $matieres = [];
    if ($selected_classe_id) {
        $stmt_matieres = $pdo->prepare("SELECT DISTINCT m.id, m.nom FROM matieres m JOIN enseignant_matieres em ON m.id = em.matiere_id WHERE em.classe_id = ? ORDER BY m.nom");
        $stmt_matieres->execute([$selected_classe_id]);
        $matieres = $stmt_matieres->fetchAll();
    }
    
    $etudiants_notes = [];
    if ($selected_classe_id && $selected_matiere_id && $selected_annee_id) {
        // Récupérer les étudiants inscrits dans la classe pour l'année
        $stmt_etudiants = $pdo->prepare("
            SELECT u.id, u.nom, u.prenom, u.photo_profil
            FROM utilisateurs u
            JOIN inscriptions i ON u.id = i.etudiant_id
            WHERE i.classe_id = ? AND i.annee_id = ? AND i.statut = 'actif'
            ORDER BY u.nom, u.prenom
        ");
        $stmt_etudiants->execute([$selected_classe_id, $selected_annee_id]);
        $etudiants = $stmt_etudiants->fetchAll();
        
        // Récupérer les notes existantes pour ces étudiants dans cette matière
        $stmt_notes = $pdo->prepare("SELECT * FROM notes WHERE classe_id = ? AND matiere_id = ? AND annee_id = ?");
        $stmt_notes->execute([$selected_classe_id, $selected_matiere_id, $selected_annee_id]);
        $notes_existantes_raw = $stmt_notes->fetchAll();
        $notes_existantes = [];
        foreach ($notes_existantes_raw as $note) {
            $notes_existantes[$note['etudiant_id']][] = $note;
        }

        // Combiner les étudiants et leurs notes
        foreach ($etudiants as $etudiant) {
            $etudiants_notes[] = [
                'etudiant' => $etudiant,
                'notes' => $notes_existantes[$etudiant['id']] ?? []
            ];
        }
    }

} catch (PDOException $e) {
    $error_db = "Erreur de connexion : " . $e->getMessage();
    $annees = $classes = $matieres = $etudiants_notes = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Notes - GestiSchool Galaxy</title>
    
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
        .main-header h1 { font-family: var(--font-display); color: #fff; font-size: 2rem; }
        .content-card { background: var(--bg-dark-secondary); border-radius: 12px; padding: 2rem; border: 1px solid var(--border-color); animation: fadeIn 0.5s ease; }
        .form-control, .form-select { background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: var(--text-primary); }
        .form-control:focus, .form-select:focus { background: rgba(0,0,0,0.4); border-color: var(--accent-glow-1); box-shadow: 0 0 10px var(--accent-glow-1); color: #fff; }
        .form-select option { background-color: var(--bg-dark-secondary); }
        .btn-glow { border: 1px solid var(--accent-glow-1); color: var(--accent-glow-1); background-color: transparent; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; }
        .btn-glow:hover { background-color: var(--accent-glow-1); color: var(--bg-dark-primary); box-shadow: 0 0 15px var(--accent-glow-1); }
        .etudiant-row { border-bottom: 1px solid var(--border-color); padding: 1rem 0; }
        .profile-pic { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); }
        .note-input-group { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
        .note-input { width: 80px; text-align: center; }
        .toast-container { z-index: 1056; }
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
                <li class="nav-category">Évaluation</li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#evaluationCollapse" role="button" aria-expanded="true" aria-controls="evaluationCollapse">
                        <i class="fas fa-graduation-cap"></i> Évaluation <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="collapse show" id="evaluationCollapse">
                        <ul class="nav flex-column ps-4">
                            <li><a class="nav-link active" href="notes.php">Gestion des Notes</a></li>
                            <li><a class="nav-link" href="bulletins.php">Bulletins</a></li>
                            <li><a class="nav-link" href="deliberations.php">Délibérations</a></li>
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
        <header class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="font-display"><i class="fas fa-edit me-3"></i>Gestion des Notes</h1>
            <button class="btn" id="sidebar-toggle"><i class="fas fa-bars"></i></button>
        </header>

        <div class="content-card">
            <!-- Filtres -->
            <form method="GET" action="notes.php" id="filter-form" class="row g-3 align-items-end mb-4">
                <div class="col-md-4"><label class="form-label">Année Scolaire</label><select class="form-select" name="annee_id" onchange="this.form.submit()"><?php foreach($annees as $annee): ?><option value="<?= $annee['id'] ?>" <?= ($annee['id'] == $selected_annee_id) ? 'selected' : '' ?>><?= htmlspecialchars($annee['annee']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Classe</label><select class="form-select" name="classe_id" onchange="this.form.submit()"><option value="">-- Choisir --</option><?php foreach($classes as $classe): ?><option value="<?= $classe['id'] ?>" <?= ($classe['id'] == $selected_classe_id) ? 'selected' : '' ?>><?= htmlspecialchars($classe['nom']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Matière</label><select class="form-select" name="matiere_id" onchange="this.form.submit()"><option value="">-- Choisir --</option><?php foreach($matieres as $matiere): ?><option value="<?= $matiere['id'] ?>" <?= ($matiere['id'] == $selected_matiere_id) ? 'selected' : '' ?>><?= htmlspecialchars($matiere['nom']) ?></option><?php endforeach; ?></select></div>
            </form>
            <hr style="border-color: var(--border-color);">
            
            <!-- Liste des étudiants et notes -->
            <div id="notes-list" class="mt-4">
            <?php if (!empty($etudiants_notes)): ?>
                <?php foreach($etudiants_notes as $data): $etudiant = $data['etudiant']; ?>
                <div class="etudiant-row row align-items-center" id="etudiant-<?= $etudiant['id'] ?>">
                    <div class="col-md-3 d-flex align-items-center">
                        <img src="../../<?= htmlspecialchars($etudiant['photo_profil'] ?: 'assets/img/profiles/default.png') ?>" class="profile-pic me-3">
                        <strong><?= htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']) ?></strong>
                    </div>
                    <div class="col-md-9">
                        <div id="notes-container-<?= $etudiant['id'] ?>">
                        <?php foreach($data['notes'] as $note): ?>
                            <form class="note-input-group" data-note-id="<?= $note['id'] ?>">
                                <input type="number" class="form-control note-input" step="0.25" min="0" max="20" value="<?= htmlspecialchars($note['note']) ?>">
                                <select class="form-select" style="width:150px;">
                                    <option value="devoir" <?= $note['type_note'] == 'devoir' ? 'selected' : '' ?>>Devoir</option>
                                    <option value="composition" <?= $note['type_note'] == 'composition' ? 'selected' : '' ?>>Composition</option>
                                    <option value="examen" <?= $note['type_note'] == 'examen' ? 'selected' : '' ?>>Examen</option>
                                    <option value="oral" <?= $note['type_note'] == 'oral' ? 'selected' : '' ?>>Oral</option>
                                </select>
                                <input type="date" class="form-control" style="width:180px;" value="<?= htmlspecialchars($note['date_note']) ?>">
                                <button type="button" class="btn btn-sm btn-glow save-note-btn">Enregistrer</button>
                            </form>
                        <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-info add-note-field-btn" data-etudiant-id="<?= $etudiant['id'] ?>"><i class="fas fa-plus"></i> Ajouter une note</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php elseif($selected_classe_id && $selected_matiere_id): ?>
                 <div class="text-center p-5"><i class="fas fa-folder-open fa-3x text-secondary mb-3"></i><p class="text-secondary">Aucun étudiant inscrit dans cette classe ou aucune note saisie.</p></div>
            <?php else: ?>
                <div class="text-center p-5"><i class="fas fa-filter fa-3x text-secondary mb-3"></i><p class="text-secondary">Veuillez sélectionner une année, une classe et une matière pour commencer.</p></div>
            <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Toast Container pour les notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('active'));
    }

    const toastContainer = document.querySelector('.toast-container');

    function showToast(message, type = 'success') {
        const toastId = 'toast-' + Date.now();
        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>`;
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement);
        toast.show();
        toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());
    }
    
    // Ajout d'un nouveau champ de note
    document.querySelectorAll('.add-note-field-btn').forEach(button => {
        button.addEventListener('click', function() {
            const etudiantId = this.dataset.etudiantId;
            const container = document.getElementById(`notes-container-${etudiantId}`);
            const newFieldHTML = `
                <form class="note-input-group" data-note-id="">
                    <input type="number" class="form-control note-input" step="0.25" min="0" max="20" placeholder="Note" required>
                    <select class="form-select" style="width:150px;">
                        <option value="devoir">Devoir</option>
                        <option value="composition">Composition</option>
                        <option value="examen">Examen</option>
                        <option value="oral">Oral</option>
                    </select>
                    <input type="date" class="form-control" style="width:180px;" value="<?= date('Y-m-d') ?>" required>
                    <button type="button" class="btn btn-sm btn-glow save-note-btn">Enregistrer</button>
                </form>`;
            container.insertAdjacentHTML('beforeend', newFieldHTML);
        });
    });

    // Enregistrement d'une note (délégation d'événement)
    document.getElementById('notes-list').addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('save-note-btn')) {
            const form = e.target.closest('.note-input-group');
            const etudiantRow = e.target.closest('.etudiant-row');
            
            const noteId = form.dataset.noteId;
            const etudiantId = etudiantRow.id.split('-')[1];
            const noteInput = form.querySelector('input[type="number"]');
            const typeInput = form.querySelector('select');
            const dateInput = form.querySelector('input[type="date"]');

            if (!noteInput.value || !dateInput.value) {
                showToast("Veuillez remplir la note et la date.", 'danger');
                return;
            }

            const formData = new FormData();
            formData.append('ajax_action', 'save_note');
            formData.append('note_id', noteId);
            formData.append('etudiant_id', etudiantId);
            formData.append('classe_id', '<?= $selected_classe_id ?>');
            formData.append('matiere_id', '<?= $selected_matiere_id ?>');
            formData.append('annee_id', '<?= $selected_annee_id ?>');
            formData.append('note', noteInput.value);
            formData.append('type_note', typeInput.value);
            formData.append('date_note', dateInput.value);

            fetch('notes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    if (data.note_id) {
                        form.dataset.noteId = data.note_id;
                    }
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                showToast('Erreur de communication avec le serveur.', 'danger');
                console.error('Error:', error);
            });
        }
    });

});
</script>

</body>
</html>