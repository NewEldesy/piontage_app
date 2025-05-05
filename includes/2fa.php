<?php
/**
 * Fonctions pour l'authentification à deux facteurs (2FA)
 */

/**
 * Génère une clé secrète pour l'authentification à deux facteurs
 * @return string La clé secrète générée
 */
function generate2FASecret() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 caractères
    $secret = '';
    
    // Générer une clé de 16 caractères (80 bits comme recommandé)
    for ($i = 0; $i < 16; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    
    return $secret;
}

/**
 * Génère l'URL pour le QR code à scanner avec l'application d'authentification
 * @param string $secret La clé secrète
 * @param string $username Le nom d'utilisateur
 * @param string $appName Le nom de l'application
 * @return string L'URL pour le QR code
 */
function getQRCodeUrl($secret, $username, $appName = 'PointageApp') {
    $appName = urlencode($appName);
    $username = urlencode($username);
    
    return "otpauth://totp/{$appName}:{$username}?secret={$secret}&issuer={$appName}";
}

/**
 * Génère un code TOTP (Time-based One-Time Password) basé sur la clé secrète
 * @param string $secret La clé secrète
 * @param int $timeSlice Le créneau horaire (par défaut 30 secondes)
 * @return string Le code TOTP à 6 chiffres
 */
function generateTOTP($secret, $timeSlice = null) {
    // Convertir la clé secrète de base32 en binaire
    $secret = base32Decode($secret);
    
    // Définir le créneau horaire (30 secondes par défaut)
    if ($timeSlice === null) {
        $timeSlice = floor(time() / 30);
    }
    
    // Convertir le créneau horaire en binaire (8 octets)
    $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
    
    // Générer le hachage HMAC-SHA1
    $hash = hash_hmac('sha1', $time, $secret, true);
    
    // Extraire 4 octets du hachage en fonction du dernier octet
    $offset = ord(substr($hash, -1)) & 0x0F;
    $binary = (ord(substr($hash, $offset)) & 0x7F) << 24
            | (ord(substr($hash, $offset + 1)) & 0xFF) << 16
            | (ord(substr($hash, $offset + 2)) & 0xFF) << 8
            | (ord(substr($hash, $offset + 3)) & 0xFF);
    
    // Générer le code à 6 chiffres
    $code = $binary % 1000000;
    
    // Ajouter des zéros au début si nécessaire
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

/**
 * Vérifie si un code TOTP est valide
 * @param string $secret La clé secrète
 * @param string $code Le code à vérifier
 * @param int $discrepancy Le nombre de créneaux horaires à vérifier avant et après le créneau actuel
 * @return bool True si le code est valide, false sinon
 */
function verifyTOTP($secret, $code, $discrepancy = 1) {
    // Vérifier le code pour le créneau horaire actuel et les créneaux adjacents
    $currentTimeSlice = floor(time() / 30);
    
    for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
        $calculatedCode = generateTOTP($secret, $currentTimeSlice + $i);
        if ($calculatedCode == $code) {
            return true;
        }
    }
    
    return false;
}

/**
 * Décode une chaîne encodée en base32
 * @param string $base32 La chaîne encodée en base32
 * @return string La chaîne décodée
 */
function base32Decode($base32) {
    $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32charsFlipped = array_flip(str_split($base32chars));
    $paddingCharCount = substr_count($base32, '=');
    $allowedValues = [6, 4, 3, 1, 0];
    
    if (!in_array($paddingCharCount, $allowedValues)) {
        return false;
    }
    
    for ($i = 0; $i < $paddingCharCount; $i++) {
        if (substr($base32, -($i + 1), 1) != '=') {
            return false;
        }
    }
    
    $base32 = str_replace('=', '', $base32);
    $base32 = str_split($base32);
    $binaryString = '';
    
    for ($i = 0; $i < count($base32); $i += 8) {
        $x = '';
        
        for ($j = 0; $j < 8; $j++) {
            $x .= str_pad(base_convert(@$base32charsFlipped[@$base32[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
        }
        
        $eightBits = str_split($x, 8);
        
        for ($z = 0; $z < count($eightBits); $z++) {
            $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
        }
    }
    
    return $binaryString;
}

/**
 * Génère une URL pour un QR code Google Charts
 * @param string $data Les données à encoder dans le QR code
 * @param int $size La taille du QR code en pixels
 * @return string L'URL du QR code
 */
function getGoogleQRCodeUrl($data, $size = 200) {
    return 'https://chart.googleapis.com/chart?chs=' . $size . 'x' . $size . '&chld=M|0&cht=qr&chl=' . urlencode($data);
}

/**
 * Génère des codes de récupération pour l'utilisateur
 * @param int $count Le nombre de codes à générer
 * @param int $length La longueur de chaque code
 * @return array Les codes de récupération générés
 */
function generateRecoveryCodes($count = 8, $length = 10) {
    $codes = [];
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    for ($i = 0; $i < $count; $i++) {
        $code = '';
        for ($j = 0; $j < $length; $j++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $codes[] = $code;
    }
    
    return $codes;
}

/**
 * Vérifie si un code de récupération est valide pour un utilisateur
 * @param PDO $pdo La connexion à la base de données
 * @param int $userId L'ID de l'utilisateur
 * @param string $code Le code de récupération à vérifier
 * @return bool True si le code est valide, false sinon
 */
function verifyRecoveryCode($pdo, $userId, $code) {
    $stmt = $pdo->prepare("SELECT id, code FROM recovery_codes WHERE user_id = ? AND code = ? AND used = 0");
    $stmt->execute([$userId, $code]);
    
    if ($row = $stmt->fetch()) {
        // Marquer le code comme utilisé
        $updateStmt = $pdo->prepare("UPDATE recovery_codes SET used = 1, used_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->execute([$row['id']]);
        
        return true;
    }
    
    return false;
}

/**
 * Enregistre les codes de récupération pour un utilisateur
 * @param PDO $pdo La connexion à la base de données
 * @param int $userId L'ID de l'utilisateur
 * @param array $codes Les codes de récupération à enregistrer
 * @return bool True si les codes ont été enregistrés avec succès, false sinon
 */
function saveRecoveryCodes($pdo, $userId, $codes) {
    try {
        // Supprimer les anciens codes de récupération
        $stmt = $pdo->prepare("DELETE FROM recovery_codes WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Insérer les nouveaux codes
        $stmt = $pdo->prepare("INSERT INTO recovery_codes (user_id, code) VALUES (?, ?)");
        
        foreach ($codes as $code) {
            $stmt->execute([$userId, $code]);
        }
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Obtient les codes de récupération non utilisés d'un utilisateur
 * @param PDO $pdo La connexion à la base de données
 * @param int $userId L'ID de l'utilisateur
 * @return array Les codes de récupération non utilisés
 */
function getUnusedRecoveryCodes($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT code FROM recovery_codes WHERE user_id = ? AND used = 0");
    $stmt->execute([$userId]);
    
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}