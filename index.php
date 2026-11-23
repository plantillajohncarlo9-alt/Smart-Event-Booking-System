<?php
session_start();
require_once "db.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

if (isset($_GET["logout"])) {
    $_SESSION = [];
    session_destroy();
    header("Location: index.php");
    exit;
}

if (isset($_SESSION["customer_id"])) {
    header("Location: booking.php");
    exit;
}

$error = "";
$success = "";
$activeForm = "login";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    // CUSTOMER REGISTRATION
    if ($action === "register") {

        $activeForm = "register";

        $fullName = trim($_POST["full_name"] ?? "");
        $email = strtolower(trim($_POST["email"] ?? ""));
        $phone = trim($_POST["phone"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($fullName === "" || $email === "" || $password === "") {
            $error = "Please complete all required fields.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email.";

        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters.";

        } else {

            $check = $conn->prepare(
                "SELECT customer_id FROM customers WHERE email = ?"
            );

            $check->bind_param("s", $email);
            $check->execute();

            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            if ($exists) {
                $error = "Email is already registered.";

            } else {

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare(
                    "INSERT INTO customers
                    (full_name, email, password, phone)
                    VALUES (?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "ssss",
                    $fullName,
                    $email,
                    $hashedPassword,
                    $phone
                );

                if ($stmt->execute()) {
                    $success = "Registration successful! You can now log in.";
                    $activeForm = "login";
                } else {
                    $error = "Registration failed. Please try again.";
                }

                $stmt->close();
            }
        }
    }

    // CUSTOMER LOGIN
    elseif ($action === "login") {

        $email = strtolower(trim($_POST["email"] ?? ""));
        $password = $_POST["password"] ?? "";

        $stmt = $conn->prepare(
            "SELECT customer_id, full_name, password
             FROM customers WHERE email = ?"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $customer = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($customer &&
            password_verify($password, $customer["password"])) {

            session_regenerate_id(true);

            $_SESSION["customer_id"] = (int)$customer["customer_id"];
            $_SESSION["customer_name"] = $customer["full_name"];

            header("Location: booking.php");
            exit;

        } else {
            $error = "Incorrect email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Golden Moments | Welcome</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600&family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=2">
    <script src="script.js" defer></script>

    <style>
        body.auth-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background:
                radial-gradient(circle at 8% 14%, rgba(244, 198, 211, .55), transparent 34%),
                radial-gradient(circle at 92% 86%, rgba(234, 217, 184, .36), transparent 36%),
                linear-gradient(135deg, #fff8f6 0%, #fae8ee 52%, #fffdfb 100%);
        }

        body.auth-page::before,
        body.auth-page::after {
            content: '✿';
            position: fixed;
            z-index: 0;
            color: rgba(185, 120, 142, .14);
            font-size: clamp(160px, 24vw, 310px);
            line-height: 1;
            pointer-events: none;
        }

        body.auth-page::before {
            top: 65px;
            left: -70px;
            transform: rotate(-22deg);
        }

        body.auth-page::after {
            right: -65px;
            bottom: -65px;
            transform: rotate(25deg);
        }

        .auth-page .site-header,
        .auth-page .auth-layout,
        .auth-page .site-footer {
            position: relative;
            z-index: 1;
        }

        .auth-page .site-header {
            background: rgba(255, 251, 249, .92);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
        }

        .auth-page .brand {
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: 30px;
            font-weight: 700;
            color: var(--pink-dark);
        }

        .auth-page .brand span {
            color: var(--gold);
        }

        .auth-page .header-note {
            color: var(--muted);
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: 16px;
        }

        .auth-page .auth-layout {
            flex: 1;
            width: min(1140px, 90%);
            margin: auto;
            padding: 52px 0;
            display: grid;
            grid-template-columns: 1.15fr .85fr;
            align-items: center;
            gap: 56px;
        }

        .auth-page .intro-panel {
            max-width: 560px;
        }

        .auth-page .intro-panel .eyebrow {
            color: var(--gold);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 3.5px;
            margin-bottom: 14px;
        }

        .auth-page .intro-panel h1 {
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-weight: 600;
            font-size: clamp(44px, 5.5vw, 68px);
            line-height: 1.06;
            color: var(--brown);
            margin-bottom: 18px;
        }

        .auth-page .intro-panel h1 span {
            color: var(--pink-dark);
        }

        .auth-page .intro-panel > p:last-child {
            max-width: 480px;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.8;
        }

        .auth-page .auth-card {
            width: 100%;
            max-width: 430px;
            justify-self: end;
            padding: 34px;
            background: rgba(255, 253, 251, .96);
            border: 1px solid var(--border);
            border-radius: 22px;
            box-shadow: 0 18px 44px rgba(130, 83, 99, .12);
        }

        .auth-page .tabs {
            display: flex;
            gap: 10px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
        }

        .auth-page .tab-button {
            flex: 1;
            padding: 12px 8px;
            background: transparent;
            color: var(--muted);
            border-radius: 0;
            border-bottom: 3px solid transparent;
            box-shadow: none;
        }

        .auth-page .tab-button:hover {
            background: #fff5f7;
            color: var(--pink-dark);
            transform: none;
            box-shadow: none;
        }

        .auth-page .tab-button.active {
            color: var(--pink-dark);
            border-bottom: 3px solid var(--pink-mid);
            background: transparent;
        }

        .auth-page .auth-form {
            display: none;
        }

        .auth-page .auth-form.show {
            display: block;
        }

        .auth-page .auth-form h2 {
            font-size: 26px;
            margin-bottom: 6px;
        }

        .auth-page .auth-form .muted {
            font-size: 13px;
            margin-bottom: 20px;
        }

        .auth-page .auth-form label {
            margin-bottom: 6px;
            font-size: 12.5px;
        }

        .auth-page .auth-form input {
            margin-bottom: 15px;
            padding: 12px 14px;
            background: #fffdfb;
            border: 1px solid #ead6dc;
            border-radius: 12px;
        }

        .auth-page .auth-form input:focus {
            border-color: var(--pink-mid);
            box-shadow: 0 0 0 3px rgba(198, 141, 160, .15);
        }

        .auth-page .auth-form .btn.primary.full {
            width: 100%;
            margin-top: 4px;
            padding: 13px;
            background: linear-gradient(135deg, var(--pink-mid), var(--pink-dark));
            color: #fff;
            border-radius: 30px;
        }

        .auth-page .auth-form .btn.primary.full:hover {
            background: linear-gradient(135deg, var(--brown), var(--pink-dark));
        }

        .auth-page .form-switch {
            margin-top: 18px;
            text-align: center;
            font-size: 12.5px;
            color: var(--muted);
        }

        .auth-page .text-button {
            display: inline;
            padding: 0;
            margin-left: 3px;
            background: transparent;
            color: var(--pink-dark);
            border-radius: 0;
            font-size: 12.5px;
            box-shadow: none;
        }

        .auth-page .text-button:hover {
            background: transparent;
            color: var(--gold);
            transform: none;
            box-shadow: none;
        }

        .auth-page .site-footer {
            margin-top: 0;
            padding: 18px 20px 24px;
            background: transparent;
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 820px) {
            .auth-page .auth-layout {
                grid-template-columns: 1fr;
                gap: 32px;
                padding: 36px 0;
            }

            .auth-page .intro-panel {
                text-align: center;
                margin: auto;
            }

            .auth-page .intro-panel > p:last-child {
                margin: auto;
            }

            .auth-page .auth-card {
                justify-self: center;
            }
        }

        @media (max-width: 520px) {
            .auth-page .auth-layout {
                width: 92%;
                padding: 28px 0;
            }

            .auth-page .intro-panel h1 {
                font-size: 40px;
            }

            .auth-page .auth-card {
                padding: 24px 20px;
            }
        }
    </style>
</head>

<body class="auth-page">

<header class="site-header">
    <a class="brand" href="index.php">
        Golden <span>Moments</span>
    </a>

    <span class="header-note">
        Make every celebration memorable
    </span>
</header>

<main class="auth-layout">

    <section class="intro-panel">

        <p class="eyebrow">EVENTS MADE SPECIAL</p>

        <h1>
            Your moments.<br>
            <span>Our masterpiece.</span>
        </h1>

        <p>
            Plan your celebration with Golden Moments.
            Register or log in to start your event booking.
        </p>

    </section>

    <section class="auth-card">

        <div class="tabs">

            <button type="button"
                class="tab-button <?= $activeForm === "login" ? "active" : "" ?>"
                data-target="login-panel">
                Log in
            </button>

            <button type="button"
                class="tab-button <?= $activeForm === "register" ? "active" : "" ?>"
                data-target="register-panel">
                Create account
            </button>

        </div>

        <?php if ($error !== ""): ?>
            <div class="alert error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="alert success">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <!-- LOGIN FORM -->

        <form id="login-panel"
            class="auth-form <?= $activeForm === "login" ? "show" : "" ?>"
            method="POST"
            action="index.php">

            <h2>Welcome back</h2>

            <p class="muted">
                Log in to continue to your booking.
            </p>

            <input type="hidden" name="action" value="login">

            <label>Email address</label>

            <input type="email"
                name="email"
                placeholder="you@example.com"
                required>

            <label>Password</label>

            <input type="password"
                name="password"
                placeholder="Enter your password"
                required>

            <button class="btn primary full" type="submit">
                Log in
            </button>

            <p class="form-switch">
                New here?

                <button type="button"
                    class="text-button"
                    data-show="register-panel">
                    Create an account
                </button>
            </p>

        </form>

        <!-- REGISTRATION FORM -->

        <form id="register-panel"
            class="auth-form <?= $activeForm === "register" ? "show" : "" ?>"
            method="POST"
            action="index.php">

            <h2>Create your account</h2>

            <p class="muted">
                Register first before making a booking.
            </p>

            <input type="hidden" name="action" value="register">

            <label>Full name</label>

            <input type="text"
                name="full_name"
                maxlength="100"
                placeholder="Your full name"
                required>

            <label>Email address</label>

            <input type="email"
                name="email"
                maxlength="100"
                placeholder="you@example.com"
                required>

            <label>Phone number (optional)</label>

            <input type="tel"
                name="phone"
                maxlength="20"
                placeholder="09XXXXXXXXX">

            <label>Password</label>

            <input type="password"
                name="password"
                minlength="8"
                placeholder="At least 8 characters"
                required>

            <button class="btn primary full" type="submit">
                Create account
            </button>

            <p class="form-switch">
                Already registered?

                <button type="button"
                    class="text-button"
                    data-show="login-panel">
                    Log in
                </button>
            </p>

        </form>

    </section>

</main>

<footer class="site-footer">
    © <?= date("Y") ?> Golden Moments Event Booking
</footer>

</body>
</html>
