<?php
session_start();
include 'db_connect.php';

// Handle checkout from cart (multi-item) — pass ALL selected items to Checkout
if (isset($_GET['fromCart']) && $_GET['fromCart'] == 1 && isset($_SESSION['cart_checkout'])) {
    // cart_checkout already holds all selected items; Checkout.php will read it
    header("Location: Checkout.php");
    exit;
}

// Accept either productID or productName
$productID   = isset($_POST['productID']) ? (int)$_POST['productID'] : 0;
$productName = $_POST['productName'] ?? '';
$quantity    = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT, [
    'options' => ['default' => 1, 'min_range' => 1]
]);

// Try to fetch product by ID first, then by name
$tables  = ['productsmedian', 'productssophisticated', 'productsluxurious'];
$product = null;

if ($productID > 0) {
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT ProductID, ProductName, Price FROM $table WHERE ProductID = ?");
        $stmt->execute([$productID]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($product) break;
    }
} elseif ($productName) {
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT ProductID, ProductName, Price FROM $table WHERE ProductName = ?");
        $stmt->execute([$productName]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($product) break;
    }
}

if (!$product || $quantity < 1) {
    $_SESSION['flash_error'] = 'Product not found or invalid quantity.';
    header('Location: Products.php');
    exit;
}

$price = $product['Price'];
$total = $price * $quantity;

// Save single item to session — Checkout.php handles the DB insert
$_SESSION['buy_now'] = [
    'productID'   => $product['ProductID'],
    'productName' => $product['ProductName'],
    'quantity'    => $quantity,
    'price'       => $price,
    'total'       => $total
];

header('Location: Checkout.php');
exit;
