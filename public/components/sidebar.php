<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$current = $current ?? basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">

    <div class="logo">

        <img src="assets/coat.jpeg" alt="Coat of Arms">

        <div>

            <h3>PARLIAMENT OF UGANDA</h3>

            <p>Cafeteria Management</p>

        </div>

    </div>

    <div class="workspace">

        <h5>SELECT WORKSPACE</h5>

        <div class="workspace-buttons">

            <button class="active">ADMIN ERP</button>

            <button>MP PORTAL</button>

        </div>

    </div>

    <div class="menu-title">OPERATIONS CONSOLE</div>

    <ul class="menu">
        <li class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">
            <a href="dashboard.php"><i class="fa-solid fa-table-columns"></i>Dashboard</a>
        </li>

        <li class="<?= $current === 'food_orders.php' ? 'active' : '' ?>">
            <a href="food_orders.php"><i class="fa-solid fa-utensils"></i>Food Orders</a>
        </li>

        <li class="<?= $current === 'daily_menu.php' ? 'active' : '' ?>">
            <a href="daily_menu.php"><i class="fa-solid fa-book-open"></i>Daily Menu</a>
        </li>

        <li class="<?= $current === 'kitchen_dashboard.php' ? 'active' : '' ?>">
            <a href="kitchen_dashboard.php"><i class="fa-solid fa-kitchen-set"></i>Kitchen Dashboard</a>
        </li>

        <li class="<?= $current === 'departments.php' ? 'active' : '' ?>">
            <a href="departments.php"><i class="fa-solid fa-building"></i>Departments</a>
        </li>

        <li class="<?= $current === 'customers.php' ? 'active' : '' ?>">
            <a href="customers.php"><i class="fa-solid fa-users"></i>Customers</a>
        </li>

        <li class="<?= $current === 'customer_categories.php' ? 'active' : '' ?>">
            <a href="customer_categories.php"><i class="fa-solid fa-layer-group"></i>Customer Categories</a>
        </li>

        <li class="<?= $current === 'meal_coupons.php' ? 'active' : '' ?>">
            <a href="meal_coupons.php"><i class="fa-solid fa-ticket"></i>Meal Coupons</a>
        </li>

        <li class="<?= $current === 'invoices.php' ? 'active' : '' ?>">
            <a href="invoices.php"><i class="fa-solid fa-file-invoice"></i>Invoices</a>
        </li>

        <li class="<?= $current === 'payments.php' ? 'active' : '' ?>">
            <a href="payments.php"><i class="fa-solid fa-credit-card"></i>Payments</a>
        </li>

        <li class="<?= $current === 'credit_accounts.php' ? 'active' : '' ?>">
            <a href="credit_accounts.php"><i class="fa-solid fa-wallet"></i>Credit Accounts</a>
        </li>

        <li class="<?= $current === 'catering_management.php' ? 'active' : '' ?>">
            <a href="catering_management.php"><i class="fa-solid fa-calendar-days"></i>Catering Management</a>
        </li>

        <li class="<?= $current === 'reports.php' ? 'active' : '' ?>">
            <a href="reports.php"><i class="fa-solid fa-chart-column"></i>Reports</a>
        </li>

        <li class="<?= $current === 'audit_logs.php' ? 'active' : '' ?>">
            <a href="audit_logs.php"><i class="fa-solid fa-file-shield"></i>Audit Logs</a>
        </li>

        <li class="<?= $current === 'user_management.php' ? 'active' : '' ?>">
            <a href="user_management.php"><i class="fa-solid fa-user-gear"></i>User Management</a>
        </li>

        <li class="<?= $current === 'settings.php' ? 'active' : '' ?>">
            <a href="settings.php"><i class="fa-solid fa-gear"></i>Settings</a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i>
            Logout System
        </a>
    </div>

</aside>
<!-- Expected output: the dashboard should have the following  components
Sidebar
│
├── Logo
├── Workspace Selector
├── Navigation Title
├── Navigation Links
├── Bottom Information
└── Logout 
The tabs existing are Dashboard, Food orders, Daily Menus, kitchen dashboard, Departments, Customers, Customer categories, 
Meal coupons, Inovices, Payments, Credit Accounts, Catering Management, Reports, Audit LOgs, User Management, Settings
Logout-->