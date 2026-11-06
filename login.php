<?php
// login.php - login page for cashier, owner, and admin.
require_once __DIR__ . '/includes/bootstrap.php';

if (isset($_SESSION['user_role'])) {
    header('Location: ' . ($_SESSION['user_role'] === 'cashier' ? 'cashier.php' : ($_SESSION['user_role'] === 'owner' ? 'owner.php' : 'admin.php')));
    exit();
}

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($auth->login($username, $password)) {
        $role = $_SESSION['user_role'];
        header('Location: ' . ($role === 'cashier' ? 'cashier.php' : ($role === 'owner' ? 'owner.php' : 'admin.php')));
        exit();
    }
    $error_message = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>21 GRIDS Login</title>
    <style>
        body{font-family:'Segoe UI',sans-serif;background:#faf8f5;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;color:#3f3028}
        .login-card{background:white;padding:60px;border:4px solid #e8dec9;border-radius:20px;width:500px;text-align:center;box-shadow:0 12px 24px rgba(0,0,0,.05)}
        select,input,button{padding:20px;width:100%;border-radius:10px;border:2px solid #ccc;font-size:20px;box-sizing:border-box;margin-bottom:20px}
        button{background:#5c3d2e;color:white;font-weight:bold;cursor:pointer}
        .error{color:#b71c1c;font-weight:bold;margin-bottom:15px}
    </style>
</head>
<body>
<div class="login-card">
    <h1 style="margin-bottom:8px;">21 Grids Food &amp; Beverage House</h1>
    <h2 style="margin-top:0;">Login</h2>
    <?php if ($error_message): ?><div class="error"><?=e($error_message)?></div><?php endif; ?>
    <form method="POST">
        <input type="text" name="username" required placeholder="Username">
        <input type="password" name="password" required placeholder="Password">
        <button type="submit">Authorize Terminal Session</button>
    </form>
    <?php
    // admin / admin123
    // owner / owner123
    // cashier1 / cashier123
    // cashier2 / cashier123
    // cashier3 / cashier123
    ?>
</div>
</body>
</html>
