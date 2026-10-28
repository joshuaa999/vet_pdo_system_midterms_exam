
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "dbconfig.php";

 // Handle owner deletion.
if (isset($_GET["delete"]) && ctype_digit($_GET["delete"])) {
    $owner_id = (int) $_GET["delete"];

    try {
        $pdo->beginTransaction();

        // Get the owner's details before deleting.
        $stmt = $pdo->prepare(
            "SELECT owner_name FROM owners WHERE owner_id = :owner_id"
        );
        $stmt->execute(["owner_id" => $owner_id]);
        $owner = $stmt->fetch();

        if ($owner) {
            // Record the deletion in the audit log.
            $stmt = $pdo->prepare(
                "INSERT INTO deletion_logs
                 (entity_type, record_id, record_description, deleted_by)
                 VALUES
                 (:entity_type, :record_id, :record_description, :deleted_by)"
            );

            $stmt->execute([
                "entity_type" => "Owner",
                "record_id" => $owner_id,
                "record_description" => $owner["owner_name"],
                "deleted_by" => $_SESSION["user_id"]
            ]);

            // Delete the owner record.
            $stmt = $pdo->prepare(
                "DELETE FROM owners WHERE owner_id = :owner_id"
            );
            $stmt->execute(["owner_id" => $owner_id]);
        }

        $pdo->commit();

        header("Location: owners.php?message=deleted");
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        header("Location: owners.php?message=has_pets");
        exit;
    }
}


// Load an owner for editing.
$edit_owner = null;

if (isset($_GET["edit"]) && ctype_digit($_GET["edit"])) {
    $stmt = $pdo->prepare(
        "SELECT * FROM owners WHERE owner_id = :owner_id"
    );
    $stmt->execute(["owner_id" => (int) $_GET["edit"]]);
    $edit_owner = $stmt->fetch();
}

// Handle registration and updates.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $owner_name = trim($_POST["owner_name"] ?? "");
    $phone_number = trim($_POST["phone_number"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $owner_id = $_POST["owner_id"] ?? "";

    if ($owner_name !== "" && $phone_number !== "") {
        if ($owner_id !== "" && ctype_digit($owner_id)) {
            $stmt = $pdo->prepare(
                "UPDATE owners
                 SET owner_name = :owner_name,
                     phone_number = :phone_number,
                     email = :email,
                     address = :address,
                     last_updated_by = :last_updated_by,
                     last_updated = CURRENT_TIMESTAMP
                 WHERE owner_id = :owner_id"
            );

            $stmt->execute([
                "owner_name" => $owner_name,
                "phone_number" => $phone_number,
                "email" => $email !== "" ? $email : null,
                "address" => $address !== "" ? $address : null,
                "owner_id" => (int) $owner_id,
                "last_updated_by" => $_SESSION["user_id"]
            ]);

            header("Location: owners.php?message=updated");
            exit;
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO owners
                 (owner_name, phone_number, email, address, added_by)
                 VALUES
                 (:owner_name, :phone_number, :email, :address, :added_by)"
            );

            $stmt->execute([
                "owner_name" => $owner_name,
                "phone_number" => $phone_number,
                "email" => $email !== "" ? $email : null,
                "address" => $address !== "" ? $address : null,
                "added_by" => $_SESSION["user_id"]
            ]);

            header("Location: owners.php?message=added");
            exit;
        }
    }

    header("Location: owners.php?message=required");
    exit;
}

// Retrieve all owners.

$stmt = $pdo->query(
    "SELECT
        owners.*,
        added_user.username AS added_by_name,
        updated_user.username AS updated_by_name
     FROM owners
     LEFT JOIN users AS added_user
        ON owners.added_by = added_user.user_id
     LEFT JOIN users AS updated_user
        ON owners.last_updated_by = updated_user.user_id
     ORDER BY owners.owner_id DESC"
);
$owners = $stmt->fetchAll();


function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$messages = [
    "added" => "Owner registered successfully.",
    "updated" => "Owner information updated successfully.",
    "deleted" => "Owner deleted successfully.",
    "has_pets" => "This owner cannot be deleted while they have registered pets.",
    "required" => "Owner name and phone number are required."
];

$message = $messages[$_GET["message"] ?? ""] ?? "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Owner Management | Vet Clinic</title>
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
main { max-width: 1200px; width: 92%; margin: 28px auto; }
.panel {
    background: white;
    padding: 24px;
    border-radius: 10px;
    border: 1px solid #e1e9e3;
    margin-bottom: 24px;
}
.message {
    background: #e1f5e8;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 18px;
}
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.form-group { display: flex; flex-direction: column; gap: 7px; }
.full { grid-column: 1 / -1; }
input, textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5ce;
    border-radius: 6px;
    font: inherit;
}
textarea { min-height: 75px; resize: vertical; }
button, .button {
    display: inline-block;
    padding: 10px 15px;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    cursor: pointer;
    font: inherit;
}
button { background: #2563eb; color: white; }
.cancel { background: #e5e7eb; color: #26352d; }
.delete { color: #dc2626; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th, td {
    text-align: left;
    padding: 11px;
    border-bottom: 1px solid #e1e9e3;
    vertical-align: top;
}
th { background: #edf3ef; }
.actions { white-space: nowrap; }
@media (max-width: 650px) {
    .form-grid { grid-template-columns: 1fr; }
    .full { grid-column: auto; }
}
</style>
</head>
<body>
<header>
    <h1>Vet Clinic Management System</h1>
    <p>Manage registered pet owners</p>
    <nav>
        <a href="index.php">Home</a>
        <a href="owners.php">Owners</a>
        <a href="pets.php">Pets</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main>
    <?php if ($message !== ""): ?>
        <div class="message"><?= h($message) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2><?= $edit_owner ? "Update Owner" : "Register New Owner" ?></h2>

        <form method="POST" action="owners.php">
            <input type="hidden" name="owner_id"
                value="<?= h($edit_owner["owner_id"] ?? "") ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label for="owner_name">Owner Name *</label>
                    <input id="owner_name" name="owner_name" type="text"
                        value="<?= h($edit_owner["owner_name"] ?? "") ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="phone_number">Phone Number *</label>
                    <input id="phone_number" name="phone_number" type="tel"
                        value="<?= h($edit_owner["phone_number"] ?? "") ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input id="email" name="email" type="email"
                        value="<?= h($edit_owner["email"] ?? "") ?>">
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <input id="address" name="address" type="text"
                        value="<?= h($edit_owner["address"] ?? "") ?>">
                </div>
            </div>

            <p>
                <button type="submit">
                    <?= $edit_owner ? "Save Changes" : "Register Owner" ?>
                </button>

                <?php if ($edit_owner): ?>
                    <a class="button cancel" href="owners.php">Cancel</a>
                <?php endif; ?>
            </p>
        </form>
    </section>

    <section class="panel">
        <h2>Registered Owners</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Owner Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>Date Added</th>
                        <th>Added By</th>
                        <th>Last Updated</th>
                         <th>Updated By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($owners) > 0): ?>
                    <?php foreach ($owners as $owner): ?>
                    <tr>
                        <td><?= h($owner["owner_id"]) ?></td>
                        <td><?= h($owner["owner_name"]) ?></td>
                        <td><?= h($owner["phone_number"]) ?></td>
                        <td><?= h($owner["email"]) ?></td>
                        <td><?= h($owner["address"]) ?></td>
                        <td><?= h($owner["date_added"]) ?></td>
                        <td><?= h($owner["added_by_name"] ?? "Unknown") ?></td>
                        <td><?= h($owner["last_updated"] ?? "Never") ?></td>
                        <td><?= h($owner["updated_by_name"] ?? "Never") ?></td>
                        <td class="actions">
                            <a href="owners.php?edit=<?= h($owner["owner_id"]) ?>">Edit</a>
                            |
                            <a class="delete"
                               href="owners.php?delete=<?= h($owner["owner_id"]) ?>"
                               onclick="return confirm('Delete this owner? Owners with registered pets cannot be deleted.');">
                               Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10">No owners registered yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
