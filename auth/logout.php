<?php
session_start();

// Détruire toutes les variables de session.
$_SESSION = array();

// Détruire le cookie de session.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalement, détruire la session.
session_destroy();

// Rediriger vers la page de connexion avec un message optionnel
header("Location: login.php?status=logged_out");
exit();
?>