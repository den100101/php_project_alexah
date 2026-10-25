<?php

include "db.php";

$message = "";

if (isset($_POST['register-btn'])) {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {

        $message = "Please complete all fields.";

    } else {

        // Check if username already exists
        $check = "SELECT * FROM tbl_account WHERE USERNAME = ?";

        $stmt = $conn->prepare($check);

        $stmt->bind_param("s", $username);

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $message = "Username already exists.";

        } else {

            // HASH THE PASSWORD
            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Save account
            // Public registration always creates a STUDENT account.
            // Other usertypes must be created by the database administrator.
            $usertype = "student";

            $sql = "INSERT INTO tbl_account (USERNAME, PASSWORD, USERTYPE)
                    VALUES (?, ?, ?)";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "sss",
                $username,
                $hashed_password,
                $usertype
            );

            if ($stmt->execute()) {

                $message = "Registration successful!";

            } else {

                $message = "Registration failed.";

            }
        }
    }
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Register</title>

    <style>

        body {
            font-family: Arial;
            background: #f2f2f2;
        }

        .container {
            width: 350px;
            margin: 100px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 10px gray;
        }

        h2 {
            text-align: center;
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 10px;
            background: black;
            color: white;
            border: none;
            cursor: pointer;
        }

        .message {
            text-align: center;
            margin: 10px;
        }

        a {
            display: block;
            text-align: center;
            margin-top: 15px;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>Register Student Account</h2>

    <div class="message">
        <?php echo $message; ?>
    </div>

    <form method="POST">

        <input
            type="text"
            name="username"
            placeholder="Username"
            required
        >

        <input
            type="password"
            name="password"
            placeholder="Password"
            required
        >

        <button type="submit" name="register-btn">
            Register
        </button>

    </form>

    <a href="login.php">
        Already have an account? Login
    </a>

</div>

</body>

</html>