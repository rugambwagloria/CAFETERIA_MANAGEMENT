<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$current = basename($_SERVER['PHP_SELF']);
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

</head>

<body>

    <div class="dashboard">

        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- ================= MAIN ================= -->

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

                            <h4><?php echo $_SESSION['user']; ?></h4>

                            <p><?php echo $_SESSION['role']; ?></p>

                        </div>

                    </div>

                </div>

            </header>

            <!-- ================= CONTENT ================= -->

            <main class="content">

                <div class="content-placeholder">

                    <h1>Executive Dashboard</h1>

                    <p>

                        Dashboard widgets will be added here.

                    </p>

                </div>

            </main>

        </div>

    </div>

</body>

</html>