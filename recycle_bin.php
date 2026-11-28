<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "dbconfig.php";

// Handle pet restoration.
if (isset($_GET["restore"]) && ctype_digit($_GET["restore"])) {
    $recycle_id = (int) $_GET["restore"];

    try {
        $pdo->beginTransaction();

        // Retrieve the deleted pet from the recycle bin.
        $stmt = $pdo->prepare(
            "SELECT * FROM pets_recycle_bin
             WHERE recycle_id = :recycle_id"
        );
        $stmt->execute(["recycle_id" => $recycle_id]);
        $pet = $stmt->fetch();

        if ($pet) {
            // Restore the original pet record.
            $stmt = $pdo->prepare(
                "INSERT INTO pets
                (pet_id, owner_id, pet_name, species, breed, sex,
                 birth_date, date_added, added_by, last_updated_by,
                 last_updated, pet_image)
                VALUES
                (:pet_id, :owner_id, :pet_name, :species, :breed, :sex,
                 :birth_date, :date_added, :added_by, :last_updated_by,
                 :last_updated, :pet_image)"
            );

            $stmt->execute([
                "pet_id" => $pet["pet_id"],
                "owner_id" => $pet["owner_id"],
                "pet_name" => $pet["pet_name"],
                "species" => $pet["species"],
                "breed" => $pet["breed"],
                "sex" => $pet["sex"],
                "birth_date" => $pet["birth_date"],
                "date_added" => $pet["date_added"],
                "added_by" => $pet["added_by"],
                "last_updated_by" => $pet["last_updated_by"],
                "last_updated" => $pet["last_updated"],
                "pet_image" => $pet["pet_image"]
            ]);

            // Remove the restored record from the recycle bin.
            $stmt = $pdo->prepare(
                "DELETE FROM pets_recycle_bin
                 WHERE recycle_id = :recycle_id"
            );
            $stmt->execute(["recycle_id" => $recycle_id]);
        }

        $pdo->commit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        die("Pet restoration failed. Please try again.");
    }

    header("Location: recycle_bin.php?message=restored");
    exit;
}


// Retrieve deleted pets and the usernames of users who deleted them.
$stmt = $pdo->prepare(
    "SELECT
        r.*,
        u.username AS deleted_by_name
     FROM pets_recycle_bin r
     LEFT JOIN users u ON r.deleted_by = u.user_id
     ORDER BY r.deleted_at DESC"
);

$stmt->execute();
$deleted_pets = $stmt->fetchAll();

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Bin | Vet Clinic</title>

    <style>
    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #f3f6f4;
        color: #26352d;
    }

    header {
        background: #173f35;
        color: white;
        padding: 24px 5%;
    }

    header h1 { margin: 0 0 8px; }
    header p { margin: 0; color: #d5e5dc; }

    nav { margin-top: 18px; }

    nav a {
        color: white;
        margin-right: 18px;
        text-decoration: none;
    }

    main {
        max-width: 1250px;
        width: 92%;
        margin: 28px auto;
    }

    .panel {
        background: white;
        padding: 24px;
        border-radius: 10px;
        border: 1px solid #e1e9e3;
        margin-bottom: 24px;
    }

    .table-wrap { overflow-x: auto; }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th, td {
        text-align: left;
        padding: 11px;
        border-bottom: 1px solid #e1e9e3;
        vertical-align: top;
    }

    th { background: #edf3ef; }

    .restore {
        color: #2563eb;
        text-decoration: none;
        font-weight: bold;
    }
    </style>
</head>

<body>

<header>
    <h1>Vet Clinic Management System</h1>
    <p>Manage deleted pet records and restore them when needed</p>

    <nav>
        <a href="index.php">Home</a>
        <a href="owners.php">Owners</a>
        <a href="pets.php">Pets</a>
        <a href="recycle_bin.php">Recycle Bin</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main>
    <section class="panel">
        <h2>Recycle Bin</h2>
        <p>Review deleted pet records, check who deleted them, and restore records when necessary.</p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Pet ID</th>
                        <th>Pet Name</th>
                        <th>Species</th>
                        <th>Breed</th>
                        <th>Deleted By</th>
                        <th>Date Deleted</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($deleted_pets as $pet): ?>
                        <tr>
                            <td><?= htmlspecialchars($pet["pet_id"]) ?></td>
                            <td><?= htmlspecialchars($pet["pet_name"]) ?></td>
                            <td><?= htmlspecialchars($pet["species"]) ?></td>
                            <td><?= htmlspecialchars($pet["breed"] ?? "") ?></td>
                            <td><?= htmlspecialchars($pet["deleted_by_name"] ?? "Unknown") ?></td>
                            <td><?= htmlspecialchars($pet["deleted_at"]) ?></td>
                            <td>
                                <a class="restore"
                                   href="recycle_bin.php?restore=<?= (int) $pet['recycle_id'] ?>"
                                   onclick="return confirm('Are you sure you want to restore this pet?');">
                                    Restore
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($deleted_pets)): ?>
                        <tr>
                            <td colspan="7">No deleted pet records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

</body>
</html>

