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
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600&family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --rose: #c68da0;
            --rose-dark: #b9788e;
            --rose-soft: #f8e9ed;
            --pink-pale: #fff8f6;
            --cream: #fff8f5;
            --gold: #c6a46a;
            --ink: #554348;
            --muted: #927e84;
            --line: #f0dfe4;
            --shadow: 0 14px 36px rgba(130, 83, 99, .10);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            background:
                radial-gradient(circle at 8% 12%, rgba(244, 198, 211, .52), transparent 34%),
                radial-gradient(circle at 92% 88%, rgba(234, 217, 184, .34), transparent 36%),
                linear-gradient(135deg, #fff8f6 0%, #fae8ee 50%, #fffdfb 100%);
            background-attachment: fixed;
        }

        body::before,
        body::after {
            content: '✿';
            position: fixed;
            z-index: -1;
            color: rgba(185, 120, 142, .15);
            font-size: clamp(150px, 24vw, 310px);
            line-height: 1;
            pointer-events: none;
        }

        body::before {
            top: 70px;
            left: -70px;
            transform: rotate(-22deg);
        }

        body::after {
            right: -65px;
            bottom: -65px;
            transform: rotate(25deg);
        }

        .site-header {
            width: min(1160px, calc(100% - 40px));
            margin: 20px auto 0;
            padding: 14px 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            border: 1px solid var(--line);
            border-radius: 20px;
            background: rgba(255, 251, 249, .92);
            box-shadow: 0 6px 24px rgba(130, 83, 99, .06);
            backdrop-filter: blur(10px);
        }

        .brand {
            color: var(--rose-dark);
            text-decoration: none;
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: 29px;
            font-weight: 700;
            letter-spacing: .2px;
        }

        .brand span {
            display: block;
            margin-top: -4px;
            color: var(--gold);
            font-family: 'DM Sans', sans-serif;
            font-style: normal;
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 2.2px;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .welcome-user {
            color: #6e4f56;
            font-size: 13px;
            font-weight: 700;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 30px;
            padding: 12px 24px;
            font: 700 12.5px 'DM Sans', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: .25s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn.outline {
            color: var(--rose-dark);
            border: 1px solid #e6c5cf;
            background: #fffdfb;
        }

        .btn.outline:hover {
            background: var(--rose-soft);
            color: #6e4f56;
        }

        .btn.small {
            padding: 9px 18px;
            font-size: 12px;
        }

        .btn.primary {
            min-height: 48px;
            padding: 13px 28px;
            color: #fff;
            background: linear-gradient(135deg, var(--rose), var(--rose-dark));
            box-shadow: 0 7px 20px rgba(185, 120, 142, .26);
        }

        .btn.primary:hover {
            background: linear-gradient(135deg, #6e4f56, var(--rose-dark));
        }

        .page-wrap {
            width: min(980px, calc(100% - 40px));
            margin: 0 auto;
            padding: 46px 0 64px;
        }

        .page-heading {
            max-width: 720px;
            margin: 0 auto 28px;
            text-align: center;
        }

        .eyebrow {
            margin: 0 0 8px;
            color: var(--gold);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .page-heading h1 {
            margin: 0;
            color: #6e4f56;
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: clamp(42px, 5.5vw, 60px);
            line-height: 1.02;
            font-weight: 600;
        }

        .page-heading h1 span {
            color: var(--rose-dark);
        }

        .page-heading > p:last-child {
            max-width: 620px;
            margin: 12px auto 0;
            color: var(--muted);
            font-size: 13.5px;
            line-height: 1.75;
        }

        .page-heading::after {
            content: '✧  ─────────  ♡  ─────────  ✧';
            display: block;
            margin-top: 18px;
            color: var(--gold);
            font-size: 12px;
            letter-spacing: 2px;
        }

        .alert {
            margin: 0 auto 22px;
            padding: 14px 18px;
            border: 1px solid transparent;
            border-radius: 13px;
            font-size: 13px;
            line-height: 1.6;
        }

        .alert.success {
            color: #4d7350;
            border-color: #cde2cf;
            background: #edf6ee;
        }

        .alert.error {
            color: #a34f4f;
            border-color: #f3d2cf;
            background: #fff0ef;
        }

        .content-card {
            position: relative;
            margin: 0 0 26px;
            padding: clamp(22px, 4vw, 34px);
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 22px;
            background: rgba(255, 253, 251, .95);
            box-shadow: var(--shadow);
        }

        .content-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 28px;
            right: 28px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold-light), var(--rose-soft), var(--gold-light), transparent);
        }

        .content-card h2 {
            margin: 0 0 22px;
            color: #6e4f56;
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            font-weight: 700;
        }

        .content-card h2::before {
            content: '✧ ';
            color: var(--gold);
            font-family: serif;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px 22px;
        }

        .booking-form label {
            display: block;
            margin: 0 0 7px;
            color: #6e4f56;
            font-size: 12.5px;
            font-weight: 600;
        }

        .booking-form input,
        .booking-form select {
            width: 100%;
            min-height: 46px;
            margin-bottom: 0;
            padding: 11px 14px;
            outline: none;
            border: 1px solid #ead6dc;
            border-radius: 12px;
            color: var(--ink);
            background: #fffdfb;
            font: 400 13.5px 'DM Sans', sans-serif;
            transition: .2s ease;
        }

        .booking-form input:focus,
        .booking-form select:focus {
            border-color: var(--rose);
            box-shadow: 0 0 0 3px rgba(198, 141, 160, .16);
            background: #fff;
        }

        .booking-form input::placeholder {
            color: #b49fa5;
        }

        .booking-form > button {
            margin-top: 24px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--line);
            border-radius: 15px;
        }

        table {
            width: 100%;
            min-width: 740px;
            border-collapse: collapse;
            background: #fffdfb;
        }

        thead {
            background: var(--rose-soft);
        }

        th {
            padding: 14px 15px;
            color: #6e4f56;
            text-align: left;
            white-space: nowrap;
            font-size: 12px;
            font-weight: 700;
        }

        td {
            padding: 14px 15px;
            border-top: 1px solid #f2e4e8;
            color: var(--ink);
            font-size: 12.5px;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #fff8fa;
        }

        td small {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: 11px;
        }

        .status {
            display: inline-block;
            padding: 5px 12px;
            border: 1px solid #ead8b8;
            border-radius: 20px;
            color: #8f6d34;
            background: #fff7e8;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .muted {
            color: var(--muted);
            font-size: 12.5px;
        }

        .site-footer {
            padding: 22px 20px 28px;
            color: var(--muted);
            text-align: center;
            font-size: 12px;
        }

        @media (max-width: 640px) {
            .site-header {
                width: calc(100% - 24px);
                margin-top: 12px;
                padding: 13px 16px;
            }

            .brand {
                font-size: 23px;
            }

            .brand span {
                font-size: 7.5px;
                letter-spacing: 1.4px;
            }

            .nav-links {
                gap: 10px;
            }

            .welcome-user {
                max-width: 110px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: 11.5px;
            }

            .btn.small {
                padding: 8px 14px;
                font-size: 11px;
            }

            .page-wrap {
                width: calc(100% - 24px);
                padding: 36px 0 46px;
            }

            .page-heading > p:last-child {
                font-size: 12.5px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .content-card {
                border-radius: 18px;
            }

            .content-card h2 {
                font-size: 24px;
            }

            .booking-form > button {
                width: 100%;
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
