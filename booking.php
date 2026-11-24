
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

// SUBMIT BOOKING
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
        $_SESSION["flash_message"] = "Please complete all required fields.";
        $_SESSION["flash_type"] = "error";

    } elseif ($eventDate < date("Y-m-d")) {

        $_SESSION["flash_message"] = "Please choose today or a future date.";
        $_SESSION["flash_type"] = "error";

    } else {

        // CHECK VENUE AVAILABILITY
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

// FLASH MESSAGE
if (isset($_SESSION["flash_message"])) {
    $message = $_SESSION["flash_message"];
    $messageType = $_SESSION["flash_type"];

    unset(
        $_SESSION["flash_message"],
        $_SESSION["flash_type"]
    );
}

// GET CUSTOMER BOOKINGS
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

<title>CED Event & Catering Services | My Bookings</title>

<link rel="stylesheet" href="style.css">

<style>

/* PAGE */
.page-wrap {
    max-width: 1100px;
    margin: auto;
    padding: 35px 20px;
}

.selection-section {
    margin-bottom: 30px;
}

.selection-title {
    color: #573e49;
    font-size: 19px;
    font-weight: 700;
    margin-bottom: 8px;
}

.selection-description {
    color: #927c87;
    font-size: 13px;
    margin-bottom: 18px;
}

/* IMAGE GRID */
.image-selection-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.venue-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

/* RADIO CARD */
.image-option {
    display: block;
    cursor: pointer;
    min-width: 0;
}

.image-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.image-card {
    height: 100%;
    overflow: hidden;
    border: 2px solid #f0dfe6;
    border-radius: 15px;
    background: white;
    transition: 0.25s ease;
}

.image-card:hover {
    border-color: #d28aa4;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(160, 94, 119, 0.15);
}

.image-option input:checked + .image-card {
    border: 3px solid #c46f90;
    background: #fff0f5;
    box-shadow: 0 7px 20px rgba(196, 111, 144, 0.18);
}

.image-option input:focus-visible + .image-card {
    outline: 3px solid #b85f83;
    outline-offset: 3px;
}

/* ACTUAL PHOTOGRAPHS */
.card-photo {
    width: 100%;
    height: 155px;
    display: block;
    object-fit: cover;
}

.venue-grid .card-photo {
    height: 190px;
}

.image-card-info {
    padding: 12px 8px;
    text-align: center;
}

.image-card-info h3 {
    color: #634651;
    font-size: 14px;
    margin: 0;
}

.image-card-info p {
    color: #a18a95;
    font-size: 11px;
    margin: 5px 0 0;
}

.selected-indicator {
    display: none;
    color: #c46f90;
    font-size: 11px;
    font-weight: 700;
    margin-top: 8px;
}

.image-option input:checked + .image-card
.selected-indicator {
    display: block;
}

/* BOOKING FIELDS */
.booking-extra-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin: 25px 0;
}

.booking-extra-fields label {
    display: block;
    color: #634651;
    font-weight: 600;
    margin-bottom: 9px;
}

.booking-extra-fields input,
.booking-extra-fields select {
    width: 100%;
    box-sizing: border-box;
}

/* SUBMIT */
.booking-submit {
    margin-top: 10px;
}

/* STATUS */
.booking-status-label {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    background: #fbe7ef;
    color: #a34f70;
    font-size: 12px;
    font-weight: 600;
}

/* RESPONSIVE */
@media (max-width: 768px) {

    .image-selection-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .venue-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .venue-grid .card-photo {
        height: 140px;
    }

    .booking-extra-fields {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 420px) {

    .card-photo {
        height: 110px;
    }

    .venue-grid .card-photo {
        height: 110px;
    }

    .image-card-info {
        padding: 10px 5px;
    }
}

</style>
</head>

<body>

<header class="site-header">

    <a class="brand" href="booking.php">
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

    <section class="page-heading">

        <p class="eyebrow">RESERVATION SYSTEM</p>

        <h1>Book your <span>special event</span></h1>

        <p>
            Choose your preferred event type and venue area.
            Explore our event photographs to help you decide.
            A down payment settled in person is required
            to confirm your schedule.
        </p>

    </section>

    <?php if ($message !== ""): ?>

        <div class="alert <?= e($messageType) ?>">
            <?= e($message) ?>
        </div>

    <?php endif; ?>

    <section class="content-card">

        <h2>Event booking form</h2>

        <form method="POST" action="booking.php" class="booking-form">

            <!-- EVENT TYPE -->

            <div class="selection-section">

                <h3 class="selection-title">
                    Choose Event Type
                </h3>

                <p class="selection-description">
                    Select the event that you are planning.
                </p>

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

                                <img
                                    class="card-photo"
                                    src="<?= e($details["image"]) ?>"
                                    alt="<?= e($name) ?> event"
                                    loading="lazy"
                                >

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

                <h3 class="selection-title">
                    Choose Venue Area
                </h3>

                <p class="selection-description">
                    Explore our available event locations.
                </p>

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

                                <img
                                    class="card-photo"
                                    src="<?= e($details["image"]) ?>"
                                    alt="<?= e($name) ?> venue"
                                    loading="lazy"
                                >

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

            <!-- OTHER DETAILS -->

            <div class="booking-extra-fields">

                <div>

                    <label>Event date</label>

                    <input
                        type="date"
                        name="event_date"
                        min="<?= date("Y-m-d") ?>"
                        required
                    >

                </div>

                <div>

                    <label>Event time</label>

                    <input
                        type="time"
                        name="event_time"
                        required
                    >

                </div>

                <div>

                    <label>Number of guests</label>

                    <input
                        type="number"
                        name="guest_count"
                        min="1"
                        max="10000"
                        placeholder="e.g. 100"
                        required
                    >

                </div>

                <div>

                    <label>Package & Cost</label>

                    <select name="package_name" required>

                        <option value="">Choose package</option>

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

            <button
                class="btn primary booking-submit"
                type="submit"
            >
                Submit reservation request
            </button>

        </form>

    </section>

    <!-- BOOKING SUMMARY -->

    <section class="content-card">

        <h2>My booking summary & status</h2>

        <?php if ($bookings->num_rows === 0): ?>

            <p class="muted">
                You don't have any reservations yet.
            </p>

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

                                    <small>
                                        Area: <?= e($row["venue_area"]) ?>
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

                                    <small class="muted">
                                        Pay in person
                                    </small>

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

    © <?= date("Y") ?> CED Event & Catering Services —
    In-person payment policy applies.

</footer>

</body>
</html>

<?php
$stmt->close();
?>
