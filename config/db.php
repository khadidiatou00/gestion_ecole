<?php


// Paramètres de connexion à la base de données
// Il est recommandé de stocker ces informations sensibles dans des variables d'environnement
// sur un serveur de production pour plus de sécurité.
define('DB_HOST', 'localhost');         
define('DB_NAME', 'gestion_ecole');     
define('DB_USER', 'root');              
define('DB_PASS', '');                  
define('DB_CHARSET', 'utf8mb4');       

// Création du DSN (Data Source Name)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// Options pour PDO
$options = [
    // Gérer les erreurs en lançant des exceptions, ce qui est plus propre
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Récupérer les résultats sous forme de tableaux associatifs par défaut
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Désactiver l'émulation des requêtes préparées pour une meilleure sécurité
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Tentative de connexion à la base de données
try {
    // Création de l'instance de PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // En cas d'échec de la connexion, on arrête le script et on affiche un message d'erreur.
    // En production, il faudrait logger cette erreur plutôt que de l'afficher à l'utilisateur.
    error_log("Erreur de connexion à la base de données : " . $e->getMessage());
    die("<h1>Erreur de connexion</h1><p>Impossible de se connecter à la base de données. Veuillez contacter l'administrateur du site.</p>");
}

// La variable $pdo est maintenant disponible pour être utilisée dans les autres fichiers.
?>