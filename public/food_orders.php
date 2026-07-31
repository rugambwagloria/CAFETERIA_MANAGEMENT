<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
include __DIR__ . 'components/sidebar.php'; 

/* ---------------------------------------------------------------------
 * DB connection
 * ------------------------------------------------------------------- */
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    die('Database Connection Failed.');
}

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(float $v): string
{
    return number_format($v, 0, '.', ',');
}

/* ---------------------------------------------------------------------
 * NOTE ON SCHEMA ASSUMPTIONS
 * ---------------------------------------------------------------------
 * This page assumes two tables:
 *
 *   orders (
 *       order_id      INT PK,
 *       order_number  VARCHAR   -- e.g. "ORD-0231"
 *       customer_name VARCHAR
 *       meal_type     ENUM('breakfast','lunch','dinner','drinks')
 *       order_type    VARCHAR   -- e.g. "Dine-in" / "Takeaway"
 *       status        ENUM('pending','preparing','ready','completed','cancelled')
 *       total_amount  DECIMAL
 *       created_at    DATETIME
 *   )
 *
 *   order_items (
 *       order_item_id INT PK,
 *       order_id      INT FK -> orders.order_id,
 *       item_name     VARCHAR,
 *       quantity      INT,
 *       line_total    DECIMAL
 *   )
 *
 * If your actual table/column names differ, just adjust the SQL below —
 * everything downstream (cards, tabs, empty state) works off the arrays
 * returned by these two queries.
 * ----------------------------------------------------------------- */

$validTabs = ['all', 'pending', 'preparing', 'ready', 'completed', 'cancelled'];
$activeTab = strtolower($_GET['status'] ?? 'all');
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'all';
}

$dbError = '';
$orders  = [];
$itemsByOrder = [];

// Pre-flight check so the UI shows the real reason (wrong DB / tables not imported).
$preflight = '';
try {
    $tablesStmt = $pdo->query("SHOW TABLES LIKE 'orders'");
    $ordersTableExists = (bool)$tablesStmt->fetch();
    $itemsStmt = $pdo->query("SHOW TABLES LIKE 'order_items'");
    $itemsTableExists = (bool)$itemsStmt->fetch();

    if (!$ordersTableExists || !$itemsTableExists) {
        $preflight = sprintf(
            'Connected DB: "%s". Missing tables: %s%s.',
            (string)$db,
            !$ordersTableExists ? 'orders' : '',
            (!$ordersTableExists && !$itemsTableExists) ? ', ' : ''
        );
        if (!$itemsTableExists) {
            $preflight .= (!$ordersTableExists ? 'order_items' : 'order_items');
        }
    }
} catch (Exception $e) {
    // ignore preflight failures
}

try {
    $sql = 'SELECT order_id, order_number, customer_name, meal_type, order_type, status, total_amount, created_at
            FROM orders';
    $params = [];
    if ($activeTab !== 'all') {
        $sql .= ' WHERE status = :status';
        $params['status'] = $activeTab;
    }
    $sql .= ' ORDER BY created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    if ($orders) {
        $orderIds = array_column($orders, 'order_id');
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $itemStmt = $pdo->prepare(
            "SELECT order_id, item_name, quantity, line_total
             FROM order_items
             WHERE order_id IN ($placeholders)"
        );
        $itemStmt->execute($orderIds);
        foreach ($itemStmt->fetchAll() as $row) {
            $itemsByOrder[$row['order_id']][] = $row;
        }
    }
} catch (Exception $e) {
    $dbError = 'Orders table not found or query failed. Import your orders schema and try again.';
}

/* Icon + label per meal type, matching the Daily Menu page */
$mealMeta = [
    'breakfast' => ['label' => 'BREAKFAST', 'icon' => '☕'],
    'lunch'     => ['label' => 'LUNCH',     'icon' => '☀️'],
    'dinner'    => ['label' => 'DINNER',    'icon' => '🍲'],
    'drinks'    => ['label' => 'DRINKS',    'icon' => '🥤'],
];

/* Status badge colour, matching the tier-colour convention used on Daily Menu */
function status_color(string $status): string
{
    return match ($status) {
        'pending'   => 'tier-red',
        'preparing' => 'tier-default',
        'ready'     => 'tier-blue',
        'completed' => 'status-done',
        'cancelled' => 'status-cancelled',
        default     => 'tier-default',
    };
}

function status_label(string $status): string
{
    return match ($status) {
        'pending'   => 'Pending',
        'preparing' => 'Preparing',
        'ready'     => 'Ready',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default     => ucfirst($status),
    };
}

/* ---------------------------------------------------------------------
 * Data for the "New Food Order" modal
 * ---------------------------------------------------------------------
 * Assumed tables (adjust names/columns to match your schema):
 *
 *   departments (department_id, name)
 *   customers   (customer_id, name, department_id, category_id)
 *   customer_categories (category_id, name)
 *   menu_items  (item_id, name, base_price, meal_type, in_stock)
 * ----------------------------------------------------------------- */
$departments = [];
$customers   = [];
$dishes      = [];

try {
    $departments = $pdo->query('SELECT department_id, name FROM departments ORDER BY name')->fetchAll();
} catch (Exception $e) {
    $departments = [];
}

try {
    $customers = $pdo->query(
        'SELECT c.customer_id, c.name, c.department_id, cc.name AS category_name
         FROM customers c
         LEFT JOIN customer_categories cc ON cc.category_id = c.category_id
         ORDER BY c.name'
    )->fetchAll();
} catch (Exception $e) {
    $customers = [];
}

// Pull in-stock dishes from the same schema used by Daily Menu:
//   daily_menus + food_items + item_category_prices (tier pricing matrix)
try {
    $menuId = null;
    $menuRow = $pdo->query('SELECT menu_id FROM daily_menus ORDER BY menu_date DESC LIMIT 1')->fetch();
    if ($menuRow && isset($menuRow['menu_id'])) {
        $menuId = (int)$menuRow['menu_id'];
    }

    if ($menuId === null) {
        $dishes = [];
    } else {
        // For the modal, we show base item price. Tier matrix is selected separately;
        // we’ll still need tier price when submitting.
        $dishes = $pdo->query(
            "SELECT item_id, name, meal_type, base_price
             FROM food_items
             WHERE menu_id = " . $menuId . " AND in_stock = 1
             ORDER BY meal_type, name"
        )->fetchAll();
    }
} catch (Exception $e) {
    $dishes = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Food Orders — Uganda Parliament ERP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/food_orders.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/topbar.css">
    <script src="assets/js/quick-action.js"></script>
</head>

<body>

    <div class="app-layout">
        <?php require_once 'components/sidebar.php'; ?>

        <div class="main-content">

            <?php require_once 'components/topbar.php'; ?>

            <div class="page">

                <div class="page-head">
                    <div>
                        <h1>Food Orders</h1>
                        <p>Track and manage every cafeteria order as it moves from placed to served</p>
                    </div>
                    <div class="head-actions">
                        <a class="btn" href="orders_report.php">📄 Export Report</a>
                        <button class="btn dark" type="button" onclick="openNewOrderModal()">+ New Order</button>
                        <a class="btn" href="daily_menu.php" title="Add dishes from Daily Menu">🍽️ Add Dishes/Drinks</a>
                    </div>
                </div>

                <div class="tabs">
                    <?php foreach ($validTabs as $tab): ?>
                        <a href="?status=<?= h($tab) ?>"
                            class="<?= $tab === $activeTab ? 'active' : '' ?>">
                            <?= $tab === 'all' ? 'All' : ucfirst($tab) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="grid">
                    <?php if (!empty($preflight)): ?>
                        <div class="empty"><?= h($preflight) ?></div>
                    <?php elseif (!empty($dbError)): ?>
                        <div class="empty"><?= h($dbError) ?></div>
                    <?php elseif (!$orders): ?>
                        <div class="empty">No orders yet<?= $activeTab !== 'all' ? ' with status "' . h(status_label($activeTab)) . '"' : '' ?>. New orders will appear here as they come in.</div>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <?php $meta = $mealMeta[$order['meal_type']] ?? ['label' => strtoupper($order['meal_type']), 'icon' => '🍽️']; ?>
                            <div class="card">
                                <div class="card-top">
                                    <span class="badge"><?= $meta['icon'] ?> <?= h($meta['label']) ?></span>
                                    <span class="status-pill <?= status_color($order['status']) ?>">
                                        <?= h(status_label($order['status'])) ?>
                                    </span>
                                </div>

                                <div class="name"><?= h($order['customer_name']) ?></div>
                                <div class="order-meta">
                                    Order #<?= h($order['order_number']) ?> · <?= h($order['order_type']) ?> ·
                                    <?= h(date('d M Y, H:i', strtotime($order['created_at']))) ?>
                                </div>

                                <div class="price">
                                    UGX <?= money((float) $order['total_amount']) ?>
                                    <small>Total</small>
                                </div>

                                <?php if (!empty($itemsByOrder[$order['order_id']])): ?>
                                    <div class="matrix">
                                        <div class="matrix-title">ORDER ITEMS</div>
                                        <?php foreach ($itemsByOrder[$order['order_id']] as $line): ?>
                                            <div class="item-row">
                                                <span><span class="qty">x<?= (int) $line['quantity'] ?></span><?= h($line['item_name']) ?></span>
                                                <span class="line-total">UGX <?= money((float) $line['line_total']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="card-actions">
                                    <a class="icon-btn" href="edit_order.php?id=<?= (int) $order['order_id'] ?>" title="Edit">✎</a>
                                    <form class="inline" method="post" action="cancel_order.php"
                                        onsubmit="return confirm('Cancel this order?');">
                                        <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
                                        <button type="submit" class="icon-btn" title="Cancel">🗑</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

        </div><!-- /.main-content -->
    </div><!-- /.app-layout -->

    <!-- ============================= NEW ORDER MODAL ============================= -->
    <div class="modal-overlay" id="newOrderOverlay">
        <div class="modal">
            <form id="newOrderForm" method="post" action="create_order.php">
                <div class="modal-header">
                    <div>
                        <h2>Parliament Cafeteria ERP - New Food Order</h2>
                        <p>Generate daily catering tickets and department accounts ledger records</p>
                    </div>
                    <button type="button" class="modal-close" onclick="closeNewOrderModal()" aria-label="Close">✕</button>
                </div>

                <div class="modal-body">
                    <div class="form-grid">
                        <div class="field">
                            <label for="department">Parliament Department <span class="req">*</span></label>
                            <select id="department" name="department_id" required>
                                <option value="" disabled selected>Select department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= (int) $dept['department_id'] ?>"><?= h($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$departments): ?>
                                <small style="color:var(--muted)">No departments found — add some in Departments.</small>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label for="customer_name">Customer Name <span class="req">*</span></label>
                            <input
                                type="text"
                                id="customer_name"
                                name="customer_name"
                                required
                                placeholder="Type customer name"
                                autocomplete="off">
                            <small style="color:var(--muted)">Departments are selected above; customer name is typed.</small>
                        </div>

                        <div class="field">
                            <label for="pricing_tier">Category pricing</label>
                            <select id="pricing_tier" name="pricing_tier" required>
                                <option value="" disabled selected>Select pricing tier</option>
                                <?php
                                // Pricing tiers are derived from the category pricing used by Daily Menu.
                                // Daily Menu uses customer_categories and item_category_prices.
                                try {
                                    $tiers = $pdo->query('SELECT category_id, name FROM customer_categories ORDER BY category_id')->fetchAll();
                                } catch (Exception $e) {
                                    $tiers = [];
                                }
                                foreach ($tiers as $tier):
                                ?>
                                    <option value="<?= (int)$tier['category_id'] ?>"><?= h($tier['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($tiers)): ?>
                                <small style="color:var(--muted)">No pricing tiers found — create customer categories in Daily Menu setup.</small>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label>Order Type <span class="req">*</span></label>
                            <div class="radio-row">
                                <label>
                                    <input type="radio" name="order_type" value="Walk-in Order" checked>
                                    Walk-in Order
                                </label>
                                <label>
                                    <input type="radio" name="order_type" value="Phone Order">
                                    Phone Order
                                </label>
                            </div>
                        </div>
                    </div>

                    <hr class="modal-divider">

                    <div class="section-label active-step">ADD DISHES / DRINKS</div>


                    <div class="dish-grid">
                        <?php if (!$dishes): ?>
                            <div style="grid-column:1/-1;color:var(--muted);font-size:13px;padding:12px 0;">
                                No in-stock menu items found. Add items from the Daily Menu page first.
                            </div>
                        <?php else: ?>
                            <?php foreach ($dishes as $dish): ?>
                                <button type="button" class="dish-card"
                                    data-id="<?= (int) $dish['item_id'] ?>"
                                    data-name="<?= h($dish['name']) ?>"
                                    data-price="<?= (float) $dish['base_price'] ?>"
                                    onclick="toggleDish(this)">
                                    <span class="qty-badge">1</span>
                                    <div class="dish-name" title="<?= h($dish['name']) ?>"><?= h($dish['name']) ?></div>
                                    <div class="dish-price">UGX <?= money((float) $dish['base_price']) ?></div>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <input type="hidden" name="order_items" id="orderItemsInput">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeNewOrderModal()">Cancel</button>
                    <div class="footer-right">
                        <button type="submit" name="mode" value="serve" class="btn blue">Directly Serve &amp; Close</button>
                        <button type="submit" name="mode" value="kitchen" class="btn dark">Submit order to Kitchen (Pending)</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        const selectedDishes = {}; // { itemId: { name, price, qty } }

        // Default tier when customer is typed instead of selected.
        document.addEventListener('DOMContentLoaded', function() {
            const tierInput = document.getElementById('tierInput');
            if (tierInput && !tierInput.value) {
                tierInput.value = 'Standard';
            }
            updateTierFromCustomerName();
        });

        function openNewOrderModal() {
            document.getElementById('newOrderOverlay').classList.add('open');
        }

        function closeNewOrderModal() {
            document.getElementById('newOrderOverlay').classList.remove('open');
        }

        // Close on backdrop click
        document.getElementByI
        /* --- TOPBAR --- */
        .topbar {
            display: flex;
            align-items: center;
            gap: 16px;
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 14px 28px;
        }

        .brand {
            font-weight: 700;
            font-size: 15px;
            white-space: nowrap;
        }

        .brand span {
            color: var(--muted);
            font-weight: 600;
            margin: 0 4px;
        }

        .search {
            flex: 1;
            max-width: 340px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
            color: var(--muted);
            font-size: 13px;
        }

        .quick-action {
            margin-left: auto;
            background: var(--black);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .user {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .user .name {
            font-weight: 700;
        }

        .user .role {
            color: var(--muted);
            display: block;
            font-size: 12px;
            font-weight: 400;
        }

        .avatar {
            width: 32px;
            height: 32px;
        /* --- TOPBAR --- */
        .topbar {
            display: flex;
            align-items: center;
            gap: 16px;
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 14px 28px;
        }

        .brand {
            font-weight: 700;
            font-size: 15px;
            white-space: nowrap;
        }

        .brand span {
            color: var(--muted);
            font-weight: 600;
            margin: 0 4px;
        }

        .search {
            flex: 1;
            max-width: 340px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
            color: var(--muted);
            font-size: 13px;
        }

        .quick-action {
            margin-left: auto;
            background: var(--black);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .user {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .user .name {
            font-weight: 700;
        }

        .user .role {
            color: var(--muted);
            display: block;
            font-size: 12px;
            font-weight: 400;
        }

        .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #dbe4ff;
        }

        /* --- PAGE --- */
        .page {
            padding: 28px 32px 60px;
            max-width: 1300px;
            margin: 0 auto;
        }

        .page-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        .page-head h1 {
            font-size: 26px;
            margin: 0 0 4px;
        }

        .page-head p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .head-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        /* --- TOPBAR --- */
        .topbar {
            display: flex;
            align-items: center;
            gap: 16px;
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 14px 28px;
        }

        .brand {
            font-weight: 700;
            font-size: 15px;
            white-space: nowrap;
        }

        .brand span {
            color: var(--muted);
            font-weight: 600;
            margin: 0 4px;
        }

        .search {
            flex: 1;
            max-width: 340px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
            color: var(--muted);
            font-size: 13px;
        }

        .quick-action {
            margin-left: auto;
            background: var(--black);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .user {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .user .name {
            font-weight: 700;
        }

        .user .role {
            color: var(--muted);
            display: block;
            font-size: 12px;
            font-weight: 400;
        }

        .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #dbe4ff;
        }

        /* --- PAGE --- */
        .page {
            padding: 28px 32px 60px;
            max-width: 1300px;
            margin: 0 auto;
        }

        .page-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        .page-head h1 {
            font-size: 26px;
            margin: 0 0 4px;
        }

        .page-head p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .head-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn.dark {
            background: var(--black);
            color: #fff;
            border-color: var(--black);
        }

        .tabs {
            display: flex;
            gap: 26px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .tabs a {
            padding: 10px 2px;
            color: var(--muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 2px solid transparent;
        }

        .tabs a.active {
            color: var(--text);


        .btn.dark {
            background: var(--black);
            color: #fff;
            border-color: var(--black);
        }

        .tabs {
            display: flex;
            gap: 26px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .tabs a {
            padding: 10px 2px;
            color: var(--muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 2px solid transparent;
        }

        .tabs a.active {
            color: var(--text);

            border-radius: 50%;
            background: #dbe4ff;
        }

        /* --- PAGE --- */
        .page {
            padding: 28px 32px 60px;
            max-width: 1300px;
            margin: 0 auto;
        }

        .page-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        .page-head h1 {
            font-size: 26px;
            margin: 0 0 4px;
        }

        .page-head p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .head-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn.dark {
            background: var(--black);
            color: #fff;
            border-color: var(--black);
        }

        .tabs {
            display: flex;
            gap: 26px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .tabs a {
            padding: 10px 2px;
            color: var(--muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 2px solid transparent;
        }

        .tabs a.active {
            color: var(--text);
d('newOrderOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeNewOrderModal();
        });

        function updateTierFromCustomerName() {
            // No customer lookup (name is typed). Keep default tier.
            // Guard against missing elements (this page modal doesn't define tierInput/tier).
            const tierInputEl = document.getElementById('tierInput');
            if (!tierInputEl) return;

            const tierEl = document.getElementById('tier');
            const tier = tierInputEl.value || 'Standard';

            if (tierEl) {
                tierEl.textContent = String(tier).toUpperCase() + ' PRICING TIER';
            }
        }


        function toggleDish(card) {
            const id = card.dataset.id;
            const badge = card.querySelector('.qty-badge');

            if (selectedDishes[id]) {
                // Already selected — clicking again increments quantity
                selectedDishes[id].qty += 1;
            } else {
                selectedDishes[id] = {
                    name: card.dataset.name,
                    price: parseFloat(card.dataset.price),
                    qty: 1
                };
                card.classList.add('selected');
            }

            badge.textContent = selectedDishes[id].qty;
            document.getElementById('orderItemsInput').value = JSON.stringify(selectedDishes);
        }

        // Shift+click (or right-click) a selected dish to remove it entirely
        document.querySelectorAll('.dish-card').forEach(function(card) {
            card.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                const id = card.dataset.id;
                if (selectedDishes[id]) {
                    delete selectedDishes[id];
                    card.classList.remove('selected');
                    document.getElementById('orderItemsInput').value = JSON.stringify(selectedDishes);
                }
            });
        });
    </script>

</body>

</html>