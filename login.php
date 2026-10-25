<?php
require_once __DIR__ . "/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION["USERNAME"], $_SESSION["USERTYPE"])) {
    header("Location: dashboard.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit-btn"])) {
    $username = trim((string)($_POST["usernamelog"] ?? ""));
    $password = (string)($_POST["passwordlog"] ?? "");

    if ($username === "" || $password === "") {
        $message = "Please enter your username and password.";
    } else {
        try {
            $stmt = $conn->prepare(
                "SELECT ID, USERNAME, PASSWORD, USERTYPE, IMAGE_PATH
                 FROM tbl_account
                 WHERE USERNAME = ?
                 LIMIT 1"
            );

            $stmt->bind_param("s", $username);
            $stmt->execute();

            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if ($row && password_verify($password, $row["PASSWORD"])) {
                session_regenerate_id(true);

                $_SESSION["USER_ID"] = (int)$row["ID"];
                $_SESSION["USERNAME"] = $row["USERNAME"];
                $_SESSION["USERTYPE"] = strtolower(trim($row["USERTYPE"]));
                $_SESSION["IMAGE_PATH"] = $row["IMAGE_PATH"] ?? "";

                header("Location: dashboard.php");
                exit();
            }

            $message = "Invalid username or password.";
        } catch (Throwable $e) {
            error_log("Login failed: " . $e->getMessage());
            $message = "Unable to log in right now. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | School Management System</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f2f2f2;
            min-height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 380px;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 24px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            font-size: 25px;
            margin: 0 0 8px;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin: 0 0 24px;
            font-size: 14px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: bold;
            margin: 14px 0 6px;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        input:focus {
            outline: 2px solid #777;
            outline-offset: 1px;
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            background: #111;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #333;
        }

        .message {
            color: #b00020;
            background: #fff0f0;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .register-link {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #222;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <main class="container">
        <h1>Welcome Back</h1>
        <p class="subtitle">School Management System</p>

        <?php if ($message !== ""): ?>
            <div class="message" role="alert">
                <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <label for="usernamelog">Username</label>
            <input
                type="text"
                id="usernamelog"
                name="usernamelog"
                maxlength="50"
                autocomplete="username"
                required>

            <label for="passwordlog">Password</label>
            <input
                type="password"
                id="passwordlog"
                name="passwordlog"
                autocomplete="current-password"
                required>

            <button type="submit" name="submit-btn">Login</button>
        </form>

        <a class="register-link" href="register.php">
            Create an account
        </a>
    </main>
</body>

</html>