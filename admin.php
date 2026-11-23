
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

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* =================================
           CED ADMIN - PASTEL PINK DESIGN
        ================================= */

        body {
            background:
                radial-gradient(circle at 10% 10%, rgba(255,218,229,.8), transparent 30%),
                radial-gradient(circle at 90% 80%, rgba(242,190,207,.45), transparent 30%),
                linear-gradient(135deg, #fff5f7, #fce8ee, #fffafb);
            background-attachment: fixed;
            color: #59414a;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .admin-sidebar {
            width: 255px;
            min-height: 100vh;
            padding: 30px 18px;
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 100;
            display: flex;
            flex-direction: column;
            color: white;
            background: linear-gradient(180deg, #bd8499, #98677d);
            box-shadow: 5px 0 25px rgba(130, 75, 96, .12);
        }

        .admin-logo {
            text-align: center;
            padding: 12px 5px 35px;
            color: #fff4f6;
            font-family: 'Playfair Display', serif;
            font-size: 25px;
            font-style: italic;
            font-weight: 700;
        }

        .admin-logo small {
            display: block;
            font-family: 'DM Sans', sans-serif;
            font-size: 10px;
            letter-spacing: 3px;
            font-style: normal;
            margin-top: 7px;
            color: #ffe5ed;
        }

        .admin-menu {
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .admin-menu a,
        .admin-logout a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 15px;
            color: #fff4f6;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            transition: .25s;
        }

        .admin-menu a:hover,
        .admin-menu a.active,
        .admin-logout a:hover {
            background: rgba(255,255,255,.2);
        }

        .admin-menu .menu-icon {
            width: 22px;
            text-align: center;
            font-size: 17px;
        }

        .admin-logout {
            margin-top: auto;
            padding-top: 25px;
        }

        /* MAIN AREA */

        .admin-main {
            width: calc(100% - 255px);
            margin-left: 255px;
            padding: 32px;
            min-height: 100vh;
        }

        .admin-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .admin-topbar h2 {
            font-family: 'Playfair Display', serif;
            font-size: 27px;
            margin: 0;
            color: #59414a;
        }

        .admin-topbar p {
            color: #9b858d;
            font-size: 13px;
            margin-top: 4px;
        }

        .admin-user {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,.75);
            padding: 9px 15px;
            border-radius: 30px;
            border: 1px solid #f0dce2;
            font-size: 13px;
            font-weight: 700;
        }

        .admin-user-icon {
            width: 35px;
            height: 35px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #f3d5df;
            color: #9d6178;
        }

        /* WELCOME BANNER */

        .admin-welcome {
            position: relative;
            overflow: hidden;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            color: #704c5b;
            background:
                linear-gradient(100deg, rgba(255,245,248,.97), rgba(255,226,235,.85)),
                url('images/wedding-banner.jpg') center/cover;
            border: 1px solid #f0d5df;
            box-shadow: 0 8px 25px rgba(166,105,125,.08);
        }

        .admin-welcome h1 {
            color: #704c5b;
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            margin-bottom: 7px;
        }

        .admin-welcome p {
            color: #987783;
            font-size: 13px;
        }

        /* STATISTICS */

        .admin-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .admin-stat-card {
            background: rgba(255,250,251,.95);
            border: 1px solid #f0dce2;
            border-radius: 17px;
            padding: 21px;
            box-shadow: 0 7px 22px rgba(166,105,125,.08);
            transition: .25s;
        }

        .admin-stat-card:hover {
            transform: translateY(-4px);
        }

        .admin-stat-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            background: #fce8ee;
            border-radius: 12px;
            font-size: 20px;
            margin-bottom: 14px;
        }

        .admin-stat-card p {
            color: #9b858d;
            font-size: 12px;
            font-weight: 600;
        }

        .admin-stat-card h2 {
            font-family: 'DM Sans', sans-serif;
            color: #59414a;
            font-size: 29px;
            margin: 3px 0 0;
        }

        /* CONTENT PANEL */

        .admin-panel {
            background: rgba(255,250,251,.96);
            border: 1px solid #f0dce2;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(166,105,125,.07);
            margin-bottom: 25px;
        }

        .admin-panel-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .admin-panel-heading h2 {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            margin: 0;
        }

        .admin-panel-heading span {
            color: #a1848e;
            font-size: 12px;
        }

        /* TABLE */

        .admin-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .admin-table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        .admin-table th {
            background: #fcecf1;
            color: #765361;
            padding: 14px;
            font-size: 11px;
            text-align: left;
            white-space: nowrap;
        }

        .admin-table td {
            padding: 14px;
            border-bottom: 1px solid #f1e2e7;
            font-size: 12px;
            vertical-align: middle;
        }

        .admin-table tr:hover td {
            background: #fff5f7;
        }

        .admin-table small {
            display: block;
            color: #a18d94;
            margin-top: 4px;
        }

        .status-form {
            display: flex;
            align-items: center;
            gap: 7px;
            min-width: 180px;
        }

        .status-form select {
            min-width: 105px;
            padding: 8px;
            font-size: 11px;
            border: 1px solid #ead4dd;
            border-radius: 8px;
            background: white;
        }

        .admin-save {
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            color: white;
            background: #c77f98;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }

        .admin-save:hover {
            background: #a96580;
        }

        .admin-alert {
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .admin-alert.error {
            background: #fbe7e8;
            color: #a84e5a;
        }

        .admin-alert.success {
            background: #e8f2e8;
            color: #547a59;
        }

        .admin-empty {
            text-align: center;
            padding: 40px 15px;
            color: #a18d94;
        }

        /* LOGIN PAGE */

        .admin-login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px;
            background:
                radial-gradient(circle at 10% 10%, rgba(255,218,229,.8), transparent 30%),
                radial-gradient(circle at 90% 90%, rgba(242,190,207,.55), transparent 35%),
                linear-gradient(135deg, #fff2f5, #f8dfe7, #fffafb);
        }

        .admin-login-card {
            width: 100%;
            max-width: 440px;
            padding: 40px;
            border-radius: 23px;
            background: rgba(255,250,251,.96);
            border: 1px solid #f0dce2;
            box-shadow: 0 15px 50px rgba(130,75,96,.15);
        }

        .login-crown {
            text-align: center;
            font-size: 35px;
            margin-bottom: 5px;
        }

        .login-brand {
            text-align: center;
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-style: italic;
            color: #875b6c;
            margin-bottom: 25px;
        }

        .admin-login-card h1 {
            text-align: center;
            font-family: 'Playfair Display', serif;
            color: #59414a;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .login-subtitle {
            text-align: center;
            color: #a18d94;
            font-size: 13px;
            margin-bottom: 25px;
        }

        .admin-login-form {
            display: flex;
            flex-direction: column;
            gap: 13px;
        }

        .admin-login-form label {
            font-size: 12px;
            color: #765361;
        }

        .admin-login-form input {
            padding: 13px 15px;
            border: 1px solid #ead4dd;
            border-radius: 10px;
            background: white;
        }

        .admin-login-form input:focus {
            outline: none;
            border-color: #c77f98;
            box-shadow: 0 0 0 3px rgba(199,127,152,.12);
        }

        .login-submit {
            border: none;
            border-radius: 30px;
            padding: 13px;
            margin-top: 10px;
            background: linear-gradient(135deg, #d995aa, #b96f88);
            color: white;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(185,111,136,.2);
        }

        .login-submit:hover {
            background: #a96580;
        }

        .back-website {
            text-align: center;
            margin-top: 23px;
            font-size: 12px;
        }

        .back-website a {
            color: #98677d;
        }

        .admin-footer {
            text-align: center;
            padding: 22px;
            color: #a18d94;
            font-size: 11px;
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
                padding: 15px;
            }

            .admin-layout {
                display: block;
            }

            .admin-logo {
                padding: 5px 5px 15px;
            }

            .admin-menu {
                flex-direction: row;
                flex-wrap: wrap;
            }

            .admin-menu a {
                padding: 9px 11px;
                font-size: 11px;
            }

            .admin-logout {
                margin-top: 10px;
                padding-top: 5px;
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
                padding: 27px;
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
                    ♙ Login to Dashboard
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
            <a href="index.php">← Back to Customer Website</a>
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
                <div class="admin-stat-icon">▦</div>
                <p>Total Bookings</p>
                <h2><?= $totalBookings ?></h2>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-icon">✓</div>
                <p>Approved</p>
                <h2><?= $approvedBookings ?></h2>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-icon">◷</div>
                <p>Pending</p>
                <h2><?= $pendingBookings ?></h2>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-icon">★</div>
                <p>Completed</p>
                <h2><?= $completedBookings ?></h2>
            </div>

        </section>

        <!-- RECENT BOOKINGS -->
        <section class="admin-panel" id="bookings">

            <div class="admin-panel-heading">
                <h2>♧ Recent Bookings</h2>
                <span>All customer reservations</span>
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

            <p style="color:#9b858d;font-size:13px;">
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

            <p style="color:#9b858d;font-size:13px;">
                Logged in as:
                <strong><?= e($_SESSION["admin_username"]) ?></strong>
            </p>
        </section>

        <footer class="admin-footer">
            CED Event & Catering Services &bull; Admin Dashboard
        </footer>

    </main>
</div>

<?php endif; ?>

</body>
</html>
