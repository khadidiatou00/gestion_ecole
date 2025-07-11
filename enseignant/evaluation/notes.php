<?php
session_start();
require_once '../../config/db.php';

// Vérification de l'authentification et du rôle
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit();
}

// Seuls les enseignants peuvent accéder à cette page
if ($_SESSION['user_role'] !== 'enseignant') {
    header('Location: ../../index.php');
    exit();
}

$enseignant_id = $_SESSION['user_id'];

// Récupérer les informations de l'enseignant
try {
    $stmt_enseignant = $pdo->prepare("SELECT nom, prenom, photo_profil FROM utilisateurs WHERE id = ?");
    $stmt_enseignant->execute([$enseignant_id]);
    $enseignant_info = $stmt_enseignant->fetch();

    // Récupérer l'année scolaire en cours
    $annee_en_cours_id = $pdo->query("SELECT id FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetchColumn();

    // Récupérer les classes et matières de l'enseignant
    $stmt_classes = $pdo->prepare("
        SELECT DISTINCT c.id, c.nom, c.niveau 
        FROM enseignant_matieres em
        JOIN classes c ON em.classe_id = c.id
        WHERE em.enseignant_id = ? AND em.annee_id = ?
        ORDER BY c.nom
    ");
    $stmt_classes->execute([$enseignant_id, $annee_en_cours_id]);
    $classes = $stmt_classes->fetchAll();

    // Récupérer les matières enseignées
    $stmt_matieres = $pdo->prepare("
        SELECT DISTINCT m.id, m.nom 
        FROM enseignant_matieres em
        JOIN matieres m ON em.matiere_id = m.id
        WHERE em.enseignant_id = ? AND em.annee_id = ?
        ORDER BY m.nom
    ");
    $stmt_matieres->execute([$enseignant_id, $annee_en_cours_id]);
    $matieres = $stmt_matieres->fetchAll();

    // Traitement du formulaire de sélection
    $selected_classe = $_POST['classe'] ?? null;
    $selected_matiere = $_POST['matiere'] ?? null;
    $selected_type = $_POST['type_note'] ?? 'devoir';

    // Si une classe et une matière sont sélectionnées, récupérer les étudiants et leurs notes
    $etudiants = [];
    $notes_existantes = [];
    if ($selected_classe && $selected_matiere) {
        // Récupérer les étudiants de la classe
        $stmt_etudiants = $pdo->prepare("
            SELECT u.id, u.nom, u.prenom 
            FROM inscriptions i
            JOIN utilisateurs u ON i.etudiant_id = u.id
            WHERE i.classe_id = ? AND i.annee_id = ? AND i.statut = 'actif'
            ORDER BY u.nom, u.prenom
        ");
        $stmt_etudiants->execute([$selected_classe, $annee_en_cours_id]);
        $etudiants = $stmt_etudiants->fetchAll();

        // Récupérer les notes existantes pour cette matière
        if (!empty($etudiants)) {
            $etudiant_ids = array_column($etudiants, 'id');
            $placeholders = implode(',', array_fill(0, count($etudiant_ids), '?'));
            
            $stmt_notes = $pdo->prepare("
                SELECT etudiant_id, note 
                FROM notes 
                WHERE matiere_id = ? 
                AND classe_id = ? 
                AND annee_id = ?
                AND type_note = ?
                AND etudiant_id IN ($placeholders)
            ");
            
            $params = array_merge([$selected_matiere, $selected_classe, $annee_en_cours_id, $selected_type], $etudiant_ids);
            $stmt_notes->execute($params);
            
            foreach ($stmt_notes->fetchAll() as $note) {
                $notes_existantes[$note['etudiant_id']] = $note['note'];
            }
        }

        // Traitement de l'envoi des notes
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notes'])) {
            try {
                $pdo->beginTransaction();
                
                foreach ($_POST['notes'] as $etudiant_id => $note_value) {
                    // Vérifier si la note est valide (entre 0 et 20)
                    if ($note_value !== '' && (floatval($note_value) < 0 || floatval($note_value) > 20)) {
                        throw new Exception("Les notes doivent être comprises entre 0 et 20");
                    }
                    
                    // Si une note existe déjà pour cet étudiant, on la met à jour
                    if (isset($notes_existantes[$etudiant_id])) {
                        if ($note_value === '') {
                            // Supprimer la note si le champ est vide
                            $stmt = $pdo->prepare("
                                DELETE FROM notes 
                                WHERE etudiant_id = ? 
                                AND matiere_id = ? 
                                AND classe_id = ? 
                                AND annee_id = ?
                                AND type_note = ?
                            ");
                            $stmt->execute([$etudiant_id, $selected_matiere, $selected_classe, $annee_en_cours_id, $selected_type]);
                        } else {
                            // Mettre à jour la note existante
                            $stmt = $pdo->prepare("
                                UPDATE notes 
                                SET note = ?, updated_at = NOW()
                                WHERE etudiant_id = ? 
                                AND matiere_id = ? 
                                AND classe_id = ? 
                                AND annee_id = ?
                                AND type_note = ?
                            ");
                            $stmt->execute([$note_value, $etudiant_id, $selected_matiere, $selected_classe, $annee_en_cours_id, $selected_type]);
                        }
                    } elseif ($note_value !== '') {
                        // Insérer une nouvelle note
                        $stmt = $pdo->prepare("
                            INSERT INTO notes (
                                etudiant_id, 
                                matiere_id, 
                                classe_id, 
                                enseignant_id, 
                                note, 
                                type_note, 
                                date_note, 
                                annee_id,
                                created_at,
                                updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, NOW(), NOW())
                        ");
                        $stmt->execute([
                            $etudiant_id,
                            $selected_matiere,
                            $selected_classe,
                            $enseignant_id,
                            $note_value,
                            $selected_type,
                            $annee_en_cours_id
                        ]);
                    }
                }
                
                $pdo->commit();
                $success_message = "Les notes ont été enregistrées avec succès!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        }
    }
} catch (PDOException $e) {
    $error_db = "Erreur de base de données : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saisie des Notes - GestiSchool Vibrant</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-main: #f4f7fc;
            --sidebar-bg: #ffffff;
            --card-bg: #ffffff;
            --primary: #4f46e5;
            --secondary: #64748b;
            --accent: #ec4899;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border-color: #e2e8f0;
            --font-body: 'Poppins', sans-serif;
            --font-title: 'Montserrat', sans-serif;
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: translateY(20px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        @keyframes pulse-glow {
            0% { box-shadow: 0 0 10px rgba(79, 70, 229, 0.2), 0 0 20px rgba(79, 70, 229, 0.1); }
            50% { box-shadow: 0 0 20px rgba(79, 70, 229, 0.4), 0 0 40px rgba(79, 70, 229, 0.2); }
            100% { box-shadow: 0 0 10px rgba(79, 70, 229, 0.2), 0 0 20px rgba(79, 70, 229, 0.1); }
        }

        body {
            font-family: var(--font-body);
            background-color: var(--bg-main);
            color: var(--text-dark);
        }

        .page-wrapper { display: flex; min-height: 100vh; }
        
        /* Barre latérale */
        #sidebar {
            width: 260px; position: fixed; top: 0; left: 0; height: 100vh;
            z-index: 1000; background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            display: flex; flex-direction: column;
            transition: all 0.3s ease;
        }
        .sidebar-header {
            padding: 1.5rem; text-align: center; border-bottom: 1px solid var(--border-color);
        }
        .sidebar-header .logo {
            font-family: var(--font-title); font-size: 1.6rem; color: var(--primary);
            font-weight: 700; text-decoration: none;
        }
        .sidebar-nav { padding: 1rem; flex-grow: 1; overflow-y: auto; }
        .nav-category { font-size: 0.75rem; color: var(--text-light); text-transform: uppercase; padding: 1rem; font-weight: 600; letter-spacing: 0.5px; }
        .nav-link {
            display: flex; align-items: center; padding: 0.8rem 1rem;
            color: var(--secondary); text-decoration: none; border-radius: 8px;
            margin-bottom: 5px; font-weight: 500; transition: all 0.3s ease;
        }
        .nav-link i {
            width: 25px; margin-right: 15px; text-align: center;
            font-size: 1.2rem; transition: all 0.3s ease;
        }
        .nav-link:hover {
            background-color: #eef2ff; color: var(--primary);
        }
        .nav-link.active {
            background: var(--primary); color: #fff;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
        }
        .nav-link.active i { transform: scale(1.1); }
        
        /* Pied de la barre latérale */
        .sidebar-footer { padding: 1rem; border-top: 1px solid var(--border-color); }
        .user-info { display: flex; align-items: center; }
        .user-info img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; margin-right: 12px; border: 2px solid var(--primary); }
        .user-info .username { font-weight: 600; color: var(--text-dark); }
        
        /* Contenu principal */
        #main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2.5rem; }
        .main-header h1 { font-family: var(--font-title); font-weight: 700; }
        
        .dashboard-card {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 16px; padding: 1.5rem 2rem;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.07), 0 4px 6px -2px rgba(0,0,0,0.05);
            animation: fadeInScale 0.6s ease-out forwards;
            opacity: 0;
            transform-origin: center;
        }
        .card-header-custom {
            display: flex; align-items: center; margin-bottom: 1.5rem;
            padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);
        }
        .card-header-custom .icon-wrapper {
            width: 48px; height: 48px; border-radius: 12px;
            display: grid; place-items: center; margin-right: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff; font-size: 1.5rem; box-shadow: 0 4px 8px rgba(79, 70, 229, 0.3);
        }
        .card-header-custom h5 { font-family: var(--font-title); font-size: 1.3rem; margin: 0; font-weight: 600; }
        
        .quick-access-btn {
            text-align: center; background: var(--card-bg);
            border: 1px solid var(--border-color); border-radius: 16px;
            padding: 2rem 1.5rem; text-decoration: none; color: var(--text-dark);
            font-weight: 600; font-family: var(--font-title);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.07), 0 4px 6px -2px rgba(0,0,0,0.05);
            animation: fadeInScale 0.6s ease-out forwards;
            opacity: 0;
        }
        .quick-access-btn i {
            font-size: 2.5rem; margin-bottom: 1rem;
            background: -webkit-linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            transition: all 0.3s ease;
        }
        .quick-access-btn:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
        }

        /* Styles spécifiques à la page de notes */
        .note-input {
            width: 80px;
            text-align: center;
            font-weight: 600;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 0.5rem;
            transition: all 0.3s ease;
        }
        .note-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.25);
        }
        .note-input.invalid {
            border-color: #dc3545;
        }
        .student-row {
            transition: all 0.3s ease;
        }
        .student-row:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        .table-responsive {
            border-radius: 16px;
            overflow: hidden;
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        .table td {
            vertical-align: middle;
        }
        .badge-type {
            background-color: #e0e7ff;
            color: var(--primary);
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 50px;
        }
        .btn-save {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border: none;
            font-weight: 600;
            letter-spacing: 0.5px;
            padding: 0.75rem 2rem;
            transition: all 0.3s ease;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.4);
        }
        .select2-container--bootstrap-5 .select2-selection {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 0.5rem;
            height: auto;
        }
        .select2-container--bootstrap-5 .select2-selection:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.25);
        }

        @media (max-width: 992px) { 
            #sidebar { left: -260px; } 
            #sidebar.active { left: 0; } 
            #main-content { margin-left: 0; width: 100%; } 
            #sidebar-toggle { display: block; } 
        }
        #sidebar-toggle { display: none; background: transparent; border: none; font-size: 1.5rem; }
    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- Barre Latérale -->
    <aside id="sidebar">
        <div class="sidebar-header"><a href="../dashboard.php" class="logo"><i class="fas fa-graduation-cap"></i> GestiSchool</a></div>
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
                <li class="nav-category">Pédagogie</li>
                <li class="nav-item"><a class="nav-link" href="../pedagogie/cours.php"><i class="fas fa-book-open"></i> Mes Cours</a></li>
                <li class="nav-item"><a class="nav-link" href="../pedagogie/devoirs.php"><i class="fas fa-file-signature"></i> Devoirs</a></li>
                <li class="nav-item"><a class="nav-link" href="../pedagogie/projets.php"><i class="fas fa-project-diagram"></i> Projets</a></li>
                <li class="nav-item"><a class="nav-link" href="../pedagogie/ressources.php"><i class="fas fa-folder-open"></i> Ressources</a></li>
                <li class="nav-category">Évaluation</li>
                <li class="nav-item"><a class="nav-link active" href="notes.php"><i class="fas fa-marker"></i> Saisie des Notes</a></li>
                <li class="nav-item"><a class="nav-link" href="competences.php"><i class="fas fa-tasks"></i> Compétences</a></li>
                <li class="nav-category">Vie de Classe</li>
                <li class="nav-item"><a class="nav-link" href="../presence/absences.php"><i class="fas fa-user-check"></i> Appel & Absences</a></li>
                <li class="nav-item"><a class="nav-link" href="../presence/retard.php"><i class="fas fa-user-clock"></i> Retards</a></li>
                <li class="nav-category">Communication</li>
                <li class="nav-item"><a class="nav-link" href="../communication/messagerie.php"><i class="fas fa-envelope"></i> Messagerie</a></li>
                <li class="nav-item"><a class="nav-link" href="../communication/annonces.php"><i class="fas fa-bullhorn"></i> Annonces</a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <img src="../../<?= htmlspecialchars($enseignant_info['photo_profil'] ?: 'assets/img/profiles/default.png') ?>" alt="Photo de profil">
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
                <h1 class="d-inline-block align-middle"><i class="fas fa-marker me-2" style="color: var(--primary);"></i>Saisie des Notes</h1>
            </div>
        </header>

        <!-- Messages d'alerte -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" style="animation: fadeInScale 0.4s ease-out;">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="animation: fadeInScale 0.4s ease-out;">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Formulaire de sélection -->
        <div class="dashboard-card mb-4" style="animation-delay: 0.1s;">
            <div class="card-header-custom">
                <div class="icon-wrapper"><i class="fas fa-filter"></i></div>
                <h5>Filtrer les Notes</h5>
            </div>
            <form method="POST" class="row g-3">
                <div class="col-md-4">
                    <label for="classe" class="form-label fw-bold">Classe</label>
                    <select class="form-select" id="classe" name="classe" required>
                        <option value="">Sélectionnez une classe</option>
                        <?php foreach ($classes as $classe): ?>
                            <option value="<?= $classe['id'] ?>" <?= $selected_classe == $classe['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($classe['nom'] . ' (' . $classe['niveau'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="matiere" class="form-label fw-bold">Matière</label>
                    <select class="form-select" id="matiere" name="matiere" required>
                        <option value="">Sélectionnez une matière</option>
                        <?php foreach ($matieres as $matiere): ?>
                            <option value="<?= $matiere['id'] ?>" <?= $selected_matiere == $matiere['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($matiere['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="type_note" class="form-label fw-bold">Type de note</label>
                    <select class="form-select" id="type_note" name="type_note" required>
                        <option value="devoir" <?= $selected_type == 'devoir' ? 'selected' : '' ?>>Devoir</option>
                        <option value="composition" <?= $selected_type == 'composition' ? 'selected' : '' ?>>Composition</option>
                        <option value="examen" <?= $selected_type == 'examen' ? 'selected' : '' ?>>Examen</option>
                        <option value="oral" <?= $selected_type == 'oral' ? 'selected' : '' ?>>Oral</option>
                    </select>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary btn-save">
                        <i class="fas fa-search me-2"></i>Rechercher
                    </button>
                </div>
            </form>
        </div>

        <!-- Tableau des notes -->
        <?php if ($selected_classe && $selected_matiere && !empty($etudiants)): ?>
            <div class="dashboard-card" style="animation-delay: 0.2s;">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="icon-wrapper"><i class="fas fa-table"></i></div>
                        <h5>Notes des Étudiants</h5>
                    </div>
                    <span class="badge-type">
                        <i class="fas fa-tag me-2"></i><?= ucfirst($selected_type) ?>
                    </span>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="classe" value="<?= $selected_classe ?>">
                    <input type="hidden" name="matiere" value="<?= $selected_matiere ?>">
                    <input type="hidden" name="type_note" value="<?= $selected_type ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="30%">Étudiant</th>
                                    <th width="15%">Note (/20)</th>
                                    <th width="50%">Commentaire</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($etudiants as $index => $etudiant): ?>
                                    <tr class="student-row">
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($etudiant['nom']) ?></strong>
                                            <?= htmlspecialchars($etudiant['prenom']) ?>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="notes[<?= $etudiant['id'] ?>]" 
                                                   class="form-control note-input" 
                                                   min="0" 
                                                   max="20" 
                                                   step="0.25" 
                                                   value="<?= isset($notes_existantes[$etudiant['id']]) ? htmlspecialchars($notes_existantes[$etudiant['id']]) : '' ?>">
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="commentaires[<?= $etudiant['id'] ?>]" 
                                                   class="form-control" 
                                                   placeholder="Commentaire optionnel...">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-save text-white">
                            <i class="fas fa-save me-2"></i>Enregistrer les notes
                        </button>
                    </div>
                </form>
            </div>
        <?php elseif ($selected_classe && $selected_matiere && empty($etudiants)): ?>
            <div class="dashboard-card text-center p-5" style="animation-delay: 0.2s;">
                <i class="fas fa-user-graduate mb-4" style="font-size: 3rem; color: var(--primary);"></i>
                <h4 class="mb-3">Aucun étudiant trouvé dans cette classe</h4>
                <p class="text-muted">Vérifiez que des étudiants sont bien inscrits dans cette classe pour l'année en cours.</p>
            </div>
        <?php elseif (!isset($selected_classe) || !isset($selected_matiere)): ?>
            <div class="dashboard-card text-center p-5" style="animation-delay: 0.2s;">
                <i class="fas fa-info-circle mb-4" style="font-size: 3rem; color: var(--primary);"></i>
                <h4 class="mb-3">Sélectionnez une classe et une matière</h4>
                <p class="text-muted">Veuillez choisir une classe et une matière pour afficher la liste des étudiants et saisir leurs notes.</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Toggle sidebar
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) sidebarToggle.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));

    // Validation des notes
    const noteInputs = document.querySelectorAll('.note-input');
    noteInputs.forEach(input => {
        input.addEventListener('change', function() {
            const value = parseFloat(this.value);
            if (isNaN(value)) {
                this.classList.remove('invalid');
                return;
            }
            
            if (value < 0 || value > 20) {
                this.classList.add('invalid');
            } else {
                this.classList.remove('invalid');
            }
        });
    });

    // Animation des éléments
    const animateElements = document.querySelectorAll('.dashboard-card');
    animateElements.forEach((el, index) => {
        el.style.animationDelay = `${index * 0.1}s`;
    });
});
</script>

</body>
</html>