
<?php
require_once "dbconfig.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST["first_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $address = trim($_POST["address"] ?? "");
    $age = !empty($_POST["age"]) ? (int) $_POST["age"] : null;

    if (
        $first_name === "" ||
        $last_name === "" ||
        $email === "" ||
        $username === "" ||
        $password === ""
    ) {
        $message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
    } else {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users
                    (first_name, last_name, email, username,
                     password, address, age)
                    VALUES
                    (:first_name, :last_name, :email, :username,
                     :password, :address, :age)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ":first_name" => $first_name,
                ":last_name" => $last_name,
                ":email" => $email,
                ":username" => $username,
                ":password" => $hashed_password,
                ":address" => $address !== "" ? $address : null,
                ":age" => $age
            ]);

            header("Location: login.php?registered=1");
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() === "23000") {
                $message = "Email or username is already registered.";
            } else {
                $message = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration - Vet Clinic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 30px 15px;
        }

        .container {
            max-width: 480px;
            margin: 30px auto;
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
            margin-top: 14px;
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
            margin-top: 12px;
        }

        a {
            color: #234b70;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Vet Clinic User Registration</h2>

        <?php if ($message !== ""): ?>
            <p class="message">
                <?= htmlspecialchars($message) ?>
            </p>
        <?php endif; ?>

        <form method="POST">
            <label>First Name *</label>
            <input type="text" name="first_name" required>

            <label>Last Name *</label>
            <input type="text" name="last_name" required>

            <label>Email *</label>
            <input type="email" name="email" required>

            <label>Username *</label>
            <input type="text" name="username" required>

            <label>Password *</label>
            <input type="password" name="password"
                   minlength="8" required>

            <label>Address</label>
            <input type="text" name="address">

            <label>Age</label>
            <input type="number" name="age" min="1" max="120">

            <button type="submit">Register</button>
        </form>

        <p>Already registered? <a href="login.php">Login here</a></p>
    </div>
</body>
</html>
