<?php
/**
 * Configuration de la base de données SQLite
 */

// Chemin vers le fichier de base de données SQLite
$db_path = __DIR__ . '/../database/pointage.db';

// Création de la connexion PDO à SQLite
try {
    $pdo = new PDO('sqlite:' . $db_path);
    // Configuration pour que PDO lance des exceptions en cas d'erreur
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Configuration pour que PDO retourne des tableaux associatifs
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Activer les clés étrangères
    $pdo->exec('PRAGMA foreign_keys = ON;');
} catch (PDOException $e) {
    // En cas d'erreur, afficher un message et arrêter le script
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

/**
 * Fonction pour initialiser la base de données si elle n'existe pas
 */
function initializeDatabase($pdo) {
    // Vérifier si les tables existent déjà
    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    if (!$result->fetch()) {
        // Créer la table des utilisateurs (administrateurs et gestionnaires)
        $pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            role TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Créer la table des employés
        $pdo->exec("CREATE TABLE employees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            department TEXT NOT NULL,
            position TEXT NOT NULL,
            email TEXT,
            phone TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Créer la table des pointages
        $pdo->exec("CREATE TABLE attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id)
        )");

        // Insérer un utilisateur administrateur par défaut
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password, name, email, role) 
                    VALUES ('admin', '$hashedPassword', 'Administrateur', 'admin@example.com', 'admin')");

        // Insérer quelques employés de démonstration
        $pdo->exec("INSERT INTO employees (employee_id, name, department, position) 
                    VALUES 
                    ('EMP001', 'Jean Dupont', 'Informatique', 'Développeur'),
                    ('EMP002', 'Marie Martin', 'Ressources Humaines', 'Responsable RH'),
                    ('EMP003', 'Pierre Leroy', 'Marketing', 'Chef de projet'),
                    ('EMP004', 'Sophie Bernard', 'Finance', 'Comptable'),
                    ('EMP005', 'Thomas Petit', 'Informatique', 'Administrateur système')");

        // Insérer quelques pointages de démonstration
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $pdo->exec("INSERT INTO attendance (employee_id, action, timestamp) 
                    VALUES 
                    (1, 'entry', '$today 08:15:00'),
                    (2, 'entry', '$today 08:30:00'),
                    (4, 'entry', '$today 09:00:00'),
                    (5, 'entry', '$today 08:45:00'),
                    (3, 'exit', '$yesterday 17:45:00'),
                    (1, 'exit', '$yesterday 17:30:00'),
                    (2, 'exit', '$yesterday 17:00:00'),
                    (4, 'exit', '$yesterday 18:00:00'),
                    (5, 'exit', '$yesterday 18:15:00')");
    }
}

// Vérifier si le répertoire de la base de données existe, sinon le créer
if (!file_exists(dirname($db_path))) {
    mkdir(dirname($db_path), 0755, true);
}

// Initialiser la base de données si nécessaire
initializeDatabase($pdo);
