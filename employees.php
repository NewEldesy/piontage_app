<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur
if (!isLoggedIn() || !hasRole('admin')) {
    setAlert('Vous n\'avez pas les droits pour accéder à cette page.', 'error');
    redirect('dashboard.php');
}

// Traitement de la suppression d'un employé
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $employeeId = $_GET['delete'];
    
    // Vérifier si l'employé existe
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ?");
    $stmt->bindParam(1, $employeeId, PDO::PARAM_INT);
    $stmt->execute();

    // echo "<pre>";
    // print_r($_GET);
    // $stmtCheck = $pdo->prepare("SELECT * FROM employees");
    // $stmtCheck->execute();
    // $all = $stmtCheck->fetchAll();
    // print_r($all);
    // echo "</pre>";
    // exit;
    
    var_dump($stmt->rowCount()); exit;
    if ($stmt->rowCount() > 0) {
        // Supprimer les pointages associés
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        
        // Supprimer l'employé
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        
        setAlert('L\'employé a été supprimé avec succès.', 'success');
    } else {
        setAlert('Employé non trouvé.', 'error');
    }
    
    redirect('employees.php');
}

// Récupérer tous les employés
$searchTerm = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$sortField = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'name';
$sortOrder = isset($_GET['order']) ? sanitize($_GET['order']) : 'asc';

// Valider les champs de tri
$allowedFields = ['employee_id', 'name', 'department', 'position'];
if (!in_array($sortField, $allowedFields)) {
    $sortField = 'name';
}

$allowedOrders = ['asc', 'desc'];
if (!in_array($sortOrder, $allowedOrders)) {
    $sortOrder = 'asc';
}

// Construire la requête SQL
$sql = "
    SELECT id, employee_id, name, department, position
    FROM employees
    WHERE employee_id LIKE :search OR name LIKE :search OR department LIKE :search OR position LIKE :search
    ORDER BY $sortField $sortOrder
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':search' => "%$searchTerm%"]);
$employees = $stmt->fetchAll();

// Compter le nombre total d'employés
$stmt = $pdo->query("SELECT COUNT(*) as count FROM employees");
$totalEmployees = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Employés - PointageApp</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <section class="page-header">
                <h1>Gestion des Employés</h1>
                <p>Gérez les informations des employés de l'entreprise</p>
                <a href="employee-add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Ajouter un employé
                </a>
            </section>
            
            <section class="employees-section">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title-group">
                            <h3>Liste des Employés</h3>
                            <span class="badge badge-primary"><?php echo $totalEmployees; ?> employés</span>
                        </div>
                        <div class="card-actions">
                            <form method="GET" action="" class="search-form">
                                <div class="form-group">
                                    <input type="text" name="search" placeholder="Rechercher..." value="<?php echo $searchTerm; ?>">
                                    <button type="submit" class="btn btn-icon">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>
                                            <a href="?sort=employee_id&order=<?php echo $sortField === 'employee_id' && $sortOrder === 'asc' ? 'desc' : 'asc'; ?>&search=<?php echo $searchTerm; ?>">
                                                ID
                                                <?php if ($sortField === 'employee_id'): ?>
                                                    <i class="fas fa-sort-<?php echo $sortOrder === 'asc' ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?sort=name&order=<?php echo $sortField === 'name' && $sortOrder === 'asc' ? 'desc' : 'asc'; ?>&search=<?php echo $searchTerm; ?>">
                                                Nom
                                                <?php if ($sortField === 'name'): ?>
                                                    <i class="fas fa-sort-<?php echo $sortOrder === 'asc' ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?sort=department&order=<?php echo $sortField === 'department' && $sortOrder === 'asc' ? 'desc' : 'asc'; ?>&search=<?php echo $searchTerm; ?>">
                                                Département
                                                <?php if ($sortField === 'department'): ?>
                                                    <i class="fas fa-sort-<?php echo $sortOrder === 'asc' ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?sort=position&order=<?php echo $sortField === 'position' && $sortOrder === 'asc' ? 'desc' : 'asc'; ?>&search=<?php echo $searchTerm; ?>">
                                                Poste
                                                <?php if ($sortField === 'position'): ?>
                                                    <i class="fas fa-sort-<?php echo $sortOrder === 'asc' ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($employees) > 0): ?>
                                        <?php foreach ($employees as $employee): 
                                            $isPresent = isEmployeePresent($pdo, $employee['id']);
                                        ?>
                                            <tr>
                                                <td><?php echo $employee['employee_id']; ?></td>
                                                <td>
                                                    <div class="employee-name">
                                                        <div class="employee-avatar small">
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
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="employee-view.php?id=<?php echo $employee['id']; ?>" class="btn btn-icon btn-info" title="Voir">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="employee-edit.php?id=<?php echo $employee['id']; ?>" class="btn btn-icon btn-warning" title="Modifier">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $employee['id']; ?>, '<?php echo $employee['name']; ?>')" class="btn btn-icon btn-danger" title="Supprimer">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">Aucun employé trouvé.</td>
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

    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirmation de suppression</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer l'employé <span id="employeeName"></span> ?</p>
                <p class="text-danger">Cette action est irréversible et supprimera également tous les pointages associés.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="cancelDelete">Annuler</button>
                <a href="#" id="confirmDelete" class="btn btn-danger">Supprimer</a>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour afficher la modal de confirmation de suppression
        function confirmDelete(id, name) {
            const modal = document.getElementById('deleteModal');
            const employeeName = document.getElementById('employeeName');
            const confirmBtn = document.getElementById('confirmDelete');
            const cancelBtn = document.getElementById('cancelDelete');
            const closeBtn = document.querySelector('.modal-close');
            
            employeeName.textContent = name;
            confirmBtn.href = 'employees.php?delete=' + id;
            
            modal.style.display = 'flex';
            
            // Fermer la modal
            function closeModal() {
                modal.style.display = 'none';
            }
            
            cancelBtn.onclick = closeModal;
            closeBtn.onclick = closeModal;
            
            // Fermer la modal si on clique en dehors
            window.onclick = function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            };
        }
    </script>
    <script src="assets/js/script.js"></script>
</body>
</html>