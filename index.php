<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PointageApp - Accueil</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <section class="hero">
                <div class="hero-content">
                    <h1>Gestion des Entrées et Sorties du Personnel</h1>
                    <p>Suivez les déplacements de votre personnel en temps réel et consultez les données à distance.</p>
                    <div class="hero-buttons">
                        <a href="login.php" class="btn btn-primary">Commencer</a>
                        <a href="#features" class="btn btn-outline">En savoir plus</a>
                    </div>
                </div>
            </section>

            <section id="features" class="features">
                <h2>Fonctionnalités</h2>
                <div class="feature-cards">
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="card-content">
                            <h3>Pointage Simplifié</h3>
                            <p>Enregistrement rapide des entrées et sorties du personnel avec une interface intuitive.</p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="card-content">
                            <h3>Gestion à Distance</h3>
                            <p>Les gestionnaires peuvent suivre les déplacements du personnel en temps réel, peu importe où ils se trouvent.</p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="card-content">
                            <h3>Rapports Détaillés</h3>
                            <p>Générez des rapports personnalisés pour analyser les tendances et optimiser la gestion du personnel.</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>