
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

/* EVENT TYPES */
$eventTypes = [
    "Wedding" => [
        "description" => "Elegant wedding celebration",
        "image" => "https://images.unsplash.com/photo-1519741497674-611481863552?w=700&auto=format&fit=crop"
    ],
    "Birthday" => [
        "description" => "Colorful birthday celebration",
        "image" => "https://images.unsplash.com/photo-1530103862676-de8c9debad1d?w=700&auto=format&fit=crop"
    ],
    "Debut" => [
        "description" => "Elegant debut celebration",
        "image" => "https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?w=700&auto=format&fit=crop"
    ],
    "Anniversary" => [
        "description" => "A memorable celebration",
        "image" => "https://images.unsplash.com/photo-1511795409834-ef04bbd61622?w=700&auto=format&fit=crop"
    ],
    "Corporate Event" => [
        "description" => "Professional business event",
        "image" => "https://images.unsplash.com/photo-1519167758481-83f550bb49b3?w=700&auto=format&fit=crop"
    ],
    "Other" => [
        "description" => "Other special occasions",
        "image" => "https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=700&auto=format&fit=crop"
    ]
];

/* VENUE AREAS */
$venueAreas = [
    "Pool Area" => [
        "description" => "Refreshing outdoor venue",
        "image" => "https://images.unsplash.com/photo-1566073771259-6a8506099945?w=700&auto=format&fit=crop"
    ],
    "Garden" => [
        "description" => "Beautiful garden surroundings",
        "image" => "https://images.unsplash.com/photo-1507504031003-b417219a0fde?w=700&auto=format&fit=crop"
    ],
    "Grand Ballroom" => [
        "description" => "Elegant spacious venue",
        "image" => "https://images.unsplash.com/photo-1519167758481-83f550bb49b3?w=700&auto=format&fit=crop"
    ],
    "Function Room" => [
        "description" => "Comfortable indoor venue",
        "image" => "https://images.unsplash.com/photo-1511795409834-ef04bbd61622?w=700&auto=format&fit=crop"
    ]
];

/* SUBMIT BOOKING */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $eventType = trim($_POST["event_type"] ?? "");
    $venueArea = trim($_POST["venue_area"] ?? "");
    $eventDate = $_POST["event_date"] ?? "";
    $eventTime = $_POST["event_time"] ?? "";

    $guestCount = filter_var(
        $_POST["guest_count"] ?? "",
        FILTER_VALIDATE_INT
    );

    $packageName = trim($_POST["package_name"] ?? "");

    if (
        !array_key_exists($eventType, $eventTypes) ||
        !array_key_exists($venueArea, $venueAreas) ||
        $eventDate === "" ||
        $eventTime === "" ||
        !$guestCount ||
        $guestCount < 1 ||
        $packageName === ""
    ) {

        $_SESSION["flash_message"] =
            "Please complete all required fields.";

        $_SESSION["flash_type"] = "error";

    } elseif ($eventDate < date("Y-m-d")) {

        $_SESSION["flash_message"] =
            "Please choose today or a future date.";

        $_SESSION["flash_type"] = "error";

    } else {

        /* CHECK VENUE AVAILABILITY */
        $checkStmt = $conn->prepare(
            "SELECT booking_id FROM bookings
             WHERE venue_area = ?
             AND event_date = ?
             AND event_time = ?
             AND booking_status != 'Cancelled'"
        );

        $checkStmt->bind_param(
            "sss",
            $venueArea,
            $eventDate,
            $eventTime
        );

        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {

            $_SESSION["flash_message"] =
                "Sorry, this venue area is already booked for the selected date and time.";

            $_SESSION["flash_type"] = "error";

        } else {

            /* SAVE BOOKING */
            $stmt = $conn->prepare(
                "INSERT INTO bookings
                (
                    customer_id,
                    event_type,
                    venue_area,
                    event_date,
                    event_time,
                    guest_count,
                    package_name,
                    booking_status,
                    payment_status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', 'Unpaid')"
            );

            $stmt->bind_param(
                "issssis",
                $customerId,
                $eventType,
                $venueArea,
                $eventDate,
                $eventTime,
                $guestCount,
                $packageName
            );

            if ($stmt->execute()) {

                $_SESSION["flash_message"] =
                    "Reservation submitted successfully! Please settle your down payment in person at CED Event & Catering Services.";

                $_SESSION["flash_type"] = "success";

            } else {

                $_SESSION["flash_message"] =
                    "Unable to save booking. Please try again.";

                $_SESSION["flash_type"] = "error";
            }

            $stmt->close();
        }

        $checkStmt->close();
    }

    header("Location: booking.php");
    exit;
}

/* FLASH MESSAGE */
if (isset($_SESSION["flash_message"])) {

    $message = $_SESSION["flash_message"];
    $messageType = $_SESSION["flash_type"];

    unset(
        $_SESSION["flash_message"],
        $_SESSION["flash_type"]
    );
}

/* GET CUSTOMER BOOKINGS */
$stmt = $conn->prepare(
    "SELECT
        booking_id,
        event_type,
        venue_area,
        event_date,
        event_time,
        guest_count,
        package_name,
        booking_status,
        payment_status
    FROM bookings
    WHERE customer_id = ?
    ORDER BY booking_id DESC"
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

    <title>CED Event & Catering Services | Book Event</title>

    <link rel="stylesheet" href="style.css">
</head>

<body class="floral-booking">

<header class="site-header">

    <a class="brand" href="booking.php">
        <span class="brand-crown">✿</span>
        CED <span>Event & Catering Services</span>
    </a>

    <nav class="nav-links">

        <span class="welcome-user">
            Hello, <?= e($_SESSION["customer_name"] ?? "Customer") ?>
        </span>

        <a class="btn outline small" href="index.php?logout=1">
            Log out
        </a>

    </nav>

</header>

<main class="page-wrap">

    <!-- PAGE INTRODUCTION -->

    <section class="page-heading floral-heading">

        <p class="eyebrow">✦ PLAN • BOOK • CELEBRATE ✦</p>

        <h1>
            Book your <span>special event</span>
        </h1>

        <p>
            Let's make your special moments unforgettable.
            Choose your preferred event type, venue, date,
            and package with CED Event & Catering Services.
        </p>

        <div class="heading-decoration">
            ♡ &nbsp; ✿ &nbsp; ♡
        </div>

    </section>

    <!-- ALERT MESSAGE -->

    <?php if ($message !== ""): ?>

        <div class="alert <?= e($messageType) ?>">
            <?= e($message) ?>
        </div>

    <?php endif; ?>

    <!-- BOOKING FORM -->

    <section class="content-card booking-main-card">

        <div class="booking-card-heading">

            <span class="heading-flower">✿</span>

            <div>
                <h2>Event Booking Form</h2>

                <p class="muted">
                    Select your event and preferred venue below.
                </p>
            </div>

        </div>

        <form method="POST" action="booking.php" class="booking-form">

            <!-- EVENT TYPE -->

            <div class="selection-section">

                <div class="selection-heading">

                    <span class="section-number">01</span>

                    <div>
                        <h3 class="selection-title">
                            Choose Your Event
                        </h3>

                        <p class="selection-description">
                            What special occasion are you planning?
                        </p>
                    </div>

                </div>

                <div class="image-selection-grid">

                    <?php foreach ($eventTypes as $name => $details): ?>

                        <label class="image-option">

                            <input
                                type="radio"
                                name="event_type"
                                value="<?= e($name) ?>"
                                required
                            >

                            <div class="image-card">

                                <div class="photo-container">

                                    <img
                                        class="card-photo"
                                        src="<?= e($details["image"]) ?>"
                                        alt="<?= e($name) ?> event"
                                        loading="lazy"
                                    >

                                    <span class="photo-heart">♡</span>

                                </div>

                                <div class="image-card-info">

                                    <h3><?= e($name) ?></h3>

                                    <p><?= e($details["description"]) ?></p>

                                    <div class="selected-indicator">
                                        ✓ SELECTED
                                    </div>

                                </div>

                            </div>

                        </label>

                    <?php endforeach; ?>

                </div>

            </div>

            <!-- VENUE AREA -->

            <div class="selection-section">

                <div class="selection-heading">

                    <span class="section-number">02</span>

                    <div>
                        <h3 class="selection-title">
                            Choose Your Venue
                        </h3>

                        <p class="selection-description">
                            Discover the perfect location for your celebration.
                        </p>
                    </div>

                </div>

                <div class="image-selection-grid venue-grid">

                    <?php foreach ($venueAreas as $name => $details): ?>

                        <label class="image-option">

                            <input
                                type="radio"
                                name="venue_area"
                                value="<?= e($name) ?>"
                                required
                            >

                            <div class="image-card">

                                <div class="photo-container">

                                    <img
                                        class="card-photo"
                                        src="<?= e($details["image"]) ?>"
                                        alt="<?= e($name) ?> venue"
                                        loading="lazy"
                                    >

                                    <span class="photo-heart">♡</span>

                                </div>

                                <div class="image-card-info">

                                    <h3><?= e($name) ?></h3>

                                    <p><?= e($details["description"]) ?></p>

                                    <div class="selected-indicator">
                                        ✓ SELECTED
                                    </div>

                                </div>

                            </div>

                        </label>

                    <?php endforeach; ?>

                </div>

            </div>

            <!-- EVENT DETAILS -->

            <div class="selection-section details-section">

                <div class="selection-heading">

                    <span class="section-number">03</span>

                    <div>
                        <h3 class="selection-title">
                            Event Details
                        </h3>

                        <p class="selection-description">
                            Complete your reservation information.
                        </p>
                    </div>

                </div>

                <div class="booking-extra-fields">

                    <div>
                        <label>Event Date</label>

                        <input
                            type="date"
                            name="event_date"
                            min="<?= date("Y-m-d") ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>Event Time</label>

                        <input
                            type="time"
                            name="event_time"
                            required
                        >
                    </div>

                    <div>
                        <label>Number of Guests</label>

                        <input
                            type="number"
                            name="guest_count"
                            min="1"
                            max="10000"
                            placeholder="Enter number of guests"
                            required
                        >
                    </div>

                    <div>
                        <label>Package & Cost</label>

                        <select name="package_name" required>

                            <option value="">Choose your package</option>

                            <option value="Basic Package - ₱10,000">
                                Basic Package - ₱10,000
                            </option>

                            <option value="Classic Package - ₱20,000">
                                Classic Package - ₱20,000
                            </option>

                            <option value="Premium Package - ₱35,000">
                                Premium Package - ₱35,000
                            </option>

                            <option value="Custom Package - ₱50,000">
                                Custom Package - ₱50,000
                            </option>

                        </select>
                    </div>

                </div>

            </div>

            <!-- SUBMIT -->

            <div class="booking-submit-area">

                <p>
                    ♡ Your dream event starts with one reservation.
                </p>

                <button
                    class="btn primary booking-submit"
                    type="submit"
                >
                    Submit Reservation Request &nbsp; →
                </button>

            </div>

        </form>

    </section>

    <!-- BOOKING SUMMARY -->

    <section class="content-card booking-summary-card">

        <div class="booking-card-heading">

            <span class="heading-flower">♡</span>

            <div>
                <h2>My Booking Summary</h2>

                <p class="muted">
                    View your reservation details and booking status.
                </p>
            </div>

        </div>

        <?php if ($bookings->num_rows === 0): ?>

            <div class="empty-bookings">
                <span>♡</span>

                <h3>No bookings yet</h3>

                <p class="muted">
                    Your reservations will appear here once submitted.
                </p>
            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Event & Venue</th>
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

                                    <small>
                                        Venue: <?= e($row["venue_area"]) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= e($row["event_date"]) ?>

                                    <small>
                                        <?= e(date(
                                            "g:i A",
                                            strtotime($row["event_time"])
                                        )) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= e($row["guest_count"]) ?>
                                </td>

                                <td>
                                    <?= e($row["package_name"] ?: "—") ?>
                                </td>

                                <td>
                                    <span class="booking-status-label">
                                        <?= e($row["booking_status"] ?: "Pending") ?>
                                    </span>
                                </td>

                                <td>

                                    <?php
                                    $payStatus = $row["payment_status"];

                                    if (
                                        $row["booking_status"] === "Completed" ||
                                        $row["booking_status"] === "Approved"
                                    ) {
                                        $payStatus = "Paid";
                                    }

                                    echo e($payStatus);
                                    ?>

                                    <small>Pay in person</small>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

    <!-- FOOTER -->

    <div class="booking-footer">
        <span>✿</span>
        <p>CED Event & Catering Services</p>
        <small>Making every celebration unforgettable ♡</small>
    </div>

</main>

</body>
</html>
