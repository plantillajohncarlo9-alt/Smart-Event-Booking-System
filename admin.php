
<?php
session_start();
require_once "db.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$error = "";
$message = "";
$messageType = "";

// LOGOUT ADMIN
if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// ADMIN LOGIN
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
        $error = "Incorrect admin username or password.";
    }
}

// UPDATE BOOKING STATUS
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
        header("Location: admin.php");
        exit;
    }

    $bookingId = filter_var($_POST["booking_id"] ?? "", FILTER_VALIDATE_INT);
    $bookingStatus = trim($_POST["booking_status"] ?? "");
    $paymentStatus = trim($_POST["payment_status"] ?? "");

    $validBookingStatuses = ["Pending", "Approved", "Completed", "Cancelled", "Rejected"];
    $validPaymentStatuses = ["Unpaid", "Paid"];

    if (
        $bookingId &&
        in_array($bookingStatus, $validBookingStatuses, true) &&
        in_array($paymentStatus, $validPaymentStatuses, true)
    ) {
        $stmt = $conn->prepare(
            "UPDATE bookings
             SET booking_status = ?, payment_status = ?
             WHERE booking_id = ?"
        );

        $stmt->bind_param("ssi", $bookingStatus, $paymentStatus, $bookingId);

        if ($stmt->execute()) {
            $_SESSION["flash_message"] = "Booking #{$bookingId} successfully updated!";
            $_SESSION["flash_type"] = "success";
        } else {
            $_SESSION["flash_message"] = "Failed to update booking.";
            $_SESSION["flash_type"] = "error";
        }

        $stmt->close();
    }

    header("Location: admin.php");
    exit;
}

// DELETE BOOKING
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_booking"])) {

    if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
        header("Location: admin.php");
        exit;
    }

    $bookingId = filter_var($_POST["booking_id"] ?? "", FILTER_VALIDATE_INT);

    if ($bookingId) {

        $stmt = $conn->prepare(
            "DELETE FROM bookings WHERE booking_id = ?"
        );

        $stmt->bind_param("i", $bookingId);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $_SESSION["flash_message"] = "Booking #{$bookingId} successfully deleted!";
            $_SESSION["flash_type"] = "success";
        } else {
            $_SESSION["flash_message"] = "Failed to delete booking.";
            $_SESSION["flash_type"] = "error";
        }

        $stmt->close();

    } else {
        $_SESSION["flash_message"] = "Invalid booking ID.";
        $_SESSION["flash_type"] = "error";
    }

    header("Location: admin.php");
    exit;
}

// FLASH MESSAGE
if (isset($_SESSION["flash_message"])) {
    $message = $_SESSION["flash_message"];
    $messageType = $_SESSION["flash_type"];

    unset($_SESSION["flash_message"], $_SESSION["flash_type"]);
}

// GET BOOKINGS
$result = null;

if (isset($_SESSION["admin_logged_in"]) && $_SESSION["admin_logged_in"] === true) {

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CED Event & Catering Services | Admin Panel</title>

    <link rel="stylesheet" href="style.css">

    <style>
        /* ACTION BUTTONS */
        .action-buttons {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
        }

        .action-buttons form {
            margin: 0;
        }

        .delete-btn {
            background: #df4967;
            color: white;
            border: none;
            border-radius: 30px;
            padding: 11px 19px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s ease;
        }

        .delete-btn:hover {
            background: #bd3150;
            transform: translateY(-2px);
        }

        /* DELETE CONFIRMATION MODAL */
        .delete-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(48, 28, 38, 0.55);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .delete-modal.show {
            display: flex;
        }

        .delete-modal-content {
            background: #fffafa;
            width: 100%;
            max-width: 430px;
            padding: 32px;
            border-radius: 18px;
            text-align: center;
            box-shadow: 0 15px 50px rgba(0,0,0,0.18);
            animation: modalAppear 0.2s ease;
        }

        @keyframes modalAppear {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .delete-icon {
            width: 65px;
            height: 65px;
            background: #ffe5eb;
            color: #d94362;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 18px;
            font-size: 30px;
        }

        .delete-modal-content h2 {
            color: #543b46;
            margin-bottom: 12px;
        }

        .delete-modal-content p {
            color: #826d77;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .modal-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .cancel-delete,
        .confirm-delete {
            padding: 12px 22px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }

        .cancel-delete {
            background: #f1e6eb;
            color: #624c57;
        }

        .cancel-delete:hover {
            background: #e7d5dd;
        }

        .confirm-delete {
            background: #df4967;
            color: white;
        }

        .confirm-delete:hover {
            background: #bd3150;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .action-buttons {
                flex-wrap: wrap;
            }

            .delete-modal-content {
                padding: 25px 18px;
            }
        }
    </style>
</head>

<body>

<header class="site-header">

    <a class="brand" href="admin.php">
        CED <span>Admin Panel</span>
    </a>

    <?php if (isset($_SESSION["admin_logged_in"]) && $_SESSION["admin_logged_in"] === true): ?>
        <nav class="nav-links">
            <a class="btn outline small" href="admin.php?logout=1">
                Log out
            </a>
        </nav>
    <?php endif; ?>

</header>

<main class="page-wrap">

<?php if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true): ?>

    <!-- ADMIN LOGIN -->

    <section class="auth-layout" style="display:flex; justify-content:center; align-items:center; min-height:70vh;">

        <section class="auth-card" style="width:100%; max-width:450px;">

            <h2>Admin Sign In</h2>

            <p class="muted">
                Enter your admin credentials to manage bookings.
            </p>

            <?php if ($error !== ""): ?>
                <div class="alert error">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="admin.php" class="auth-form show" style="display:block;">

                <input type="hidden" name="admin_login" value="1">

                <label>Admin Username / Email</label>

                <input
                    type="text"
                    name="username"
                    placeholder="admin or admin@gmail.com"
                    required
                >

                <label>Password</label>

                <input
                    type="password"
                    name="password"
                    placeholder="Enter password"
                    required
                >

                <button class="btn primary full" type="submit" style="margin-top:1rem;">
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
            Manage Event <span>Reservations & Payments</span>
        </h1>

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

                        <?php $id = (int)$row["booking_id"]; ?>

                        <tr>

                            <td>
                                #<?= $id ?>
                            </td>

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
                                <small>
                                    <?= e(date("g:i A", strtotime($row["event_time"]))) ?>
                                </small>
                            </td>

                            <td>
                                <?= e($row["guest_count"]) ?>
                            </td>

                            <td>
                                <?= e($row["package_name"] ?: "—") ?>
                            </td>

                            <!-- PAYMENT STATUS -->

                            <td>

                                <select
                                    name="payment_status"
                                    class="status-select"
                                    form="update-form-<?= $id ?>"
                                >

                                    <option value="Unpaid" <?= $row["payment_status"] === "Unpaid" ? "selected" : "" ?>>
                                        Unpaid
                                    </option>

                                    <option value="Paid" <?= $row["payment_status"] === "Paid" ? "selected" : "" ?>>
                                        Paid
                                    </option>

                                </select>

                            </td>

                            <!-- BOOKING STATUS -->

                            <td>

                                <select
                                    name="booking_status"
                                    class="status-select"
                                    form="update-form-<?= $id ?>"
                                >

                                    <option value="Pending" <?= $row["booking_status"] === "Pending" ? "selected" : "" ?>>
                                        Pending
                                    </option>

                                    <option value="Approved" <?= $row["booking_status"] === "Approved" ? "selected" : "" ?>>
                                        Approved
                                    </option>

                                    <option value="Completed" <?= $row["booking_status"] === "Completed" ? "selected" : "" ?>>
                                        Completed
                                    </option>

                                    <option value="Cancelled" <?= $row["booking_status"] === "Cancelled" ? "selected" : "" ?>>
                                        Cancelled
                                    </option>

                                    <option value="Rejected" <?= $row["booking_status"] === "Rejected" ? "selected" : "" ?>>
                                        Rejected
                                    </option>

                                </select>

                            </td>

                            <!-- ACTION BUTTONS -->

                            <td>

                                <div class="action-buttons">

                                    <!-- DELETE BUTTON - LEFT -->

                                    <button
                                        type="button"
                                        class="delete-btn"
                                        onclick="openDeleteModal(<?= $id ?>)"
                                    >
                                        Delete
                                    </button>

                                    <!-- UPDATE BUTTON - RIGHT -->

                                    <form
                                        method="POST"
                                        action="admin.php"
                                        id="update-form-<?= $id ?>"
                                    >

                                        <input type="hidden" name="booking_id" value="<?= $id ?>">

                                        <input type="hidden" name="update_status" value="1">

                                        <button type="submit" class="btn primary small">
                                            Update
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9" style="text-align:center;">
                            No bookings found.
                        </td>
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


<!-- DELETE CONFIRMATION MODAL -->

<div class="delete-modal" id="deleteModal">

    <div class="delete-modal-content">

        <div class="delete-icon">
            !
        </div>

        <h2>Delete Booking?</h2>

        <p>
            Are you sure you want to delete booking
            <strong id="deleteBookingId"></strong>?
            This action cannot be undone.
        </p>

        <div class="modal-actions">

            <button
                type="button"
                class="cancel-delete"
                onclick="closeDeleteModal()"
            >
                Cancel
            </button>

            <form method="POST" action="admin.php" id="deleteForm">

                <input type="hidden" name="booking_id" id="selectedBookingId">

                <input type="hidden" name="delete_booking" value="1">

                <button type="submit" class="confirm-delete">
                    Confirm Delete
                </button>

            </form>

        </div>

    </div>

</div>


<script>

    // OPEN DELETE MODAL

    function openDeleteModal(bookingId) {

        document.getElementById("selectedBookingId").value = bookingId;

        document.getElementById("deleteBookingId").textContent = "#" + bookingId;

        document.getElementById("deleteModal").classList.add("show");

    }


    // CLOSE DELETE MODAL

    function closeDeleteModal() {

        document.getElementById("deleteModal").classList.remove("show");

    }


    // CLOSE WHEN CLICKING OUTSIDE THE MODAL

    document.getElementById("deleteModal").addEventListener("click", function(event) {

        if (event.target === this) {
            closeDeleteModal();
        }

    });


    // CLOSE USING ESCAPE KEY

    document.addEventListener("keydown", function(event) {

        if (event.key === "Escape") {
            closeDeleteModal();
        }

    });

</script>

</body>
</html>
