<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    setAlert('Veuillez vous connecter pour accéder à cette page.', 'error');
    redirect('login.php');
}

$error = '';
$success = '';
$searchResults = [];

// Traitement de la recherche d'employé
if (isset($_POST['search'])) {
    $searchTerm = sanitize($_POST['employee_id']);
    
    if (!empty($searchTerm)) {
        $stmt = $pdo->prepare("
            SELECT id, employee_id, name, department, position
            FROM employees
            WHERE employee_id LIKE ? OR name LIKE ?
        ");
        $stmt->execute(["%$searchTerm%", "%$searchTerm%"]);
        $searchResults = $stmt->fetchAll();
        
        if (empty($searchResults)) {
            $error = "Aucun employé trouvé avec ces critères.";
        }
    } else {
        $error = "Veuillez entrer un identifiant ou un nom d'employé.";
    }
}

// Traitement de l'enregistrement d'entrée/sortie
if (isset($_POST['record_action'])) {
    $employeeId = $_POST['employee_id'];
    $action = $_POST['action'];
    
    // Afficher des informations de débogage
    error_log("Tentative d'enregistrement pour employé ID: " . $employeeId . ", Action: " . $action);
    
    // Vérifier si l'employé existe sans conversion de type
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
    
    if ($employee) {
        // Enregistrer l'action
        try {
            $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, action) VALUES (?, ?)");
            $stmt->execute([$employeeId, $action]);
            
            $success = "L'action a été enregistrée avec succès pour " . $employee['name'] . ".";
            
            // Rediriger pour éviter la soumission multiple du formulaire
            setAlert($success, 'success');
            redirect('pointage.php');
        } catch (PDOException $e) {
            $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
            error_log("Erreur SQL: " . $e->getMessage());
        }
    } else {
        $error = "Employé non trouvé (ID: " . htmlspecialchars($employeeId) . "). Veuillez réessayer.";
        
        // Informations de débogage supplémentaires
        error_log("Employé non trouvé. ID recherché: " . $employeeId);
        
        // Vérifier si l'employé existe avec une requête plus large pour le débogage
        $stmt = $pdo->query("SELECT id, employee_id, name FROM employees LIMIT 5");
        $debug = $stmt->fetchAll();
        error_log("Premiers employés dans la base: " . print_r($debug, true));
    }
}

// Récupérer l'historique des pointages récents
$stmt = $pdo->query("
    SELECT a.id, a.action, a.timestamp, e.id as employee_id, e.employee_id as emp_code, e.name, e.department
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    ORDER BY a.timestamp DESC
    LIMIT 10
");
$recentPointages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Système de Pointage - PointageApp</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <section class="page-header">
                <h1>Système de Pointage</h1>
                <p>Enregistrez les entrées et sorties du personnel</p>
                <div class="date-display">
                    <i class="fas fa-clock"></i>
                    <?php echo date('H:i:s'); ?>
                </div>
            </section>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <section class="pointage-tabs">
                <div class="tabs">
                    <button class="tab-btn active" data-tab="manual">Pointage Manuel</button>
                    <button class="tab-btn" data-tab="automatic">Pointage Automatique</button>
                    <button class="tab-btn" data-tab="history">Historique</button>
                </div>
                
                <div class="tab-content active" id="manual">
                    <div class="card">
                        <div class="card-header">
                            <h3>Rechercher un Employé</h3>
                            <p>Entrez l'identifiant ou le nom de l'employé pour enregistrer son entrée ou sa sortie</p>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" class="search-form">
                                <div class="form-group">
                                    <label for="employee_id">ID ou Nom de l'Employé</label>
                                    <div class="form-row">
                                        <input type="text" id="employee_id" name="employee_id" placeholder="Ex: EMP001 ou Jean Dupont">
                                        <button type="submit" name="search" class="btn btn-primary">Rechercher</button>
                                    </div>
                                </div>
                            </form>
                            
                            <?php if (!empty($searchResults)): ?>
                                <div class="search-results">
                                    <h4>Résultats de la recherche</h4>
                                    <?php foreach ($searchResults as $employee): 
                                        $isPresent = isEmployeePresent($pdo, $employee['id']);
                                        $lastAction = getLastAction($pdo, $employee['id']);
                                        $lastActionText = $lastAction ? 
                                            ($lastAction['action'] === 'entry' ? 'Entrée à ' : 'Sortie à ') . 
                                            date('H:i', strtotime($lastAction['timestamp'])) : 
                                            'Aucune action';
                                    ?>
                                        <div class="employee-card">
                                            <div class="employee-info">
                                                <div class="employee-avatar">
                                                    <?php echo substr($employee['name'], 0, 2); ?>
                                                </div>
                                                <div class="employee-details">
                                                    <h4><?php echo $employee['name']; ?></h4>
                                                    <p><?php echo $employee['employee_id']; ?> - <?php echo $employee['department']; ?></p>
                                                    <div class="employee-status">
                                                        <span class="badge <?php echo $isPresent ? 'badge-success' : 'badge-danger'; ?>">
                                                            <?php echo $isPresent ? 'Présent' : 'Absent'; ?>
                                                        </span>
                                                        <span class="last-action"><?php echo $lastActionText; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="employee-actions">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                    <?php if ($isPresent): ?>
                                                        <input type="hidden" name="action" value="exit">
                                                        <button type="submit" name="record_action" class="btn btn-danger">
                                                            <i class="fas fa-sign-out-alt"></i> Sortie
                                                        </button>
                                                    <?php else: ?>
                                                        <input type="hidden" name="action" value="entry">
                                                        <button type="submit" name="record_action" class="btn btn-success">
                                                            <i class="fas fa-sign-in-alt"></i> Entrée
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="tab-content" id="automatic">
                    <div class="card">
                        <div class="card-header">
                            <h3>Pointage par Badge</h3>
                            <p>Scannez votre badge pour enregistrer automatiquement votre entrée ou sortie</p>
                        </div>
                        <div class="card-body">
                            <div class="badge-scanner">
                                <div class="scanner-placeholder">
                                    <i class="fas fa-id-card"></i>
                                    <p>Placez votre badge sur le lecteur</p>
                                </div>
                                <p class="scanner-info">Le système détectera automatiquement s'il s'agit d'une entrée ou d'une sortie</p>
                                
                                <!-- Simulation de scan pour la démonstration -->
                                <form method="POST" action="" class="scanner-form">
                                    <div class="form-group">
                                        <label for="badge_id">Simuler un scan de badge</label>
                                        <div class="form-row">
                                            <input type="text" id="badge_id" name="employee_id" placeholder="Entrez l'ID de l'employé">
                                            <button type="submit" name="search" class="btn btn-primary">Scanner</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="tab-content" id="history">
                    <div class="card">
                        <div class="card-header">
                            <h3>Historique des Pointages</h3>
                            <p>Les derniers pointages enregistrés dans le système</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employé</th>
                                            <th>ID</th>
                                            <th>Département</th>
                                            <th>Action</th>
                                            <th>Date</th>
                                            <th>Heure</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentPointages as $pointage): ?>
                                            <tr>
                                                <td>
                                                    <div class="employee-name">
                                                        <div class="employee-avatar small">
                                                            <?php echo substr($pointage['name'], 0, 2); ?>
                                                        </div>
                                                        <?php echo $pointage['name']; ?>
                                                    </div>
                                                </td>
                                                <td><?php echo $pointage['emp_code']; ?></td>
                                                <td><?php echo $pointage['department']; ?></td>
                                                <td>
                                                    <span class="badge <?php echo $pointage['action'] === 'entry' ? 'badge-success' : 'badge-danger'; ?>">
                                                        <i class="fas fa-<?php echo $pointage['action'] === 'entry' ? 'sign-in-alt' : 'sign-out-alt'; ?>"></i>
                                                        <?php echo $pointage['action'] === 'entry' ? 'Entrée' : 'Sortie'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($pointage['timestamp'])); ?></td>
                                                <td><?php echo date('H:i', strtotime($pointage['timestamp'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
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