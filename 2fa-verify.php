<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/2fa.php';

// Vérifier si l'utilisateur a passé la première étape d'authentification
if (!isset($_SESSION['temp_user_id'])) {
    setAlert('Veuillez vous connecter d\'abord.', 'error');
    redirect('login.php');
}

$error = '';
$showRecoveryForm = isset($_GET['recovery']) && $_GET['recovery'] == 1;

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['temp_user_id']]);
$user = $stmt->fetch();

if (!$user || !$user['two_factor_enabled']) {
    // L'utilisateur n'existe pas ou n'a pas activé l'authentification à deux facteurs
    session_destroy();
    setAlert('Une erreur est survenue. Veuillez vous reconnecter.', 'error');
    redirect('login.php');
}

// Traitement du formulaire de vérification 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_code'])) {
        $code = sanitize($_POST['code']);
        
        if (empty($code)) {
            $error = 'Veuillez entrer le code d\'authentification.';
        } elseif (verifyTOTP($user['two_factor_secret'], $code)) {
            // Code valide, connexion réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            
            // Supprimer les variables temporaires
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['temp_user_name']);
            
            setAlert('Connexion réussie. Bienvenue ' . $user['name'] . '!', 'success');
            redirect('dashboard.php');
        } else {
            $error = 'Code d\'authentification invalide. Veuillez réessayer.';
        }
    } elseif (isset($_POST['verify_recovery'])) {
        $recoveryCode = sanitize($_POST['recovery_code']);
        
        if (empty($recoveryCode)) {
            $error = 'Veuillez entrer un code de récupération.';
        } elseif (verifyRecoveryCode($pdo, $user['id'], $recoveryCode)) {
            // Code de récupération valide, connexion réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            
            // Supprimer les variables temporaires
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['temp_user_name']);
            
            setAlert('Connexion réussie avec un code de récupération. Pensez à configurer de nouveaux codes de récupération.', 'warning');
            redirect('dashboard.php');
        } else {
            $error = 'Code de récupération invalide. Veuillez réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification à deux facteurs - PointageApp</title>
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
                        <i class="fas fa-shield-alt"></i>
                        <h1>Vérification à deux facteurs</h1>
                        <p><?php echo $showRecoveryForm ? 'Entrez un code de récupération' : 'Entrez le code de votre application d\'authentification'; ?></p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($showRecoveryForm): ?>
                        <!-- Formulaire de code de récupération -->
                        <form class="login-form" method="POST" action="">
                            <div class="form-group">
                                <label for="recovery_code">Code de récupération</label>
                                <input type="text" id="recovery_code" name="recovery_code" placeholder="Entrez un code de récupération" required autofocus>
                                <p class="form-help">Entrez l'un de vos codes de récupération à 10 caractères.</p>
                            </div>
                            
                            <button type="submit" name="verify_recovery" class="btn btn-primary btn-block">Vérifier</button>
                            
                            <div class="login-footer">
                                <a href="2fa-verify.php">Utiliser l'application d'authentification</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- Formulaire de code d'authentification -->
                        <form class="login-form" method="POST" action="">
                            <div class="form-group">
                                <label for="code">Code d'authentification</label>
                                <input type="text" id="code" name="code" placeholder="Entrez le code à 6 chiffres" required autofocus>
                                <p class="form-help">Ouvrez votre application d'authentification pour obtenir le code.</p>
                            </div>
                            
                            <button type="submit" name="verify_code" class="btn btn-primary btn-block">Vérifier</button>
                            
                            <div class="login-footer">
                                <a href="2fa-verify.php?recovery=1">Utiliser un code de récupération</a>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <div class="login-cancel">
                        <a href="logout.php" class="btn btn-outline btn-block">Annuler</a>
                    </div>
                </div>
            </section>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>