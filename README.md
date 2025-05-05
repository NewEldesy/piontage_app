# Application de Pointage du Personnel (PointageApp)

Une application PHP avec base de données SQLite pour gérer les entrées et sorties du personnel d'une entreprise.

## Fonctionnalités

- **Système d'authentification** : Connexion sécurisée pour les administrateurs et gestionnaires
- **Tableau de bord** : Vue d'ensemble des présences avec statistiques et graphiques
- **Système de pointage** : Enregistrement des entrées et sorties du personnel
- **Gestion des employés** : Ajout, modification et suppression des employés
- **Rapports** : Génération de rapports détaillés (quotidien, par employé, par département)
- **Interface responsive** : Consultable sur ordinateur, tablette et mobile

## Prérequis

- PHP 8.2 ou supérieur
- Extension PDO SQLite activée
- Serveur web (Apache, Nginx, etc.)

## Installation

1. Clonez ce dépôt sur votre serveur web :
   \`\`\`
   git clone https://github.com/NewEldesy/pointage_app.git
   \`\`\`

2. Accédez à l'application via votre navigateur :
   \`\`\`
   http://localhost/pointage_app
   \`\`\`

4. Connectez-vous avec les identifiants par défaut :
   - Nom d'utilisateur : `admin`
   - Mot de passe : `admin123`

## Structure du projet

```
pointage_app/
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── script.js
├── config/
│   └── database.php
├── database/
│   └── pointage.db
├── includes/
│   ├── footer.php
│   ├── functions.php
│   └── header.php
├── index.php
├── login.php
├── logout.php
├── dashboard.php
├── pointage.php
├── employees.php
├── employee-add.php
├── employee-edit.php
├── employee-view.php
├── reports.php
└── README.md
```

## Personnalisation

### Ajouter un nouvel utilisateur administrateur

\`\`\`php
$hashedPassword = password_hash('votre_mot_de_passe', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (username, password, name, email, role) VALUES (?, ?, ?, ?, ?)");
$stmt->execute(['nom_utilisateur', $hashedPassword, 'Nom Complet', 'email@exemple.com', 'admin']);
\`\`\`

### Modifier les départements

Les départements sont créés dynamiquement lors de l'ajout d'employés. Vous pouvez ajouter de nouveaux départements en ajoutant de nouveaux employés avec ces départements.

## Sécurité

- Les mots de passe sont hachés avec `password_hash()`
- Protection contre les injections SQL grâce à PDO et les requêtes préparées
- Validation et nettoyage des entrées utilisateur

## Améliorations possibles

- Ajout d'un système de badges pour le pointage automatique
- Intégration avec des systèmes biométriques
- Export des rapports en PDF
- Système de notifications par email
- API REST pour l'intégration avec d'autres systèmes

## Licence

Ce projet est sous licence MIT. Voir le fichier LICENSE pour plus de détails.

## Contributions

Les contributions sont les bienvenues ! N'hésitez pas à ouvrir une issue ou à soumettre une pull request.
