<?php
session_start();
// La variable $pdo sera créée et disponible grâce à cette ligne
require_once '../config/db.php';

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error_message = "Veuillez remplir tous les champs.";
    } else {
        try {
            // CORRECTION : On n'appelle plus de fonction. La variable $pdo est déjà disponible.
            $stmt = $pdo->prepare("SELECT id, nom, prenom, password, role FROM utilisateurs WHERE email = ? AND statut = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];

                $redirect_url = match($user['role']) {
                    'admin' => '../admin/dashboard.php',
                    'enseignant' => '../enseignant/dashboard.php',
                    'etudiant' => '../etudiant/dashboard.php',
                    default => 'login.php'
                };
                header("Location: $redirect_url");
                exit();
            } else {
                $error_message = "Email ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            // En production, il est préférable de logger cette erreur.
            // error_log($e->getMessage());
            $error_message = "Erreur de connexion. Veuillez réessayer plus tard.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - GestiSchool Aurora</title>
    
    <!-- Dépendances externes (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS Intégré -->
    <style>
        :root {
            --primary-color: #6a11cb;
            --secondary-color: #2575fc;
            --accent-color: #30cfd0;
            --text-color: #e0e0e0;
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        @keyframes gradient-animation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            background: linear-gradient(-45deg, var(--primary-color), var(--secondary-color), #23a6d5, #23d5ab);
            background-size: 400% 400%;
            animation: gradient-animation 15s ease infinite;
        }

        .auth-card {
            background: var(--glass-bg);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            animation: fadeInUp 0.8s ease-out forwards;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-card .logo {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            background: -webkit-linear-gradient(45deg, var(--accent-color), #fff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 15px rgba(48, 207, 208, 0.4);
        }

        .auth-card h2 {
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .input-group {
            position: relative;
        }
        
        .form-control {
            background: transparent;
            border: none;
            border-bottom: 1px solid var(--glass-border);
            border-radius: 0;
            color: var(--text-color);
            padding-left: 2.5rem;
            transition: all 0.3s ease;
        }

        .form-control::placeholder { color: rgba(255, 255, 255, 0.6); }
        .form-control:focus {
            background: transparent;
            color: white;
            box-shadow: none;
            border-bottom-color: var(--accent-color);
        }

        .input-group-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.7);
            transition: color 0.3s ease;
        }
        .form-control:focus ~ .input-group-icon {
            color: var(--accent-color);
        }
        
        .btn-aurora {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 0.8rem;
            font-weight: 600;
            color: #fff;
            transition: all 0.4s ease;
            box-shadow: 0 4px 15px 0 rgba(0, 0, 0, 0.2);
            background-size: 200% auto;
        }

        .btn-aurora:hover {
            background-position: right center;
            transform: scale(1.05);
            box-shadow: 0 6px 20px 0 rgba(0, 0, 0, 0.3);
        }

        .auth-link {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .auth-link:hover {
            color: #fff;
            text-decoration: underline;
        }

        .alert-custom {
            background-color: rgba(217, 83, 79, 0.2);
            border: 1px solid rgba(217, 83, 79, 0.4);
            color: #f8d7da;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="auth-card">
                    <div class="text-center mb-4">
                        <i class="fas fa-graduation-cap logo"></i>
                        <h2 class="text-center">Bienvenue</h2>
                    </div>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-custom text-center mb-4" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">
                        <div class="mb-4 input-group">
                            <input type="email" class="form-control" name="email" placeholder="Adresse e-mail" required>
                            <span class="input-group-icon"><i class="fas fa-envelope"></i></span>
                        </div>
                        <div class="mb-4 input-group">
                            <input type="password" class="form-control" name="password" placeholder="Mot de passe" required>
                            <span class="input-group-icon"><i class="fas fa-lock"></i></span>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-aurora">Se Connecter</button>
                        </div>
                        <div class="text-center mt-4">
                            <a href="password_reset.php" class="auth-link">Mot de passe oublié ?</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>