<?php
session_start();
require_once "db.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$error = "";
$success = "";

$adminCountResult = $conn->query("SELECT COUNT(*) AS total FROM admins");
$adminCount = (int)$adminCountResult->fetch_assoc()["total"];

/* LOGOUT */
if (isset($_GET["logout"])) {
    unset($_SESSION["admin_id"], $_SESSION["admin_username"]);
    header("Location: admin.php");
    exit;
}

/* POST ACTIONS */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    /* CREATE FIRST ADMIN */
    if ($action === "create_admin") {
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($username === "" || strlen($password) < 8) {
            $error = "Enter a username and a password with at least 8 characters.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO admins (username, password) VALUES (?, ?)"
            );
            $stmt->bind_param("ss", $username, $hash);

            if ($stmt->execute()) {
                $success = "Admin account created. You can now log in.";
                $adminCount = 1;
            } else {
                $error = "Could not create admin account.";
            }

            $stmt->close();
        }
    }

    /* ADMIN LOGIN */
    elseif ($action === "login") {
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        $stmt = $conn->prepare(
            "SELECT admin_id, username, password FROM admins WHERE username = ?"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();

        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($password, $admin["password"])) {
            session_regenerate_id(true);

            $_SESSION["admin_id"] = (int)$admin["admin_id"];
            $_SESSION["admin_username"] = $admin["username"];

            header("Location: admin.php");
            exit;
        } else {
            $error = "Incorrect admin username or password.";
        }
    }

    /* UPDATE BOOKING STATUS */
    elseif ($action === "update_status" && isset($_SESSION["admin_id"])) {
        $bookingId = filter_var(
            $_POST["booking_id"] ?? "",
            FILTER_VALIDATE_INT
        );

        $status = $_POST["booking_status"] ?? "";

        $allowed = ["Pending", "Approved", "Rejected", "Completed"];

        if ($bookingId && in_array($status, $allowed, true)) {
            $stmt = $conn->prepare(
                "UPDATE bookings SET booking_status = ? WHERE booking_id = ?"
            );

            $stmt->bind_param("si", $status, $bookingId);
            $stmt->execute();
            $stmt->close();

            header("Location: admin.php?updated=1#bookings");
            exit;
        }
    }
}

/* DASHBOARD DATA */
if (isset($_SESSION["admin_id"])) {

    if (isset($_GET["updated"])) {
        $success = "Booking status updated.";
    }

    $sql = "SELECT
                b.booking_id,
                b.event_type,
                b.venue_area,
                b.event_date,
                b.event_time,
                b.guest_count,
                b.package_name,
                b.booking_status,
                b.payment_status,
                c.full_name,
                c.email,
                c.phone
            FROM bookings b
            INNER JOIN customers c
                ON b.customer_id = c.customer_id
            ORDER BY b.booking_id DESC";

    $bookings = $conn->query($sql);

    /* STATISTICS */
    $statsResult = $conn->query("
        SELECT
            COUNT(*) AS total,
            SUM(booking_status = 'Approved') AS approved,
            SUM(booking_status = 'Pending') AS pending,
            SUM(booking_status = 'Completed') AS completed
        FROM bookings
    ");

    $stats = $statsResult->fetch_assoc();

    $totalBookings = (int)($stats["total"] ?? 0);
    $approvedBookings = (int)($stats["approved"] ?? 0);
    $pendingBookings = (int)($stats["pending"] ?? 0);
    $completedBookings = (int)($stats["completed"] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CED Event & Catering Services | Admin</title>

    <link rel="stylesheet" href="style.css">

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600&family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* =================================
           CED ADMIN - BLUSH & CREAM THEME
        ================================= */

        body {
            background:
                radial-gradient(circle at 8% 12%, rgba(244, 198, 211, .52), transparent 34%),
                radial-gradient(circle at 92% 88%, rgba(234, 217, 184, .34), transparent 36%),
                linear-gradient(135deg, #fff8f6, #fae8ee, #fffdfb);
            background-attachment: fixed;
            color: #554348;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .admin-sidebar {
            width: 248px;
            min-height: 100vh;
            padding: 28px 18px;
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 100;
            display: flex;
            flex-direction: column;
            color: #fff;
            background: linear-gradient(180deg, #c7859a 0%, #a96980 100%);
            box-shadow: 6px 0 26px rgba(130, 75, 96, .12);
        }

        .admin-logo {
            text-align: center;
            padding: 8px 6px 32px;
            color: #fff6f8;
            font-family: 'Cormorant Garamond', serif;
            font-size: 28px;
            font-style: italic;
            font-weight: 700;
            line-height: 1.15;
        }

        .admin-logo small {
            display: block;
            font-family: 'DM Sans', sans-serif;
            font-size: 9.5px;
            letter-spacing: 2.6px;
            font-style: normal;
            margin-top: 7px;
            color: #ffe9f0;
        }

        .admin-menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .admin-menu a,
        .admin-logout a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: #fff6f8;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            transition: .25s ease;
        }

        .admin-menu a:hover,
        .admin-menu a.active,
        .admin-logout a:hover {
            background: rgba(255, 255, 255, .2);
            color: #fff;
        }

        .admin-menu .menu-icon {
            width: 20px;
            text-align: center;
            font-size: 15px;
        }

        .admin-logout {
            margin-top: auto;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, .16);
        }

        /* MAIN AREA */

        .admin-main {
            width: calc(100% - 248px);
            margin-left: 248px;
            padding: 28px 34px;
            min-height: 100vh;
        }

        .admin-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 24px;
        }

        .admin-topbar h2 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            margin: 0;
            color: #6e4f56;
        }

        .admin-topbar p {
            color: #927e84;
            font-size: 13px;
            margin-top: 3px;
        }

        .admin-user {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 253, 251, .92);
            padding: 8px 16px 8px 8px;
            border-radius: 30px;
            border: 1px solid #f0dfe4;
            box-shadow: 0 4px 14px rgba(130, 83, 99, .06);
            font-size: 13px;
            font-weight: 700;
            color: #6e4f56;
        }

        .admin-user-icon {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #f8e9ed;
            color: #b9788e;
            font-size: 15px;
        }

        /* WELCOME BANNER */

        .admin-welcome {
            position: relative;
            overflow: hidden;
            padding: 26px 30px;
            border-radius: 20px;
            margin-bottom: 22px;
            color: #6e4f56;
            background:
                linear-gradient(100deg, rgba(255, 251, 249, .96), rgba(252, 232, 238, .88)),
                url('images/wedding-banner.jpg') center/cover;
            border: 1px solid #f0dfe4;
            box-shadow: 0 10px 26px rgba(130, 83, 99, .07);
        }

        .admin-welcome h1 {
            color: #6e4f56;
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .admin-welcome p {
            color: #927e84;
            font-size: 13px;
        }

        /* STATISTICS */

        .admin-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .admin-stat-card {
            background: rgba(255, 253, 251, .96);
            border: 1px solid #f0dfe4;
            border-radius: 16px;
            padding: 18px 20px;
            box-shadow: 0 8px 22px rgba(130, 83, 99, .07);
            transition: .25s ease;
        }

        .admin-stat-card:hover {
            transform: translateY(-3px);
        }

        .admin-stat-top {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .admin-stat-icon {
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            background: #f8e9ed;
            color: #b9788e;
            border-radius: 8px;
            font-size: 14px;
        }

        .admin-stat-card p {
            color: #927e84;
            font-size: 12px;
            font-weight: 600;
            margin: 0;
        }

        .admin-stat-card h2 {
            font-family: 'Playfair Display', serif;
            color: #554348;
            font-size: 28px;
            font-weight: 700;
            margin: 2px 0 0;
        }

        /* CONTENT PANEL */

        .admin-panel {
            background: rgba(255, 253, 251, .96);
            border: 1px solid #f0dfe4;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 10px 26px rgba(130, 83, 99, .07);
            margin-bottom: 22px;
        }

        .admin-panel-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .admin-panel-heading h2 {
            font-family: 'Playfair Display', serif;
            font-size: 21px;
            color: #6e4f56;
            margin: 0;
        }

        .admin-panel-heading span {
            color: #927e84;
            font-size: 12px;
        }

        /* TABLE */

        .admin-table-wrap {
            width: 100%;
            overflow-x: auto;
            border-radius: 14px;
            border: 1px solid #f0dfe4;
        }

        .admin-table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
            background: #fffdfb;
        }

        .admin-table th {
            background: #f8e9ed;
            color: #6e4f56;
            padding: 13px 15px;
            font-size: 12px;
            font-weight: 700;
            text-align: left;
            white-space: nowrap;
        }

        .admin-table td {
            padding: 13px 15px;
            border-bottom: 1px solid #f2e4e8;
            font-size: 12.5px;
            color: #554348;
            vertical-align: middle;
        }

        .admin-table tr:last-child td {
            border-bottom: none;
        }

        .admin-table tr:hover td {
            background: #fff8fa;
        }

        .admin-table small {
            display: block;
            color: #927e84;
            margin-top: 3px;
        }

        .status-form {
            display: flex;
            align-items: center;
            gap: 7px;
            min-width: 180px;
        }

        .status-form select {
            min-width: 108px;
            padding: 7px 10px;
            margin-bottom: 0;
            font-size: 12px;
            border: 1px solid #ead6dc;
            border-radius: 9px;
            background: #fff;
            color: #554348;
        }

        .admin-save {
            border: none;
            border-radius: 20px;
            padding: 7px 14px;
            color: #fff;
            background: linear-gradient(135deg, #c68da0, #b9788e);
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: none;
        }

        .admin-save:hover {
            background: linear-gradient(135deg, #6e4f56, #b9788e);
            transform: translateY(-1px);
        }

        .admin-alert {
            padding: 13px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .admin-alert.error {
            background: #fff0ef;
            color: #a34f4f;
            border: 1px solid #f3d2cf;
        }

        .admin-alert.success {
            background: #edf6ee;
            color: #4d7350;
            border: 1px solid #cde2cf;
        }

        .admin-empty {
            text-align: center;
            padding: 40px 15px;
            color: #927e84;
        }

        /* LOGIN PAGE */

        .admin-login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px 20px;
            background:
                radial-gradient(circle at 12% 14%, rgba(244, 198, 211, .62), transparent 34%),
                radial-gradient(circle at 88% 86%, rgba(234, 217, 184, .38), transparent 36%),
                linear-gradient(135deg, #fff7f8, #fae7ed, #fffdfb);
        }

        .admin-login-card {
            width: 100%;
            max-width: 410px;
            padding: 36px 34px;
            border-radius: 24px;
            background: rgba(255, 253, 251, .96);
            border: 1px solid #f0dfe4;
            box-shadow: 0 18px 46px rgba(130, 83, 99, .14);
        }

        .login-crown {
            text-align: center;
            font-size: 26px;
            color: #c6a46a;
            margin-bottom: 2px;
        }

        .login-brand {
            text-align: center;
            font-family: 'Cormorant Garamond', serif;
            font-size: 31px;
            font-style: italic;
            font-weight: 700;
            color: #6e4f56;
            margin-bottom: 18px;
        }

        .admin-login-card h1 {
            text-align: center;
            font-family: 'Playfair Display', serif;
            color: #554348;
            font-size: 28px;
            margin-bottom: 4px;
        }

        .login-subtitle {
            text-align: center;
            color: #927e84;
            font-size: 13px;
            margin-bottom: 22px;
        }

        .admin-login-form {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .admin-login-form label {
            font-size: 12px;
            color: #6e4f56;
            margin-bottom: 5px;
        }

        .admin-login-form input {
            padding: 12px 15px;
            margin-bottom: 14px;
            border: 1px solid #ead6dc;
            border-radius: 12px;
            background: #fffdfb;
        }

        .admin-login-form input:focus {
            outline: none;
            border-color: #c68da0;
            box-shadow: 0 0 0 3px rgba(198, 141, 160, .15);
        }

        .login-submit {
            width: 100%;
            border: none;
            border-radius: 30px;
            padding: 13px;
            margin-top: 6px;
            background: linear-gradient(135deg, #c68da0, #b9788e);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(185, 120, 142, .28);
        }

        .login-submit:hover {
            background: linear-gradient(135deg, #6e4f56, #b9788e);
        }

        .back-website {
            text-align: center;
            margin-top: 20px;
            font-size: 12.5px;
        }

        .back-website a {
            color: #927e84;
            font-weight: 500;
        }

        .back-website a:hover {
            color: #b9788e;
        }

        .admin-footer {
            text-align: center;
            padding: 20px;
            color: #927e84;
            font-size: 12px;
        }

        /* RESPONSIVE */

        @media (max-width: 1050px) {
            .admin-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                position: relative;
                width: 100%;
                min-height: auto;
                padding: 16px;
            }

            .admin-layout {
                display: block;
            }

            .admin-logo {
                padding: 4px 4px 14px;
            }

            .admin-menu {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
            }

            .admin-menu a {
                padding: 9px 12px;
                font-size: 12px;
            }

            .admin-logout {
                margin-top: 10px;
                padding-top: 10px;
            }

            .admin-main {
                width: 100%;
                margin-left: 0;
                padding: 20px;
            }

            .admin-topbar {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 500px) {
            .admin-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .admin-stat-card {
                padding: 15px;
            }

            .admin-stat-card h2 {
                font-size: 24px;
            }

            .admin-welcome {
                padding: 22px;
            }

            .admin-welcome h1 {
                font-size: 25px;
            }

            .admin-panel {
                padding: 16px;
            }

            .admin-login-card {
                padding: 28px 22px;
            }
        }
    </style>
</head>

<body>

<?php if (!isset($_SESSION["admin_id"])): ?>

<!-- =================================
     ADMIN LOGIN
================================= -->

<div class="admin-login-page">
    <section class="admin-login-card">

        <div class="login-crown">♛</div>
        <div class="login-brand">CED Event & Catering</div>

        <?php if ($adminCount === 0): ?>

            <h1>Create Admin</h1>
            <p class="login-subtitle">
                Set up your first administrator account
            </p>

            <form method="POST" class="admin-login-form">
                <input type="hidden" name="action" value="create_admin">

                <label>Admin Username</label>
                <input
                    type="text"
                    name="username"
                    maxlength="100"
                    placeholder="Enter username"
                    required
                >

                <label>Password</label>
                <input
                    type="password"
                    name="password"
                    minlength="8"
                    placeholder="Create password (at least 8 characters)"
                    required
                >

                <button type="submit" class="login-submit">
                    Create Admin Account
                </button>
            </form>

        <?php else: ?>

            <h1>Admin Login</h1>
            <p class="login-subtitle">
                Access your dashboard
            </p>

            <form method="POST" class="admin-login-form">
                <input type="hidden" name="action" value="login">

                <label>Username</label>
                <input
                    type="text"
                    name="username"
                    placeholder="Enter username"
                    required
                >

                <label>Password</label>
                <input
                    type="password"
                    name="password"
                    placeholder="Enter password"
                    required
                >

                <button type="submit" class="login-submit">
                    → Login
                </button>
            </form>

        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="admin-alert error" style="margin-top:18px;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="admin-alert success" style="margin-top:18px;">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <div class="back-website">
            <a href="index.php">← Back to Website</a>
        </div>

    </section>
</div>

<?php else: ?>

<!-- =================================
     ADMIN DASHBOARD
================================= -->

<div class="admin-layout">

    <!-- SIDEBAR -->
    <aside class="admin-sidebar">

        <div class="admin-logo">
            ♛ CED Events
            <small>EVENT & CATERING SERVICES</small>
        </div>

        <nav class="admin-menu">
            <a href="#dashboard" class="active">
                <span class="menu-icon">⌂</span>
                Dashboard
            </a>

            <a href="#bookings">
                <span class="menu-icon">▣</span>
                Manage Bookings
            </a>

            <a href="#events">
                <span class="menu-icon">☆</span>
                Manage Events
            </a>

            <a href="#settings">
                <span class="menu-icon">⚙</span>
                Settings
            </a>
        </nav>

        <div class="admin-logout">
            <a href="admin.php?logout=1">
                <span class="menu-icon">⇥</span>
                Logout
            </a>
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="admin-main">

        <!-- TOPBAR -->
        <header class="admin-topbar" id="dashboard">
            <div>
                <h2>Dashboard</h2>
                <p>Welcome back, <?= e($_SESSION["admin_username"]) ?>!</p>
            </div>

            <div class="admin-user">
                <span class="admin-user-icon">♙</span>
                <?= e($_SESSION["admin_username"]) ?>
            </div>
        </header>

        <?php if ($success !== ""): ?>
            <div class="admin-alert success">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="admin-alert error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- WELCOME BANNER -->
        <section class="admin-welcome">
            <h1>Welcome to CED Events!</h1>
            <p>
                Manage your bookings and make every celebration memorable.
            </p>
        </section>

        <!-- STATISTICS -->
        <section class="admin-stats">

            <div class="admin-stat-card">
                <div class="admin-stat-top">
                    <span class="admin-stat-icon">▦</span>
                    <p>Total Bookings</p>
                </div>
                <h2><?= $totalBookings ?></h2>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-top">
                    <span class="admin-stat-icon">✓</span>
                    <p>Approved</p>
                </div>
                <h2><?= $approvedBookings ?></h2>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-top">
                    <span class="admin-stat-icon">◷</span>
                    <p>Pending</p>
                </div>
                <h2><?= $pendingBookings ?></h2>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-top">
                    <span class="admin-stat-icon">★</span>
                    <p>Completed</p>
                </div>
                <h2><?= $completedBookings ?></h2>
            </div>

        </section>

        <!-- RECENT BOOKINGS -->
        <section class="admin-panel" id="bookings">

            <div class="admin-panel-heading">
                <h2>♧ Recent Bookings</h2>
                <span>View All →</span>
            </div>

            <?php if ($bookings->num_rows === 0): ?>

                <div class="admin-empty">
                    <h3>No bookings yet</h3>
                    <p>Customer bookings will appear here.</p>
                </div>

            <?php else: ?>

                <div class="admin-table-wrap">
                    <table class="admin-table">

                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th>Event</th>
                                <th>Venue</th>
                                <th>Date & Time</th>
                                <th>Guests</th>
                                <th>Payment</th>
                                <th>Status / Action</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php while ($row = $bookings->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <strong><?= e($row["full_name"]) ?></strong>
                                    <small><?= e($row["email"]) ?></small>
                                </td>

                                <td>
                                    <?= e($row["event_type"]) ?>
                                    <small><?= e($row["package_name"] ?: "No package") ?></small>
                                </td>

                                <td>
                                    <?= e($row["venue_area"]) ?>
                                </td>

                                <td>
                                    <?= e($row["event_date"]) ?>
                                    <small>
                                        <?= e(date("g:i A", strtotime($row["event_time"]))) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= e($row["guest_count"]) ?>
                                </td>

                                <td>
                                    <?= e($row["payment_status"] ?: "Unpaid") ?>
                                </td>

                                <td>
                                    <form method="POST" class="status-form">

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="update_status"
                                        >

                                        <input
                                            type="hidden"
                                            name="booking_id"
                                            value="<?= (int)$row["booking_id"] ?>"
                                        >

                                        <select name="booking_status">
                                            <?php foreach (
                                                ["Pending", "Approved", "Rejected", "Completed"]
                                                as $status
                                            ): ?>
                                                <option
                                                    value="<?= e($status) ?>"
                                                    <?= ($row["booking_status"] === $status ? "selected" : "") ?>
                                                >
                                                    <?= e($status) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button type="submit" class="admin-save">
                                            Save
                                        </button>

                                    </form>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </section>

        <!-- EVENTS INFORMATION -->
        <section class="admin-panel" id="events">
            <div class="admin-panel-heading">
                <h2>☆ Event Management</h2>
            </div>

            <p style="color:#927e84;font-size:13px;">
                Review customer event types through the bookings table.
                Event catalog management can be added when an events table
                is available in your database.
            </p>
        </section>

        <!-- SETTINGS -->
        <section class="admin-panel" id="settings">
            <div class="admin-panel-heading">
                <h2>⚙ Admin Settings</h2>
            </div>

            <p style="color:#927e84;font-size:13px;">
                Logged in as:
                <strong><?= e($_SESSION["admin_username"]) ?></strong>
            </p>
        </section>

        <footer class="admin-footer">
            CED Event & Catering Services • Admin Dashboard
        </footer>

    </main>
</div>

<?php endif; ?>

</body>
</html>