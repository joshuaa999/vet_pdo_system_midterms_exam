
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vet Clinic Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 30px 15px;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px #00000015;
        }

        h1 {
            color: #234b70;
        }

        .links a {
            display: inline-block;
            margin: 8px 8px 8px 0;
            padding: 12px 16px;
            background: #234b70;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        .links a:hover {
            background: #173650;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Vet Clinic Management System</h1>

        <p>
            Welcome,
            <strong><?= htmlspecialchars($_SESSION["full_name"]) ?></strong>!
        </p>

        <p>Manage clinic owners and their pets using the links below.</p>

        <div class="links">
            <a href="owners.php">Manage Owners</a>
            <a href="pets.php">Manage Pets</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</body>
</html>
