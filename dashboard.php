<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    setAlert('Veuillez vous connecter pour accéder à cette page.', 'error');
    redirect('login.php');
}

// Récupérer les statistiques
$stats = getAttendanceStats($pdo);

// Récupérer les activités récentes
$recentActivities = getRecentActivities($pdo, 5);

// Récupérer les données pour le graphique de présence hebdomadaire
$weeklyData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    
    // Nombre d'employés présents ce jour-là
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT employee_id) as count
        FROM attendance
        WHERE action = 'entry'
        AND DATE(timestamp) = ?
    ");
    $stmt->execute([$date]);
    $presentCount = $stmt->fetch()['count'];
    
    // Nombre total d'employés
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees");
    $totalCount = $stmt->fetch()['count'];
    
    $weeklyData[] = [
        'date' => date('d/m', strtotime($date)),
        'day' => date('l', strtotime($date)),
        'present' => $presentCount,
        'absent' => $totalCount - $presentCount
    ];
}

// Convertir les données en JSON pour le graphique
$weeklyDataJson = json_encode($weeklyData);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - PointageApp</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <section class="dashboard-header">
                <h1>Tableau de bord</h1>
                <p>Aperçu des entrées et sorties du personnel</p>
                <div class="date-display">
                    <i class="fas fa-calendar"></i>
                    <?php echo date('d F Y'); ?>
                </div>
            </section>
            
            <section class="stats-cards">
                <div class="card stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Employés Présents</h3>
                        <div class="stat-value"><?php echo $stats['present']; ?></div>
                        <p class="stat-change">+2 depuis hier</p>
                    </div>
                </div>
                
                <div class="card stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Employés Absents</h3>
                        <div class="stat-value"><?php echo $stats['absent']; ?></div>
                        <p class="stat-change">-1 depuis hier</p>
                    </div>
                </div>
                
                <div class="card stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-sign-in-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Entrées Aujourd'hui</h3>
                        <div class="stat-value"><?php echo $stats['entries']; ?></div>
                        <p class="stat-change">+4 depuis hier</p>
                    </div>
                </div>
                
                <div class="card stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Sorties Aujourd'hui</h3>
                        <div class="stat-value"><?php echo $stats['exits']; ?></div>
                        <p class="stat-change">+2 depuis hier</p>
                    </div>
                </div>
            </section>
            
            <section class="dashboard-tabs">
                <div class="tabs">
                    <button class="tab-btn active" data-tab="overview">Aperçu</button>
                    <button class="tab-btn" data-tab="employees">Employés</button>
                    <button class="tab-btn" data-tab="activities">Activités récentes</button>
                </div>
                
                <div class="tab-content active" id="overview">
                    <div class="dashboard-grid">
                        <div class="card chart-card">
                            <div class="card-header">
                                <h3>Présence Hebdomadaire</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="weeklyChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h3>Activités Récentes</h3>
                                <p>Les 5 dernières entrées et sorties</p>
                            </div>
                            <div class="card-body">
                                <div class="activities-list">
                                    <?php foreach ($recentActivities as $activity): ?>
                                        <div class="activity-item">
                                            <div class="activity-avatar">
                                                <?php echo substr($activity['name'], 0, 2); ?>
                                            </div>
                                            <div class="activity-content">
                                                <div class="activity-header">
                                                    <span class="activity-name"><?php echo $activity['name']; ?></span>
                                                    <span class="activity-badge <?php echo $activity['action'] === 'entry' ? 'badge-success' : 'badge-danger'; ?>">
                                                        <i class="fas fa-<?php echo $activity['action'] === 'entry' ? 'sign-in-alt' : 'sign-out-alt'; ?>"></i>
                                                        <?php echo $activity['action'] === 'entry' ? 'Entrée' : 'Sortie'; ?>
                                                    </span>
                                                </div>
                                                <div class="activity-details">
                                                    <span><?php echo $activity['emp_code']; ?> - <?php echo $activity['department']; ?></span>
                                                </div>
                                                <div class="activity-footer">
                                                    <span><?php echo date('d/m/Y', strtotime($activity['timestamp'])) === date('d/m/Y') ? 'Aujourd\'hui' : date('d/m/Y', strtotime($activity['timestamp'])); ?></span>
                                                    <span class="activity-time"><?php echo date('H:i', strtotime($activity['timestamp'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="tab-content" id="employees">
                    <div class="card">
                        <div class="card-header">
                            <h3>Liste des Employés</h3>
                            <p>Statut actuel de tous les employés</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom</th>
                                            <th>Département</th>
                                            <th>Poste</th>
                                            <th>Statut</th>
                                            <th>Dernière action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->query("
                                            SELECT e.id, e.employee_id, e.name, e.department, e.position
                                            FROM employees e
                                            ORDER BY e.name
                                        ");
                                        $employees = $stmt->fetchAll();
                                        
                                        foreach ($employees as $employee):
                                            $isPresent = isEmployeePresent($pdo, $employee['id']);
                                            $lastAction = getLastAction($pdo, $employee['id']);
                                            $lastActionText = $lastAction ? 
                                                ($lastAction['action'] === 'entry' ? 'Entrée à ' : 'Sortie à ') . 
                                                date('H:i', strtotime($lastAction['timestamp'])) : 
                                                'Aucune action';
                                        ?>
                                        <tr>
                                            <td><?php echo $employee['employee_id']; ?></td>
                                            <td>
                                                <div class="employee-name">
                                                    <div class="employee-avatar">
                                                        <?php echo substr($employee['name'], 0, 2); ?>
                                                    </div>
                                                    <?php echo $employee['name']; ?>
                                                </div>
                                            </td>
                                            <td><?php echo $employee['department']; ?></td>
                                            <td><?php echo $employee['position']; ?></td>
                                            <td>
                                                <span class="badge <?php echo $isPresent ? 'badge-success' : 'badge-danger'; ?>">
                                                    <?php echo $isPresent ? 'Présent' : 'Absent'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $lastActionText; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="tab-content" id="activities">
                    <div class="card">
                        <div class="card-header">
                            <h3>Toutes les Activités</h3>
                            <p>Historique complet des entrées et sorties</p>
                        </div>
                        <div class="card-body">
                            <div class="activities-list extended">
                                <?php
                                $stmt = $pdo->query("
                                    SELECT a.id, a.action, a.timestamp, e.id as employee_id, e.employee_id as emp_code, e.name, e.department
                                    FROM attendance a
                                    JOIN employees e ON a.employee_id = e.id
                                    ORDER BY a.timestamp DESC
                                    LIMIT 10
                                ");
                                $allActivities = $stmt->fetchAll();
                                
                                foreach ($allActivities as $activity):
                                ?>
                                <div class="activity-item">
                                    <div class="activity-avatar">
                                        <?php echo substr($activity['name'], 0, 2); ?>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-header">
                                            <span class="activity-name"><?php echo $activity['name']; ?></span>
                                            <span class="activity-badge <?php echo $activity['action'] === 'entry' ? 'badge-success' : 'badge-danger'; ?>">
                                                <i class="fas fa-<?php echo $activity['action'] === 'entry' ? 'sign-in-alt' : 'sign-out-alt'; ?>"></i>
                                                <?php echo $activity['action'] === 'entry' ? 'Entrée' : 'Sortie'; ?>
                                            </span>
                                        </div>
                                        <div class="activity-details">
                                            <span><?php echo $activity['emp_code']; ?> - <?php echo $activity['department']; ?></span>
                                        </div>
                                        <div class="activity-footer">
                                            <span><?php echo date('d/m/Y', strtotime($activity['timestamp'])) === date('d/m/Y') ? 'Aujourd\'hui' : date('d/m/Y', strtotime($activity['timestamp'])); ?></span>
                                            <span class="activity-time"><?php echo date('H:i', strtotime($activity['timestamp'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>

    <script>
        // Initialiser le graphique de présence hebdomadaire
        const weeklyData = <?php echo $weeklyDataJson; ?>;
        const ctx = document.getElementById('weeklyChart').getContext('2d');
        
        const weeklyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: weeklyData.map(item => item.date),
                datasets: [
                    {
                        label: 'Présents',
                        data: weeklyData.map(item => item.present),
                        backgroundColor: '#22c55e',
                        borderColor: '#22c55e',
                        borderWidth: 1
                    },
                    {
                        label: 'Absents',
                        data: weeklyData.map(item => item.absent),
                        backgroundColor: '#ef4444',
                        borderColor: '#ef4444',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Gestion des onglets
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.dataset.tab;
                
                // Désactiver tous les onglets
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                // Activer l'onglet sélectionné
                btn.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });
    </script>
    <script src="assets/js/script.js"></script>
</body>
</html>