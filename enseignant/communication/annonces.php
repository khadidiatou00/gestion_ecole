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

// Initialisation des variables pour éviter les erreurs
$annonces_list = [];
$classes_list = [];
$enseignant_info = [];

// --- LOGIQUE CRUD ---
try {
    $annee_en_cours_id = $pdo->query("SELECT id FROM annees_scolaires WHERE statut = 'en_cours' LIMIT 1")->fetchColumn();
    if (!$annee_en_cours_id) { throw new Exception("Aucune année scolaire active n'a été trouvée."); }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // --- CRÉER OU MODIFIER UNE ANNONCE ---
        if (isset($_POST['action']) && in_array($_POST['action'], ['creer_annonce', 'modifier_annonce'])) {
            $titre = trim($_POST['titre']);
            $contenu = trim($_POST['contenu']);
            $classe_id = !empty($_POST['classe_id']) ? $_POST['classe_id'] : null;

            if (empty($titre) || empty($contenu)) {
                throw new Exception("Le titre et le contenu sont obligatoires.");
            }

            if ($_POST['action'] === 'creer_annonce') {
                $sql = "INSERT INTO annonces (enseignant_id, titre, contenu, classe_id, annee_id) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$enseignant_id, $titre, $contenu, $classe_id, $annee_en_cours_id]);
                $_SESSION['notification'] = ['type' => 'success', 'message' => 'Annonce publiée avec succès.'];
            } else { // modifier_annonce
                $annonce_id = $_POST['annonce_id'];
                $sql = "UPDATE annonces SET titre = ?, contenu = ?, classe_id = ? WHERE id = ? AND enseignant_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$titre, $contenu, $classe_id, $annonce_id, $enseignant_id]);
                $_SESSION['notification'] = ['type' => 'success', 'message' => 'Annonce modifiée avec succès.'];
            }
            header('Location: annonces.php');
            exit();
        }
        
        // --- SUPPRIMER UNE ANNONCE ---
        if (isset($_POST['action']) && $_POST['action'] === 'supprimer_annonce') {
            $annonce_id = $_POST['annonce_id'];
            $stmt = $pdo->prepare("DELETE FROM annonces WHERE id = ? AND enseignant_id = ?");
            $stmt->execute([$annonce_id, $enseignant_id]);
            $_SESSION['notification'] = ['type' => 'info', 'message' => 'Annonce supprimée.'];
            header('Location: annonces.php');
            exit();
        }
    }

    // --- LOGIQUE D'AFFICHAGE ---
    $stmt_enseignant = $pdo->prepare("SELECT nom, prenom, photo_profil FROM utilisateurs WHERE id = ?");
    $stmt_enseignant->execute([$enseignant_id]);
    $enseignant_info = $stmt_enseignant->fetch();
    
    // Récupérer la liste des annonces de l'enseignant
    $stmt_annonces = $pdo->prepare(
        "SELECT a.*, c.nom as classe_nom 
         FROM annonces a
         LEFT JOIN classes c ON a.classe_id = c.id
         WHERE a.enseignant_id = ? AND a.annee_id = ?
         ORDER BY a.date_publication DESC"
    );
    $stmt_annonces->execute([$enseignant_id, $annee_en_cours_id]);
    $annonces_list = $stmt_annonces->fetchAll();

    // Récupérer les classes de l'enseignant pour le formulaire
    $stmt_classes = $pdo->prepare("SELECT DISTINCT c.id, c.nom FROM classes c JOIN enseignant_matieres em ON c.id = em.classe_id WHERE em.enseignant_id = ? AND em.annee_id = ? ORDER BY c.nom");
    $stmt_classes->execute([$enseignant_id, $annee_en_cours_id]);
    $classes_list = $stmt_classes->fetchAll();

} catch (Exception $e) {
    $notification = ['type' => 'danger', 'message' => "Erreur : " . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Annonces - GestiSchool</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: #f4f7fc; --sidebar-bg: #ffffff; --card-bg: #ffffff; --primary: #4f46e5;
            --secondary: #64748b; --accent: #ec4899; --text-dark: #1e293b; --text-light: #64748b;
            --border-color: #e2e8f0; --font-body: 'Poppins', sans-serif; --font-title: 'Montserrat', sans-serif;
        }
        @keyframes fadeInScale { from { opacity: 0; transform: translateY(20px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        body { font-family: var(--font-body); background-color: var(--bg-main); color: var(--text-dark); }
        .page-wrapper { display: flex; min-height: 100vh; }
        #sidebar { width: 260px; position: fixed; top: 0; left: 0; height: 100vh; z-index: 1000; background: var(--sidebar-bg); border-right: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.05); display: flex; flex-direction: column; transition: all 0.3s ease; }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid var(--border-color); }
        .sidebar-header .logo { font-family: var(--font-title); font-size: 1.6rem; color: var(--primary); font-weight: 700; text-decoration: none; }
        .nav-link { display: flex; align-items: center; padding: 0.8rem 1rem; color: var(--secondary); text-decoration: none; border-radius: 8px; margin-bottom: 5px; font-weight: 500; transition: all 0.3s ease; }
        .nav-link:hover { background-color: #eef2ff; color: var(--primary); }
        .nav-link.active { background: var(--primary); color: #fff; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
        .sidebar-footer { padding: 1rem; border-top: 1px solid var(--border-color); }
        .user-info img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; margin-right: 12px; border: 2px solid var(--primary); }
        #main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2.5rem; }
        .main-header h1 { font-family: var(--font-title); font-weight: 700; }
        .dashboard-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem 2rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.07), 0 4px 6px -2px rgba(0,0,0,0.05); animation: fadeInScale 0.6s ease-out forwards; opacity: 0; }
        .card-header-custom { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
        .card-header-custom .icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: grid; place-items: center; margin-right: 1rem; background: linear-gradient(135deg, var(--primary), var(--accent)); color: #fff; font-size: 1.5rem; box-shadow: 0 4px 8px rgba(79, 70, 229, 0.3); }
        .card-header-custom h5 { font-family: var(--font-title); font-size: 1.3rem; margin: 0; font-weight: 600; }
        .accordion-button:not(.collapsed) { color: var(--primary); background-color: #eef2ff; box-shadow: inset 0 -1px 0 rgba(0,0,0,.125); }
        .accordion-button:focus { box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.25); }
        .modal-content { border-radius: 16px; border: none; }
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
                <li class="nav-category">Communication</li>
                <li class="nav-item"><a class="nav-link" href="messagerie.php"><i class="fas fa-envelope"></i> Messagerie</a></li>
                <li class="nav-item"><a class="nav-link active" href="annonces.php"><i class="fas fa-bullhorn"></i> Annonces</a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info d-flex align-items-center">
                <img src="../../<?= htmlspecialchars($enseignant_info['photo_profil'] ?? 'assets/img/profiles/default.png') ?>" alt="Photo de profil">
                <div><?= htmlspecialchars($enseignant_info['prenom'] . ' ' . $enseignant_info['nom']) ?> <a href="../../auth/logout.php" class="text-danger small d-block">Déconnexion</a></div>
            </div>
        </div>
    </aside>

    <!-- Contenu Principal -->
    <main id="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <h1 class="d-inline-block align-middle"><i class="fas fa-bullhorn me-2" style="color: var(--primary);"></i>Annonces</h1>
        </header>

        <?php if ($notification): ?>
            <div class="alert alert-<?= $notification['type'] ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($notification['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="dashboard-card" style="animation-delay: 0.1s;">
            <div class="card-header-custom">
                <div class="d-flex align-items-center">
                    <div class="icon-wrapper"><i class="fas fa-list-alt"></i></div>
                    <h5>Mes Annonces Publiées</h5>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#annonceModal"><i class="fas fa-plus me-2"></i>Nouvelle annonce</button>
            </div>
            
            <div class="accordion" id="annoncesAccordion">
                <?php if (empty($annonces_list)): ?>
                    <div class="text-center text-muted p-5">
                        <i class="fas fa-comment-slash fa-3x mb-3"></i><br>
                        Vous n'avez publié aucune annonce pour le moment.
                    </div>
                <?php endif; ?>
                <?php foreach ($annonces_list as $index => $annonce): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-<?= $annonce['id'] ?>">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $annonce['id'] ?>" aria-expanded="false" aria-controls="collapse-<?= $annonce['id'] ?>">
                                <span class="flex-grow-1"><strong><?= htmlspecialchars($annonce['titre']) ?></strong></span>
                                <?php if ($annonce['classe_nom']): ?>
                                    <span class="badge bg-secondary me-3"><?= htmlspecialchars($annonce['classe_nom']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-info me-3">Toutes les classes</span>
                                <?php endif; ?>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($annonce['date_publication'])) ?></small>
                            </button>
                        </h2>
                        <div id="collapse-<?= $annonce['id'] ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?= $annonce['id'] ?>" data-bs-parent="#annoncesAccordion">
                            <div class="accordion-body">
                                <p style="white-space: pre-wrap;"><?= nl2br(htmlspecialchars($annonce['contenu'])) ?></p>
                                <hr>
                                <div class="text-end">
                                    <button class="btn btn-sm btn-outline-primary btn-edit" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#annonceModal"
                                            data-id="<?= $annonce['id'] ?>"
                                            data-titre="<?= htmlspecialchars($annonce['titre']) ?>"
                                            data-contenu="<?= htmlspecialchars($annonce['contenu']) ?>"
                                            data-classe-id="<?= $annonce['classe_id'] ?>">
                                        <i class="fas fa-pencil-alt me-1"></i> Modifier
                                    </button>
                                    <form action="annonces.php" method="POST" class="d-inline" onsubmit="return confirm('Voulez-vous vraiment supprimer cette annonce ?');">
                                        <input type="hidden" name="action" value="supprimer_annonce">
                                        <input type="hidden" name="annonce_id" value="<?= $annonce['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i> Supprimer</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<!-- Modal pour créer/modifier une annonce -->
<div class="modal fade" id="annonceModal" tabindex="-1" aria-labelledby="annonceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="annonceForm" action="annonces.php" method="POST">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="annonce_id" id="formAnnonceId">
                <div class="modal-header">
                    <h5 class="modal-title" id="annonceModalLabel">Nouvelle Annonce</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="titre" class="form-label">Titre</label>
                        <input type="text" class="form-control" id="formTitre" name="titre" required>
                    </div>
                     <div class="mb-3">
                        <label for="classe_id" class="form-label">Destinataire</label>
                        <select class="form-select" id="formClasseId" name="classe_id">
                            <option value="">Toutes mes classes</option>
                            <?php foreach ($classes_list as $classe): ?>
                                <option value="<?= $classe['id'] ?>"><?= htmlspecialchars($classe['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="contenu" class="form-label">Contenu de l'annonce</label>
                        <textarea class="form-control" id="formContenu" name="contenu" rows="8" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Publier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const annonceModal = document.getElementById('annonceModal');
    
    annonceModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget; // Bouton qui a déclenché la modal
        const form = document.getElementById('annonceForm');
        const modalTitle = document.getElementById('annonceModalLabel');
        
        // Récupérer les champs du formulaire
        const actionInput = form.querySelector('#formAction');
        const annonceIdInput = form.querySelector('#formAnnonceId');
        const titreInput = form.querySelector('#formTitre');
        const contenuInput = form.querySelector('#formContenu');
        const classeIdInput = form.querySelector('#formClasseId');

        // Vérifier si on modifie ou on crée
        if (button.classList.contains('btn-edit')) {
            // Mode Modification
            modalTitle.textContent = 'Modifier l\'annonce';
            actionInput.value = 'modifier_annonce';
            annonceIdInput.value = button.dataset.id;
            titreInput.value = button.dataset.titre;
            contenuInput.value = button.dataset.contenu;
            classeIdInput.value = button.dataset.classeId;
        } else {
            // Mode Création
            modalTitle.textContent = 'Nouvelle Annonce';
            actionInput.value = 'creer_annonce';
            form.reset(); // Vider le formulaire
            annonceIdInput.value = '';
        }
    });
});
</script>

</body>
</html>