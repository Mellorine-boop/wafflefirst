<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/App/Core/DatabaseException.php';
require_once __DIR__ . '/../src/App/Models/MenuItem.php';
require_once __DIR__ . '/../src/App/Models/Waffle.php';
require_once __DIR__ . '/../src/App/Models/Drink.php';
require_once __DIR__ . '/../src/App/Models/User.php';
require_once __DIR__ . '/../src/App/Models/Payment.php';
require_once __DIR__ . '/../src/App/Models/Sale.php';
require_once __DIR__ . '/../src/App/Repositories/ProductRepository.php';
require_once __DIR__ . '/../src/App/Repositories/UserRepository.php';
require_once __DIR__ . '/../src/App/Repositories/SaleRepository.php';
require_once __DIR__ . '/../src/App/Services/Cart.php';
require_once __DIR__ . '/../src/App/Services/AuthService.php';
require_once __DIR__ . '/../src/App/Services/OrderService.php';
require_once __DIR__ . '/../src/App/Payments/PaymentMethod.php';
require_once __DIR__ . '/../src/App/Payments/AbstractPayment.php';
require_once __DIR__ . '/../src/App/Payments/CashPayment.php';
require_once __DIR__ . '/../src/App/Payments/OnlinePayment.php';
require_once __DIR__ . '/../src/App/Payments/GCashPayment.php';
require_once __DIR__ . '/../src/App/Payments/CardPayment.php';
require_once __DIR__ . '/../src/App/DSA/HashTable.php';
require_once __DIR__ . '/../src/App/DSA/Algorithms.php';

use App\Config\Database;
use App\Repositories\ProductRepository;
use App\Repositories\UserRepository;
use App\Repositories\SaleRepository;
use App\Services\Cart;
use App\Services\AuthService;
use App\Services\OrderService;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$db = Database::connect();

$productsRepo = new ProductRepository($db);
$userRepo = new UserRepository($db);
$saleRepo = new SaleRepository($db);

$auth = new AuthService($userRepo);
$orderService = new OrderService($db, $productsRepo);
$cart = new Cart($_SESSION['cart'] ?? []);

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
