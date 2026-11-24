
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
    "Wedding", "Birthday", "Debut",
    "Anniversary", "Corporate Event", "Other"
];

$venueAreas = [
    "Pool Area", "Garden", "Grand Ballroom", "Function Room"
];

// HANDLE BOOKING SUBMISSION
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $eventType = trim($_POST["event_type"] ?? "");
    $venueArea = trim($_POST["venue_area"] ?? "");
    $eventDate = $_POST["event_date"] ?? "";
    $eventTime = $_POST["event_time"] ?? "";
    $guestCount = filter_var($_POST["guest_count"] ?? "", FILTER_VALIDATE_INT);
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
        $_SESSION["flash_message"] = "Please complete all required fields.";
        $_SESSION["flash_type"] = "error";

    } elseif ($eventDate < date("Y-m-d")) {

        $_SESSION["flash_message"] = "Please choose today or a future date.";
        $_SESSION["flash_type"] = "error";

    } else {

        $validPackages = [
            "Basic Package - ₱10,000",
            "Classic Package - ₱20,000",
            "Premium Package - ₱35,000",
            "Custom Package - ₱50,000"
        ];

        if (!in_array($packageName, $validPackages, true)) {

            $_SESSION["flash_message"] = "Invalid package selected.";
            $_SESSION["flash_type"] = "error";

        } else {

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

                $checkStmt->close();

            } else {

                $checkStmt->close();

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
        }
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
   VISUAL BOOKING SELECTION
================================= */

.choice-section {
    margin-bottom: 35px;
}

.choice-section > label,
.section-label {
    display: block;
    font-weight: 700;
    color: #59434d;
    margin-bottom: 15px;
    font-size: 15px;
}

.choice-description {
    color: #967d88;
    font-size: 13px;
    margin-top: -8px;
    margin-bottom: 18px;
}

.visual-choice-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.venue-choice-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.visual-choice {
    position: relative;
    display: block;
    cursor: pointer;
    min-width: 0;
}

.visual-choice input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.choice-card {
    height: 100%;
    border: 2px solid #f0dce4;
    border-radius: 15px;
    overflow: hidden;
    background: #fffafa;
    transition: all 0.25s ease;
}

.choice-card:hover {
    border-color: #d38ba2;
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(164, 99, 123, 0.12);
}

.visual-choice input:checked + .choice-card {
    border-color: #c66f8b;
    background: #fff0f5;
    box-shadow: 0 0 0 3px rgba(198, 111, 139, 0.12);
}

.visual-choice input:focus-visible + .choice-card {
    outline: 3px solid #9c5270;
    outline-offset: 3px;
}

.choice-art {
    height: 145px;
    display: flex;
    justify-content: center;
    align-items: center;
    background: linear-gradient(140deg, #fce8ef, #f7d9e4);
    overflow: hidden;
}

.venue-choice-grid .choice-art {
    height: 155px;
}

.choice-art svg {
    width: 100%;
    height: 100%;
    max-width: 220px;
    padding: 12px;
}

.choice-info {
    padding: 13px 10px;
    text-align: center;
}

.choice-info h3 {
    font-size: 14px;
    color: #59404c;
    margin: 0 0 5px;
}

.choice-info p {
    font-size: 11px;
    color: #987e89;
    margin: 0;
    line-height: 1.5;
}

.choice-check {
    display: none;
    color: #bd6381;
    font-size: 12px;
    font-weight: 700;
    margin-top: 8px;
}

.visual-choice input:checked + .choice-card .choice-check {
    display: block;
}

/* Different illustration backgrounds */

.event-wedding .choice-art {
    background: linear-gradient(135deg, #f8e1e8, #f4cdd9);
}

.event-birthday .choice-art {
    background: linear-gradient(135deg, #ffe9d9, #fbd3bb);
}

.event-debut .choice-art {
    background: linear-gradient(135deg, #eee0f7, #d9c2eb);
}

.event-anniversary .choice-art {
    background: linear-gradient(135deg, #f9e2e9, #edc5d4);
}

.event-corporate .choice-art {
    background: linear-gradient(135deg, #dceaf4, #c5dce9);
}

.event-other .choice-art {
    background: linear-gradient(135deg, #e9e4f7, #d5d0ec);
}

.venue-pool .choice-art {
    background: linear-gradient(135deg, #d8f0f5, #acdce8);
}

.venue-garden .choice-art {
    background: linear-gradient(135deg, #e0f0dc, #c5e1bf);
}

.venue-ballroom .choice-art {
    background: linear-gradient(135deg, #f8e9d2, #edd3a9);
}

.venue-function .choice-art {
    background: linear-gradient(135deg, #e9e1f1, #d6c9e7);
}

/* OTHER FORM FIELDS */

.booking-form .form-grid {
    margin-top: 25px;
}

.booking-form button[type="submit"] {
    margin-top: 20px;
}

/* BOOKING SUMMARY */

.booking-summary-title {
    margin-bottom: 20px;
}

/* MOBILE RESPONSIVE */

@media (max-width: 900px) {

    .venue-choice-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

}

@media (max-width: 600px) {

    .visual-choice-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .choice-art {
        height: 115px;
    }

    .venue-choice-grid .choice-art {
        height: 120px;
    }

    .choice-info h3 {
        font-size: 12px;
    }

    .choice-info p {
        font-size: 10px;
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

        <h1>
            Book your <span>special event</span>
        </h1>

        <p>
            Explore our event styles and venue areas.
            Choose your preferred event, location, and package.
            A down payment settled in person is required to confirm your schedule.
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

        <p class="muted">
            Select an event type and venue area to explore your options.
        </p>


        <form method="POST" action="booking.php" class="booking-form">

            <!-- =================================
                 EVENT TYPE SELECTION
            ================================== -->

            <div class="choice-section">

                <label class="section-label">
                    Choose Event Type
                </label>

                <p class="choice-description">
                    Select the type of celebration you are planning.
                </p>


                <div class="visual-choice-grid">


                    <!-- WEDDING -->

                    <label class="visual-choice event-wedding">

                        <input type="radio"
                               name="event_type"
                               value="Wedding"
                               required>

                        <div class="choice-card">

                            <div class="choice-art">

                                <svg viewBox="0 0 200 130" xmlns="http://www.w3.org/2000/svg">

                                    <circle cx="100" cy="65" r="48" fill="#fff8fa" opacity=".55"/>

                                    <path d="M65 108 L88 48 L100 68 L112 48 L135 108 Z"
                                          fill="#fff" stroke="#b77b91" stroke-width="2"/>

                                    <path d="M88 48 L100 68 L112 48"
                                          fill="none" stroke="#d19bad" stroke-width="2"/>

                                    <circle cx="88" cy="40" r="12" fill="none" stroke="#b77b91" stroke-width="5"/>

                                    <circle cx="112" cy="40" r="12" fill="none" stroke="#d5a4b4" stroke-width="5"/>

                                    <path d="M78 28 Q100 12 122 28"
                                          fill="none" stroke="#c58ca0" stroke-width="2"/>

                                    <circle cx="100" cy="20" r="4" fill="#d6a4b4"/>

                                    <path d="M65 108 Q100 116 135 108"
                                          fill="none" stroke="#c58ca0" stroke-width="2"/>

                                </svg>

                            </div>

                            <div class="choice-info">

                                <h3>Wedding</h3>

                                <p>Elegant and romantic celebration</p>

                                <span class="choice-check">✓ Selected</span>

                            </div>

                        </div>

                    </label>


                    <!-- BIRTHDAY -->

                    <label class="visual-choice event-birthday">

                        <input type="radio"
                               name="event_type"
                               value="Birthday"
                               required>

                        <div class="choice-card">

                            <div class="choice-art">

                                <svg viewBox="0 0 200 130" xmlns="http://www.w3.org/2000/svg">

                                    <circle cx="100" cy="65" r="49" fill="#fff8f1" opacity=".55"/>

                                    <rect x="52" y="66" width="96" height="39" rx="8"
                                          fill="#f6b5a5" stroke="#c77f70" stroke-width="2"/>

                                    <path d="M52 82 H148" stroke="#fff1dc" stroke-width="5"/>

                                    <rect x="65" y="43" width="70" height="27" rx="5"
                                          fill="#fff1d9" stroke="#d99c77" stroke-width="2"/>

                                    <path d="M75 43 Q85 32 95 43 Q105 32 115 43 Q125 32 125 43"
                                          fill="#f5b7a0"/>

                                    <path d="M100 43 V22" stroke="#8b6170" stroke-width="3"/>

                                    <path d="M95 22 Q100 9 105 22 Q100 31 95 22"
                                          fill="#edaa58"/>

                                    <circle cx="70" cy="30" r="7" fill="#d98aa4"/>

                                    <circle cx="130" cy="25" r="8" fill="#edb46c"/>

                                    <path d="M40 45 L44 55 M36 50 L47 50 M155 45 L160 55 M152 50 L164 50"
                                          stroke="#c98a9e" stroke-width="2"/>

                                </svg>

                            </div>

                            <div class="choice-info">

                                <h3>Birthday</h3>

                                <p>Fun and colorful celebration</p>

                                <span class="choice-check">✓ Selected</span>

                            </div>

                        </div>

                    </label>


                    <!-- DEBUT -->

                    <label class="visual-choice event-debut">

                        <input type="radio"
                               name="event_type"
                               value="Debut"
                               required>

                        <div class="choice-card">

                            <div class="choice-art">

                                <svg viewBox="0 0 200 130" xmlns="http://www.w3.org/2000/svg">

                                    <circle cx="100" cy="65" r="48" fill="#fffaff" opacity=".6"/>

                                    <path d="M100 15 L108 43 L137 45 L115 63 L122 92 L100 76 L78 92 L85 63 L63 45 L92 43 Z"
                                          fill="#e5c7f1" stroke="#9f76b4" stroke-width="2"/>

                                    <path d="M100 32 L105 49 L123 51 L109 62 L113 80 L100 70 L87 80 L91 62 L77 51 L95 49 Z"
                                          fill="#fff6dc"/>

                                    <path d="M45 105 Q100 117 155 105"
                                          fill="none" stroke="#b894d0" stroke-width="3"/>

                                    <circle cx="49" cy="35" r="4" fill="#d6b4e5"/>
                                    <circle cx="150" cy="35" r="4" fill="#d6b4e5"/>
                                    <circle cx="55" cy="80" r="3" fill="#b894d0"/>
                                    <circle cx="145" cy="80" r="3" fill="#b894d0"/>

                                </svg>

                            </div>

                            <div class="choice-info">

                                <h3>Debut</h3>

                                <p>Elegant and memorable milestone</p>

                                <span class="choice-check">✓ Selected</span>

                            </div>

                        </div>

                    </label>


                    <!-- ANNIVERSARY -->

                    <label class="visual-choice event-anniversary">

                        <input type="radio"
                               name="event_type"
                               value="Anniversary"
                               required>

                        <div class="choice-card">

                            <div class="choice-art">

                                <svg viewBox="0 0 200 130" xmlns="http://www.w3.org/2000/svg">

                                    <circle cx="100" cy="65" r="48" fill="#fff8fa" opacity=".6"/>

                                    <path d="M100 105
                                             C80 89 47 69 65 43
                                             C78 25 96 41 100 51
                                             C111 30 136 30 145 49
                                             C157 75 120 96 100 105Z"
                                          fill="#e88ca5" stroke="#bd617e" stroke-width="2"/>

                                    <path d="M100 94
                                             C88 83 67 69 77 53
                                             C85 43 96 51 100 60
                                             C108 47 124 47 130 57
                                             C140 74 115 88 100 94Z"
                                          fill="#fff0f4"/>

                                    <path d="M42 43 Q50 30 58 43 M142 99 Q150 85 158 99"
                                          fill="none" stroke="#d3a0b1" stroke-width="3"/>

                                    <circle cx="50" cy="76" r="4" fill="#d9a3b4"/>
                                    <circle cx="151" cy="34" r="4" fill="#d9a3b4"/>

                                </svg>

                            </div>

                            <div class="choice-info">

                                <h3>Anniversary</h3>

                                <p>Celebrate special memories together</p>

                                <span class="choice-check">✓ Selected</span>

                            </div>

                        </div>

                    </label>


                    <!-- CORPORATE EVENT -->

                    <label class="visual-choice event-corporate">

                        <input type="radio"
                               name="event_type"
                               value="Corporate Event"
                               required>

                        <div class="choice-card">

                            <div class="choice-art">

                                <svg viewBox="0 0 200 130" xmlns="http://www.w3.org/2000/svg">

                                    <circle cx="100" cy="65" r="48" fill="#f8fcff" opacity=".6"/>

                                    <rect x="45" y="35" width="110" height="65" rx="7"
                                          fill="#e5f1f8" stroke="#7296ae" stroke-width="2"/>

                                    <rect x="60" y="47" width="80" height="35" rx="3"
                                          fill="#94b8ca"/>

                                    <path d="M100 82 V95 M82 96 H118"
                                          stroke="#577c96" stroke-width="4" stroke-linecap="round"/>

                                    <path d="M69 68 L85 58 L97 65 L116 52 L131 59"
                                          fill="none" stroke="#fff" stroke-width="3"/>

                                    <circle cx="85" cy="58" r="3" fill="#fff"/>
                                    <circle cx="116" cy="52" r="3" fill="#fff"/>

                                    <path d="M38 105 H162" stroke="#7296ae" stroke-width="3"/>

                                </svg>

                            </div>

                            <div class="choice-info">

                                <h3>Corporate Event</h3>

                                <p>Professional gatherings and meetings</p>

                                <span class="choice-check">✓ Selected</span>

                            </div>

                        </div>

                    </label>


                    <!-- OTHER -->

                    <label class="visual-choice event-other">

                        <input type="radio"
                               name="event_type"
                               value="Other"
                               required>

                        <div class="choice-card">

                            <div class="choice-art">

                                <svg viewBox="0 0 200 130" xmlns="http://www.w3.org/2000/svg">

                                    <circle cx="100" cy="65" r="48" fill="#fbfaff" opacity=".6"/>

                                    <path d="M100 19 L109 49 L140 49 L115 69 L124 101 L100 82 L76 101 L85 69 L60 49 L91 49 Z"
                                          fill="#c8b9e7" stroke="#8c77b6" stroke-width="2"/>

                                    <circle cx="100" cy="62" r="15" fill="#fff5d9"/>

                                    <path d="M100 43 V27 M100 97 V112 M80 62 H64 M120 62 H136"
                                          stroke="#a48ac7" stroke-width="3" stroke-linecap="round"/>

                                </svg>

                            </div>

                            <div class="choice-info">

                                <h3>Other</h3>

                                <p>For other special occasions</p>

                                <span class="choice-check">✓ Selected</span>

                            </div>

                        </div>

                    </label>


                </div>

            </div>


            <!-- =================================
                 VENUE AREA SELECTION
            ================================== -->

            <div class="choice-section">

                <label class="section-label">
                    Choose Venue Area
                </label>

                <p class="choice-description">
                    Explore the available spaces for your event.
                </p>


                <div class="visual-choice-grid venue-choice-grid">


                    <!-- POOL AREA -->

                    <label class="visual-choice venue-pool">

                        <input type="radio"
                               name="venue_area"
                               value="Pool Area"
                               required>

                        <div class="choice-card">

                            <div class="choice-art">

                                <svg viewBox="0 0 200 130" xmlns="http://www.w3.org/2000/svg">

                                    <rect x="15" y="20" width="170" height="90" rx="14"
                                          fill="#e5f8fc"/>

                                    <circle cx="152" cy="36" r="13" fill="#f5d18c"/>

                                    <path d="M15 65 Q45 49 75 65 T135 65 T185 65 V110 H15 Z"
                                          fill="#65b9d0"/>

                                    <path d="M15 81 Q45 65 75 81 T135 81 T185 81"
                                          fill="none" stroke="#fff" stroke-width="4"/>

                                    <path d="M15 98 Q45 82 75 98 T135 98 T185 98"
                                          fill="none" stroke="#c7edf2" stroke-width="4"/>

                                    <path d="M40 48 L40 28 M34 28 H46"
                                          stroke="#b58a64" stroke-width="4" stroke-linecap="round"/>

                                    <path d="M31 45 Q40 35 49 45"
                                          fill="#e2a98b"/>

                                </svg>

                            </div>

                            <div class="choice-info">

                                <h3>Pool Area</h3>

                                <p>Relaxed outdoor setting</p>

                                <span class="choice-check">✓ Selected</span>

                            </div>

                        </div>

                    </label>


                    <!-- GARDEN -->

                    <label class="visual-choice venue-garden">

                        <input type="radio"
                               name="venue_area"
                               value="Garden"
                               required>

                        <div class="choice-card">

                            <div class="choice-art">

                                <svg viewBox="0 0 200 130" xmlns="http://www.w3.org/2000/svg">

                                    <rect x="15" y="15" width="170" height="100" rx="14"
                                          fill="#f1f8e9"/>

                                    <circle cx="150" cy="34" r="12" fill="#f5d78d"/>

                                    <path d="M20 98 Q100 70 180 98 V115 H20Z"
                                          fill="#9ac68c"/>

                                    <rect x="91" y="57" width="17" height="44" rx="4"
                                          fill="#9b7657"/>

                                    <circle cx="100" cy="43" r="29" fill="#83b77b"/>
                                    <circle cx="80" cy="55" r="17" fill="#91c789"/>
                                    <circle cx="120" cy="55" r="17" fill="#91c789"/>

                                    <circle cx="50" cy="87" r="5" fill="#e6a0ba"/>
                                    <circle cx="65" cy="96" r="5" fill="#f3d08d"/>
                                    <circle cx="140" cy="91" r="5" fill="#e6a0ba"/>
                                    <circle cx="155" cy="99" r="5" fill="#f3d08d"/>

                                </svg>

                            </div>

                            <div class="choice-info">

                                <h3>Garden</h3>

                                <p>Fresh and natural surroundings</p>

                                <span class="choice-check">✓ Selected</span>

                            </div>

                        </div>

                    </label>


                    <!-- GRAND BALLROOM -->

                    <label class="visual-choice venue-ballroom">

                        <input type="radio"
                               name="venue_area"
                               value="Grand Ballroom"
                               required>

                        <div class="choice-card">

                            <div class="choice-art">

                                <svg viewBox="0 0 200 130" xmlns="http://www.w3.org/2000/svg">

                                    <rect x="15" y="12" width="170" height="105" rx="12"
                                          fill="#fff8e9"/>

                                    <path d="M15 35 H185" stroke="#d7b879" stroke-width="3"/>

                                    <path d="M35 35 V85 M165 35 V85"
                                          stroke="#c99f69" stroke-width="5"/>

                                    <path d="M35 35 Q100 62 165 35"
                                          fill="none" stroke="#e2c99b" stroke-width="3"/>

                                    <path d="M100 20 V47 M83 47 H117 M87 47 L77 67 M113 47 L123 67"
                                          stroke="#c69b56" stroke-width="3" stroke-linecap="round"/>

                                    <circle cx="100" cy="55" r="8" fill="#f3d78d"/>

                                    <path d="M72 90 Q100 72 128 90 V108 H72Z"
                                          fill="#dfc28c"/>

                                    <circle cx="65" cy="105" r="7" fill="#d8b77e"/>
                                    <circle cx="135" cy="105" r="7" fill="#d8b77e"/>

                                </svg>

                            </div>

                            <div class="choice-info">

                                <h3>Grand Ballroom</h3>

                                <p>Spacious and elegant interior</p>

                                <span class="choice-check">✓ Selected</span>

                            </div>

                        </div>

                    </label>


                    <!-- FUNCTION ROOM -->

                    <label class="visual-choice venue-function">

                        <input type="radio"
                               name="venue_area"
                               value="Function Room"
                               required>

                        <div class="choice-card">

                            <div class="choice-art">

                                <svg viewBox="0 0 200 130" xmlns="http://www.w3.org/2000/svg">

                                    <rect x="15" y="12" width="170" height="105" rx="12"
                                          fill="#f7f3fb"/>

                                    <rect x="48" y="25" width="104" height="42" rx="5"
                                          fill="#bba7d2"/>

                                    <rect x="57" y="32" width="86" height="27" rx="3"
                                          fill="#e8e0f2"/>

                                    <path d="M100 59 V68 M80 68 H120"
                                          stroke="#967ead" stroke-width="3"/>

                                    <rect x="43" y="82" width="114" height="12" rx="5"
                                          fill="#9c83b5"/>

                                    <path d="M55 94 V108 M145 94 V108"
                                          stroke="#806b96" stroke-width="4"/>

                                    <circle cx="34" cy="78" r="9" fill="#d6c8e6"/>
                                    <circle cx="166" cy="78" r="9" fill="#d6c8e6"/>

                                </svg>

                            </div>

                            <div class="choice-info">

                                <h3>Function Room</h3>

                                <p>Comfortable indoor gathering space</p>

                                <span class="choice-check">✓ Selected</span>

                            </div>

                        </div>

                    </label>


                </div>

            </div>


            <!-- =================================
                 DATE, TIME, GUESTS AND PACKAGE
            ================================== -->

            <div class="form-grid">

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


            <button class="btn primary" type="submit">
                Submit reservation request
            </button>

        </form>

    </section>


    <!-- =================================
         CUSTOMER BOOKING SUMMARY
    ================================== -->

    <section class="content-card">

        <h2 class="booking-summary-title">
            My booking summary & status
        </h2>


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
                                        <?= e(date("g:i A", strtotime($row["event_time"]))) ?>
                                    </small>

                                </td>


                                <td>
                                    <?= e($row["guest_count"]) ?>
                                </td>


                                <td>
                                    <?= e($row["package_name"] ?: "—") ?>
                                </td>


                                <td>

                                    <span class="status">
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

    © <?= date("Y") ?> CED Event & Catering Services
    — In-person payment policy applies.

</footer>


</body>
</html>

<?php
$stmt->close();
?>
