<?php
/**
 * Script pour créer et initialiser la base de données SQLite
 * 
 * Ce script peut être exécuté directement pour créer ou recréer la base de données
 */

// Chemin vers le fichier de base de données SQLite
$db_path = __DIR__ . '/database/pointage.db';
$db_dir = dirname($db_path);

// Afficher un message de début
echo "=== Création de la base de données PointageApp ===\n";

// Vérifier si le répertoire de la base de données existe, sinon le créer
if (!file_exists($db_dir)) {
    echo "Création du répertoire database...\n";
    if (mkdir($db_dir, 0755, true)) {
        echo "Répertoire database créé avec succès.\n";
    } else {
        die("Erreur: Impossible de créer le répertoire database.\n");
    }
} else {
    echo "Le répertoire database existe déjà.\n";
}

// Vérifier si le fichier de base de données existe déjà
if (file_exists($db_path)) {
    echo "Attention: Le fichier de base de données existe déjà.\n";
    echo "Voulez-vous le supprimer et créer une nouvelle base de données? (y/n): ";
    
    // Si le script est exécuté en ligne de commande
    if (php_sapi_name() === 'cli') {
        $answer = trim(fgets(STDIN));
    } 
    // Si le script est exécuté dans un navigateur
    else {
        // Nous simulons une réponse "y" pour continuer automatiquement
        echo "Exécution dans un navigateur, suppression automatique de la base existante.\n";
        $answer = 'y';
    }
    
    if (strtolower($answer) === 'y') {
        echo "Suppression de l'ancienne base de données...\n";
        if (unlink($db_path)) {
            echo "Base de données supprimée avec succès.\n";
        } else {
            die("Erreur: Impossible de supprimer l'ancienne base de données.\n");
        }
    } else {
        echo "Opération annulée. La base de données existante n'a pas été modifiée.\n";
        exit;
    }
}

// Création de la connexion PDO à SQLite
try {
    echo "Création de la connexion à la base de données...\n";
    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connexion établie avec succès.\n";
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage() . "\n");
}

// Activer les clés étrangères
echo "Activation des clés étrangères...\n";
$pdo->exec('PRAGMA foreign_keys = ON;');

// Créer les tables
echo "Création des tables...\n";

try {
    // Table des utilisateurs (administrateurs et gestionnaires)
    echo "- Création de la table 'users'...\n";
    $pdo->exec("CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        role TEXT NOT NULL,
        two_factor_enabled INTEGER DEFAULT 0,
        two_factor_secret TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Table des codes de récupération
    echo "- Création de la table 'recovery_codes'...\n";
    $pdo->exec("CREATE TABLE recovery_codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        code TEXT NOT NULL,
        used INTEGER DEFAULT 0,
        used_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Table des employés
    echo "- Création de la table 'employees'...\n";
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

    // Table des pointages
    echo "- Création de la table 'attendance'...\n";
    $pdo->exec("CREATE TABLE attendance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        action TEXT NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id)
    )");

    echo "Tables créées avec succès.\n";
} catch (PDOException $e) {
    die("Erreur lors de la création des tables: " . $e->getMessage() . "\n");
}

// Insérer les données de démonstration
echo "Insertion des données de démonstration...\n";

try {
    // Insérer un utilisateur administrateur par défaut
    echo "- Insertion de l'utilisateur administrateur...\n";
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (username, password, name, email, role) 
                VALUES ('admin', '$hashedPassword', 'Administrateur', 'admin@example.com', 'admin')");

    // Insérer un utilisateur gestionnaire
    echo "- Insertion de l'utilisateur gestionnaire...\n";
    $hashedPassword = password_hash('manager123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (username, password, name, email, role) 
                VALUES ('manager', '$hashedPassword', 'Gestionnaire', 'manager@example.com', 'manager')");

    // Insérer quelques employés de démonstration
    echo "- Insertion des employés de démonstration...\n";
    $pdo->exec("INSERT INTO employees (employee_id, name, department, position, email, phone) 
                VALUES 
                ('EMP001', 'Jean Dupont', 'Informatique', 'Développeur', 'jean.dupont@example.com', '0612345678'),
                ('EMP002', 'Marie Martin', 'Ressources Humaines', 'Responsable RH', 'marie.martin@example.com', '0687654321'),
                ('EMP003', 'Pierre Leroy', 'Marketing', 'Chef de projet', 'pierre.leroy@example.com', '0698765432'),
                ('EMP004', 'Sophie Bernard', 'Finance', 'Comptable', 'sophie.bernard@example.com', '0654321098'),
                ('EMP005', 'Thomas Petit', 'Informatique', 'Administrateur système', 'thomas.petit@example.com', '0632109876'),
                ('EMP006', 'Julie Moreau', 'Commercial', 'Responsable commercial', 'julie.moreau@example.com', '0676543210'),
                ('EMP007', 'Nicolas Dubois', 'Logistique', 'Responsable logistique', 'nicolas.dubois@example.com', '0643210987'),
                ('EMP008', 'Camille Roux', 'Design', 'Designer UX/UI', 'camille.roux@example.com', '0689012345')");

    // Insérer quelques pointages de démonstration
    echo "- Insertion des pointages de démonstration...\n";
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $twoDaysAgo = date('Y-m-d', strtotime('-2 day'));
    
    $pdo->exec("INSERT INTO attendance (employee_id, action, timestamp) 
                VALUES 
                (1, 'entry', '$twoDaysAgo 08:05:23'),
                (2, 'entry', '$twoDaysAgo 08:30:15'),
                (3, 'entry', '$twoDaysAgo 09:10:45'),
                (4, 'entry', '$twoDaysAgo 08:45:33'),
                (5, 'entry', '$twoDaysAgo 08:20:12'),
                (1, 'exit', '$twoDaysAgo 17:15:07'),
                (2, 'exit', '$twoDaysAgo 17:30:22'),
                (3, 'exit', '$twoDaysAgo 18:05:18'),
                (4, 'exit', '$twoDaysAgo 17:50:39'),
                (5, 'exit', '$twoDaysAgo 18:10:54'),

                (1, 'entry', '$yesterday 08:15:42'),
                (2, 'entry', '$yesterday 08:25:13'),
                (3, 'entry', '$yesterday 08:55:37'),
                (4, 'entry', '$yesterday 09:00:21'),
                (5, 'entry', '$yesterday 08:10:09'),
                (6, 'entry', '$yesterday 08:45:17'),
                (1, 'exit', '$yesterday 17:30:24'),
                (2, 'exit', '$yesterday 17:00:13'),
                (3, 'exit', '$yesterday 17:45:39'),
                (4, 'exit', '$yesterday 18:00:52'),
                (5, 'exit', '$yesterday 18:15:07'),
                (6, 'exit', '$yesterday 17:50:43'),

                (1, 'entry', '$today 08:15:00'),
                (2, 'entry', '$today 08:30:00'),
                (4, 'entry', '$today 09:00:00'),
                (5, 'entry', '$today 08:45:00'),
                (7, 'entry', '$today 08:20:00'),
                (8, 'entry', '$today 09:15:00')");

    echo "Données de démonstration insérées avec succès.\n";
} catch (PDOException $e) {
    die("Erreur lors de l'insertion des données: " . $e->getMessage() . "\n");
}
// Si le script est exécuté dans un navigateur, ajouter des liens utiles
if (php_sapi_name() !== 'cli') {
    echo "<br><br>";
    echo "<a href='index.php'>Aller à la page d'accueil</a> | ";
    echo "<a href='login.php'>Aller à la page de connexion</a>";
}
?>