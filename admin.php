<?php
session_start();
require_once "db.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$error = "";
$success = "";

// LOGOUT ADMIN
if (isset($_GET["logout"])) {
    unset($_SESSION["admin_logged_in"], $_SESSION["admin_username"]);
    session_destroy();
    header("Location: admin.php");
    exit;
}

// HANDLE ADMIN LOGIN SUBMISSION
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["admin_login"])) {
    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");

    // Tinatanggap na ngayon ang "admin" o "admin@gmail.com" bilang username, at "admin123" sa password
    if (($username === "admin" || strtolower($username) === "admin@gmail.com") && $password === "admin123") {
        session_regenerate_id(true);
        $_SESSION["admin_logged_in"] = true;
        $_SESSION["admin_username"] = $username;
        header("Location: admin.php");
        exit;
    } else {
        $error = "Mali ang username o password ng admin. (Gamitin ang username: admin o admin@gmail.com / password: admin123)";
    }
}

// HANDLE STATUS UPDATES
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {
    if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
        header("Location: admin.php");
        exit;
    }

    $bookingId = filter_var($_POST["booking_id"] ?? "", FILTER_VALIDATE_INT);
    $bookingStatus = trim($_POST["booking_status"] ?? "");
    $paymentStatus = trim($_POST["payment_status"] ?? "");

    if ($bookingId) {
        $stmt = $conn->prepare(
            "UPDATE bookings 
             SET booking_status = ?, payment_status = ? 
             WHERE booking_id = ?"
        );
        $stmt->bind_param("ssi", $bookingStatus, $paymentStatus, $bookingId);

        if ($stmt->execute()) {
            $_SESSION["flash_message"] = "Matagumpay na na-update ang booking #{$bookingId}.";
            $_SESSION["flash_type"] = "success";
        } else {
            $_SESSION["flash_message"] = "Nabigong i-update ang booking.";
            $_SESSION["flash_type"] = "error";
        }
        $stmt->close();
    }

    header("Location: admin.php");
    exit;
}

if (isset($_SESSION["flash_message"])) {
    $message = $_SESSION["flash_message"];
    $messageType = $_SESSION["flash_type"];
    unset($_SESSION["flash_message"], $_SESSION["flash_type"]);
}

// Kuhanin ang mga bookings kung naka-login na ang admin
$result = null;
if (isset($_SESSION["admin_logged_in"]) && $_SESSION["admin_logged_in"] === true) {
    $sql = "SELECT b.booking_id, b.event_type, b.venue_area, b.event_date, b.event_time, 
                   b.guest_count, b.package_name, b.booking_status, b.payment_status,
                   c.full_name, c.email, c.phone
            FROM bookings b
            LEFT JOIN customers c ON b.customer_id = c.customer_id
            ORDER BY b.booking_id DESC";
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CED Event & Catering Services | Admin Panel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-header">
    <a class="brand" href="admin.php">
        CED <span>Admin Panel</span>
    </a>
    <?php if (isset($_SESSION["admin_logged_in"]) && $_SESSION["admin_logged_in"] === true): ?>
        <nav class="nav-links">
            <a class="btn outline small" href="admin.php?logout=1">Log out</a>
        </nav>
    <?php endif; ?>
</header>

<main class="page-wrap">

    <?php if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true): ?>
        <!-- ADMIN LOGIN FORM (KAPAG HINDI PA NAKA-LOGIN) -->
        <section class="auth-layout" style="display: flex; justify-content: center; align-items: center; min-height: 70vh;">
            <section class="auth-card" style="width: 100%; max-width: 450px;">
                <h2>Admin Sign In</h2>
                <p class="muted">Ilagay ang iyong admin credentials para pamahalaan ang mga bookings.</p>

                <?php if ($error !== ""): ?>
                    <div class="alert error"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="admin.php" class="auth-form show" style="display: block;">
                    <input type="hidden" name="admin_login" value="1">

                    <label>Admin Username / Email</label>
                    <input type="text" name="username" placeholder="admin o admin@gmail.com" required>

                    <label>Password</label>
                    <input type="password" name="password" placeholder="admin123" required>

                    <button class="btn primary full" type="submit" style="margin-top: 1rem;">Log In as Admin</button>
                </form>
            </section>
        </section>

    <?php else: ?>
        <!-- ADMIN DASHBOARD (KAPAG NAKA-LOGIN NA) -->
        <section class="page-heading">
            <p class="eyebrow">ADMINISTRATION</p>
            <h1>Manage Event <span>Reservations & Payments</span></h1>
        </section>

        <?php if (!empty($message)): ?>
            <div class="alert <?= e($messageType) ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <section class="content-card">
            <h2>All Bookings</h2>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Event & Area</th>
                            <th>Date & Time</th>
                            <th>Guests</th>
                            <th>Package & Cost</th>
                            <th>Payment Status</th>
                            <th>Booking Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?= e($row["booking_id"]) ?></td>
                                    <td>
                                        <?= e($row["full_name"] ?? "N/A") ?>
                                        <small><?= e($row["email"] ?? "") ?></small>
                                    </td>
                                    <td>
                                        <?= e($row["event_type"]) ?>
                                        <small>Area: <?= e($row["venue_area"]) ?></small>
                                    </td>
                                    <td>
                                        <?= e($row["event_date"]) ?>
                                        <small><?= e(date("g:i A", strtotime($row["event_time"]))) ?></small>
                                    </td>
                                    <td><?= e($row["guest_count"]) ?></td>
                                    <td><?= e($row["package_name"] ?: "—") ?></td>
                                    
                                    <form method="POST" action="admin.php">
                                        <input type="hidden" name="booking_id" value="<?= e($row["booking_id"]) ?>">
                                        <input type="hidden" name="update_status" value="1">

                                        <td>
                                            <select name="payment_status" class="status-select">
                                                <option value="Unpaid" <?= $row["payment_status"] === "Unpaid" ? "selected" : "" ?>>Unpaid</option>
                                                <option value="Paid" <?= $row["payment_status"] === "Paid" ? "selected" : "" ?>>Paid</option>
                                            </select>
                                        </td>

                                        <td>
                                            <select name="booking_status" class="status-select">
                                                <option value="Pending" <?= $row["booking_status"] === "Pending" ? "selected" : "" ?>>Pending</option>
                                                <option value="Approved" <?= $row["booking_status"] === "Approved" ? "selected" : "" ?>>Approved</option>
                                                <option value="Completed" <?= $row["booking_status"] === "Completed" ? "selected" : "" ?>>Completed</option>
                                                <option value="Cancelled" <?= $row["booking_status"] === "Cancelled" ? "selected" : "" ?>>Cancelled</option>
                                                <option value="Rejected" <?= $row["booking_status"] === "Rejected" ? "selected" : "" ?>>Rejected</option>
                                            </select>
                                        </td>

                                        <td>
                                            <button type="submit" class="btn primary small">Update</button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center;">Walang nakitang bookings.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

</main>

<footer class="site-footer">
    © <?= date("Y") ?> CED Event & Catering Services — Admin Panel
</footer>

</body>
</html>