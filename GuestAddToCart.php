<?php
// ══════════════════════════════════════════════════════════════
//  GuestAddToCart.php
//  Adds a product to $_SESSION['guest_cart'] for unauthenticated
//  visitors. Uses the product's name as the de-duplication key.
//  On login, Login_Signup.php will merge these items into the DB.
// ══════════════════════════════════════════════════════════════
session_start();
include 'db_connect.php';

// Redirect logged-in users to the real AddToCart handler
if (!empty($_SESSION['CustomerID'])) {
    header("Location: AddToCart.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: Products.php");
    exit;
}

$productID = (int)($_POST['productID'] ?? 0);
$quantity  = max(1, (int)($_POST['quantity'] ?? 1));

if ($productID <= 0) {
    $_SESSION['flash_error'] = 'Invalid product.';
    header("Location: Products.php");
    exit;
}

// Look up the product across all three tables
$productName = null;
$tables = ['productsmedian', 'productssophisticated', 'productsluxurious'];

foreach ($tables as $table) {
    $stmt = $pdo->prepare("SELECT ProductName FROM $table WHERE ProductID = ?");
    $stmt->execute([$productID]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $productName = $row['ProductName'];
        break;
    }
}

if (!$productName) {
    $_SESSION['flash_error'] = 'Product not found.';
    header("Location: Products.php");
    exit;
}

// Initialise the guest cart array if it doesn't exist
if (!isset($_SESSION['guest_cart']) || !is_array($_SESSION['guest_cart'])) {
    $_SESSION['guest_cart'] = [];
}

// Use a stable key: "productID_<id>" — this makes removal and de-dup easy
$key = 'product_' . $productID;

if (isset($_SESSION['guest_cart'][$key])) {
    // Increase quantity if the item is already in the guest cart
    $_SESSION['guest_cart'][$key]['quantity'] += $quantity;
} else {
    $_SESSION['guest_cart'][$key] = [
        'product_id'   => $productID,
        'product_name' => $productName,
        'quantity'     => $quantity,
    ];
}

$_SESSION['flash_success'] = htmlspecialchars($productName) . ' added to your cart.';
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'Products.php'));
exit;
