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
        $_SESSION["flash_message"] = "Mangyaring punan ang lahat ng kinakailangang impormasyon.";
        $_SESSION["flash_type"] = "error";
    } elseif ($eventDate < date("Y-m-d")) {
        $_SESSION["flash_message"] = "Mangyaring pumili ng petsa ngayon o sa hinaharap.";
        $_SESSION["flash_type"] = "error";
    } else {
        // Check if the selected venue, date, and time are already booked.
        $checkStmt = $conn->prepare(
            "SELECT booking_id FROM bookings
             WHERE venue_area = ? AND event_date = ? AND event_time = ? AND booking_status != 'Cancelled' AND booking_status != 'Rejected'"
        );
        $checkStmt->bind_param("sss", $venueArea, $eventDate, $eventTime);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $_SESSION["flash_message"] = "Paumanhin, ang venue area na ito ay na-book na sa napiling petsa at oras. Mangyaring pumili ng ibang oras o araw.";
            $_SESSION["flash_type"] = "error";
            $checkStmt->close();
        } else {
            $checkStmt->close();

            $stmt = $conn->prepare(
                "INSERT INTO bookings
                (customer_id, event_type, venue_area, event_date, event_time, guest_count, package_name, booking_status, payment_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', 'Unpaid')"
            );
            $stmt->bind_param("issssis", $customerId, $eventType, $venueArea, $eventDate, $eventTime, $guestCount, $packageName);

            if ($stmt->execute()) {
                $_SESSION["flash_message"] = "Matagumpay na naisumite ang reservation! Magbayad ng down payment sa tanggapan ng CED Event & Catering Services.";
                $_SESSION["flash_type"] = "success";
            } else {
                $_SESSION["flash_message"] = "Nabigong i-save ang booking. Subukan muli.";
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --rose: #c97882;
            --rose-dark: #a95765;
            --rose-soft: #f5dadd;
            --pink-pale: #fff5f3;
            --cream: #fffaf5;
            --gold: #c6a36a;
            --ink: #583b3b;
            --muted: #927777;
            --line: #f0d8d5;
            --shadow: 0 18px 48px rgba(142, 83, 88, .12);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            font-family: 'Manrope', sans-serif;
            background:
                radial-gradient(ellipse at 8% 12%, rgba(255,255,255,.95) 0 4%, transparent 28%),
                radial-gradient(ellipse at 92% 28%, rgba(255,255,255,.75) 0 3%, transparent 25%),
                radial-gradient(ellipse at 90% 88%, rgba(237,184,185,.28), transparent 28%),
                linear-gradient(135deg, #f8dfe0 0%, #fff7f2 46%, #f5d9dc 100%);
            background-attachment: fixed;
        }
        body::before, body::after {
            content: '✿';
            position: fixed;
            z-index: -1;
            color: rgba(190, 111, 122, .16);
            font-size: clamp(150px, 24vw, 320px);
            line-height: 1;
            pointer-events: none;
        }
        body::before { top: 70px; left: -75px; transform: rotate(-22deg); }
        body::after { right: -65px; bottom: -65px; transform: rotate(25deg); }

        .site-header {
            width: min(1180px, calc(100% - 40px));
            margin: 22px auto 0;
            padding: 15px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            border: 1px solid rgba(255,255,255,.8);
            border-radius: 20px;
            background: rgba(255, 250, 247, .82);
            box-shadow: 0 8px 28px rgba(142,83,88,.09);
            backdrop-filter: blur(14px);
        }
        .brand {
            color: var(--rose-dark);
            text-decoration: none;
            font-family: 'Cormorant Garamond', serif;
            font-size: 27px;
            font-weight: 700;
            letter-spacing: -.5px;
        }
        .brand span {
            display: block;
            margin-top: -4px;
            color: #8d6868;
            font-family: 'Manrope', sans-serif;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .nav-links { display: flex; align-items: center; gap: 18px; }
        .welcome-user { color: #765858; font-size: 13px; font-weight: 700; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 999px;
            padding: 12px 23px;
            font: 700 12px 'Manrope', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: .2s ease;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn.outline {
            color: var(--rose-dark);
            border: 1px solid #dcaeb2;
            background: #fff9f6;
        }
        .btn.small { padding: 10px 19px; }
        .btn.primary {
            min-height: 48px;
            padding: 13px 28px;
            color: white;
            background: linear-gradient(135deg, #d88e96, #b96572);
            box-shadow: 0 8px 18px rgba(185,101,114,.25);
        }

        .page-wrap { width: min(1080px, calc(100% - 40px)); margin: 0 auto; padding: 58px 0 70px; }
        .page-heading { max-width: 760px; margin: 0 auto 30px; text-align: center; }
        .eyebrow {
            margin: 0 0 9px;
            color: #b47b78;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 3px;
        }
        .page-heading h1 {
            margin: 0;
            color: #704747;
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(42px, 6vw, 64px);
            line-height: .98;
            font-weight: 600;
        }
        .page-heading h1 span { color: #c47781; font-style: italic; }
        .page-heading > p:last-child {
            max-width: 660px;
            margin: 15px auto 0;
            color: #876d6c;
            font-size: 13px;
            line-height: 1.8;
        }
        .page-heading::after {
            content: '✧  ─────────  ♡  ─────────  ✧';
            display: block;
            margin-top: 20px;
            color: var(--gold);
            font-size: 12px;
            letter-spacing: 2px;
        }

        .alert {
            margin: 0 auto 22px;
            padding: 15px 19px;
            border: 1px solid transparent;
            border-radius: 14px;
            font-size: 13px;
            line-height: 1.6;
        }
        .alert.success { color: #55775d; border-color: #cfe3d0; background: #f1faf1; }
        .alert.error { color: #9d4f59; border-color: #edc5c8; background: #fff1f1; }

        .content-card {
            position: relative;
            margin: 0 0 27px;
            padding: clamp(22px, 4vw, 36px);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.95);
            border-radius: 23px;
            background: rgba(255, 251, 248, .88);
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
        }
        .content-card::before {
            content: '';
            position: absolute;
            top: 0; left: 30px; right: 30px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #d8b77f, #e9c8c5, #d8b77f, transparent);
        }
        .content-card h2 {
            margin: 0 0 23px;
            color: #754b4b;
            font-family: 'Cormorant Garamond', serif;
            font-size: 30px;
            font-weight: 700;
        }
        .content-card h2::before { content: '✧ '; color: var(--gold); font-family: serif; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px 22px; }
        .booking-form label {
            display: block;
            margin: 0 0 8px;
            color: #765656;
            font-size: 12px;
            font-weight: 800;
        }
        .booking-form input, .booking-form select {
            width: 100%;
            min-height: 48px;
            padding: 0 14px;
            outline: none;
            border: 1px solid #ecd2d0;
            border-radius: 11px;
            color: #674b4b;
            background: #fffaf8;
            font: 500 13px 'Manrope', sans-serif;
            transition: .2s ease;
        }
        .booking-form input:focus, .booking-form select:focus {
            border-color: #c77d86;
            box-shadow: 0 0 0 3px rgba(199,125,134,.13);
            background: #fff;
        }
        .booking-form input::placeholder { color: #b99b98; }
        .booking-form > button { margin-top: 25px; }

        .table-wrap { width: 100%; overflow-x: auto; border: 1px solid #f0ddda; border-radius: 14px; }
        table { width: 100%; min-width: 760px; border-collapse: collapse; background: rgba(255,255,255,.55); }
        thead { background: linear-gradient(90deg, #f8e4e3, #fff1ed); }
        th {
            padding: 15px 14px;
            color: #805757;
            text-align: left;
            white-space: nowrap;
            font-size: 11px;
            font-weight: 800;
        }
        td {
            padding: 15px 14px;
            border-top: 1px solid #f3e5e2;
            color: #684f4e;
            font-size: 12px;
            vertical-align: middle;
        }
        tbody tr:hover { background: #fff8f5; }
        td small { display: block; margin-top: 5px; color: #a18481; font-size: 10px; }
        .status {
            display: inline-block;
            padding: 6px 11px;
            border: 1px solid #ecd7b7;
            border-radius: 999px;
            color: #8a6a36;
            background: #fff7e8;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }
        .muted { color: #a28b88; font-size: 12px; }
        .site-footer {
            padding: 22px 20px 28px;
            color: #987d7b;
            text-align: center;
            font-size: 11px;
        }

        @media (max-width: 640px) {
            .site-header { width: calc(100% - 24px); margin-top: 12px; padding: 13px 15px; }
            .brand { font-size: 22px; }
            .brand span { font-size: 7px; letter-spacing: 1.3px; }
            .nav-links { gap: 9px; }
            .welcome-user { max-width: 105px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; }
            .btn.small { padding: 9px 13px; font-size: 10px; }
            .page-wrap { width: calc(100% - 24px); padding: 43px 0 48px; }
            .page-heading > p:last-child { font-size: 12px; }
            .form-grid { grid-template-columns: 1fr; gap: 16px; }
            .content-card { border-radius: 18px; }
            .content-card h2 { font-size: 27px; }
            .booking-form > button { width: 100%; }
        }
    </style>
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
        <p class="eyebrow">A BEAUTIFUL DAY BEGINS HERE</p>
        <h1>Book your <span>special event</span></h1>
        <p>Piliin ang iyong nais na venue area at package. Kinakailangan ang in-person down payment upang makumpirma ang iskedyul.</p>
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
            <p class="muted">Wala ka pang mga reservation.</p>
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
                                    <?= e($row["payment_status"]) ?>
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
