<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current = basename($_SERVER['PHP_SELF']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/controllers/DailyMenuController.php';

// Create a PDO instance from the DB config (database.php provides $host,$db,$user,$pass)
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    die('Database Connection Failed.');
}

// Simple helpers used in the template
function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(float $v): string
{
    return number_format($v, 0, '.', ',');
}

// Controller handles POST actions and data fetching
$controller = new DailyMenuController($pdo);
$controller->handlePost();

/* ---------------------------------------------------------------------
 * Filters
 * ------------------------------------------------------------------- */
$validTabs = ['all', 'breakfast', 'lunch', 'dinner', 'drinks'];
$activeTab = strtolower($_GET['meal_type'] ?? 'all');
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'all';
}

/* Menu date: defaults to today, but can be overridden e.g. ?menu_date=2026-07-16 */
$menuDate = $_GET['menu_date'] ?? date('Y-m-d');

// Fetch menu data via controller. Controller may return an error message if DB table missing.
[$menu, $items, $pricesByItem, $dbError] = $controller->getMenuData($menuDate, $activeTab);

if (!empty($dbError)) {
    $dbError = htmlspecialchars($dbError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* Icon + label per meal type, matching the screenshots */
$mealMeta = [
    'breakfast' => ['label' => 'BREAKFAST', 'icon' => '☕'],
    'lunch'     => ['label' => 'LUNCH',     'icon' => '☀️'],
    'dinner'    => ['label' => 'DINNER',    'icon' => '🍲'],
    'drinks'    => ['label' => 'DRINKS',    'icon' => '🥤'],
];

/* Tier matrix accent colour: Member of Parliament = blue, Invited Guests = red, rest = default */
function tier_color(string $categoryName): string
{
    $name = strtolower($categoryName);
    if (str_contains($name, 'parliament')) {
        return 'tier-blue';
    }
    if (str_contains($name, 'guest')) {
        return 'tier-red';
    }
    return 'tier-default';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Daily Menu — Uganda Parliament ERP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/dash.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/topbar.css">
    <script src="assets/js/quick-action.js"></script>
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
    :root {
        --bg: #f3f4f6;
        --card: #ffffff;
        --border: #e6e7ea;
        --text: #1c1c1e;
        --muted: #6b7076;
        --blue: #2f5fd6;
        --red: #d6392f;
        --green: #1c9a4b;
        --black: #111214;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background: var(--bg);
        color: var(--text);
    }

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
        border-color: var(--black);
        font-weight: 700;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .card-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 18px 0;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--bg);
        border-radius: 20px;
        padding: 5px 10px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .03em;
        color: var(--muted);
    }

    .stock {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 600;
        color: var(--green);
    }

    .toggle {
        width: 38px;
        height: 22px;
        border-radius: 20px;
        border: none;
        cursor: pointer;
        position: relative;
        background: #c8cbd1;
    }

    .toggle.on {
        background: var(--black);
    }

    .toggle::after {
        content: "";
        position: absolute;
        top: 2px;
        left: 2px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #fff;
        transition: left .15s;
    }

    .toggle.on::after {
        left: 18px;
    }

    .stock form {
        margin: 0;
    }

    .name {
        font-size: 17px;
        font-weight: 700;
        padding: 14px 18px 0;
        line-height: 1.3;
    }

    .price {
        padding: 14px 18px 4px;
        font-size: 22px;
        font-weight: 800;
    }

    .price small {
        font-size: 12px;
        font-weight: 600;
        color: var(--muted);
        margin-left: 6px;
    }

    .matrix {
        margin: 14px 18px 0;
        background: var(--bg);
        border-radius: 10px;
        padding: 12px 14px;
    }

    .matrix-title {
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .05em;
        color: var(--muted);
        margin-bottom: 10px;
    }

    .matrix-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        row-gap: 10px;
        column-gap: 10px;
        font-size: 12px;
    }

    .matrix-grid .cat {
        color: var(--muted);
        margin-bottom: 2px;
    }

    .matrix-grid .amt {
        font-weight: 800;
        font-size: 13px;
    }

    .tier-blue .amt {
        color: var(--blue);
    }

    .tier-red .amt {
        color: var(--red);
    }

    .tier-default .amt {
        color: var(--text);
    }

    .card-actions {
        margin-top: auto;
        display: flex;
        justify-content: flex-end;
        gap: 6px;
        padding: 12px 14px;
    }

    .icon-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--muted);
        text-decoration: none;
    }

    .icon-btn:hover {
        background: var(--bg);
    }

    form.inline {
        margin: 0;
    }

    .empty {
        grid-column: 1/-1;
        text-align: center;
        color: var(--muted);
        padding: 60px 0;
        font-size: 14px;
    }
    </style>
</head>

<body>

    <div class="dashboard">

        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <div class="main">

            <div class="topbar">
                <div class="brand">Uganda Parliament ERP <span>›</span> Daily Menu</div>
                <div class="search">Search documents, orders, cu...</div>
                <button class="quick-action">+ Quick Action</button>
                <div class="user">
                    <div>
                        <span class="name">Sarah Nabachwa</span>
                        <span class="role">Principal Accountant</span>
                    </div>
                    <div class="avatar"></div>
                </div>
            </div>

            <div class="page">

                <div class="page-head">
                    <div>
                        <h1>Parliament Daily Menu Registry</h1>
                        <p>Manage official breakfast, lunch, dinner options and VIP catering menus</p>
                    </div>
                    <div class="head-actions">
                        <a class="btn" href="preview_menu_pdf.php?menu_date=<?= h($menuDate) ?>">👁 Preview Menu PDF</a>
                        <a class="btn" href="archive_menu.php">🗄 Archive Menu</a>
                        <a class="btn dark" href="edit_item.php?menu_date=<?= h($menuDate) ?>">+ Add Menu Item</a>
                    </div>
                </div>

                <div class="tabs">
                    <?php foreach ($validTabs as $tab): ?>
                    <a href="?meal_type=<?= h($tab) ?>&menu_date=<?= h($menuDate) ?>"
                        class="<?= $tab === $activeTab ? 'active' : '' ?>">
                        <?= $tab === 'all' ? 'All' : ucfirst($tab) ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <div class="grid">
                    <?php if (!empty($dbError)): ?>
                    <div class="empty">Database error: <?= $dbError ?>. Import <a
                            href="../database/parliament_cafeteria.sql">database/parliament_cafeteria.sql</a> and try
                        again.</div>
                    <?php elseif (!$menu): ?>
                    <div class="empty">No menu has been set up for <?= h($menuDate) ?> yet.</div>
                    <?php elseif (!$items): ?>
                    <div class="empty">No items in this category yet. Use "Add Menu Item" to create one.</div>
                    <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <?php $meta = $mealMeta[$item['meal_type']]; ?>
                    <div class="card">
                        <div class="card-top">
                            <span class="badge"><?= $meta['icon'] ?> <?= h($meta['label']) ?></span>
                            <div class="stock">
                                In Stock
                                <form class="inline" method="post">
                                    <input type="hidden" name="action" value="toggle_stock">
                                    <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                    <button type="submit" class="toggle <?= $item['in_stock'] ? 'on' : '' ?>"
                                        aria-label="Toggle stock status"></button>
                                </form>
                            </div>
                        </div>

                        <div class="name"><?= h($item['name']) ?></div>

                        <div class="price">
                            UGX <?= money((float) $item['base_price']) ?>
                            <small>Standard VIP</small>
                        </div>

                        <?php if (!empty($pricesByItem[$item['item_id']])): ?>
                        <div class="matrix">
                            <div class="matrix-title">CATEGORY PRICING TIER MATRIX</div>
                            <div class="matrix-grid">
                                <?php foreach ($pricesByItem[$item['item_id']] as $tier): ?>
                                <div class="<?= tier_color($tier['category_name']) ?>">
                                    <div class="cat"><?= h($tier['category_name']) ?>:</div>
                                    <div class="amt">UGX <?= money((float) $tier['price']) ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="card-actions">
                            <a class="icon-btn" href="edit_item.php?id=<?= (int) $item['item_id'] ?>" title="Edit">✎</a>
                            <form class="inline" method="post" onsubmit="return confirm('Delete this menu item?');">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                <button type="submit" class="icon-btn" title="Delete">🗑</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</body>

</html>