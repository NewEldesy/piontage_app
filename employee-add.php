<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur
if (!isLoggedIn() || !hasRole('admin')) {
    setAlert('Vous n\'avez pas les droits pour accéder à cette page.', 'error');
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Traitement du formulaire d'ajout d'employé
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
        // Générer un identifiant unique pour l'employé
        $employeeId = generateEmployeeId($pdo);
        
        // Insérer l'employé dans la base de données
        try {
            $stmt = $pdo->prepare("
                INSERT INTO employees (employee_id, name, department, position, email, phone)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$employeeId, $name, $department, $position, $email, $phone]);
            
            $success = "L'employé a été ajouté avec succès avec l'identifiant $employeeId.";
            
            // Rediriger vers la liste des employés
            setAlert($success, 'success');
            redirect('employees.php');
        } catch (PDOException $e) {
            $error = "Une erreur est survenue lors de l'ajout de l'employé : " . $e->getMessage();
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
    <title>Ajouter un Employé - PointageApp</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <section class="page-header">
                <div class="header-content">
                    <h1>Ajouter un Employé</h1>
                    <p>Créer un nouvel employé dans le système</p>
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
                                    <label for="name">Nom complet <span class="required">*</span></label>
                                    <input type="text" id="name" name="name" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="department">Département <span class="required">*</span></label>
                                    <select id="department" name="department" required>
                                        <option value="">Sélectionnez un département</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                        <?php endforeach; ?>
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
                                    <input type="text" id="position" name="position" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email">
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Téléphone</label>
                                    <input type="tel" id="phone" name="phone">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="reset" class="btn btn-outline">Réinitialiser</button>
                                <button type="submit" class="btn btn-primary">Ajouter l'employé</button>
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