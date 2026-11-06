<?php
// logout.php - Ends the current authenticated session.
require_once __DIR__ . '/includes/bootstrap.php';
$auth->logout();
header('Location: login.php');
exit();
