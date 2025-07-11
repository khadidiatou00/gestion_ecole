<?php


if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    $message = escape($notification['message']);
    $type = escape($notification['type']); // ex: success, danger, warning, info
    
    // Utilise les classes d'alerte de Bootstrap
    echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>";
    echo $message;
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo "</div>";
    
    // Supprimer la notification pour qu'elle n'apparaisse plus
    unset($_SESSION['notification']);
}
?>