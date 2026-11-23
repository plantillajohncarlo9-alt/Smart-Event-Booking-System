
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
            }

            $stmt->close();
            $error = "Could not delete booking.";
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

    $sql = "SELECT b.booking_id, b.event_type, b.venue_area, b.event_date, b.event_time,
                   b.guest_count, b.package_name, b.booking_status, b.payment_status,
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
    <title>Golden Moments | Admin</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

<header class="site-header">
    <a class="brand" href="admin.php">Golden <span>Moments</span></a>

    <?php if (isset($_SESSION["admin_id"])): ?>
        <nav class="nav-links">
            <span class="welcome-user">Admin: <?= e($_SESSION["admin_username"]) ?></span>
            <a class="btn outline small" href="admin.php?logout=1">Log out</a>
        </nav>
    <?php endif; ?>
</header>

<main class="page-wrap">

<?php if (!isset($_SESSION["admin_id"])): ?>

    <section class="auth-card admin-card">
        <p class="eyebrow">ADMIN AREA</p>

        <?php if ($adminCount === 0): ?>

            <h1>Create first admin</h1>
            <p class="muted">No admin account exists yet. Create the first one to protect the dashboard.</p>

            <form method="POST" class="auth-form show">
                <input type="hidden" name="action" value="create_admin">

                <label>Admin username</label>
                <input type="text" name="username" maxlength="100" required>

                <label>Password</label>
                <input type="password" name="password" minlength="8" required>

                <button class="btn primary full" type="submit">Create admin account</button>
            </form>

        <?php else: ?>

            <h1>Admin login</h1>
            <p class="muted">Log in to manage customer bookings.</p>

            <form method="POST" class="auth-form show">
                <input type="hidden" name="action" value="login">

                <label>Username</label>
                <input type="text" name="username" required>

                <label>Password</label>
                <input type="password" name="password" required>

                <button class="btn primary full" type="submit">Log in</button>
            </form>

        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="alert success"><?= e($success) ?></div>
        <?php endif; ?>

        <p class="form-switch">
            <a href="index.php">Back to customer website</a>
        </p>
    </section>

<?php else: ?>

    <section class="page-heading">
        <p class="eyebrow">MANAGEMENT</p>
        <h1>Booking <span>dashboard</span></h1>
        <p>Review customer requests and update their booking status.</p>
    </section>

    <?php if ($success !== ""): ?>
        <div class="alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="content-card">

        <h2>All bookings</h2>

        <?php if ($bookings->num_rows === 0): ?>

            <p class="muted">No bookings have been submitted yet.</p>

        <?php else: ?>

            <div class="table-wrap">

                <table>
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Event details</th>
                            <th>Date & time</th>
                            <th>Guests</th>
                            <th>Payment</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php while ($row = $bookings->fetch_assoc()): ?>

                        <tr>

                            <td><?= e($row["full_name"]) ?></td>

                            <td>
                                <?= e($row["email"]) ?>
                                <small><?= e($row["phone"] ?: "No phone") ?></small>
                            </td>

                            <td>
                                <?= e($row["event_type"]) ?>
                                <small>
                                    <?= e($row["venue_area"]) ?> -
                                    <?= e($row["package_name"] ?: "No package") ?>
                                </small>
                            </td>

                            <td>
                                <?= e($row["event_date"]) ?>
                                <small>
                                    <?= e(date("g:i A", strtotime($row["event_time"]))) ?>
                                </small>
                            </td>

                            <td><?= e($row["guest_count"]) ?></td>

                            <td><?= e($row["payment_status"] ?: "Unpaid") ?></td>

                            <td>

                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">

                                    <!-- UPDATE BOOKING STATUS -->
                                    <form method="POST" class="status-form">

                                        <input type="hidden" name="action" value="update_status">

                                        <input type="hidden" name="booking_id"
                                               value="<?= (int)$row["booking_id"] ?>">

                                        <select name="booking_status">

                                            <?php foreach (["Pending", "Approved", "Rejected", "Completed"] as $status): ?>

                                                <option value="<?= e($status) ?>"
                                                    <?= ($row["booking_status"] === $status ? "selected" : "") ?>>
                                                    <?= e($status) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                        <button class="btn primary small" type="submit">
                                            Save
                                        </button>

                                    </form>

                                    <!-- DELETE BOOKING -->
                                    <form method="POST"
                                          onsubmit="return confirm('Are you sure you want to delete this booking? This action cannot be undone.');">

                                        <input type="hidden" name="action" value="delete_booking">

                                        <input type="hidden" name="booking_id"
                                               value="<?= (int)$row["booking_id"] ?>">

                                        <button type="submit"
                                                class="btn small"
                                                style="background: #dc2626; color: white; border: none; cursor: pointer;">
                                            Delete
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>
                </table>

            </div>

        <?php endif; ?>

    </section>

<?php endif; ?>

</main>

<footer class="site-footer">
    Golden Moments &bull; Admin Panel
</footer>

</body>
</html>
