<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

// database connection
require_once dirname(__DIR__) . '/config/database.php';

$today = date('Y-m-d');

// Today's total orders
$totalOrdersToday = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = '$today'"))[0];

// Today's revenue
$revenueToday = mysqli_fetch_row(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = '$today' AND status != 'cancelled'"))[0];

// Pending orders
$pendingOrders = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE status = 'pending'"))[0];

// Items in stock
$itemsInStock = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM food_items WHERE in_stock = 1"))[0];

// Out of stock items
$itemsOutOfStock = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM food_items WHERE in_stock = 0"))[0];

// Today's orders by meal type
$mealTypeCounts = [];
$mtResult = mysqli_query($conn, "
    SELECT meal_type, COUNT(*) as cnt, SUM(total_amount) as total
    FROM orders
    WHERE DATE(created_at) = '$today' AND status != 'cancelled'
    GROUP BY meal_type
");
while ($row = mysqli_fetch_assoc($mtResult)) {
    $mealTypeCounts[] = $row;
}

// Recent orders — last 8
$recentOrders = [];
$roResult = mysqli_query($conn, "
    SELECT order_number, customer_name, meal_type, status, total_amount, created_at
    FROM orders
    ORDER BY created_at DESC
    LIMIT 8
");
while ($row = mysqli_fetch_assoc($roResult)) {
    $recentOrders[] = $row;
}

// Top selling items today
$topItems = [];
$tiResult = mysqli_query($conn, "
    SELECT oi.item_name, SUM(oi.quantity) as total_qty, SUM(oi.line_total) as total_revenue
    FROM order_items oi
    JOIN orders o ON o.order_id = oi.order_id
    WHERE DATE(o.created_at) = '$today' AND o.status != 'cancelled'
    GROUP BY oi.item_name
    ORDER BY total_qty DESC
    LIMIT 5
");
while ($row = mysqli_fetch_assoc($tiResult)) {
    $topItems[] = $row;
}

// Weekly revenue — last 7 days
$weeklyRevenue = [];
$wrResult = mysqli_query($conn, "
    SELECT DATE(created_at) as day, SUM(total_amount) as revenue
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status != 'cancelled'
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
while ($row = mysqli_fetch_assoc($wrResult)) {
    $weeklyRevenue[] = $row;
}

// Orders by status today
$ordersByStatus = [];
$osResult = mysqli_query($conn, "
    SELECT status, COUNT(*) as cnt
    FROM orders
    WHERE DATE(created_at) = '$today'
    GROUP BY status
");
while ($row = mysqli_fetch_assoc($osResult)) {
    $ordersByStatus[$row['status']] = $row['cnt'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/dash.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        /* Dashboard widgets */
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.07);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-card .icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .stat-card .icon.blue   { background: #e8f0fe; color: #3b6fd4; }
        .stat-card .icon.green  { background: #e6f9f0; color: #27ae60; }
        .stat-card .icon.orange { background: #fff3e0; color: #e67e22; }
        .stat-card .icon.red    { background: #fdecea; color: #e74c3c; }
        .stat-card .info h3 { font-size: 1.6rem; font-weight: 700; margin: 0; color: #1a1a2e; }
        .stat-card .info p  { font-size: 0.8rem; color: #6b7280; margin: 0; }
        .stat-card .info .sub { font-size: 0.75rem; color: #9ca3af; margin-top: 2px; }

        .dash-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .dash-grid.thirds {
            grid-template-columns: 2fr 1fr;
        }
        .dash-widget {
            background: #fff;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.07);
        }
        .dash-widget h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1a1a2e;
            margin: 0 0 0.25rem 0;
        }
        .dash-widget .subtitle {
            font-size: 0.78rem;
            color: #9ca3af;
            margin: 0 0 1rem 0;
        }
        .orders-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .orders-table th { text-align: left; padding: 0.5rem 0.75rem; color: #6b7280; font-weight: 600; border-bottom: 1px solid #f3f4f6; }
        .orders-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #f9fafb; color: #374151; }
        .orders-table tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
        }
        .badge-pending    { background: #fff3cd; color: #856404; }
        .badge-preparing  { background: #cfe2ff; color: #084298; }
        .badge-ready      { background: #d1ecf1; color: #0c5460; }
        .badge-completed  { background: #d4edda; color: #155724; }
        .badge-cancelled  { background: #f8d7da; color: #721c24; }

        .top-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.83rem;
        }
        .top-item:last-child { border-bottom: none; }
        .top-item .rank {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 700;
            color: #6b7280;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }
        .top-item .rank.gold   { background: #fef3c7; color: #92400e; }
        .top-item .rank.silver { background: #f3f4f6; color: #374151; }
        .top-item .rank.bronze { background: #fde8d8; color: #92400e; }
        .top-item .name { flex: 1; color: #374151; font-weight: 500; }
        .top-item .qty  { color: #6b7280; font-size: 0.78rem; margin-right: 0.5rem; }
        .top-item .amt  { color: #27ae60; font-weight: 600; }

        .meal-type-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        .meal-type-card {
            background: #f9fafb;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            text-align: center;
        }
        .meal-type-card .mt-label { font-size: 0.75rem; color: #6b7280; text-transform: capitalize; }
        .meal-type-card .mt-count { font-size: 1.4rem; font-weight: 700; color: #1a1a2e; }
        .meal-type-card .mt-rev   { font-size: 0.75rem; color: #27ae60; }

        .stock-alert {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            background: #fdecea;
            border-radius: 8px;
            font-size: 0.82rem;
            color: #e74c3c;
            margin-bottom: 0.75rem;
        }
        .stock-alert i { font-size: 1rem; }

        @media (max-width: 1100px) {
            .stat-cards { grid-template-columns: repeat(2, 1fr); }
            .dash-grid, .dash-grid.thirds { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="dashboard">

    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <div class="main">

        <header class="navbar">
            <div class="breadcrumb">
                <span>Uganda Parliament ERP</span>
                <i class="fa-solid fa-angle-right"></i>
                <strong>Executive Dashboard</strong>
            </div>
            <div class="navbar-right">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search...">
                </div>
                <button class="quick-btn">
                    <i class="fa-solid fa-plus"></i>
                    Quick Action
                </button>
                <div class="notification">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <div class="profile">
                    <div class="avatar">
                        <?php echo strtoupper(substr($_SESSION['user'], 0, 1)); ?>
                    </div>
                    <div>
                        <h4><?php echo htmlspecialchars($_SESSION['user']); ?></h4>
                        <p><?php echo htmlspecialchars($_SESSION['role']); ?></p>
                    </div>
                </div>
            </div>
        </header>

        <main class="content">

            <!-- Page title -->
            <div style="margin-bottom:1.25rem;">
                <h2 style="font-size:1.3rem;font-weight:700;color:#1a1a2e;margin:0;">Executive Dashboard</h2>
                <p style="font-size:0.82rem;color:#9ca3af;margin:4px 0 0;">
                    <?php echo date('l, F j, Y'); ?> &mdash; Live cafeteria overview
                </p>
            </div>

            <!-- Stat Cards -->
            <div class="stat-cards">
                <div class="stat-card">
                    <div class="icon blue"><i class="fa-solid fa-cart-shopping"></i></div>
                    <div class="info">
                        <h3><?php echo number_format($totalOrdersToday); ?></h3>
                        <p>Today's Orders</p>
                        <span class="sub"><?php echo ($ordersByStatus['pending'] ?? 0); ?> pending</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon green"><i class="fa-solid fa-coins"></i></div>
                    <div class="info">
                        <h3>UGX <?php echo number_format($revenueToday); ?></h3>
                        <p>Today's Revenue</p>
                        <span class="sub">Completed orders only</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon orange"><i class="fa-solid fa-clock"></i></div>
                    <div class="info">
                        <h3><?php echo number_format($pendingOrders); ?></h3>
                        <p>Pending Orders</p>
                        <span class="sub">Awaiting preparation</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div class="info">
                        <h3><?php echo number_format($itemsOutOfStock); ?></h3>
                        <p>Out of Stock Items</p>
                        <span class="sub"><?php echo $itemsInStock; ?> items available</span>
                    </div>
                </div>
            </div>

            <!-- Out of stock alert -->
            <?php if ($itemsOutOfStock > 0): ?>
            <div class="stock-alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <strong><?php echo $itemsOutOfStock; ?> food item<?php echo $itemsOutOfStock > 1 ? 's are' : ' is'; ?> currently out of stock.</strong>
                <a href="food_items.php" style="margin-left:auto;color:#e74c3c;font-weight:600;text-decoration:none;">View Items →</a>
            </div>
            <?php endif; ?>

            <!-- Weekly Revenue Chart + Meal Type Breakdown -->
            <div class="dash-grid thirds" style="margin-bottom:1rem;">
                <div class="dash-widget">
                    <h4>Weekly Revenue</h4>
                    <p class="subtitle">Last 7 days — completed orders</p>
                    <canvas id="revenueChart" height="120"></canvas>
                </div>
                <div class="dash-widget">
                    <h4>Today by Meal Type</h4>
                    <p class="subtitle">Orders breakdown</p>
                    <div class="meal-type-grid">
                        <?php
                        $mealMap = [];
                        foreach ($mealTypeCounts as $m) {
                            $mealMap[$m['meal_type']] = $m;
                        }
                        $mealTypes = ['breakfast', 'lunch', 'dinner', 'drinks'];
                        foreach ($mealTypes as $mt):
                            $cnt = $mealMap[$mt]['cnt'] ?? 0;
                            $rev = $mealMap[$mt]['total'] ?? 0;
                        ?>
                        <div class="meal-type-card">
                            <div class="mt-label"><?php echo ucfirst($mt); ?></div>
                            <div class="mt-count"><?php echo $cnt; ?></div>
                            <div class="mt-rev">UGX <?php echo number_format($rev); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Orders + Top Items -->
            <div class="dash-grid thirds">
                <div class="dash-widget">
                    <h4>Recent Orders</h4>
                    <p class="subtitle">Latest 8 orders across all meal types</p>
                    <?php if (!empty($recentOrders)): ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Meal</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $o): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($o['order_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($o['customer_name']); ?></td>
                                <td style="text-transform:capitalize;"><?php echo htmlspecialchars($o['meal_type']); ?></td>
                                <td>UGX <?php echo number_format($o['total_amount']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $o['status']; ?>">
                                        <?php echo ucfirst($o['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p style="color:#9ca3af;font-size:0.83rem;text-align:center;padding:2rem 0;">No orders yet today.</p>
                    <?php endif; ?>
                </div>

                <div class="dash-widget">
                    <h4>Top Selling Items Today</h4>
                    <p class="subtitle">By quantity ordered</p>
                    <?php if (!empty($topItems)): ?>
                        <?php foreach ($topItems as $i => $item): ?>
                        <div class="top-item">
                            <div class="rank <?php echo $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : '')); ?>">
                                <?php echo $i + 1; ?>
                            </div>
                            <span class="name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                            <span class="qty"><?php echo $item['total_qty']; ?>x</span>
                            <span class="amt">UGX <?php echo number_format($item['total_revenue']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#9ca3af;font-size:0.83rem;text-align:center;padding:2rem 0;">No sales data for today.</p>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
</div>

<script>
// Weekly Revenue Chart
const weeklyData = <?php
    $labels   = [];
    $revenues = [];
    // fill last 7 days even if no data
    for ($d = 6; $d >= 0; $d--) {
        $day = date('Y-m-d', strtotime("-$d days"));
        $labels[] = date('D j', strtotime($day));
        $found = false;
        foreach ($weeklyRevenue as $w) {
            if ($w['day'] === $day) {
                $revenues[] = (float) $w['revenue'];
                $found = true;
                break;
            }
        }
        if (!$found) $revenues[] = 0;
    }
    echo json_encode(['labels' => $labels, 'revenues' => $revenues]);
?>;

const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: weeklyData.labels,
        datasets: [{
            label: 'Revenue (UGX)',
            data: weeklyData.revenues,
            backgroundColor: 'rgba(59, 111, 212, 0.15)',
            borderColor: '#3b6fd4',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => 'UGX ' + ctx.parsed.y.toLocaleString()
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: val => 'UGX ' + val.toLocaleString(),
                    font: { size: 10 }
                },
                grid: { color: '#f3f4f6' }
            },
            x: {
                ticks: { font: { size: 10 } },
                grid: { display: false }
            }
        }
    }
});
</script>

</body>
</html>