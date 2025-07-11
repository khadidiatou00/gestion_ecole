<?php

?>

</div> <!-- Fin du .content-wrapper -->

<footer class="text-center text-white-50 py-3" style="position: relative; z-index: 1;">
    <div class="container">
        <p class="mb-0">© <?= date('Y') ?> <?= APP_NAME ?>. Tous droits réservés.</p>
    </div>
</footer>

<!-- Bootstrap 5.3 JS Bundle (inclus Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- tsParticles - Librairie pour l'animation -->
<script src="https://cdn.jsdelivr.net/npm/tsparticles@3.3.0/tsparticles.bundle.min.js"></script>

<!-- Script d'initialisation de l'animation -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Configuration de l'animation "étincelles brillantes"
    tsParticles.load({
        id: "tsparticles",
        options: {
            // Un preset "stars" légèrement modifié pour un effet magique
            preset: "stars",
            particles: {
                // Couleurs brillantes
                color: {
                    value: ["#FFFFFF", "#FFD700", "#00FFFF"] // Blanc, Or, Cyan
                },
                // Mouvement lent et subtil
                move: {
                    speed: 0.5,
                    direction: "none",
                    outModes: "out",
                },
                // Scintillement
                twinkle: {
                    particles: {
                        enable: true,
                        frequency: 0.05,
                        opacity: 1
                    }
                },
                // Nombre de particules
                number: {
                    value: 100, // Ajustez pour plus ou moins de densité
                    density: {
                        enable: true,
                        area: 800
                    }
                }
            },
            background: {
                color: "#0c0a1a" // Couleur de fond doit correspondre au body
            },
            fullScreen: {
                enable: true,
                zIndex: -1 // Très important : reste en arrière-plan
            },
            interactivity: {
                events: {
                    onHover: {
                        enable: true,
                        mode: "bubble" // Grossit les particules au survol
                    }
                },
                modes: {
                    bubble: {
                        distance: 150,
                        duration: 2,
                        opacity: 1,
                        size: 6,
                    }
                }
            }
        },
    });
});
</script>

</body>
</html>