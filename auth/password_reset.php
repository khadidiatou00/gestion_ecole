<?php
session_start();
$message = '';
$message_type = 'info';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    // NOTE : La logique réelle d'envoi d'email est complexe et n'est pas implémentée ici.
    // Il faudrait :
    // 1. Vérifier si l'email existe dans la DB.
    // 2. Générer un token sécurisé.
    // 3. Stocker le token avec l'email et une date d'expiration.
    // 4. Utiliser une librairie comme PHPMailer pour envoyer l'email.
    
    // Pour la démo, on affiche un message de confirmation générique.
    $message = "Si un compte est associé à <strong>" . htmlspecialchars($email) . "</strong>, un lien de réinitialisation a été envoyé.";
    $message_type = 'success';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation - GestiSchool Aurora</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6a11cb; --secondary-color: #2575fc; --accent-color: #30cfd0;
            --text-color: #e0e0e0; --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.2);
        }
        @keyframes gradient-animation {
            0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; }
        }
        body {
            font-family: 'Poppins', sans-serif; margin: 0; padding: 0; height: 100vh; overflow: hidden;
            display: flex; align-items: center; justify-content: center; color: var(--text-color);
            background: linear-gradient(-45deg, var(--primary-color), var(--secondary-color), #23a6d5, #23d5ab);
            background-size: 400% 400%; animation: gradient-animation 15s ease infinite;
        }
        .auth-card {
            background: var(--glass-bg); border-radius: 20px; border: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
            padding: 2.5rem; width: 100%; max-width: 450px;
            animation: fadeInUp 0.8s ease-out forwards;
        }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .auth-card .logo {
            font-size: 3rem; margin-bottom: 0.5rem; background: -webkit-linear-gradient(45deg, var(--accent-color), #fff);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 15px rgba(48, 207, 208, 0.4);
        }
        .input-group { position: relative; }
        .form-control {
            background: transparent; border: none; border-bottom: 1px solid var(--glass-border); border-radius: 0;
            color: var(--text-color); padding-left: 2.5rem; transition: all 0.3s ease;
        }
        .form-control::placeholder { color: rgba(255, 255, 255, 0.6); }
        .form-control:focus { background: transparent; color: white; box-shadow: none; border-bottom-color: var(--accent-color); }
        .input-group-icon { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: rgba(255, 255, 255, 0.7); }
        .form-control:focus ~ .input-group-icon { color: var(--accent-color); }
        .btn-aurora {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); border: none; border-radius: 10px;
            padding: 0.8rem; font-weight: 600; color: #fff; transition: all 0.4s ease;
            box-shadow: 0 4px 15px 0 rgba(0, 0, 0, 0.2); background-size: 200% auto;
        }
        .btn-aurora:hover { background-position: right center; transform: scale(1.05); }
        .auth-link { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: color 0.3s ease; }
        .auth-link:hover { color: #fff; text-decoration: underline; }
        .alert-success-custom { background-color: rgba(25, 135, 84, 0.2); border-color: rgba(25, 135, 84, 0.4); color: #a3cfbb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="auth-card">
                    <div class="text-center mb-4">
                        <i class="fas fa-key logo"></i>
                        <h2 class="text-center">Mot de Passe Oublié</h2>
                        <p class="text-white-50">Ne vous inquiétez pas, nous allons vous aider.</p>
                    </div>
                    
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success-custom text-center mb-4" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?= $message; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="password_reset.php">
                        <div class="mb-4 input-group">
                            <input type="email" class="form-control" name="email" placeholder="Votre adresse e-mail" required>
                            <span class="input-group-icon"><i class="fas fa-envelope"></i></span>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-aurora">Envoyer le lien</button>
                        </div>
                        <div class="text-center mt-4">
                            <a href="login.php" class="auth-link"><i class="fas fa-arrow-left me-1"></i> Retour à la connexion</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>