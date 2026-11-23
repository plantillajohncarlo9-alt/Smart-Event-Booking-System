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

    <link rel="stylesheet" href="/smart-event-booking/style.css?v=2">
    <script src="script.js" defer></script>
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
