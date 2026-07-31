<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$current = $current ?? basename($_SERVER['PHP_SELF']);
?>



<header class="topbar">

    <!-- Left -->
    <div class="topbar-left">

        <div class="breadcrumb">

            <a href="dashboard.php">

                Uganda Parliament ERP

            </a>

            <i class="fa-solid fa-angle-right"></i>

            <span>

                Food Orders

            </span>

        </div>

    </div>

    <!-- Center -->

    <div class="topbar-center">

        <div class="search-box">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                type="text"
                placeholder="Search documents, orders, customers..."
            >

        </div>

    </div>

    <!-- Right -->

    <div class="topbar-right">

        <div class="quick-action-container">

         
            <button id="quickActionBtn" class="quick-action">
                <i class="fa-solid fa-plus"></i>

                <span>Quick Action</span>

            </button>

            <div id="quickActionDropdown" class="quick-action-dropdown">

                <ul>

                    <li>

                        <a href="create_order.php">

                            <i class="fa-solid fa-utensils"></i>

                            <span>New Food Order</span>

                        </a>

                    </li>

                    <li>

                        <a href="create_invoice.php">

                            <i class="fa-regular fa-file-lines"></i>

                            <span>Generate Invoice</span>

                        </a>

                    </li>

                    <li>

                        <a href="record_payment.php">

                            <i class="fa-solid fa-dollar-sign"></i>

                            <span>Record Payment</span>

                        </a>

                    </li>

                    <li>

                        <a href="daily_menu.php">

                            <i class="fa-solid fa-plus"></i>

                            <span>Add Daily Menu Item</span>

                        </a>

                    </li>

                </ul>

            </div>

        </div>

        <div class="notification">

            <i class="fa-regular fa-bell"></i>

        </div>

        <div class="user">

            <div class="avatar">

                <i class="fa-regular fa-user"></i>

            </div>

            <div class="user-info">

                <span class="name">

                    Sarah Nabachwa

                </span>

                <span class="role">

                    Principal Accountant

                </span>

            </div>

        </div>

    </div>

</header>