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

// SUBMIT BOOKING — existing booking logic retained
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
        $checkStmt = $conn->prepare(
            "SELECT booking_id FROM bookings
             WHERE venue_area = ?
             AND event_date = ?
             AND event_time = ?
             AND booking_status != 'Cancelled'"
        );
        $checkStmt->bind_param("sss", $venueArea, $eventDate, $eventTime);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $_SESSION["flash_message"] = "Sorry, this specific venue area is already booked for the selected date and time.";
            $_SESSION["flash_type"] = "error";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO bookings
                (customer_id, event_type, venue_area, event_date, event_time,
                 guest_count, package_name, booking_status, payment_status)
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
                $_SESSION["flash_message"] = "Reservation submitted successfully! Please settle your down payment in person at CED Event & Catering Services.";
                $_SESSION["flash_type"] = "success";
            } else {
                $_SESSION["flash_message"] = "Unable to save booking. Please try again.";
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
    unset($_SESSION["flash_message"], $_SESSION["flash_type"]);
}

// GET CUSTOMER BOOKINGS
$stmt = $conn->prepare(
    "SELECT booking_id, event_type, venue_area, event_date, event_time,
            guest_count, package_name, booking_status, payment_status
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
<title>CED Event & Catering Services | Booking</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
:root {
    --rose-900: #79223f;
    --rose-800: #963454;
    --rose-700: #b7476c;
    --rose-600: #ca6687;
    --rose-300: #efb6c8;
    --rose-200: #f7d5df;
    --rose-100: #fff0f4;
    --cream: #fffaf8;
    --ink: #553541;
    --muted: #987b85;
    --line: rgba(177, 87, 119, .22);
    --shadow: 0 14px 35px rgba(126, 49, 78, .11);
}

* { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    margin: 0;
    color: var(--ink);
    font-family: 'DM Sans', sans-serif;
    background:
        radial-gradient(ellipse at 8% 12%, rgba(255,255,255,.92), transparent 27%),
        radial-gradient(ellipse at 94% 30%, rgba(255,255,255,.70), transparent 24%),
        radial-gradient(ellipse at 10% 90%, rgba(255,255,255,.65), transparent 23%),
        linear-gradient(135deg, #f9dce5 0%, #fff1f3 43%, #f7d6e1 100%);
    min-height: 100vh;
    overflow-x: hidden;
}
body::before, body::after {
    content: "";
    position: fixed;
    z-index: -1;
    pointer-events: none;
    width: 330px;
    height: 100vh;
    top: 0;
    opacity: .18;
    background-image: url('https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=700&q=85');
    background-size: cover;
    background-position: center;
    filter: saturate(.8);
    mask-image: linear-gradient(to bottom, transparent 0%, #000 18%, #000 80%, transparent 100%);
}
body::before { left: -215px; }
body::after { right: -215px; transform: scaleX(-1); }

.ced-header {
    width: min(1420px, calc(100% - 42px));
    margin: 18px auto 0;
    min-height: 76px;
    padding: 10px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    border: 1px solid rgba(255,255,255,.9);
    border-radius: 22px;
    background: rgba(255, 250, 250, .76);
    box-shadow: 0 8px 28px rgba(139, 60, 88, .10);
    backdrop-filter: blur(14px);
}
.ced-brand { text-decoration: none; color: var(--rose-900); display: flex; flex-direction: column; line-height: 1; min-width: 210px; }
.ced-brand strong { font: 42px/ .9 'Great Vibes', cursive; }
.ced-brand span { font: italic 13px 'Playfair Display', serif; margin-left: 6px; margin-top: 5px; }
.ced-nav { display: flex; align-items: center; gap: clamp(15px, 3vw, 42px); }
.ced-nav a { color: var(--rose-900); text-decoration: none; font: 500 15px 'Playfair Display', serif; transition: color .2s ease; }
.ced-nav a:hover { color: var(--rose-600); }
.ced-user { display: flex; align-items: center; gap: 10px; }
.ced-greeting { color: var(--rose-900); border: 1px solid #edbdcc; background: rgba(255,255,255,.55); border-radius: 30px; padding: 10px 16px; font-size: 13px; }
.ced-logout { background: linear-gradient(135deg, #d77d9a, #b63e67); color: white !important; border-radius: 30px; padding: 12px 19px; box-shadow: 0 5px 13px rgba(177, 62, 103, .2); }

.ced-main { width: min(1420px, calc(100% - 42px)); margin: 0 auto; padding: 30px 0 65px; }
.ced-hero { text-align: center; padding: 12px 15px 28px; animation: riseIn .7s ease both; }
.ced-eyebrow { color: var(--rose-800); font-size: 11px; letter-spacing: .28em; font-weight: 700; margin: 0 0 8px; }
.ced-hero h1 { font: 68px/.98 'Great Vibes', cursive; color: #a93660; margin: 0; text-shadow: 0 3px 12px rgba(255,255,255,.8); }
.ced-hero p { color: #79515f; font: italic 16px 'Playfair Display', serif; margin: 12px 0 0; }

.ced-section { margin: 0 0 30px; animation: riseIn .7s ease both; }
.ced-section:nth-of-type(3) { animation-delay: .08s; }
.ced-section-heading { display: grid; grid-template-columns: minmax(25px,1fr) auto minmax(25px,1fr); align-items: center; gap: 20px; text-align: center; margin: 0 0 17px; }
.ced-heading-line { height: 1px; background: linear-gradient(90deg, transparent, #c77b96); position: relative; }
.ced-heading-line:last-child { background: linear-gradient(90deg, #c77b96, transparent); }
.ced-heading-copy { min-width: 260px; }
.ced-heading-copy h2 { margin: 0; color: #a3335d; font: 700 30px 'Playfair Display', serif; }
.ced-heading-copy p { margin: 3px 0 0; color: #896d78; font: italic 13px 'Playfair Display', serif; }
.ced-section-icon { display: inline-block; margin-right: 8px; color: #bd4770; font-size: 25px; vertical-align: 2px; }

.ced-choice-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 14px; }
.ced-choice-grid.venue-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.ced-choice { display: block; position: relative; cursor: pointer; min-width: 0; }
.ced-choice input { position: absolute; opacity: 0; width: 1px; height: 1px; }
.ced-choice-card { position: relative; overflow: hidden; border: 2px solid rgba(255,255,255,.95); border-radius: 17px; background: #fff8fa; box-shadow: 0 7px 18px rgba(124, 48, 77, .12); transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease; }
.ced-choice-card:hover { transform: translateY(-5px); box-shadow: 0 14px 25px rgba(124, 48, 77, .18); }
.ced-choice input:checked + .ced-choice-card { border-color: #b83e69; box-shadow: 0 0 0 3px rgba(184,62,105,.13), 0 12px 25px rgba(124,48,77,.16); transform: translateY(-3px); }
.ced-choice input:focus-visible + .ced-choice-card { outline: 3px solid #a93660; outline-offset: 3px; }
.ced-choice-photo { width: 100%; height: 145px; display: block; object-fit: cover; transition: transform .55s ease; }
.ced-choice:hover .ced-choice-photo { transform: scale(1.06); }
.venue-grid .ced-choice-photo { height: 155px; }
.ced-choice-label { min-height: 45px; margin: -1px 8px 8px; position: relative; z-index: 1; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 9px 8px; border-radius: 30px; color: #7d2948; background: rgba(255, 242, 246, .96); box-shadow: 0 3px 9px rgba(110, 42, 68, .10); font: 600 14px 'Playfair Display', serif; text-align: center; }
.ced-choice-label .choice-icon { font-size: 19px; color: #b53c68; }
.ced-selected { display: none; position: absolute; right: 10px; top: 10px; z-index: 2; padding: 5px 9px; border-radius: 20px; color: white; background: #a93660; font-size: 10px; font-weight: 700; letter-spacing: .04em; box-shadow: 0 3px 8px #6b29434a; }
.ced-choice input:checked + .ced-choice-card .ced-selected { display: block; }

.ced-lower-grid { display: grid; grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr); gap: 20px; align-items: start; margin-top: 34px; }
.ced-panel { padding: 23px; border: 1px solid rgba(255,255,255,.95); border-radius: 24px; background: rgba(255, 250, 250, .82); box-shadow: var(--shadow); backdrop-filter: blur(12px); }
.ced-panel-title { display: flex; align-items: center; gap: 10px; margin: 0 0 20px; color: #a3335d; font: 600 23px 'Playfair Display', serif; }
.ced-panel-title .title-icon { color: #bd4770; font-size: 22px; }
.ced-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 17px 15px; }
.ced-field label { display: block; margin: 0 0 8px; color: #79344f; font-size: 13px; font-weight: 700; }
.ced-field input, .ced-field select { width: 100%; min-height: 45px; padding: 11px 13px; border: 1px solid #edbdcc; border-radius: 12px; background: rgba(255,255,255,.78); color: #704052; font: 14px 'DM Sans', sans-serif; outline: none; transition: border-color .2s, box-shadow .2s; }
.ced-field input:focus, .ced-field select:focus { border-color: #bd5b7e; box-shadow: 0 0 0 3px rgba(189,91,126,.12); }
.ced-submit { width: 100%; min-height: 49px; margin-top: 20px; border: 0; border-radius: 30px; color: #fff; background: linear-gradient(110deg, #dc89a4, #b63f69); box-shadow: 0 8px 18px rgba(182,63,105,.23); font: 600 15px 'Playfair Display', serif; cursor: pointer; transition: transform .2s, box-shadow .2s; }
.ced-submit:hover { transform: translateY(-2px); box-shadow: 0 12px 22px rgba(182,63,105,.3); }
.ced-alert { padding: 13px 17px; margin: 0 0 20px; border-radius: 13px; font-size: 14px; }
.ced-alert.success { color: #386c50; background: #eaf7ef; border: 1px solid #c9e8d4; }
.ced-alert.error { color: #9b3150; background: #fff0f3; border: 1px solid #efc3cf; }
.ced-muted { color: var(--muted); font-size: 13px; }
.ced-table-wrap { width: 100%; overflow-x: auto; border: 1px solid #f0c8d4; border-radius: 14px; }
.ced-table { width: 100%; border-collapse: collapse; min-width: 600px; background: rgba(255,255,255,.58); }
.ced-table th { padding: 13px 11px; color: #79223f; background: #f9dce5; font: 600 12px 'Playfair Display', serif; text-align: left; white-space: nowrap; }
.ced-table td { padding: 13px 11px; color: #684653; border-top: 1px solid #f1d5de; font-size: 12px; vertical-align: middle; }
.ced-table td small { display: block; margin-top: 5px; color: #a1848e; font-size: 11px; }
.ced-status { display: inline-block; padding: 5px 10px; border-radius: 20px; color: #963454; background: #f9dce5; font-size: 11px; font-weight: 700; white-space: nowrap; }
.ced-footer { padding: 22px 15px 28px; color: #906b7a; text-align: center; font: italic 12px 'Playfair Display', serif; }

@keyframes riseIn { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
@media (max-width: 1050px) {
    .ced-choice-grid { grid-template-columns: repeat(3, minmax(0,1fr)); }
    .ced-choice-grid.venue-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .ced-lower-grid { grid-template-columns: 1fr; }
    .ced-nav { gap: 15px; }
}
@media (max-width: 700px) {
    .ced-header { width: min(100% - 22px, 600px); padding: 13px 15px; flex-wrap: wrap; }
    .ced-brand { min-width: auto; }
    .ced-brand strong { font-size: 35px; }
    .ced-nav { order: 3; width: 100%; justify-content: center; flex-wrap: wrap; gap: 17px; padding-top: 7px; }
    .ced-nav a { font-size: 13px; }
    .ced-greeting { display: none; }
    .ced-main { width: min(100% - 22px, 600px); padding-top: 23px; }
    .ced-hero h1 { font-size: 51px; }
    .ced-hero p { font-size: 14px; line-height: 1.6; }
    .ced-heading-copy { min-width: 0; }
    .ced-heading-copy h2 { font-size: 22px; }
    .ced-heading-copy p { font-size: 11px; }
    .ced-section-heading { gap: 10px; }
    .ced-choice-grid { grid-template-columns: repeat(2, minmax(0,1fr)); gap: 11px; }
    .ced-choice-grid.venue-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .ced-choice-photo, .venue-grid .ced-choice-photo { height: 125px; }
    .ced-choice-label { font-size: 12px; min-height: 40px; }
    .ced-panel { padding: 17px; border-radius: 19px; }
    .ced-panel-title { font-size: 20px; }
}
@media (max-width: 390px) {
    .ced-form-grid { grid-template-columns: 1fr; }
    .ced-user { margin-left: auto; }
    .ced-hero h1 { font-size: 45px; }
}
</style>
</head>
<body>
<header class="ced-header">
    <a class="ced-brand" href="booking.php">
        <strong>♛ CED</strong>
        <span>Event &amp; Catering Services</span>
    </a>
    <nav class="ced-nav" aria-label="Main navigation">
        <a href="booking.php">Home</a>
        <a href="#event-selection">Events</a>
        <a href="#booking-form">Booking</a>
        <a href="#booking-summary">My Bookings</a>
    </nav>
    <div class="ced-user">
        <span class="ced-greeting">♙ Hello, <?= e($_SESSION["customer_name"] ?? "Customer") ?></span>
        <a class="ced-nav ced-logout" href="index.php?logout=1">⇥ Log out</a>
    </div>
</header>

<main class="ced-main">
    <section class="ced-hero">
        <p class="ced-eyebrow">✦ &nbsp; RESERVATION SYSTEM &nbsp; ✦</p>
        <h1>Book your special event</h1>
        <p>Choose your preferred event type and venue area.<br>A down payment settled in person is required to confirm your schedule.</p>
    </section>

    <?php if ($message !== ""): ?>
        <div class="ced-alert <?= e($messageType) ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <form method="POST" action="booking.php" class="booking-form">
        <section class="ced-section" id="event-selection">
            <div class="ced-section-heading">
                <span class="ced-heading-line"></span>
                <div class="ced-heading-copy">
                    <h2><span class="ced-section-icon">♛</span>Choose Your Event</h2>
                    <p>Find the perfect event package for your special occasion.</p>
                </div>
                <span class="ced-heading-line"></span>
            </div>

            <div class="ced-choice-grid">
                <?php
                $eventCards = [
                    ["Wedding", "♡", "https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=700&q=85"],
                    ["Birthday", "♜", "https://images.unsplash.com/photo-1464349095431-e9a21285b5f3?auto=format&fit=crop&w=700&q=85"],
                    ["Debut", "♕", "https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=700&q=85"],
                    ["Corporate Event", "✧", "https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=700&q=85"],
                    ["Anniversary", "♡", "https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=700&q=85"],
                    ["Other", "❀", "https://images.unsplash.com/photo-1549465220-1a8b9238cd48?auto=format&fit=crop&w=700&q=85"]
                ];
                foreach ($eventCards as $card): ?>
                    <label class="ced-choice">
                        <input type="radio" name="event_type" value="<?= e($card[0]) ?>" required>
                        <div class="ced-choice-card">
                            <span class="ced-selected">✓ SELECTED</span>
                            <img class="ced-choice-photo" src="<?= e($card[2]) ?>" alt="<?= e($card[0]) ?> event" loading="lazy">
                            <div class="ced-choice-label"><span class="choice-icon"><?= e($card[1]) ?></span><?= e($card[0]) ?></div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="ced-section" id="venue-selection">
            <div class="ced-section-heading">
                <span class="ced-heading-line"></span>
                <div class="ced-heading-copy">
                    <h2><span class="ced-section-icon">♥</span>Choose Your Venue</h2>
                    <p>Select the perfect venue area for your event.</p>
                </div>
                <span class="ced-heading-line"></span>
            </div>

            <div class="ced-choice-grid venue-grid">
                <?php
                $venueCards = [
                    ["Grand Ballroom", "♜", "https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=800&q=85"],
                    ["Garden", "♣", "https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=800&q=85"],
                    ["Pool Area", "≋", "https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=800&q=85"],
                    ["Function Room", "♟", "https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=800&q=85"]
                ];
                foreach ($venueCards as $card): ?>
                    <label class="ced-choice">
                        <input type="radio" name="venue_area" value="<?= e($card[0]) ?>" required>
                        <div class="ced-choice-card">
                            <span class="ced-selected">✓ SELECTED</span>
                            <img class="ced-choice-photo" src="<?= e($card[2]) ?>" alt="<?= e($card[0]) ?> venue" loading="lazy">
                            <div class="ced-choice-label"><span class="choice-icon"><?= e($card[1]) ?></span><?= e($card[0] === "Garden" ? "Garden Venue" : $card[0]) ?></div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="ced-lower-grid">
            <section class="ced-panel" id="booking-form">
                <h2 class="ced-panel-title"><span class="title-icon">♛</span>Event Booking Form</h2>
                <div class="ced-form-grid">
                    <div class="ced-field">
                        <label for="event_date">▦ &nbsp; Event Date</label>
                        <input id="event_date" type="date" name="event_date" min="<?= date("Y-m-d") ?>" required>
                    </div>
                    <div class="ced-field">
                        <label for="event_time">◷ &nbsp; Event Time</label>
                        <input id="event_time" type="time" name="event_time" required>
                    </div>
                    <div class="ced-field">
                        <label for="guest_count">♟ &nbsp; Number of Guests</label>
                        <input id="guest_count" type="number" name="guest_count" min="1" max="10000" placeholder="e.g. 100" required>
                    </div>
                    <div class="ced-field">
                        <label for="package_name">♜ &nbsp; Package &amp; Cost</label>
                        <select id="package_name" name="package_name" required>
                            <option value="">Choose package</option>
                            <option value="Basic Package - ₱10,000">Basic Package - ₱10,000</option>
                            <option value="Classic Package - ₱20,000">Classic Package - ₱20,000</option>
                            <option value="Premium Package - ₱35,000">Premium Package - ₱35,000</option>
                            <option value="Custom Package - ₱50,000">Custom Package - ₱50,000</option>
                        </select>
                    </div>
                </div>
                <button class="ced-submit" type="submit">➤ &nbsp; Submit Reservation Request</button>
            </section>

            <section class="ced-panel" id="booking-summary">
                <h2 class="ced-panel-title"><span class="title-icon">♛</span>My Booking Summary &amp; Status</h2>
                <?php if ($bookings->num_rows === 0): ?>
                    <p class="ced-muted">You don't have any reservations yet. Your booking details will appear here after you submit a reservation.</p>
                <?php else: ?>
                    <div class="ced-table-wrap">
                        <table class="ced-table">
                            <thead><tr>
                                <th>Event &amp; Venue Area</th><th>Date &amp; Time</th><th>Guests</th><th>Package &amp; Cost</th><th>Booking Status</th><th>Payment</th>
                            </tr></thead>
                            <tbody>
                            <?php while ($row = $bookings->fetch_assoc()): ?>
                                <tr>
                                    <td><?= e($row["event_type"]) ?><small>Area: <?= e($row["venue_area"]) ?></small></td>
                                    <td><?= e($row["event_date"]) ?><small><?= e(date("g:i A", strtotime($row["event_time"]))) ?></small></td>
                                    <td><?= e($row["guest_count"]) ?></td>
                                    <td><?= e($row["package_name"] ?: "—") ?></td>
                                    <td><span class="ced-status"><?= e($row["booking_status"] ?: "Pending") ?></span></td>
                                    <td>
                                        <?php
                                        $payStatus = $row["payment_status"];
                                        if ($row["booking_status"] === "Completed" || $row["booking_status"] === "Approved") {
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
        </div>
    </form>
</main>
<footer class="ced-footer">© <?= date("Y") ?> CED Event &amp; Catering Services — In-person payment policy applies.</footer>
</body>
</html>
<?php $stmt->close(); ?>
