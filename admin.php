
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

if (isset($_GET["logout"])) {
    unset($_SESSION["admin_id"], $_SESSION["admin_username"]);
    header("Location: admin.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "create_admin") {
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($username === "" || strlen($password) < 8) {
            $error = "Enter a username and a password with at least 8 characters.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hash);

            if ($stmt->execute()) {
                $success = "Admin account created. You can now log in.";
                $adminCount = 1;
            } else {
                $error = "Could not create admin account.";
            }

            $stmt->close();
        }

    } elseif ($action === "login") {
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        $stmt = $conn->prepare("SELECT admin_id, username, password FROM admins WHERE username = ?");
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

    } elseif ($action === "update_status" && isset($_SESSION["admin_id"])) {
        $bookingId = filter_var($_POST["booking_id"] ?? "", FILTER_VALIDATE_INT);
        $status = $_POST["booking_status"] ?? "";
        $allowed = ["Pending", "Approved", "Rejected", "Completed"];

        if ($bookingId && in_array($status, $allowed, true)) {
            $stmt = $conn->prepare("UPDATE bookings SET booking_status = ? WHERE booking_id = ?");
            $stmt->bind_param("si", $status, $bookingId);
            $stmt->execute();
            $stmt->close();

            header("Location: admin.php?updated=1");
            exit;
        }

    } elseif ($action === "delete_booking" && isset($_SESSION["admin_id"])) {
        $bookingId = filter_var($_POST["booking_id"] ?? "", FILTER_VALIDATE_INT);

        if ($bookingId && $bookingId > 0) {
            $stmt = $conn->prepare("DELETE FROM bookings WHERE booking_id = ?");
            $stmt->bind_param("i", $bookingId);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $stmt->close();
                header("Location: admin.php?deleted=1");
                exit;
            } else {
                $error = "Unable to delete booking. Please try again.";
            }

            $stmt->close();
        } else {
            $error = "Invalid booking ID.";
        }
    }
}

if (isset($_SESSION["admin_id"])) {

    if (isset($_GET["updated"])) {
        $success = "Booking status updated.";
    }

    if (isset($_GET["deleted"])) {
        $success = "Booking successfully deleted.";
    }

    $statsResult = $conn->query("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(booking_status = 'Pending'), 0) AS pending,
            COALESCE(SUM(booking_status = 'Approved'), 0) AS approved,
            COALESCE(SUM(booking_status = 'Completed'), 0) AS completed
        FROM bookings
    ");

    $stats = $statsResult->fetch_assoc();

    $sql = "SELECT b.booking_id, b.event_type, b.venue_area, b.event_date,
                   b.event_time, b.guest_count, b.package_name,
                   b.booking_status, b.payment_status,
                   c.full_name, c.email, c.phone
            FROM bookings b
            INNER JOIN customers c ON b.customer_id = c.customer_id
            ORDER BY b.booking_id DESC";

    $bookings = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Golden Moments | Admin Dashboard</title>

    <link rel="stylesheet" href="style.css">

    <style>
        :root {
            --gold: #b68a45;
            --gold-dark: #987039;
            --cream: #faf7f0;
            --white: #ffffff;
            --dark: #33291f;
            --muted: #887f73;
            --border: #eee6d9;
            --shadow: 0 8px 28px rgba(65, 48, 27, 0.07);
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: var(--cream);
            color: var(--dark);
            margin: 0;
            font-family: Arial, sans-serif;
        }

        .admin-header {
            background: var(--white);
            padding: 17px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .admin-brand {
            font-family: Georgia, serif;
            font-size: 25px;
            font-weight: bold;
            color: var(--dark);
            text-decoration: none;
        }

        .admin-brand span {
            color: var(--gold);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .admin-welcome {
            font-size: 13px;
            color: var(--muted);
        }

        .logout-btn {
            text-decoration: none;
            color: var(--gold-dark);
            border: 1px solid var(--gold);
            padding: 9px 17px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: bold;
        }

        .logout-btn:hover {
            background: var(--gold);
            color: white;
        }

        .dashboard {
            max-width: 1450px;
            margin: auto;
            padding: 35px 5%;
        }

        .dashboard-heading {
            margin-bottom: 28px;
        }

        .eyebrow {
            color: var(--gold-dark);
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }

        .dashboard-heading h1 {
            font-family: Georgia, serif;
            font-size: 34px;
            margin: 0 0 9px;
        }

        .dashboard-heading h1 span {
            color: var(--gold);
        }

        .dashboard-heading p {
            color: var(--muted);
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--white);
            padding: 23px;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow);
        }

        .stat-label {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 12px;
        }

        .stat-number {
            font-family: Georgia, serif;
            font-size: 32px;
            font-weight: bold;
            color: var(--dark);
        }

        .stat-card.total {
            border-top: 4px solid var(--gold);
        }

        .stat-card.pending {
            border-top: 4px solid #d6a34b;
        }

        .stat-card.approved {
            border-top: 4px solid #6c9b79;
        }

        .stat-card.completed {
            border-top: 4px solid #7188a5;
        }

        .bookings-panel {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 13px;
            box-shadow: var(--shadow);
            padding: 25px;
        }

        .panel-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }

        .panel-heading h2 {
            font-family: Georgia, serif;
            font-size: 23px;
            margin: 0;
        }

        .booking-count {
            color: var(--muted);
            font-size: 12px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .booking-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1050px;
        }

        .booking-table th {
            text-align: left;
            background: #faf7f1;
            color: #756b5d;
            padding: 15px 12px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .5px;
            white-space: nowrap;
        }

        .booking-table td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            vertical-align: middle;
        }

        .booking-table tbody tr:hover {
            background: #fdfbf7;
        }

        .booking-table small {
            display: block;
            color: var(--muted);
            margin-top: 5px;
            font-size: 11px;
        }

        .booking-id {
            font-weight: bold;
            color: var(--gold-dark);
        }

        .status-form {
            display: flex;
            align-items: center;
            gap: 7px;
            margin: 0;
        }

        .status-form select {
            padding: 9px 10px;
            border: 1px solid var(--border);
            border-radius: 7px;
            background: white;
            color: var(--dark);
            font-size: 12px;
        }

        .save-btn,
        .delete-btn {
            border: none;
            border-radius: 7px;
            padding: 9px 13px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
        }

        .save-btn {
            background: var(--gold);
            color: white;
        }

        .save-btn:hover {
            background: var(--gold-dark);
        }

        .delete-btn {
            background: #fff0ee;
            color: #b4473e;
        }

        .delete-btn:hover {
            background: #b4473e;
            color: white;
        }

        .payment-label {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #f4f0e8;
            color: #786746;
            font-size: 11px;
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 45px 15px;
            color: var(--muted);
        }

        .alert {
            padding: 13px 17px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .alert.success {
            background: #edf7ee;
            color: #397449;
            border: 1px solid #cfe7d2;
        }

        .alert.error {
            background: #fff0ee;
            color: #a83d35;
            border: 1px solid #f1d0cc;
        }

        .login-area {
            max-width: 460px;
            margin: 65px auto;
            padding: 0 18px;
        }

        .login-card {
            background: white;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 35px;
            border-radius: 14px;
        }

        .login-card h1 {
            font-family: Georgia, serif;
            margin: 8px 0;
        }

        .login-card .muted {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 24px;
        }

        .login-card label {
            display: block;
            margin: 15px 0 7px;
            font-size: 13px;
            font-weight: bold;
        }

        .login-card input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 7px;
            font-size: 14px;
        }

        .login-submit {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: var(--gold);
            color: white;
            font-weight: bold;
            margin-top: 22px;
            cursor: pointer;
        }

        .login-submit:hover {
            background: var(--gold-dark);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 22px;
            color: var(--gold-dark);
            font-size: 13px;
            text-decoration: none;
        }

        .admin-footer {
            text-align: center;
            padding: 25px;
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 850px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .dashboard {
                padding: 28px 4%;
            }
        }

        @media (max-width: 520px) {
            .admin-header {
                padding: 15px 4%;
            }

            .admin-brand {
                font-size: 20px;
            }

            .admin-welcome {
                display: none;
            }

            .dashboard-heading h1 {
                font-size: 27px;
            }

            .stats-grid {
                gap: 10px;
            }

            .stat-card {
                padding: 17px;
            }

            .stat-number {
                font-size: 27px;
            }

            .bookings-panel {
                padding: 17px;
            }

            .panel-heading h2 {
                font-size: 20px;
            }
        }
    </style>
</head>

<body>

<header class="admin-header">
    <a class="admin-brand" href="admin.php">
        Golden <span>Moments</span>
    </a>

    <?php if (isset($_SESSION["admin_id"])): ?>
        <div class="header-right">
            <span class="admin-welcome">
                Welcome, <?= e($_SESSION["admin_username"]) ?>
            </span>

            <a class="logout-btn" href="admin.php?logout=1">Log out</a>
        </div>
    <?php endif; ?>
</header>

<?php if (!isset($_SESSION["admin_id"])): ?>

    <main class="login-area">
        <section class="login-card">
            <p class="eyebrow">ADMINISTRATION</p>

            <?php if ($adminCount === 0): ?>

                <h1>Create First Admin</h1>
                <p class="muted">Set up your administrator account to manage bookings.</p>

                <form method="POST">
                    <input type="hidden" name="action" value="create_admin">

                    <label>Admin Username</label>
                    <input type="text" name="username" maxlength="100" required>

                    <label>Password</label>
                    <input type="password" name="password" minlength="8" required>

                    <button class="login-submit" type="submit">
                        Create Admin Account
                    </button>
                </form>

            <?php else: ?>

                <h1>Admin Login</h1>
                <p class="muted">Log in to manage customer bookings and event requests.</p>

                <form method="POST">
                    <input type="hidden" name="action" value="login">

                    <label>Username</label>
                    <input type="text" name="username" required>

                    <label>Password</label>
                    <input type="password" name="password" required>

                    <button class="login-submit" type="submit">
                        Log In
                    </button>
                </form>

            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <div class="alert error"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert success"><?= e($success) ?></div>
            <?php endif; ?>

            <a class="back-link" href="index.php">← Back to customer website</a>
        </section>
    </main>

<?php else: ?>

    <main class="dashboard" id="overview">

        <section class="dashboard-heading">
            <p class="eyebrow">ADMINISTRATION / OVERVIEW</p>
            <h1>Booking <span>Dashboard</span></h1>
            <p>Manage event reservations and monitor customer booking requests.</p>
        </section>

        <?php if ($success !== ""): ?>
            <div class="alert success"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="stats-grid">

            <div class="stat-card total">
                <div class="stat-label">Total Bookings</div>
                <div class="stat-number"><?= (int)$stats["total"] ?></div>
            </div>

            <div class="stat-card pending">
                <div class="stat-label">Pending Requests</div>
                <div class="stat-number"><?= (int)$stats["pending"] ?></div>
            </div>

            <div class="stat-card approved">
                <div class="stat-label">Approved Bookings</div>
                <div class="stat-number"><?= (int)$stats["approved"] ?></div>
            </div>

            <div class="stat-card completed">
                <div class="stat-label">Completed Events</div>
                <div class="stat-number"><?= (int)$stats["completed"] ?></div>
            </div>

        </section>

        <section class="bookings-panel" id="bookings">

            <div class="panel-heading">
                <h2>All Bookings</h2>
                <span class="booking-count">
                    <?= (int)$stats["total"] ?> booking records
                </span>
            </div>

            <?php if (!$bookings || $bookings->num_rows === 0): ?>

                <div class="empty-state">
                    <h3>No bookings yet</h3>
                    <p>Customer bookings will appear here.</p>
                </div>

            <?php else: ?>

                <div class="table-wrap">
                    <table class="booking-table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Event & Venue</th>
                                <th>Date & Time</th>
                                <th>Guests</th>
                                <th>Package</th>
                                <th>Payment</th>
                                <th>Booking Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php while ($row = $bookings->fetch_assoc()): ?>

                            <tr>
                                <td class="booking-id">
                                    #<?= (int)$row["booking_id"] ?>
                                </td>

                                <td>
                                    <strong><?= e($row["full_name"]) ?></strong>
                                    <small><?= e($row["email"]) ?></small>
                                    <small><?= e($row["phone"] ?: "No phone") ?></small>
                                </td>

              
