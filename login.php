<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        // Vérifier les identifiants
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Connexion réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            
            setAlert('Connexion réussie. Bienvenue ' . $user['name'] . '!', 'success');
            redirect('dashboard.php');
        } else {
            $error = 'Identifiants incorrects.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - PointageApp</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <section class="login-section">
                <div class="login-container">
                    <div class="login-header">
                        <i class="fas fa-clock"></i>
                        <h1>Connexion</h1>
                        <p>Entrez vos identifiants pour accéder à votre compte</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form class="login-form" method="POST" action="">
                        <div class="form-group">
                            <label for="username">Nom d'utilisateur</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Mot de passe</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
                        
                        <div class="login-footer">
                            <a href="#">Mot de passe oublié?</a>
                        </div>
                    </form>
                </div>
            </section>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>