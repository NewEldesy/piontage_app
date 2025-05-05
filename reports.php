<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    setAlert('Veuillez vous connecter pour accéder à cette page.', 'error');
    redirect('login.php');
}

// Paramètres du rapport
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$department = isset($_GET['department']) ? sanitize($_GET['department']) : '';
$reportType = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'daily';

// Récupérer la liste des départements pour le filtre
$stmt = $pdo->query("SELECT DISTINCT department FROM employees ORDER BY department");
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Générer le rapport en fonction du type
$reportData = [];
$chartData = [];

switch ($reportType) {
    case 'daily':
        // Rapport quotidien de présence
        $sql = "
            SELECT 
                DATE(a.timestamp) as date,
                COUNT(DISTINCT a.employee_id) as present_count
            FROM attendance a
            JOIN employees e ON a.employee_id = e.id
            WHERE a.action = 'entry'
                AND DATE(a.timestamp) BETWEEN :start_date AND :end_date
                " . ($department ? "AND e.department = :department" : "") . "
            GROUP BY DATE(a.timestamp)
            ORDER BY date
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        if ($department) {
            $stmt->bindParam(':department', $department);
        }
        $stmt->execute();
        $reportData = $stmt->fetchAll();
        
        // Données pour le graphique
        $dates = [];
        $counts = [];
        foreach ($reportData as $row) {
            $dates[] = date('d/m', strtotime($row['date']));
            $counts[] = $row['present_count'];
        }
        $chartData = [
            'labels' => $dates,
            'datasets' => [
                [
                    'label' => 'Employés présents',
                    'data' => $counts,
                    'backgroundColor' => '#22c55e',
                    'borderColor' => '#22c55e',
                    'borderWidth' => 1
                ]
            ]
        ];
        break;
        
    case 'employee':
        // Rapport par employé
        $sql = "
            SELECT 
                e.id,
                e.employee_id,
                e.name,
                e.department,
                COUNT(DISTINCT DATE(a.timestamp)) as days_present,
                (SELECT COUNT(DISTINCT DATE(a2.timestamp))
                 FROM attendance a2
                 WHERE a2.employee_id = e.id
                   AND a2.action = 'exit'
                   AND DATE(a2.timestamp) BETWEEN :start_date AND :end_date) as days_with_exit,
                (SELECT AVG((julianday(a4.timestamp) - julianday(a3.timestamp)) * 24)
                 FROM attendance a3
                 JOIN attendance a4 ON DATE(a3.timestamp) = DATE(a4.timestamp) 
                    AND a3.employee_id = a4.employee_id 
                    AND a4.action = 'exit' 
                    AND a4.timestamp > a3.timestamp
                 WHERE a3.employee_id = e.id 
                    AND a3.action = 'entry'
                    AND DATE(a3.timestamp) BETWEEN :start_date AND :end_date) as avg_hours
            FROM employees e
            LEFT JOIN attendance a ON e.id = a.employee_id 
                AND a.action = 'entry'
                AND DATE(a.timestamp) BETWEEN :start_date AND :end_date
            " . ($department ? "WHERE e.department = :department" : "") . "
            GROUP BY e.id, e.employee_id, e.name, e.department
            ORDER BY days_present DESC, e.name
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        if ($department) {
            $stmt->bindParam(':department', $department);
        }
        $stmt->execute();
        $reportData = $stmt->fetchAll();
        
        // Données pour le graphique (top 10 des employés par présence)
        $topEmployees = array_slice($reportData, 0, 10);
        $names = [];
        $days = [];
        foreach ($topEmployees as $row) {
            $names[] = $row['name'];
            $days[] = $row['days_present'] ?: 0;
        }
        $chartData = [
            'labels' => $names,
            'datasets' => [
                [
                    'label' => 'Jours de présence',
                    'data' => $days,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
                    'borderWidth' => 1
                ]
            ]
        ];
        break;
        
    case 'department':
        // Rapport par département
        $sql = "
            SELECT 
                e.department,
                COUNT(DISTINCT e.id) as total_employees,
                COUNT(DISTINCT CASE WHEN a.id IS NOT NULL THEN a.employee_id END) as employees_present,
                AVG(CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END) * 100 as presence_rate
            FROM employees e
            LEFT JOIN (
                SELECT DISTINCT employee_id, id
                FROM attendance
                WHERE action = 'entry'
                AND DATE(timestamp) BETWEEN :start_date AND :end_date
            ) a ON e.id = a.employee_id
            GROUP BY e.department
            ORDER BY presence_rate DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        $reportData = $stmt->fetchAll();
        
        // Données pour le graphique
        $depts = [];
        $rates = [];
        foreach ($reportData as $row) {
            $depts[] = $row['department'];
            $rates[] = round($row['presence_rate'], 1);
        }
        $chartData = [
            'labels' => $depts,
            'datasets' => [
                [
                    'label' => 'Taux de présence (%)',
                    'data' => $rates,
                    'backgroundColor' => '#8b5cf6',
                    'borderColor' => '#8b5cf6',
                    'borderWidth' => 1
                ]
            ]
        ];
        break;
}

// Convertir les données du graphique en JSON pour JavaScript
$chartDataJson = json_encode($chartData);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - PointageApp</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <section class="page-header">
                <h1>Rapports</h1>
                <p>Générez des rapports détaillés sur la présence du personnel</p>
            </section>
            
            <section class="reports-section">
                <div class="card">
                    <div class="card-header">
                        <h3>Paramètres du rapport</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="report-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="start_date">Date de début</label>
                                    <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="end_date">Date de fin</label>
                                    <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="department">Département</label>
                                    <select id="department" name="department">
                                        <option value="">Tous les départements</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept; ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
                                                <?php echo $dept; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="report_type">Type de rapport</label>
                                    <select id="report_type" name="report_type">
                                        <option value="daily" <?php echo $reportType === 'daily' ? 'selected' : ''; ?>>Présence quotidienne</option>
                                        <option value="employee" <?php echo $reportType === 'employee' ? 'selected' : ''; ?>>Par employé</option>
                                        <option value="department" <?php echo $reportType === 'department' ? 'selected' : ''; ?>>Par département</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Générer le rapport</button>
                                <button type="button" id="exportBtn" class="btn btn-outline">
                                    <i class="fas fa-download"></i> Exporter en CSV
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>
                            <?php
                            switch ($reportType) {
                                case 'daily':
                                    echo 'Rapport de présence quotidienne';
                                    break;
                                case 'employee':
                                    echo 'Rapport par employé';
                                    break;
                                case 'department':
                                    echo 'Rapport par département';
                                    break;
                            }
                            ?>
                        </h3>
                        <p>Période: <?php echo date('d/m/Y', strtotime($startDate)); ?> - <?php echo date('d/m/Y', strtotime($endDate)); ?></p>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="reportChart"></canvas>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table" id="reportTable">
                                <thead>
                                    <tr>
                                        <?php if ($reportType === 'daily'): ?>
                                            <th>Date</th>
                                            <th>Employés présents</th>
                                        <?php elseif ($reportType === 'employee'): ?>
                                            <th>ID</th>
                                            <th>Nom</th>
                                            <th>Département</th>
                                            <th>Jours de présence</th>
                                            <th>Jours avec sortie</th>
                                            <th>Heures moyennes / jour</th>
                                        <?php elseif ($reportType === 'department'): ?>
                                            <th>Département</th>
                                            <th>Total employés</th>
                                            <th>Employés présents</th>
                                            <th>Taux de présence (%)</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($reportData) > 0): ?>
                                        <?php foreach ($reportData as $row): ?>
                                            <tr>
                                                <?php if ($reportType === 'daily'): ?>
                                                    <td><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                                                    <td><?php echo $row['present_count']; ?></td>
                                                <?php elseif ($reportType === 'employee'): ?>
                                                    <td><?php echo $row['employee_id']; ?></td>
                                                    <td><?php echo $row['name']; ?></td>
                                                    <td><?php echo $row['department']; ?></td>
                                                    <td><?php echo $row['days_present'] ?: 0; ?></td>
                                                    <td><?php echo $row['days_with_exit'] ?: 0; ?></td>
                                                    <td><?php echo $row['avg_hours'] ? number_format($row['avg_hours'], 1) : '-'; ?></td>
                                                <?php elseif ($reportType === 'department'): ?>
                                                    <td><?php echo $row['department']; ?></td>
                                                    <td><?php echo $row['total_employees']; ?></td>
                                                    <td><?php echo $row['employees_present']; ?></td>
                                                    <td><?php echo number_format($row['presence_rate'], 1); ?>%</td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="<?php echo $reportType === 'employee' ? 6 : ($reportType === 'department' ? 4 : 2); ?>" class="text-center">
                                                Aucune donnée disponible pour cette période.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>

    <script>
        // Initialiser le graphique
        const chartData = <?php echo $chartDataJson; ?>;
        const ctx = document.getElementById('reportChart').getContext('2d');
        
        const reportChart = new Chart(ctx, {
            type: '<?php echo $reportType === 'daily' ? 'line' : 'bar'; ?>',
            data: chartData,
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Fonction pour exporter le tableau en CSV
        document.getElementById('exportBtn').addEventListener('click', function() {
            const table = document.getElementById('reportTable');
            let csv = [];
            
            // En-têtes
            let headers = [];
            for (let i = 0; i < table.rows[0].cells.length; i++) {
                headers.push(table.rows[0].cells[i].textContent);
            }
            csv.push(headers.join(','));
            
            // Données
            for (let i = 1; i < table.rows.length; i++) {
                let row = [];
                for (let j = 0; j < table.rows[i].cells.length; j++) {
                    let cell = table.rows[i].cells[j].textContent.replace(/\s+/g, ' ').trim();
                    // Échapper les guillemets et entourer de guillemets si contient une virgule
                    if (cell.includes(',')) {
                        cell = '"' + cell.replace(/"/g, '""') + '"';
                    }
                    row.push(cell);
                }
                csv.push(row.join(','));
            }
            
            // Télécharger le fichier CSV
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', 'rapport_<?php echo $reportType; ?>_<?php echo date('Ymd'); ?>.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    </script>
    <script src="assets/js/script.js"></script>
</body>
</html>