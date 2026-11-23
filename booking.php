<?php
session_start();
require_once "db.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

if (!isset($_SESSION["customer_id"])) {
    header("Location: index.php");
    exit;
}

$customerId = (int)$_SESSION["customer_id"];
$message = "";
$messageType = "success";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $eventType = trim($_POST["event_type"] ?? "");
    $venueArea = trim($_POST["venue_area"] ?? "");
    $eventDate = $_POST["event_date"] ?? "";
    $eventTime = $_POST["event_time"] ?? "";
    $guestCount = filter_var($_POST["guest_count"] ?? "", FILTER_VALIDATE_INT);
    $packageName = trim($_POST["package_name"] ?? "");

    if ($eventType === "" || $venueArea === "" || $eventDate === "" || $eventTime === "" || !$guestCount || $guestCount < 1 || $packageName === "") {
        $_SESSION["flash_message"] = "Please complete all required fields, including the venue area and package.";
        $_SESSION["flash_type"] = "error";
    } elseif ($eventDate < date("Y-m-d")) {
        $_SESSION["flash_message"] = "Please choose today or a future date.";
        $_SESSION["flash_type"] = "error";
    } else {
        $checkStmt = $conn->prepare(
            "SELECT booking_id FROM bookings 
             WHERE venue_area = ? AND event_date = ? AND event_time = ? AND booking_status != 'Cancelled'"
        );
        $checkStmt->bind_param("sss", $venueArea, $eventDate, $eventTime);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $_SESSION["flash_message"] = "Sorry, this specific venue area is already booked for the selected date and time.";
            $_SESSION["flash_type"] = "error";
        } else {
            $checkStmt->close();

            $stmt = $conn->prepare(
                "INSERT INTO bookings
                (customer_id, event_type, venue_area, event_date, event_time, guest_count, package_name, booking_status, payment_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', 'Unpaid')"
            );
            $stmt->bind_param("issssis", $customerId, $eventType, $venueArea, $eventDate, $eventTime, $guestCount, $packageName);

            if ($stmt->execute()) {
                $_SESSION["flash_message"] = "Reservation submitted successfully! Please settle your down payment in person at CED Event & Catering Services.";
                $_SESSION["flash_type"] = "success";
            } else {
                $_SESSION["flash_message"] = "Unable to save booking. Please try again.";
                $_SESSION["flash_type"] = "error";
            }
            $stmt->close();
        }
    }

    header("Location: booking.php");
    exit;
}

if (isset($_SESSION["flash_message"])) {
    $message = $_SESSION["flash_message"];
    $messageType = $_SESSION["flash_type"];
    unset($_SESSION["flash_message"], $_SESSION["flash_type"]);
}

$stmt = $conn->prepare(
    "SELECT booking_id, event_type, venue_area, event_date, event_time, guest_count, package_name, booking_status, payment_status
    FROM bookings WHERE customer_id = ? ORDER BY booking_id DESC"
);
$stmt->bind_param("i", $customerId);
$stmt->execute();
$bookings = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CED Event & Catering Services | My Bookings</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-header">
    <a class="brand" href="booking.php">
        CED <span>Event & Catering Services</span>
    </a>
    <nav class="nav-links">
        <span class="welcome-user">Hello, <?= e($_SESSION["customer_name"] ?? "Customer") ?></span>
        <a class="btn outline small" href="index.php?logout=1">Log out</a>
    </nav>
</header>

<main class="page-wrap">
    <section class="page-heading">
        <p class="eyebrow">RESERVATION SYSTEM</p>
        <h1>Book your <span>special event</span></h1>
        <p>Choose your preferred venue area and package. A down payment settled in person is required to confirm your schedule.</p>
    </section>

    <?php if ($message !== ""): ?>
        <div class="alert <?= e($messageType) ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <section class="content-card">
        <h2>Event booking form</h2>
        <form method="POST" action="booking.php" class="booking-form">
            <div class="form-grid">
                <div>
                    <label>Event type</label>
                    <select name="event_type" required>
                        <option value="">Choose event type</option>
                        <option>Wedding</option>
                        <option>Birthday</option>
                        <option>Debut</option>
                        <option>Anniversary</option>
                        <option>Corporate Event</option>
                        <option>Other</option>
                    </select>
                </div>
                <div>
                    <label>Venue Area</label>
                    <select name="venue_area" required>
                        <option value="">Choose venue area</option>
                        <option value="Pool Area">Pool Area</option>
                        <option value="Garden">Garden</option>
                        <option value="Grand Ballroom">Grand Ballroom</option>
                        <option value="Function Room">Function Room</option>
                    </select>
                </div>
                <div>
                    <label>Event date</label>
                    <input type="date" name="event_date" min="<?= date("Y-m-d") ?>" required>
                </div>
                <div>
                    <label>Event time</label>
                    <input type="time" name="event_time" required>
                </div>
                <div>
                    <label>Number of guests</label>
                    <input type="number" name="guest_count" min="1" max="10000" placeholder="e.g. 100" required>
                </div>
                <div>
                    <label>Package & Cost</label>
                    <select name="package_name" required>
                        <option value="">Choose package</option>
                        <option value="Basic Package - ₱10,000">Basic Package - ₱10,000</option>
                        <option value="Classic Package - ₱20,000">Classic Package - ₱20,000</option>
                        <option value="Premium Package - ₱35,000">Premium Package - ₱35,000</option>
                        <option value="Custom Package - ₱50,000">Custom Package - ₱50,000</option>
                    </select>
                </div>
            </div>
            <button class="btn primary" type="submit">Submit reservation request</button>
        </form>
    </section>

    <section class="content-card">
        <h2>My booking summary & status</h2>
        <?php if ($bookings->num_rows === 0): ?>
            <p class="muted">You don't have any reservations yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Event & Venue Area</th>
                            <th>Date & Time</th>
                            <th>Guests</th>
                            <th>Package & Cost</th>
                            <th>Booking Status</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $bookings->fetch_assoc()): ?>
                            <tr>
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
                                <td><span class="status"><?= e($row["booking_status"] ?: "Pending") ?></span></td>
                                <td>
                                    <?php 
                                        $payStatus = $row["payment_status"];
                                        if ($row["booking_status"] === "Completed" || $row["booking_status"] === "Approved") {
                                            $payStatus = "Paid";
                                        }
                                        echo e($payStatus);
                                    ?>
                                    <small class="muted">Pay in person</small>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer class="site-footer">
    © <?= date("Y") ?> CED Event & Catering Services — In-person payment policy applies.
</footer>

</body>
</html>
<?php $stmt->close(); ?>