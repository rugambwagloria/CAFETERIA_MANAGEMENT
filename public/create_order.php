<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    die('Database Connection Failed.');
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(float $v): string
{
    return number_format($v, 0, '.', ',');
}


$orderItemsRaw = $_POST['order_items'] ?? '';
$orderItems = [];

if (is_string($orderItemsRaw) && $orderItemsRaw !== '') {
    $decoded = json_decode($orderItemsRaw, true);
    if (is_array($decoded)) {
        $orderItems = $decoded;
    }
}

$customerName = trim((string)($_POST['customer_name'] ?? ''));
$orderType = trim((string)($_POST['order_type'] ?? ''));
$pricingTier = (string)($_POST['pricing_tier'] ?? '');
$departmentId = (int)($_POST['department_id'] ?? 0);
$mode = (string)($_POST['mode'] ?? 'kitchen');

// Determine meal_type from selected items; if none selected, fallback to drinks.
$mealType = 'drinks';
$inferredMealTypes = [];
foreach ($orderItems as $id => $it) {
    // In this UI, item JSON does not include meal_type. We will infer via menu_items is not available here.
    // So we map by looking up menu_items->meal_type using item_name is not reliable.
    // Best effort: default to drinks unless meal_type is later provided by the UI.
    // (To fully support meal_type, we’ll need to query by item_id, but current JSON doesn't include item_id as meal_type.)
    $inferredMealTypes[] = null;
}
if ($orderType === '') {
    $orderType = 'Walk-in Order';
}

if ($customerName === '') {
    header('Location: food_orders.php?error=' . urlencode('Customer name is required.'));
    exit;
}

if (!$orderItems) {
    header('Location: food_orders.php?error=' . urlencode('Select at least one dish/drink.'));
    exit;
}

// Compute totals from selected items (price * qty).
$total = 0.0;
foreach ($orderItems as $id => $it) {
    $qty = isset($it['qty']) ? (int)$it['qty'] : 0;
    $price = isset($it['price']) ? (float)$it['price'] : 0.0;
    if ($qty > 0) {
        $total += $qty * $price;
    }
}
$total = round($total, 2);

$status = ($mode === 'serve') ? 'ready' : 'pending';

// Generate order_number.
$orderNumber = 'ORD-' . strtoupper(bin2hex(random_bytes(3)));

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO orders (order_number, customer_name, meal_type, order_type, status, total_amount, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );

    $stmt->execute([
        $orderNumber,
        $customerName,
        $mealType,
        $orderType,
        $status,
        $total,
    ]);

    $orderId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items (order_id, item_name, quantity, line_total)
         VALUES (?, ?, ?, ?)'
    );

    foreach ($orderItems as $id => $it) {
        $qty = isset($it['qty']) ? (int)$it['qty'] : 0;
        if ($qty <= 0) {
            continue;
        }
        $name = isset($it['name']) ? (string)$it['name'] : ('Item ' . (string)$id);
        $price = isset($it['price']) ? (float)$it['price'] : 0.0;
        $lineTotal = round($qty * $price, 2);

        $itemStmt->execute([
            $orderId,
            $name,
            $qty,
            $lineTotal,
        ]);
    }

    $pdo->commit();

    header('Location: food_orders.php');
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: food_orders.php?error=' . urlencode('Failed to save order: ' . $e->getMessage()));
    exit;
}
