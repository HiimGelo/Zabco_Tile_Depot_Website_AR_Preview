<?php
session_start();
include 'db_connect.php';

// require login
if (empty($_SESSION['CustomerID'])) {
    $_SESSION['flash_error'] = 'Please log in to add items to cart.';
    header('Location: Login&Signup.php');
    exit;
}
$customerID = (int)$_SESSION['CustomerID'];

// validate inputs
$productID = filter_input(INPUT_POST, 'productID', FILTER_VALIDATE_INT);
$quantity  = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT, [
    'options' => ['default' => 1, 'min_range' => 1]
]);

if (!$productID || $quantity < 1) {
    $_SESSION['flash_error'] = 'Invalid product or quantity.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'Products.php'));
    exit;
}

// find product name from available product tables
$tables = ['productsmedian','productssophisticated','productsluxurious'];
$productName = null;
try {
    foreach ($tables as $t) {
        $stmt = $pdo->prepare("SELECT ProductName FROM {$t} WHERE ProductID = ? LIMIT 1");
        $stmt->execute([$productID]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r) { $productName = trim($r['ProductName']); break; }
    }
} catch (Exception $e) {
    // DB error
    $_SESSION['flash_error'] = 'Database error.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'Products.php'));
    exit;
}

if (!$productName) {
    $_SESSION['flash_error'] = 'Product not found.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'Products.php'));
    exit;
}

try {
    // try to update existing cart row for this customer+product name
    $pdo->beginTransaction();

    $sel = $pdo->prepare("SELECT Quantity FROM cart WHERE CustomerID = ? AND ProductName = ? LIMIT 1");
    $sel->execute([$customerID, $productName]);
    $existing = $sel->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $newQty = max(1, (int)$existing['Quantity'] + $quantity);
        $upd = $pdo->prepare("UPDATE cart SET Quantity = ? WHERE CustomerID = ? AND ProductName = ?");
        $upd->execute([$newQty, $customerID, $productName]);
    } else {
        $ins = $pdo->prepare("INSERT INTO cart (CustomerID, ProductName, Quantity) VALUES (?, ?, ?)");
        $ins->execute([$customerID, $productName, $quantity]);
    }

    $pdo->commit();
    $_SESSION['flash_success'] = 'Added to cart.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? "ProductDetails.php?id={$productID}"));
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // fallback to session-based cart
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
    $found = false;
    foreach ($_SESSION['cart'] as &$item) {
        if (($item['productName'] ?? '') === $productName) {
            $item['qty'] = (int)$item['qty'] + $quantity;
            $found = true;
            break;
        }
    }
    unset($item);
    if (!$found) $_SESSION['cart'][] = ['productName' => $productName, 'qty' => (int)$quantity];

    $_SESSION['flash_error'] = 'Could not save to database; added to local cart instead.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? "ProductDetails.php?id={$productID}"));
    exit;
}
?>