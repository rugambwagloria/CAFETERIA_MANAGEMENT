<?php
if (isset($_GET['logout'])) {
    echo "<div class='success'>You have successfully logged out.</div>";
}

if (isset($_GET['error'])) {
    echo "<div class='error'>Invalid Username or Password.</div>";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Parliament Cafeteria Management System</title>

    <!-- CSS -->
    <link rel="stylesheet" href="css/login.css">

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Font Awesome -->

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

</head>

<body>

    <main class="login-container">



        <!-- ================= HEADER ================= -->

        <header class="card-header">

            <img src="assets/coat.jpeg"
                alt="Uganda Coat of Arms"
                class="logo">

            <h3>REPUBLIC OF UGANDA</h3>

            <h1>
                PARLIAMENT OF UGANDA CAFETERIA MANAGEMENT SYSTEM
            </h1>

            <p>
                Official Internal Canteen & Departmental Billing Ledger Portal
            </p>

        </header>

        <!-- ================= NOTICE ================= -->

        <section class="system-notice">

            <i class="fa-solid fa-circle-info"></i>

            <p>

                This is a classified government utility.
                All authorization attempts, billing transactions
                and meal distributions are securely logged
                within the state audit trails.

            </p>

        </section>

        <!-- ================= LOGIN ================= -->

        <form
            action="login_access.php"
            method="POST"
            class="login-form">

            <!-- Username -->

            <div class="form-group">

                <label for="username">

                    ACCREDITED STAFF USERNAME

                </label>

                <div class="input-box">

                    <i class="fa-solid fa-user"></i>

                    <input

                        type="text"

                        id="username"

                        name="username"

                        placeholder="Enter Username"

                        autocomplete="username"

                        required>

                </div>

            </div>

            <!-- Password -->

            <div class="form-group">

                <label for="password">

                    SECURE SYSTEM PASSWORD

                </label>

                <div class="input-box">

                    <i class="fa-solid fa-lock"></i>

                    <input

                        type="password"

                        id="password"

                        name="password"

                        placeholder="Enter Password"

                        autocomplete="current-password"

                        required>

                </div>

            </div>

            <!-- Options -->

            <div class="form-options">

                <label class="remember">

                    <input
                        type="checkbox"
                        name="remember">

                    Remember My Workstation

                </label>

                <a href="#">
                    Reset Credentials
                </a>

            </div>

            <!-- Login Button -->

            <button
                type="submit"
                class="login-btn">

                <i class="fa-solid fa-shield-halved"></i>

                Authorize Workstation Entry

            </button>

        </form>

        <!-- ================= FOOTER ================= -->

        <footer class="card-footer">

            <hr>

            <p>

                Authorized Cashiers,
                Kitchen Managers,
                Accountants
                and ICT Administrators Only

            </p>

            <small>

                System Version
                <strong>2.4.0 (Stable)</strong>

            </small>

        </footer>



    </main>

</body>

</html>