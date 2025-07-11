<?php
session_start();
require_once '../../config/db.php';

// Vérification du rôle
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'etudiant') {
    header('Location: ../../auth/login.php');
    exit();
}

// Traitement recherche & filtre
$filtre = $_GET['type'] ?? '';
$recherche = $_GET['q'] ?? '';

// Requête dynamique
$sql = "
    SELECT c.*, m.nom AS matiere_nom, u.nom AS enseignant_nom, u.prenom AS enseignant_prenom 
    FROM cours c
    LEFT JOIN matieres m ON c.matiere_id = m.id
    LEFT JOIN utilisateurs u ON c.enseignant_id = u.id
    WHERE 1 = 1
";

$params = [];

if ($filtre !== '') {
    $sql .= " AND c.type_fichier = ?";
    $params[] = $filtre;
}

if ($recherche !== '') {
    $sql .= " AND c.titre LIKE ?";
    $params[] = "%$recherche%";
}

$sql .= " ORDER BY c.date_publication DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cours = $stmt->fetchAll();

// Statistiques
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM cours")->fetchColumn(),
    'pdf' => $pdo->query("SELECT COUNT(*) FROM cours WHERE type_fichier = 'pdf'")->fetchColumn(),
    'video' => $pdo->query("SELECT COUNT(*) FROM cours WHERE type_fichier = 'video'")->fetchColumn(),
    'doc' => $pdo->query("SELECT COUNT(*) FROM cours WHERE type_fichier = 'doc' OR type_fichier = 'docx'")->fetchColumn(),
    'ppt' => $pdo->query("SELECT COUNT(*) FROM cours WHERE type_fichier = 'ppt' OR type_fichier = 'pptx'")->fetchColumn(),
];

// Icônes
function getIconByType($type) {
    return match($type) {
        'pdf' => 'fa-file-pdf text-danger',
        'doc', 'docx' => 'fa-file-word text-primary',
        'ppt', 'pptx' => 'fa-file-powerpoint text-warning',
        'video' => 'fa-file-video text-info',
        'lien' => 'fa-link text-success',
        default => 'fa-file text-muted',
    };
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Cours</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f0f2f5;
        }
        .card-cours {
            transition: 0.4s;
            border-radius: 15px;
        }
        .card-cours:hover {
            transform: scale(1.02);
            box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.15);
        }
        .icon-big {
            font-size: 2.8rem;
        }
        .filters {
            background: #fff;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 0.3rem 1rem rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
    </style>
</head>
<body>

<div class="container my-5">
    <h2 class="text-center mb-4">📚 Bibliothèque des Cours</h2>

    <div class="filters">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="q" value="<?= htmlspecialchars($recherche) ?>" class="form-control" placeholder="Rechercher un cours...">
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="">-- Tous types --</option>
                    <option value="pdf" <?= $filtre === 'pdf' ? 'selected' : '' ?>>PDF</option>
                    <option value="doc" <?= $filtre === 'doc' ? 'selected' : '' ?>>Word</option>
                    <option value="ppt" <?= $filtre === 'ppt' ? 'selected' : '' ?>>PowerPoint</option>
                    <option value="video" <?= $filtre === 'video' ? 'selected' : '' ?>>Vidéo</option>
                    <option value="lien" <?= $filtre === 'lien' ? 'selected' : '' ?>>Lien</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit"><i class="fas fa-search"></i> Rechercher</button>
            </div>
            <div class="col-md-3">
                <a href="?reset=1" class="btn btn-secondary w-100"><i class="fas fa-undo"></i> Réinitialiser</a>
            </div>
        </form>
    </div>

    <div class="mb-4 row text-center">
        <div class="col-md-3"><strong>Total :</strong> <?= $stats['total'] ?></div>
        <div class="col-md-3"><strong>PDF :</strong> <?= $stats['pdf'] ?></div>
        <div class="col-md-3"><strong>Vidéo :</strong> <?= $stats['video'] ?></div>
        <div class="col-md-3"><strong>Word :</strong> <?= $stats['doc'] ?></div>
    </div>

    <div class="row g-4">
        <?php if (empty($cours)): ?>
            <div class="col-12">
                <div class="alert alert-warning text-center">Aucun cours trouvé.</div>
            </div>
        <?php else: ?>
            <?php foreach ($cours as $c): ?>
                <div class="col-md-4">
                    <div class="card card-cours h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="text-center mb-3">
                                <i class="fas <?= getIconByType($c['type_fichier']) ?> icon-big"></i>
                            </div>
                            <h5 class="card-title"><?= htmlspecialchars($c['titre']) ?></h5>
                            <p class="card-text"><strong>Matière :</strong> <?= htmlspecialchars($c['matiere_nom'] ?? 'Non défini') ?></p>
                            <p class="card-text"><strong>Enseignant :</strong> <?= htmlspecialchars($c['enseignant_prenom'] . ' ' . $c['enseignant_nom']) ?></p>
                            <p class="card-text"><small class="text-muted">Publié le <?= date('d/m/Y à H:i', strtotime($c['date_publication'])) ?></small></p>

                            <?php if ($c['type_fichier'] === 'lien' && !empty($c['fichier_path'])): ?>
                                <a href="<?= $c['fichier_path'] ?>" target="_blank" class="btn btn-outline-success mt-auto"><i class="fas fa-link"></i> Voir</a>
                            <?php elseif (!empty($c['fichier_path'])): ?>
                                <a href="<?= '../../' . $c['fichier_path'] ?>" target="_blank" class="btn btn-outline-primary mt-auto"><i class="fas fa-download"></i> Télécharger</a>
                            <?php else: ?>
                                <span class="text-muted mt-auto">Pas de fichier</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
