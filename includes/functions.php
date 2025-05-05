<?php
/**
 * Fonctions utilitaires pour l'application
 */

/**
 * Vérifie si l'utilisateur est connecté
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur a un rôle spécifique
 * @param string $role Le rôle à vérifier
 * @return bool
 */
function hasRole($role) {
    return isLoggedIn() && $_SESSION['user_role'] === $role;
}

/**
 * Redirige vers une page spécifique
 * @param string $page La page de destination
 */
function redirect($page) {
    header("Location: $page");
    exit;
}

/**
 * Affiche un message d'alerte
 * @param string $message Le message à afficher
 * @param string $type Le type d'alerte (success, error, warning, info)
 */
function setAlert($message, $type = 'info') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Récupère et supprime le message d'alerte
 * @return array|null Le message d'alerte ou null s'il n'y en a pas
 */
function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

/**
 * Sécurise les données entrées par l'utilisateur
 * @param string $data Les données à sécuriser
 * @return string Les données sécurisées
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Génère un identifiant unique pour un employé
 * @param PDO $pdo La connexion à la base de données
 * @return string L'identifiant généré
 */
function generateEmployeeId($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees");
    $result = $stmt->fetch();
    $count = $result['count'] + 1;
    return 'EMP' . str_pad($count, 3, '0', STR_PAD_LEFT);
}

/**
 * Vérifie si un employé est présent (a pointé en entrée mais pas en sortie)
 * @param PDO $pdo La connexion à la base de données
 * @param int $employeeId L'identifiant de l'employé
 * @return bool True si l'employé est présent, false sinon
 */
function isEmployeePresent($pdo, $employeeId) {
    $stmt = $pdo->prepare("
        SELECT a1.id, a1.action
        FROM attendance a1
        LEFT JOIN attendance a2 ON a1.employee_id = a2.employee_id 
            AND a1.timestamp < a2.timestamp 
            AND DATE(a1.timestamp) = DATE(a2.timestamp)
        WHERE a1.employee_id = ? 
        AND DATE(a1.timestamp) = DATE('now')
        ORDER BY a1.timestamp DESC
        LIMIT 1
    ");
    $stmt->execute([$employeeId]);
    $result = $stmt->fetch();
    
    return $result && $result['action'] === 'entry';
}

/**
 * Obtient la dernière action d'un employé
 * @param PDO $pdo La connexion à la base de données
 * @param int $employeeId L'identifiant de l'employé
 * @return array|null Les informations sur la dernière action ou null
 */
function getLastAction($pdo, $employeeId) {
    $stmt = $pdo->prepare("
        SELECT action, timestamp
        FROM attendance
        WHERE employee_id = ?
        ORDER BY timestamp DESC
        LIMIT 1
    ");
    $stmt->execute([$employeeId]);
    return $stmt->fetch();
}

/**
 * Formate une date/heure
 * @param string $timestamp La date/heure à formater
 * @param string $format Le format souhaité
 * @return string La date/heure formatée
 */
function formatDateTime($timestamp, $format = 'd/m/Y H:i') {
    return date($format, strtotime($timestamp));
}

/**
 * Obtient les statistiques de présence
 * @param PDO $pdo La connexion à la base de données
 * @return array Les statistiques
 */
function getAttendanceStats($pdo) {
    // Nombre total d'employés
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees");
    $totalEmployees = $stmt->fetch()['total'];
    
    // Nombre d'employés présents aujourd'hui
    $presentEmployees = 0;
    $stmt = $pdo->query("SELECT id FROM employees");
    while ($row = $stmt->fetch()) {
        if (isEmployeePresent($pdo, $row['id'])) {
            $presentEmployees++;
        }
    }
    
    // Nombre d'entrées aujourd'hui
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM attendance 
        WHERE action = 'entry' 
        AND DATE(timestamp) = DATE('now')
    ");
    $entriesCount = $stmt->fetch()['count'];
    
    // Nombre de sorties aujourd'hui
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM attendance 
        WHERE action = 'exit' 
        AND DATE(timestamp) = DATE('now')
    ");
    $exitsCount = $stmt->fetch()['count'];
    
    return [
        'total' => $totalEmployees,
        'present' => $presentEmployees,
        'absent' => $totalEmployees - $presentEmployees,
        'entries' => $entriesCount,
        'exits' => $exitsCount
    ];
}

/**
 * Obtient les activités récentes
 * @param PDO $pdo La connexion à la base de données
 * @param int $limit Le nombre d'activités à récupérer
 * @return array Les activités récentes
 */
function getRecentActivities($pdo, $limit = 5) {
    $stmt = $pdo->prepare("
        SELECT a.id, a.action, a.timestamp, e.id as employee_id, e.employee_id as emp_code, e.name, e.department
        FROM attendance a
        JOIN employees e ON a.employee_id = e.id
        ORDER BY a.timestamp DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}