<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur
if (!isLoggedIn() || !hasRole('admin')) {
    setAlert('Vous n\'avez pas les droits pour accéder à cette page.', 'error');
    redirect('dashboard.php');
}

// Vérifier si l'ID de l'employé est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setAlert('ID d\'employé invalide.', 'error');
    redirect('employees.php');
}

$employeeId = (int)$_GET['id'];

// Récupérer les informations de l'employé
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch();

if (!$employee) {
    setAlert('Employé non trouvé.', 'error');
    redirect('employees.php');
}

$error = '';
$success = '';

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et nettoyer les données du formulaire
    $name = sanitize($_POST['name']);
    $department = sanitize($_POST['department']);
    $position = sanitize($_POST['position']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    
    // Validation des données
    if (empty($name) || empty($department) || empty($position)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        // Mettre à jour l'employé dans la base de données
        try {
            $stmt = $pdo->prepare("
                UPDATE employees 
                SET name = ?, department = ?, position = ?, email = ?, phone = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $department, $position, $email, $phone, $employeeId]);
            
            $success = "Les informations de l'employé ont été mises à jour avec succès.";
            
            // Mettre à jour les données de l'employé pour l'affichage
            $employee['name'] = $name;
            $employee['department'] = $department;
            $employee['position'] = $position;
            $employee['email'] = $email;
            $employee['phone'] = $phone;
            
            // Rediriger vers la liste des employés
            setAlert($success, 'success');
            redirect('employees.php');
        } catch (PDOException $e) {
            $error = "Une erreur est survenue lors de la mise à jour de l'employé : " . $e->getMessage();
        }
    }
}

// Récupérer la liste des départements pour le menu déroulant
$stmt = $pdo->query("SELECT DISTINCT department FROM employees ORDER BY department");
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un Employé - PointageApp</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <section class="page-header">
                <div class="header-content">
                    <h1>Modifier un Employé</h1>
                    <p>Modifier les informations de <?php echo $employee['name']; ?> (<?php echo $employee['employee_id']; ?>)</p>
                </div>
                <div class="header-actions">
                    <a href="employees.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Retour à la liste
                    </a>
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
            
            <section class="form-section">
                <div class="card">
                    <div class="card-header">
                        <h3>Informations de l'employé</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="employee_id">ID Employé</label>
                                    <input type="text" id="employee_id" value="<?php echo $employee['employee_id']; ?>" readonly disabled>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name">Nom complet <span class="required">*</span></label>
                                    <input type="text" id="name" name="name" value="<?php echo $employee['name']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="department">Département <span class="required">*</span></label>
                                    <select id="department" name="department" required>
                                        <?php 
                                        $departmentExists = false;
                                        foreach ($departments as $dept): 
                                            $selected = ($dept === $employee['department']) ? 'selected' : '';
                                            if ($selected) $departmentExists = true;
                                        ?>
                                            <option value="<?php echo $dept; ?>" <?php echo $selected; ?>><?php echo $dept; ?></option>
                                        <?php endforeach; ?>
                                        <?php if (!$departmentExists): ?>
                                            <option value="<?php echo $employee['department']; ?>" selected><?php echo $employee['department']; ?></option>
                                        <?php endif; ?>
                                        <option value="other">Autre...</option>
                                    </select>
                                </div>
                                
                                <div class="form-group" id="otherDepartmentGroup" style="display: none;">
                                    <label for="otherDepartment">Précisez le département <span class="required">*</span></label>
                                    <input type="text" id="otherDepartment" name="otherDepartment">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="position">Poste <span class="required">*</span></label>
                                    <input type="text" id="position" name="position" value="<?php echo $employee['position']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" value="<?php echo $employee['email']; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Téléphone</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo $employee['phone']; ?>">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="reset" class="btn btn-outline">Réinitialiser</button>
                                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>

    <script>
        // Gestion du champ "Autre département"
        document.getElementById('department').addEventListener('change', function() {
            const otherDeptGroup = document.getElementById('otherDepartmentGroup');
            const otherDeptInput = document.getElementById('otherDepartment');
            
            if (this.value === 'other') {
                otherDeptGroup.style.display = 'block';
                otherDeptInput.required = true;
            } else {
                otherDeptGroup.style.display = 'none';
                otherDeptInput.required = false;
            }
        });
        
        // Intercepter la soumission du formulaire pour gérer le département personnalisé
        document.querySelector('form').addEventListener('submit', function(e) {
            const departmentSelect = document.getElementById('department');
            const otherDepartment = document.getElementById('otherDepartment');
            
            if (departmentSelect.value === 'other' && otherDepartment.value.trim() !== '') {
                e.preventDefault();
                departmentSelect.innerHTML += `<option value="${otherDepartment.value}" selected>${otherDepartment.value}</option>`;
                this.submit();
            }
        });
    </script>
    <script src="assets/js/script.js"></script>
</body>
</html>