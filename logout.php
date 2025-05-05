<?php
session_start();
require_once 'includes/functions.php';

// Détruire la session
session_destroy();

// Rediriger vers la page d'accueil
setAlert('Vous avez été déconnecté avec succès.', 'success');
redirect('index.php');