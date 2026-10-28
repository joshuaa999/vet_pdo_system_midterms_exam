
<?php
session_start();
require_once "dbconfig.php";

if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $pdo->prepare(
        "SELECT user_id, first_name, last_name, username, password
         FROM users
         WHERE username = :username"
    );

    $stmt->execute([":username" => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user["password"])) {
        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["user_id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["full_name"] = $user["first_name"] . " " . $user["last_name"];

        header("Location: index.php");
        exit;
    }

    $message = "Invalid username or password.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Vet Clinic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 30px 15px;
        }

        .container {
            max-width: 400px;
            margin: 50px auto;
            padding: 25px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px #00000015;
        }

        h2 {
            text-align: center;
            color: #234b70;
        }

        label {
            display: block;
            margin-top: 15px;
        }

        input {
            box-sizing: border-box;
            width: 100%;
            padding: 10px;
            margin-top: 5px;
        }

        button {
            width: 100%;
            padding: 11px;
            margin-top: 20px;
            background: #234b70;
            color: white;
            border: none;
            cursor: pointer;
        }

        .message {
            color: #b42318;
        }

        .success {
            color: #18794e;
        }

        a {
            color: #234b70;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Vet Clinic Login</h2>

        <?php if (isset($_GET["registered"])): ?>
            <p class="success">Registration successful! Please log in.</p>
        <?php endif; ?>

        <?php if ($message !== ""): ?>
            <p class="message">
                <?= htmlspecialchars($message) ?>
            </p>
        <?php endif; ?>

        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit">Login</button>
        </form>

        <p>Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</body>
</html>
