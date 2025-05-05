<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    setAlert('Veuillez vous connecter pour accéder à cette page.', 'error');
    redirect('login.php');
}

// Vérifier si l'ID de l'employé est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setAlert('ID d\'employé invalide.', 'error');
    redirect('dashboard.php');
}

$employeeId = (int)$_GET['id'];

// Récupérer les informations de l'employé
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch();

if (!$employee) {
    setAlert('Employé non trouvé.', 'error');
    redirect('dashboard.php');
}

// Vérifier si l'employé est présent
$isPresent = isEmployeePresent($pdo, $employeeId);

// Récupérer l'historique des pointages de l'employé
$stmt = $pdo->prepare("
    SELECT id, action, timestamp
    FROM attendance
    WHERE employee_id = ?
    ORDER BY timestamp DESC
    LIMIT 20
");
$stmt->execute([$employeeId]);
$attendanceHistory = $stmt->fetchAll();

// Calculer les statistiques de présence
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_entries,
        COUNT(DISTINCT DATE(timestamp)) as days_present
    FROM attendance
    WHERE employee_id = ? AND action = 'entry'
");
$stmt->execute([$employeeId]);
$stats = $stmt->fetch();

// Calculer le temps de présence moyen (en heures)
$stmt = $pdo->prepare("
    SELECT 
        a1.timestamp as entry_time,
        MIN(a2.timestamp) as exit_time
    FROM attendance a1
    LEFT JOIN attendance a2 ON DATE(a1.timestamp) = DATE(a2.timestamp) 
        AND a1.employee_id = a2.employee_id 
        AND a2.action = 'exit' 
        AND a2.timestamp > a1.timestamp
    WHERE a1.employee_id = ? AND a1.action = 'entry'
    GROUP BY DATE(a1.timestamp), a1.id
    ORDER BY a1.timestamp DESC
");
$stmt->execute([$employeeId]);
$timeRecords = $stmt->fetchAll();

$totalHours = 0;
$validRecords = 0;

foreach ($timeRecords as $record) {
    if ($record['exit_time']) {
        $entry = strtotime($record['entry_time']);
        $exit = strtotime($record['exit_time']);
        $hours = ($exit - $entry) / 3600; // Convertir en heures
        
        if ($hours > 0 && $hours < 24) { // Ignorer les valeurs aberrantes
            $totalHours += $hours;
            $validRecords++;
        }
    }
}

$averageHours = $validRecords > 0 ? $totalHours / $validRecords : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil de l'Employé - PointageApp</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <section class="page-header">
                <div class="header-content">
                    <h1>Profil de l'Employé</h1>
                    <p>Informations détaillées et historique de pointage</p>
                </div>
                <div class="header-actions">
                    <?php if (hasRole('admin')): ?>
                        <a href="employee-edit.php?id=<?php echo $employeeId; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo hasRole('admin') ? 'employees.php' : 'dashboard.php'; ?>" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                </div>
            </section>
            
            <section class="employee-profile">
                <div class="profile-grid">
                    <div class="card">
                        <div class="card-header">
                            <h3>Informations personnelles</h3>
                        </div>
                        <div class="card-body">
                            <div class="profile-header">
                                <div class="profile-avatar">
                                    <?php echo substr($employee['name'], 0, 2); ?>
                                </div>
                                <div class="profile-info">
                                    <h2><?php echo $employee['name']; ?></h2>
                                    <p><?php echo $employee['position']; ?> - <?php echo $employee['department']; ?></p>
                                    <span class="badge <?php echo $isPresent ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $isPresent ? 'Présent' : 'Absent'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="profile-details">
                                <div class="detail-item">
                                    <span class="detail-label">ID Employé</span>
                                    <span class="detail-value"><?php echo $employee['employee_id']; ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Email</span>
                                    <span class="detail-value"><?php echo $employee['email'] ?: 'Non renseigné'; ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Téléphone</span>
                                    <span class="detail-value"><?php echo $employee['phone'] ?: 'Non renseigné'; ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Date d'ajout</span>
                                    <span class="detail-value"><?php echo date('d/m/Y', strtotime($employee['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h3>Statistiques de présence</h3>
                        </div>
                        <div class="card-body">
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['days_present']; ?></div>
                                    <div class="stat-label">Jours de présence</div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['total_entries']; ?></div>
                                    <div class="stat-label">Entrées totales</div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($averageHours, 1); ?></div>
                                    <div class="stat-label">Heures moyennes / jour</div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo count($timeRecords); ?></div>
                                    <div class="stat-label">Jours enregistrés</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card full-width">
                        <div class="card-header">
                            <h3>Historique des pointages</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Heure</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($attendanceHistory) > 0): ?>
                                            <?php foreach ($attendanceHistory as $record): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y', strtotime($record['timestamp'])); ?></td>
                                                    <td><?php echo date('H:i:s', strtotime($record['timestamp'])); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $record['action'] === 'entry' ? 'badge-success' : 'badge-danger'; ?>">
                                                            <i class="fas fa-<?php echo $record['action'] === 'entry' ? 'sign-in-alt' : 'sign-out-alt'; ?>"></i>
                                                            <?php echo $record['action'] === 'entry' ? 'Entrée' : 'Sortie'; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center">Aucun historique de pointage disponible.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
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