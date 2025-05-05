<header class="header">
    <div class="header-logo">
        <a href="index.php">
            <i class="fas fa-clock"></i>
            <span>PointageApp</span>
        </a>
    </div>
    
    <nav class="header-nav">
        <ul>
            <li><a href="index.php">Accueil</a></li>
            <?php if (!isLoggedIn()): ?>
                <li><a href="login.php">Connexion</a></li>
            <?php else: ?>
                <li><a href="dashboard.php">Tableau de bord</a></li>
                <?php if (hasRole('admin')): ?>
                    <li><a href="employees.php">Employés</a></li>
                <?php endif; ?>
                <li><a href="pointage.php">Pointage</a></li>
                <li><a href="reports.php">Rapports</a></li>
                <li><a href="logout.php">Déconnexion</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <div class="header-mobile-toggle">
        <button id="mobile-menu-toggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</header>

<div class="mobile-menu" id="mobile-menu">
    <ul>
        <li><a href="index.php">Accueil</a></li>
        <?php if (!isLoggedIn()): ?>
            <li><a href="login.php">Connexion</a></li>
        <?php else: ?>
            <li><a href="dashboard.php">Tableau de bord</a></li>
            <?php if (hasRole('admin')): ?>
                <li><a href="employees.php">Employés</a></li>
            <?php endif; ?>
            <li><a href="pointage.php">Pointage</a></li>
            <li><a href="reports.php">Rapports</a></li>
            <li><a href="logout.php">Déconnexion</a></li>
        <?php endif; ?>
    </ul>
</div>

<?php
// Afficher les messages d'alerte s'il y en a
$alert = getAlert();
if ($alert): 
?>
<div class="alert alert-<?php echo $alert['type']; ?>">
    <?php echo $alert['message']; ?>
    <button class="alert-close">&times;</button>
</div>
<?php endif; ?>