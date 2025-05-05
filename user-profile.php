<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/2fa.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    setAlert('Veuillez vous connecter pour accéder à cette page.', 'error');
    redirect('login.php');
}

$userId = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    setAlert('Utilisateur non trouvé.', 'error');
    redirect('dashboard.php');
}

$error = '';
$success = '';
$qrCodeUrl = '';
$secret = '';
$recoveryCodes = [];
$showQRCode = false;
$showRecoveryCodes = false;

// Traitement de l'activation/désactivation de l'authentification à deux facteurs
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Activation de l'authentification à deux facteurs
    if (isset($_POST['enable_2fa'])) {
        // Générer une nouvelle clé secrète
        $secret = generate2FASecret();
        $showQRCode = true;
        
        // Stocker temporairement la clé secrète en session
        $_SESSION['temp_2fa_secret'] = $secret;
    }
    // Vérification du code pour activer l'authentification à deux facteurs
    elseif (isset($_POST['verify_2fa'])) {
        $code = sanitize($_POST['code']);
        $secret = $_SESSION['temp_2fa_secret'] ?? '';
        
        if (empty($code)) {
            $error = 'Veuillez entrer le code d\'authentification.';
        } elseif (empty($secret)) {
            $error = 'Erreur: clé secrète manquante. Veuillez réessayer.';
        } elseif (verifyTOTP($secret, $code)) {
            // Code valide, activer l'authentification à deux facteurs
            try {
                // Mettre à jour l'utilisateur
                $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ?");
                $stmt->execute([$secret, $userId]);
                
                // Générer des codes de récupération
                $recoveryCodes = generateRecoveryCodes();
                saveRecoveryCodes($pdo, $userId, $recoveryCodes);
                
                // Afficher les codes de récupération
                $showRecoveryCodes = true;
                $success = 'L\'authentification à deux facteurs a été activée avec succès.';
                
                // Supprimer la clé secrète temporaire
                unset($_SESSION['temp_2fa_secret']);
            } catch (PDOException $e) {
                $error = 'Une erreur est survenue lors de l\'activation de l\'authentification à deux facteurs: ' . $e->getMessage();
            }
        } else {
            $error = 'Code d\'authentification invalide. Veuillez réessayer.';
            $showQRCode = true; // Afficher à nouveau le QR code
        }
    }
    // Désactivation de l'authentification à deux facteurs
    elseif (isset($_POST['disable_2fa'])) {
        $code = sanitize($_POST['code']);
        
        if (empty($code)) {
            $error = 'Veuillez entrer le code d\'authentification.';
        } elseif (verifyTOTP($user['two_factor_secret'], $code)) {
            // Code valide, désactiver l'authentification à deux facteurs
            try {
                // Mettre à jour l'utilisateur
                $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?");
                $stmt->execute([$userId]);
                
                // Supprimer les codes de récupération
                $stmt = $pdo->prepare("DELETE FROM recovery_codes WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                $success = 'L\'authentification à deux facteurs a été désactivée avec succès.';
                
                // Mettre à jour les informations de l'utilisateur
                $user['two_factor_enabled'] = 0;
                $user['two_factor_secret'] = null;
            } catch (PDOException $e) {
                $error = 'Une erreur est survenue lors de la désactivation de l\'authentification à deux facteurs: ' . $e->getMessage();
            }
        } else {
            $error = 'Code d\'authentification invalide. Veuillez réessayer.';
        }
    }
    // Régénérer les codes de récupération
    elseif (isset($_POST['regenerate_codes'])) {
        $code = sanitize($_POST['code']);
        
        if (empty($code)) {
            $error = 'Veuillez entrer le code d\'authentification.';
        } elseif (verifyTOTP($user['two_factor_secret'], $code)) {
            // Code valide, régénérer les codes de récupération
            try {
                // Générer de nouveaux codes de récupération
                $recoveryCodes = generateRecoveryCodes();
                saveRecoveryCodes($pdo, $userId, $recoveryCodes);
                
                // Afficher les nouveaux codes de récupération
                $showRecoveryCodes = true;
                $success = 'Les codes de récupération ont été régénérés avec succès.';
            } catch (PDOException $e) {
                $error = 'Une erreur est survenue lors de la régénération des codes de récupération: ' . $e->getMessage();
            }
        } else {
            $error = 'Code d\'authentification invalide. Veuillez réessayer.';
        }
    }
    // Mise à jour du profil
    elseif (isset($_POST['update_profile'])) {
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (empty($name) || empty($email)) {
            $error = 'Le nom et l\'email sont obligatoires.';
        } elseif (!empty($newPassword) && $newPassword !== $confirmPassword) {
            $error = 'Les nouveaux mots de passe ne correspondent pas.';
        } elseif (!empty($newPassword) && !password_verify($currentPassword, $user['password'])) {
            $error = 'Le mot de passe actuel est incorrect.';
        } else {
            try {
                // Mettre à jour le profil
                if (!empty($newPassword)) {
                    // Mettre à jour avec le nouveau mot de passe
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $hashedPassword, $userId]);
                } else {
                    // Mettre à jour sans changer le mot de passe
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $userId]);
                }
                
                $success = 'Votre profil a été mis à jour avec succès.';
                
                // Mettre à jour les informations de l'utilisateur
                $user['name'] = $name;
                $user['email'] = $email;
                
                // Mettre à jour le nom en session
                $_SESSION['user_name'] = $name;
            } catch (PDOException $e) {
                $error = 'Une erreur est survenue lors de la mise à jour du profil: ' . $e->getMessage();
            }
        }
    }
}

// Préparer l'URL du QR code si nécessaire
if ($showQRCode && !empty($secret)) {
    $qrCodeUrl = getGoogleQRCodeUrl(getQRCodeUrl($secret, $user['username']));
}

// Récupérer les codes de récupération existants si l'authentification à deux facteurs est activée
if ($user['two_factor_enabled'] && !$showRecoveryCodes) {
    $existingCodes = getUnusedRecoveryCodes($pdo, $userId);
    if (count($existingCodes) > 0) {
        $recoveryCodes = $existingCodes;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Utilisateur - PointageApp</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <section class="page-header">
                <div class="header-content">
                    <h1>Profil Utilisateur</h1>
                    <p>Gérez vos informations personnelles et paramètres de sécurité</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                    </a>
                </div>
            </section>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <section class="profile-section">
                <div class="tabs">
                    <button class="tab-btn active" data-tab="profile">Informations personnelles</button>
                    <button class="tab-btn" data-tab="security">Sécurité</button>
                </div>
                
                <div class="tab-content active" id="profile">
                    <div class="card">
                        <div class="card-header">
                            <h3>Informations personnelles</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" class="form">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="username">Nom d'utilisateur</label>
                                        <input type="text" id="username" value="<?php echo $user['username']; ?>" disabled readonly>
                                        <p class="form-help">Le nom d'utilisateur ne peut pas être modifié.</p>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="name">Nom complet</label>
                                        <input type="text" id="name" name="name" value="<?php echo $user['name']; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="role">Rôle</label>
                                        <input type="text" id="role" value="<?php echo ucfirst($user['role']); ?>" disabled readonly>
                                    </div>
                                </div>
                                
                                <div class="form-divider">
                                    <h4>Changer de mot de passe</h4>
                                    <p>Laissez ces champs vides si vous ne souhaitez pas changer de mot de passe.</p>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="current_password">Mot de passe actuel</label>
                                        <input type="password" id="current_password" name="current_password">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="new_password">Nouveau mot de passe</label>
                                        <input type="password" id="new_password" name="new_password">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                                        <input type="password" id="confirm_password" name="confirm_password">
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" name="update_profile" class="btn btn-primary">Enregistrer les modifications</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="tab-content" id="security">
                    <div class="card">
                        <div class="card-header">
                            <h3>Authentification à deux facteurs (2FA)</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($showQRCode): ?>
                                <!-- Affichage du QR code pour l'activation -->
                                <div class="qr-code-container">
                                    <h4>Scannez ce QR code avec votre application d'authentification</h4>
                                    <p>Utilisez une application comme Google Authenticator, Authy ou Microsoft Authenticator.</p>
                                    
                                    <div class="qr-code">
                                        <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code">
                                    </div>
                                    
                                    <div class="secret-key">
                                        <p>Si vous ne pouvez pas scanner le QR code, entrez cette clé manuellement :</p>
                                        <code><?php echo $secret; ?></code>
                                    </div>
                                    
                                    <form method="POST" action="" class="form">
                                        <div class="form-group">
                                            <label for="code">Code de vérification</label>
                                            <input type="text" id="code" name="code" placeholder="Entrez le code à 6 chiffres" required>
                                            <p class="form-help">Entrez le code généré par votre application d'authentification.</p>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="submit" name="verify_2fa" class="btn btn-primary">Vérifier et activer</button>
                                        </div>
                                    </form>
                                </div>
                            <?php elseif ($showRecoveryCodes): ?>
                                <!-- Affichage des codes de récupération -->
                                <div class="recovery-codes-container">
                                    <h4>Vos codes de récupération</h4>
                                    <p>Conservez ces codes dans un endroit sûr. Ils vous permettront de vous connecter si vous perdez l'accès à votre application d'authentification.</p>
                                    
                                    <div class="recovery-codes">
                                        <?php foreach ($recoveryCodes as $code): ?>
                                            <code><?php echo $code; ?></code>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="recovery-codes-actions">
                                        <button onclick="printRecoveryCodes()" class="btn btn-outline">
                                            <i class="fas fa-print"></i> Imprimer
                                        </button>
                                        <button onclick="copyRecoveryCodes()" class="btn btn-outline">
                                            <i class="fas fa-copy"></i> Copier
                                        </button>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <a href="user-profile.php" class="btn btn-primary">J'ai sauvegardé mes codes</a>
                                    </div>
                                </div>
                            <?php elseif ($user['two_factor_enabled']): ?>
                                <!-- 2FA est activé -->
                                <div class="two-factor-status enabled">
                                    <div class="status-icon">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <div class="status-content">
                                        <h4>L'authentification à deux facteurs est activée</h4>
                                        <p>Votre compte est protégé par une couche de sécurité supplémentaire.</p>
                                    </div>
                                </div>
                                
                                <div class="two-factor-actions">
                                    <button class="btn btn-outline" id="showDisable2FABtn">
                                        <i class="fas fa-toggle-off"></i> Désactiver l'authentification à deux facteurs
                                    </button>
                                    
                                    <button class="btn btn-outline" id="showRegenerateCodesBtn">
                                        <i class="fas fa-sync"></i> Régénérer les codes de récupération
                                    </button>
                                </div>
                                
                                <!-- Formulaire de désactivation (caché par défaut) -->
                                <div class="two-factor-form" id="disable2FAForm" style="display: none;">
                                    <form method="POST" action="" class="form">
                                        <h4>Désactiver l'authentification à deux facteurs</h4>
                                        <p>Pour désactiver l'authentification à deux facteurs, veuillez entrer un code de votre application d'authentification.</p>
                                        
                                        <div class="form-group">
                                            <label for="disable_code">Code de vérification</label>
                                            <input type="text" id="disable_code" name="code" placeholder="Entrez le code à 6 chiffres" required>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="button" class="btn btn-outline" onclick="hideDisable2FAForm()">Annuler</button>
                                            <button type="submit" name="disable_2fa" class="btn btn-danger">Désactiver</button>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Formulaire de régénération des codes (caché par défaut) -->
                                <div class="two-factor-form" id="regenerateCodesForm" style="display: none;">
                                    <form method="POST" action="" class="form">
                                        <h4>Régénérer les codes de récupération</h4>
                                        <p>Pour régénérer vos codes de récupération, veuillez entrer un code de votre application d'authentification.</p>
                                        
                                        <div class="form-group">
                                            <label for="regenerate_code">Code de vérification</label>
                                            <input type="text" id="regenerate_code" name="code" placeholder="Entrez le code à 6 chiffres" required>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="button" class="btn btn-outline" onclick="hideRegenerateCodesForm()">Annuler</button>
                                            <button type="submit" name="regenerate_codes" class="btn btn-warning">Régénérer</button>
                                        </div>
                                    </form>
                                </div>
                                
                                <?php if (count($recoveryCodes) > 0): ?>
                                    <div class="recovery-codes-preview">
                                        <h4>Vos codes de récupération</h4>
                                        <p>Vous avez <?php echo count($recoveryCodes); ?> codes de récupération non utilisés.</p>
                                        <div class="recovery-codes">
                                            <?php foreach ($recoveryCodes as $code): ?>
                                                <code><?php echo $code; ?></code>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- 2FA n'est pas activé -->
                                <div class="two-factor-status disabled">
                                    <div class="status-icon">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <div class="status-content">
                                        <h4>L'authentification à deux facteurs est désactivée</h4>
                                        <p>Activez l'authentification à deux facteurs pour renforcer la sécurité de votre compte.</p>
                                    </div>
                                </div>
                                
                                <div class="two-factor-info">
                                    <h4>Comment ça marche ?</h4>
                                    <ol>
                                        <li>Vous activez l'authentification à deux facteurs</li>
                                        <li>Vous scannez un QR code avec une application d'authentification (Google Authenticator, Authy, etc.)</li>
                                        <li>Vous recevez des codes de récupération à conserver en lieu sûr</li>
                                        <li>Lors de la connexion, vous devrez entrer un code généré par votre application en plus de votre mot de passe</li>
                                    </ol>
                                </div>
                                
                                <form method="POST" action="" class="form">
                                    <div class="form-actions">
                                        <button type="submit" name="enable_2fa" class="btn btn-primary">
                                            <i class="fas fa-shield-alt"></i> Activer l'authentification à deux facteurs
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>

    <script>
        // Fonctions pour afficher/masquer les formulaires
        function showDisable2FAForm() {
            document.getElementById('disable2FAForm').style.display = 'block';
            document.getElementById('regenerateCodesForm').style.display = 'none';
        }
        
        function hideDisable2FAForm() {
            document.getElementById('disable2FAForm').style.display = 'none';
        }
        
        function showRegenerateCodesForm() {
            document.getElementById('regenerateCodesForm').style.display = 'block';
            document.getElementById('disable2FAForm').style.display = 'none';
        }
        
        function hideRegenerateCodesForm() {
            document.getElementById('regenerateCodesForm').style.display = 'none';
        }
        
        // Fonction pour imprimer les codes de récupération
        function printRecoveryCodes() {
            const codesContainer = document.querySelector('.recovery-codes');
            const printWindow = window.open('', '_blank');
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>Codes de récupération - PointageApp</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        h1 { font-size: 18px; margin-bottom: 20px; }
                        .codes { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
                        .code { font-family: monospace; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
                    </style>
                </head>
                <body>
                    <h1>Codes de récupération pour PointageApp</h1>
                    <div class="codes">
                        ${Array.from(codesContainer.querySelectorAll('code')).map(code => 
                            `<div class="code">${code.textContent}</div>`
                        ).join('')}
                    </div>
                    <p style="margin-top: 20px;">Conservez ces codes dans un endroit sûr.</p>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }
        
        // Fonction pour copier les codes de récupération
        function copyRecoveryCodes() {
            const codes = Array.from(document.querySelectorAll('.recovery-codes code')).map(code => code.textContent).join('\n');
            
            navigator.clipboard.writeText(codes).then(() => {
                alert('Les codes de récupération ont été copiés dans le presse-papiers.');
            }).catch(err => {
                console.error('Erreur lors de la copie des codes:', err);
                alert('Impossible de copier les codes. Veuillez les copier manuellement.');
            });
        }
        
        // Initialisation des événements
        document.addEventListener('DOMContentLoaded', function() {
            const showDisable2FAButton = document.getElementById('showDisable2FABtn');
            const showRegenerateCodesButton = document.getElementById('showRegenerateCodesBtn');
            
            if (showDisable2FAButton) {
                showDisable2FAButton.addEventListener('click', showDisable2FAForm);
            }
            
            if (showRegenerateCodesButton) {
                showRegenerateCodesButton.addEventListener('click', showRegenerateCodesForm);
            }
        });
    </script>
    <script src="assets/js/script.js"></script>
</body>
</html>