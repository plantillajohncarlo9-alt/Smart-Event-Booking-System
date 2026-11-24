
<?php
session_start();
require_once "db.php";

// ESCAPE OUTPUT
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$error = "";
$message = "";
$messageType = "success";

// LOGOUT ADMIN
if (isset($_GET["logout"])) {
    unset($_SESSION["admin_logged_in"], $_SESSION["admin_username"]);
    session_destroy();
    header("Location: admin.php");
    exit;
}

// HANDLE ADMIN LOGIN
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["admin_login"])) {

    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if (
        ($username === "admin" || strtolower($username) === "admin@gmail.com")
        && $password === "admin123"
    ) {
        session_regenerate_id(true);

        $_SESSION["admin_logged_in"] = true;
        $_SESSION["admin_username"] = $username;

        header("Location: admin.php");
        exit;

    } else {
        $error = "Mali ang username o password ng admin.";
    }
}

// CHECK ADMIN LOGIN
$isAdmin = isset($_SESSION["admin_logged_in"])
    && $_SESSION["admin_logged_in"] === true;


// HANDLE STATUS UPDATES
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    if (!$isAdmin) {
        header("Location: admin.php");
        exit;
    }

    $bookingId = filter_var(
        $_POST["booking_id"] ?? "",
        FILTER_VALIDATE_INT
    );

    $bookingStatus = trim($_POST["booking_status"] ?? "");
    $paymentStatus = trim($_POST["payment_status"] ?? "");

    $allowedBookingStatuses = [
        "Pending",
        "Approved",
        "Completed",
        "Cancelled",
        "Rejected"
    ];

    $allowedPaymentStatuses = [
        "Paid",
        "Unpaid"
    ];

    if (
        $bookingId &&
        in_array($bookingStatus, $allowedBookingStatuses, true) &&
        in_array($paymentStatus, $allowedPaymentStatuses, true)
    ) {

        $stmt = $conn->prepare(
            "UPDATE bookings
             SET booking_status = ?, payment_status = ?
             WHERE booking_id = ?"
        );

        if ($stmt) {

            $stmt->bind_param(
                "ssi",
                $bookingStatus,
                $paymentStatus,
                $bookingId
            );

            if ($stmt->execute()) {

                if ($stmt->affected_rows >= 0) {
                    $_SESSION["flash_message"] =
                        "Booking #{$bookingId} successfully updated!";

                    $_SESSION["flash_type"] = "success";
                }

            } else {
                $_SESSION["flash_message"] =
                    "Failed to update booking.";

                $_SESSION["flash_type"] = "error";
            }

            $stmt->close();

        } else {
            $_SESSION["flash_message"] =
                "Unable to prepare update request.";

            $_SESSION["flash_type"] = "error";
        }

    } else {
        $_SESSION["flash_message"] =
            "Invalid booking information.";

        $_SESSION["flash_type"] = "error";
    }

    header("Location: admin.php");
    exit;
}


// HANDLE BOOKING DELETION
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_booking"])) {

    if (!$isAdmin) {
        header("Location: admin.php");
        exit;
    }

    $bookingId = filter_var(
        $_POST["booking_id"] ?? "",
        FILTER_VALIDATE_INT
    );

    if ($bookingId) {

        $stmt = $conn->prepare(
            "DELETE FROM bookings WHERE booking_id = ?"
        );

        if ($stmt) {

            $stmt->bind_param("i", $bookingId);

            if ($stmt->execute()) {

                if ($stmt->affected_rows > 0) {

                    $_SESSION["flash_message"] =
                        "Booking #{$bookingId} successfully deleted!";

                    $_SESSION["flash_type"] = "success";

                } else {

                    $_SESSION["flash_message"] =
                        "Booking not found.";

                    $_SESSION["flash_type"] = "error";
                }

            } else {

                $_SESSION["flash_message"] =
                    "Failed to delete booking. Please check database relationships.";

                $_SESSION["flash_type"] = "error";
            }

            $stmt->close();

        } else {

            $_SESSION["flash_message"] =
                "Unable to prepare delete request.";

            $_SESSION["flash_type"] = "error";
        }

    } else {

        $_SESSION["flash_message"] =
            "Invalid booking ID.";

        $_SESSION["flash_type"] = "error";
    }

    header("Location: admin.php");
    exit;
}


// DISPLAY FLASH MESSAGE
if (isset($_SESSION["flash_message"])) {

    $message = $_SESSION["flash_message"];
    $messageType = $_SESSION["flash_type"] ?? "success";

    unset(
        $_SESSION["flash_message"],
        $_SESSION["flash_type"]
    );
}


// GET ALL BOOKINGS
$result = null;

if ($isAdmin) {

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

            LEFT JOIN customers c
            ON b.customer_id = c.customer_id

            ORDER BY b.booking_id DESC";

    $result = $conn->query($sql);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>CED Event & Catering Services | Admin Panel</title>

    <link rel="stylesheet" href="style.css">

    <style>

        /* ACTION BUTTONS */

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-buttons form {
            margin: 0;
        }

        .btn-delete {
            background: #dc526b;
            color: white;
            border: none;
            padding: 10px 17px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s ease;
            box-shadow: 0 5px 12px rgba(220, 82, 107, 0.18);
        }

        .btn-delete:hover {
            background: #b8324e;
            transform: translateY(-2px);
        }

        .action-buttons .btn {
            white-space: nowrap;
        }

        /* ALERT MESSAGES */

        .alert.success {
            background: #e8f8ee;
            color: #237a45;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert.error {
            background: #fdebed;
            color: #b8324e;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

    </style>

</head>

<body>

<!-- HEADER -->

<header class="site-header">

    <a class="brand" href="admin.php">
        CED <span>Admin Panel</span>
    </a>

    <?php if ($isAdmin): ?>

        <nav class="nav-links">

            <a class="btn outline small"
               href="admin.php?logout=1">
                Log out
            </a>

        </nav>

    <?php endif; ?>

</header>


<main class="page-wrap">

<?php if (!$isAdmin): ?>

    <!-- ADMIN LOGIN -->

    <section class="auth-layout"
             style="display:flex; justify-content:center; align-items:center; min-height:70vh;">

        <section class="auth-card"
                 style="width:100%; max-width:450px;">

            <h2>Admin Sign In</h2>

            <p class="muted">
                Ilagay ang iyong admin credentials para pamahalaan ang mga bookings.
            </p>

            <?php if ($error !== ""): ?>

                <div class="alert error">
                    <?= e($error) ?>
                </div>

            <?php endif; ?>

            <form method="POST"
                  action="admin.php"
                  class="auth-form show"
                  style="display:block;">

                <input type="hidden"
                       name="admin_login"
                       value="1">

                <label>Admin Username / Email</label>

                <input type="text"
                       name="username"
                       placeholder="admin o admin@gmail.com"
                       required>

                <label>Password</label>

                <input type="password"
                       name="password"
                       placeholder="Enter password"
                       required>

                <button class="btn primary full"
                        type="submit"
                        style="margin-top:1rem;">

                    Log In as Admin

                </button>

            </form>

        </section>

    </section>


<?php else: ?>

    <!-- ADMIN DASHBOARD -->

    <section class="page-heading">

        <p class="eyebrow">ADMINISTRATION</p>

        <h1>
            Manage Event
            <span>Reservations & Payments</span>
        </h1>

    </section>


    <!-- SUCCESS OR ERROR MESSAGE -->

    <?php if ($message !== ""): ?>

        <div class="alert <?= e($messageType) ?>">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <!-- BOOKINGS TABLE -->

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

                        <?php
                            $bookingId = (int)$row["booking_id"];
                            $formId = "update-form-" . $bookingId;
                        ?>

                        <tr>

                            <!-- BOOKING ID -->

                            <td>
                                #<?= e($bookingId) ?>
                            </td>


                            <!-- CUSTOMER -->

                            <td>

                                <?= e($row["full_name"] ?? "N/A") ?>

                                <small>
                                    <?= e($row["email"] ?? "") ?>
                                </small>

                            </td>


                            <!-- EVENT AND AREA -->

                            <td>

                                <?= e($row["event_type"]) ?>

                                <small>
                                    Area: <?= e($row["venue_area"]) ?>
                                </small>

                            </td>


                            <!-- DATE AND TIME -->

                            <td>

                                <?= e($row["event_date"]) ?>

                                <small>
                                    <?= e(date(
                                        "g:i A",
                                        strtotime($row["event_time"])
                                    )) ?>
                                </small>

                            </td>


                            <!-- GUEST COUNT -->

                            <td>
                                <?= e($row["guest_count"]) ?>
                            </td>


                            <!-- PACKAGE -->

                            <td>
                                <?= e($row["package_name"] ?: "—") ?>
                            </td>


                            <!-- PAYMENT STATUS -->

                            <td>

                                <select
                                    name="payment_status"
                                    class="status-select"
                                    form="<?= e($formId) ?>"
                                >

                                    <option value="Unpaid"
                                        <?= $row["payment_status"] === "Unpaid" ? "selected" : "" ?>>

                                        Unpaid

                                    </option>

                                    <option value="Paid"
                                        <?= $row["payment_status"] === "Paid" ? "selected" : "" ?>>

                                        Paid

                                    </option>

                                </select>

                            </td>


                            <!-- BOOKING STATUS -->

                            <td>

                                <select
                                    name="booking_status"
                                    class="status-select"
                                    form="<?= e($formId) ?>"
                                >

                                    <option value="Pending"
                                        <?= $row["booking_status"] === "Pending" ? "selected" : "" ?>>

                                        Pending

                                    </option>

                                    <option value="Approved"
                                        <?= $row["booking_status"] === "Approved" ? "selected" : "" ?>>

                                        Approved

                                    </option>

                                    <option value="Completed"
                                        <?= $row["booking_status"] === "Completed" ? "selected" : "" ?>>

                                        Completed

                                    </option>

                                    <option value="Cancelled"
                                        <?= $row["booking_status"] === "Cancelled" ? "selected" : "" ?>>

                                        Cancelled

                                    </option>

                                    <option value="Rejected"
                                        <?= $row["booking_status"] === "Rejected" ? "selected" : "" ?>>

                                        Rejected

                                    </option>

                                </select>

                            </td>


                            <!-- ACTION BUTTONS -->

                            <td>

                                <div class="action-buttons">

                                    <!-- UPDATE FORM -->

                                    <form
                                        method="POST"
                                        action="admin.php"
                                        id="<?= e($formId) ?>"
                                    >

                                        <input
                                            type="hidden"
                                            name="booking_id"
                                            value="<?= e($bookingId) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="update_status"
                                            value="1"
                                        >

                                    </form>


                                    <!-- UPDATE BUTTON -->

                                    <button
                                        type="submit"
                                        form="<?= e($formId) ?>"
                                        class="btn primary small"
                                    >

                                        Update

                                    </button>


                                    <!-- DELETE FORM -->

                                    <form
                                        method="POST"
                                        action="admin.php"
                                        onsubmit="return confirm('Are you sure you want to delete booking #<?= e($bookingId) ?>? This action cannot be undone.');"
                                    >

                                        <input
                                            type="hidden"
                                            name="booking_id"
                                            value="<?= e($bookingId) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="delete_booking"
                                            value="1"
                                        >

                                        <!-- DELETE BUTTON -->

                                        <button
                                            type="submit"
                                            class="btn-delete"
                                        >

                                            Delete

                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>


                <?php else: ?>

                    <tr>

                        <td colspan="9"
                            style="text-align:center;">

                            Walang nakitang bookings.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

<?php endif; ?>

</main>


<!-- FOOTER -->

<footer class="site-footer">

    © <?= date("Y") ?> CED Event & Catering Services
    — Admin Panel

</footer>


</body>
</html>
