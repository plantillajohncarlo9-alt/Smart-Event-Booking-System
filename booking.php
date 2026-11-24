
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
    "Wedding",
    "Birthday",
    "Debut",
    "Anniversary",
    "Corporate Event",
    "Other"
];

$venueAreas = [
    "Pool Area",
    "Garden",
    "Grand Ballroom",
    "Function Room"
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
        !in_array($eventType, $eventTypes, true) ||
        !in_array($venueArea, $venueAreas, true) ||
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
                "Sorry, this specific venue area is already booked for the selected date and time.";

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

/* =================================
   IMAGE SELECTION CARDS
================================= */

.selection-section {
    margin-bottom: 35px;
}

.selection-title {
    color: #573e49;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 8px;
}

.selection-description {
    color: #927c87;
    font-size: 13px;
    margin-bottom: 18px;
}

.image-selection-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.venue-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.image-option {
    position: relative;
    display: block;
    cursor: pointer;
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
    border-radius: 17px;
    background: #fffafa;
    transition: all 0.25s ease;
}

.image-card:hover {
    border-color: #d28aa4;
    transform: translateY(-4px);
    box-shadow: 0 9px 22px rgba(160, 94, 119, 0.12);
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

.illustration {
    width: 100%;
    height: 145px;
    display: block;
    background: #fcecf2;
}

.venue-grid .illustration {
    height: 125px;
}

.image-card-info {
    padding: 14px;
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
    margin: 6px 0 0;
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

/* =================================
   FORM FIELDS
================================= */

.booking-extra-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin-top: 25px;
    margin-bottom: 25px;
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

.booking-submit {
    margin-top: 10px;
}

/* =================================
   RESPONSIVE
================================= */

@media (max-width: 768px) {

    .image-selection-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .venue-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .booking-extra-fields {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 420px) {

    .illustration {
        height: 110px;
    }

    .venue-grid .illustration {
        height: 100px;
    }

    .image-card-info {
        padding: 10px 5px;
    }

}

/* BOOKING TABLE */

.booking-status-label {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    background: #fbe7ef;
    color: #a34f70;
    font-size: 12px;
    font-weight: 600;
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

        <h1>
            Book your <span>special event</span>
        </h1>

        <p>
            Choose your preferred event type and venue area.
            Explore the illustrations to help you decide.
            A down payment settled in person is required
            to confirm your schedule.
        </p>

    </section>

    <?php if ($message !== ""): ?>

        <div class="alert <?= e($messageType) ?>">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <!-- =================================
         EVENT BOOKING FORM
    ================================== -->

    <section class="content-card">

        <h2>Event booking form</h2>

        <form method="POST" action="booking.php" class="booking-form">

            <!-- EVENT TYPE SELECTION -->

            <div class="selection-section">

                <h3 class="selection-title">
                    Choose Event Type
                </h3>

                <p class="selection-description">
                    Select the event that you are planning.
                </p>

                <div class="image-selection-grid">

                    <!-- WEDDING -->

                    <label class="image-option">

                        <input
                            type="radio"
                            name="event_type"
                            value="Wedding"
                            required
                        >

                        <div class="image-card">

                            <svg class="illustration" viewBox="0 0 300 170"
                                 xmlns="http://www.w3.org/2000/svg">

                                <rect width="300" height="170" fill="#f9e2ec"/>

                                <circle cx="150" cy="70" r="48" fill="#fff4f7"/>

                                <path d="M112 40 L150 15 L188 40"
                                      fill="none" stroke="#c47c9b"
                                      stroke-width="3"/>

                                <path d="M150 15 L150 5"
                                      stroke="#c47c9b" stroke-width="3"/>

                                <path d="M130 75 L170 75 L190 150 L110 150 Z"
                                      fill="#fff" stroke="#d9a8bc" stroke-width="2"/>

                                <path d="M130 75 Q150 105 170 75"
                                      fill="#f8d8e5"/>

                                <path d="M150 85 L150 150"
                                      stroke="#e7bfd0" stroke-width="2"/>

                                <circle cx="150" cy="43" r="13" fill="#f4c8d8"/>

                                <path d="M110 40 Q150 0 190 40"
                                      fill="none" stroke="#fff"
                                      stroke-width="8"/>

                                <path d="M95 150 Q150 135 205 150"
                                      fill="none" stroke="#d7a1b6" stroke-width="4"/>

                                <g fill="#d78eac">
                                    <circle cx="90" cy="115" r="6"/>
                                    <circle cx="210" cy="115" r="6"/>
                                    <circle cx="100" cy="130" r="5"/>
                                    <circle cx="200" cy="130" r="5"/>
                                </g>

                            </svg>

                            <div class="image-card-info">

                                <h3>Wedding</h3>

                                <p>Elegant wedding celebration</p>

                                <div class="selected-indicator">
                                    ✓ SELECTED
                                </div>

                            </div>

                        </div>

                    </label>


                    <!-- BIRTHDAY -->

                    <label class="image-option">

                        <input
                            type="radio"
                            name="event_type"
                            value="Birthday"
                            required
                        >

                        <div class="image-card">

                            <svg class="illustration" viewBox="0 0 300 170"
                                 xmlns="http://www.w3.org/2000/svg">

                                <rect width="300" height="170" fill="#fff0d9"/>

                                <circle cx="150" cy="78" r="55" fill="#fff9e9"/>

                                <path d="M70 145 L230 145"
                                      stroke="#d9a16b" stroke-width="4"/>

                                <rect x="100" y="92" width="100" height="50"
                                      rx="7" fill="#eaa5b9"/>

                                <rect x="112" y="68" width="76" height="28"
                                      rx="5" fill="#f8cf81"/>

                                <path d="M150 68 L150 43"
                                      stroke="#98715b" stroke-width="4"/>

                                <path d="M150 43 Q138 32 150 22 Q162 32 150 43"
                                      fill="#f5a343"/>

                                <g fill="#e48bad">
                                    <circle cx="75" cy="45" r="15"/>
                                    <circle cx="225" cy="45" r="15"/>
                                </g>

                                <path d="M75 60 L70 100 L80 100 Z"
                                      fill="#f4c2d3"/>

                                <path d="M225 60 L220 100 L230 100 Z"
                                      fill="#f4c2d3"/>

                                <path d="M90 85 L100 75 L110 85"
                                      stroke="#d99aaf" stroke-width="3" fill="none"/>

                                <path d="M190 85 L200 75 L210 85"
                                      stroke="#d99aaf" stroke-width="3" fill="none"/>

                            </svg>

                            <div class="image-card-info">

                                <h3>Birthday</h3>

                                <p>Colorful birthday party</p>

                                <div class="selected-indicator">
                                    ✓ SELECTED
                                </div>

                            </div>

                        </div>

                    </label>


                    <!-- DEBUT -->

                    <label class="image-option">

                        <input
                            type="radio"
                            name="event_type"
                            value="Debut"
                            required
                        >

                        <div class="image-card">

                            <svg class="illustration" viewBox="0 0 300 170"
                                 xmlns="http://www.w3.org/2000/svg">

                                <rect width="300" height="170" fill="#f2e5fa"/>

                                <circle cx="150" cy="75" r="53" fill="#fff7ff"/>

                                <path d="M150 18 L160 55 L200 50 L170 80
                                         L185 120 L150 95 L115 120
                                         L130 80 L100 50 L140 55 Z"
                                      fill="#e8c5ef"/>

                                <path d="M115 140 L150 78 L185 140 Z"
                                      fill="#a985c6"/>

                                <path d="M130 140 L150 90 L170 140"
                                      fill="#f8e8f7"/>

                                <path d="M140 65 Q150 48 160 65"
                                      fill="none" stroke="#d5a4df"
                                      stroke-width="4"/>

                                <g fill="#d1a1dc">
                                    <circle cx="70" cy="45" r="4"/>
                                    <circle cx="230" cy="40" r="4"/>
                                    <circle cx="80" cy="115" r="5"/>
                                    <circle cx="220" cy="115" r="5"/>
                                </g>

                                <path d="M55 150 Q150 130 245 150"
                                      fill="none" stroke="#bd94cf" stroke-width="3"/>

                            </svg>

                            <div class="image-card-info">

                                <h3>Debut</h3>

                                <p>Elegant 18th birthday</p>

                                <div class="selected-indicator">
                                    ✓ SELECTED
                                </div>

                            </div>

                        </div>

                    </label>


                    <!-- ANNIVERSARY -->

                    <label class="image-option">

                        <input
                            type="radio"
                            name="event_type"
                            value="Anniversary"
                            required
                        >

                        <div class="image-card">

                            <svg class="illustration" viewBox="0 0 300 170"
                                 xmlns="http://www.w3.org/2000/svg">

                                <rect width="300" height="170" fill="#fce8ed"/>

                                <circle cx="150" cy="80" r="60" fill="#fff7f5"/>

                                <path d="M150 125
                                         C125 105 85 80 105 53
                                         C120 35 143 50 150 65
                                         C160 43 185 35 200 53
                                         C220 80 175 110 150 125Z"
                                      fill="#db7695"/>

                                <path d="M150 125
                                         C130 105 110 85 125 65
                                         C140 48 150 72 150 72
                                         C150 72 165 48 180 65
                                         C195 85 170 110 150 125Z"
                                      fill="#f6b5c8"/>

                                <path d="M110 140 L190 140"
                                      stroke="#c58b9e" stroke-width="4"/>

                                <g fill="#e8a2b8">
                                    <circle cx="80" cy="45" r="5"/>
                                    <circle cx="220" cy="45" r="5"/>
                                    <circle cx="90" cy="120" r="5"/>
                                    <circle cx="210" cy="120" r="5"/>
                                </g>

                            </svg>

                            <div class="image-card-info">

                                <h3>Anniversary</h3>

                                <p>Romantic celebration</p>

                                <div class="selected-indicator">
                                    ✓ SELECTED
                                </div>

                            </div>

                        </div>

                    </label>


                    <!-- CORPORATE EVENT -->

                    <label class="image-option">

                        <input
                            type="radio"
                            name="event_type"
                            value="Corporate Event"
                            required
                        >

                        <div class="image-card">

                            <svg class="illustration" viewBox="0 0 300 170"
                                 xmlns="http://www.w3.org/2000/svg">

                                <rect width="300" height="170" fill="#e5eef7"/>

                                <rect x="45" y="25" width="210" height="115"
                                      rx="10" fill="#fff"/>

                                <rect x="65" y="42" width="170" height="55"
                                      rx="5" fill="#9bb9d3"/>

                                <rect x="78" y="53" width="144" height="35"
                                      fill="#d9e8f4"/>

                                <path d="M100 88 L120 65 L140 78 L165 55 L195 85"
                                      stroke="#7094b5" stroke-width="4"
                                      fill="none"/>

                                <rect x="85" y="112" width="130" height="8"
                                      rx="4" fill="#7e9fba"/>

                                <circle cx="70" cy="120" r="12" fill="#c2a5b3"/>
                                <circle cx="150" cy="120" r="12" fill="#c2a5b3"/>
                                <circle cx="230" cy="120" r="12" fill="#c2a5b3"/>

                                <path d="M70 132 L70 145 M150 132 L150 145 M230 132 L230 145"
                                      stroke="#70889d" stroke-width="4"/>

                            </svg>

                            <div class="image-card-info">

                                <h3>Corporate Event</h3>

                                <p>Professional business event</p>

                                <div class="selected-indicator">
                                    ✓ SELECTED
                                </div>

                            </div>

                        </div>

                    </label>


                    <!-- OTHER -->

                    <label class="image-option">

                        <input
                            type="radio"
                            name="event_type"
                            value="Other"
                            required
                        >

                        <div class="image-card">

                            <svg class="illustration" viewBox="0 0 300 170"
                                 xmlns="http://www.w3.org/2000/svg">

                                <rect width="300" height="170" fill="#e9f2e8"/>

                                <circle cx="150" cy="80" r="55" fill="#f9fff5"/>

                                <path d="M90 125 Q150 85 210 125"
                                      fill="none" stroke="#9ebc9d"
                                      stroke-width="8"/>

                                <path d="M150 30 L160 65 L195 65
                                         L167 85 L178 120
                                         L150 99 L122 120
                                         L133 85 L105 65
                                         L140 65 Z"
                                      fill="#b5cfae"/>

                                <circle cx="150" cy="80" r="13"
                                        fill="#e3a9bd"/>

                                <g fill="#a4c69d">
                                    <circle cx="80" cy="40" r="6"/>
                                    <circle cx="220" cy="40" r="6"/>
                                    <circle cx="80" cy="115" r="6"/>
                                    <circle cx="220" cy="115" r="6"/>
                                </g>

                            </svg>

                            <div class="image-card-info">

                                <h3>Other</h3>

                                <p>Other special occasions</p>

                                <div class="selected-indicator">
                                    ✓ SELECTED
                                </div>

                            </div>

                        </div>

                    </label>

                </div>

            </div>


            <!-- =================================
                 VENUE AREA SELECTION
            ================================== -->

            <div class="selection-section">

                <h3 class="selection-title">
                    Choose Venue Area
                </h3>

                <p class="selection-description">
                    Explore our available event locations.
                </p>

                <div class="image-selection-grid venue-grid">


                    <!-- POOL AREA -->

                    <label class="image-option">

                        <input
                            type="radio"
                            name="venue_area"
                            value="Pool Area"
                            required
                        >

                        <div class="image-card">

                            <svg class="illustration" viewBox="0 0 300 170"
                                 xmlns="http://www.w3.org/2000/svg">

                                <rect width="300" height="170" fill="#e2f5fb"/>

                                <circle cx="240" cy="30" r="20" fill="#f8d68e"/>

                                <path d="M0 75 Q75 55 150 75 T300 75"
                                      fill="#8bc9d9"/>

                                <rect x="20" y="80" width="260" height="65"
                                      rx="18" fill="#69bfd2"/>

                                <path d="M25 100 Q70 80 110 100 T190 100 T275 100"
                                      fill="none" stroke="#d6f6fa"
                                      stroke-width="4"/>

                                <path d="M40 65 L40 115"
                                      stroke="#96785d" stroke-width="5"/>

                                <path d="M20 65 Q40 30 60 65"
                                      fill="#79aa83"/>

                                <path d="M230 65 L230 115"
                                      stroke="#96785d" stroke-width="5"/>

                                <path d="M205 65 Q230 25 255 65"
                                      fill="#79aa83"/>

                                <path d="M0 145 L300 145"
                                      stroke="#b8d7c2" stroke-width="8"/>

                            </svg>

                            <div class="image-card-info">

                                <h3>Pool Area</h3>

                                <p>Refreshing outdoor venue</p>

                                <div class="selected-indicator">
                                    ✓ SELECTED
                                </div>

                            </div>

                        </div>

                    </label>


                    <!-- GARDEN -->

                    <label class="image-option">

                        <input
                            type="radio"
                            name="venue_area"
                            value="Garden"
                            required
                        >

                        <div class="image-card">

                            <svg class="illustration" viewBox="0 0 300 170"
                                 xmlns="http://www.w3.org/2000/svg">

                                <rect width="300" height="170" fill="#e8f3e4"/>

                                <circle cx="230" cy="30" r="20" fill="#f8dc96"/>

                                <rect x="20" y="85" width="260" height="65"
                                      fill="#d2e8c7"/>

                                <path d="M0 110 Q80 75 150 110 T300 110"
                                      fill="#8dbb88"/>

                                <path d="M150 110 L150 55"
                                      stroke="#886c4e" stroke-width="8"/>

                                <circle cx="150" cy="45" r="36"
                                        fill="#7cad79"/>

                                <circle cx="125" cy="55" r="19"
                                        fill="#91bd88"/>

                                <circle cx="177" cy="55" r="19"
                                        fill="#91bd88"/>

                                <g fill="#eaa3bd">
                                    <circle cx="50" cy="105" r="7"/>
                                    <circle cx="70" cy="125" r="7"/>
                                    <circle cx="240" cy="105" r="7"/>
                                    <circle cx="220" cy="125" r="7"/>
                                </g>

                                <g fill="#e5c66f">
                                    <circle cx="50" cy="105" r="3"/>
                                    <circle cx="70" cy="125" r="3"/>
                                    <circle cx="240" cy="105" r="3"/>
                                    <circle cx="220" cy="125" r="3"/>
                                </g>

                            </svg>

                            <div class="image-card-info">

                                <h3>Garden</h3>

                                <p>Beautiful outdoor greenery</p>

                                <div class="selected-indicator">
                                    ✓ SELECTED
                                </div>

                            </div>

                        </div>

                    </label>


                    <!-- GRAND BALLROOM -->

                    <label class="image-option">

                        <input
                            type="radio"
                            name="venue_area"
                            value="Grand Ballroom"
                            required
                        >

                        <div class="image-card">

                            <svg class="illustration" viewBox="0 0 300 170"
                                 xmlns="http://www.w3.org/2000/svg">

                                <rect width="300" height="170" fill="#f7e8ee"/>

                                <path d="M30 0 L75 0 L75 145 L30 145 Z"
                                      fill="#d8a6b9"/>

                                <path d="M225 0 L270 0 L270 145 L225 145 Z"
                                      fill="#d8a6b9"/>

                                <path d="M30 0 Q150 75 270 0"
                                      fill="#f4cad9"/>

                                <path d="M30 0 Q150 90 270 0"
                                      fill="none" stroke="#fff"
                                      stroke-width="5"/>

                                <rect x="90" y="65" width="120" height="75"
                                      rx="4" fill="#fff9f4"/>

                                <rect x="115" y="40" width="70" height="30"
                                      rx="4" fill="#e7bbcc"/>

                                <path d="M150 40 L150 20"
                                      stroke="#c48da5" stroke-width="3"/>

                                <circle cx="150" cy="15" r="9"
                                        fill="#f4d889"/>

                                <path d="M0 145 L300 145"
                                      stroke="#c99db0" stroke-width="5"/>

                                <path d="M100 145 L150 105 L200 145"
                                      fill="#f6d9e5"/>

                            </svg>

                            <div class="image-card-info">

                                <h3>Grand Ballroom</h3>

                                <p>Elegant spacious venue</p>

                                <div class="selected-indicator">
                                    ✓ SELECTED
                                </div>

                            </div>

                        </div>

                    </label>


                    <!-- FUNCTION ROOM -->

                    <label class="image-option">

                        <input
                            type="radio"
                            name="venue_area"
                            value="Function Room"
                            required
                        >

                        <div class="image-card">

                            <svg class="illustration" viewBox="0 0 300 170"
                                 xmlns="http://www.w3.org/2000/svg">

                                <rect width="300" height="170" fill="#e8edf5"/>

                                <rect x="35" y="25" width="230" height="120"
                                      rx="8" fill="#fff"/>

                                <rect x="60" y="40" width="180" height="48"
                                      rx="5" fill="#aec1d7"/>

                                <rect x="70" y="48" width="160" height="32"
                                      fill="#d8e7f5"/>

                                <path d="M90 100 L210 100"
                                      stroke="#829bb7" stroke-width="5"/>

                                <path d="M100 100 L100 125 M200 100 L200 125"
                                      stroke="#829bb7" stroke-width="4"/>

                                <rect x="115" y="105" width="70" height="20"
                                      rx="4" fill="#c2a4b5"/>

                                <circle cx="65" cy="120" r="11" fill="#d6afc0"/>
                                <circle cx="235" cy="120" r="11" fill="#d6afc0"/>

                                <path d="M0 145 L300 145"
                                      stroke="#b7c6d8" stroke-width="5"/>

                            </svg>

                            <div class="image-card-info">

                                <h3>Function Room</h3>

                                <p>Comfortable indoor venue</p>

                                <div class="selected-indicator">
                                    ✓ SELECTED
                                </div>

                            </div>

                        </div>

                    </label>

                </div>

            </div>


            <!-- =================================
                 OTHER BOOKING DETAILS
            ================================== -->

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


    <!-- =================================
         BOOKING SUMMARY
    ================================== -->

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
