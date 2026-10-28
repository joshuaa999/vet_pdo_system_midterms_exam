
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "dbconfig.php";


 // Handle pet deletion.
if (isset($_GET["delete"]) && ctype_digit($_GET["delete"])) {
    $pet_id = (int) $_GET["delete"];

    try {
        $pdo->beginTransaction();

        // Get the pet details before deleting it.
        $stmt = $pdo->prepare(
            "SELECT pet_name FROM pets WHERE pet_id = :pet_id"
        );
        $stmt->execute(["pet_id" => $pet_id]);
        $pet = $stmt->fetch();

        if ($pet) {
            // Record the deletion in the audit log.
            $stmt = $pdo->prepare(
                "INSERT INTO deletion_logs
                 (entity_type, record_id, record_description, deleted_by)
                 VALUES
                 (:entity_type, :record_id, :record_description, :deleted_by)"
            );

            $stmt->execute([
                "entity_type" => "Pet",
                "record_id" => $pet_id,
                "record_description" => $pet["pet_name"],
                "deleted_by" => $_SESSION["user_id"]
            ]);

            // Delete the pet record.
            $stmt = $pdo->prepare(
                "DELETE FROM pets WHERE pet_id = :pet_id"
            );
            $stmt->execute(["pet_id" => $pet_id]);
        }

        $pdo->commit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        die("Pet deletion failed. Please try again.");
    }

    header("Location: pets.php?message=deleted");
    exit;
}


// Retrieve a pet for editing.
$edit_pet = null;

if (isset($_GET["edit"]) && ctype_digit($_GET["edit"])) {
    $stmt = $pdo->prepare(
        "SELECT * FROM pets WHERE pet_id = :pet_id"
    );
    $stmt->execute(["pet_id" => (int) $_GET["edit"]]);
    $edit_pet = $stmt->fetch();
}

// Handle insertion and updating.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $pet_id = $_POST["pet_id"] ?? "";
    $owner_id = $_POST["owner_id"] ?? "";
    $pet_name = trim($_POST["pet_name"] ?? "");
    $species = trim($_POST["species"] ?? "");
    $breed = trim($_POST["breed"] ?? "");
    $sex = $_POST["sex"] ?? "";
    $birth_date = $_POST["birth_date"] ?? "";

    if (
        $pet_name !== "" &&
        $species !== "" &&
        ctype_digit($owner_id) &&
        in_array($sex, ["Male", "Female", "Unknown"], true)
    ) {
        // Check that the selected owner exists.
        $check = $pdo->prepare(
            "SELECT owner_id FROM owners WHERE owner_id = :owner_id"
        );
        $check->execute(["owner_id" => (int) $owner_id]);

        if ($check->fetch()) {
            $birth_date = $birth_date !== "" ? $birth_date : null;
            $breed = $breed !== "" ? $breed : null;

            if ($pet_id !== "" && ctype_digit($pet_id)) {
                $stmt = $pdo->prepare(
                    "UPDATE pets
                     SET owner_id = :owner_id,
                         pet_name = :pet_name,
                         species = :species,
                         breed = :breed,
                         sex = :sex,
                         birth_date = :birth_date,
                         last_updated_by = :last_updated_by,
                         last_updated = CURRENT_TIMESTAMP
                     WHERE pet_id = :pet_id"
                );

                $stmt->execute([
                    "owner_id" => (int) $owner_id,
                    "pet_name" => $pet_name,
                    "species" => $species,
                    "breed" => $breed,
                    "sex" => $sex,
                    "birth_date" => $birth_date,
                    "pet_id" => (int) $pet_id,
                    "last_updated_by" => $_SESSION["user_id"]
                ]);

                header("Location: pets.php?message=updated");
                exit;
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO pets
                     (owner_id, pet_name, species, breed, sex, birth_date, added_by)
                     VALUES
                     (:owner_id, :pet_name, :species, :breed, :sex, :birth_date, :added_by)"
                );

                $stmt->execute([
                    "owner_id" => (int) $owner_id,
                    "pet_name" => $pet_name,
                    "species" => $species,
                    "breed" => $breed,
                    "sex" => $sex,
                    "birth_date" => $birth_date,
                    "added_by" => $_SESSION["user_id"]
                ]);

                header("Location: pets.php?message=added");
                exit;
            }
        }
    }

    header("Location: pets.php?message=invalid");
    exit;
}

// Get pets, owners, and the users who added or updated them.
$sql = "SELECT
            pets.*,
            owners.owner_name,
            added_user.username AS added_by_name,
            updated_user.username AS updated_by_name
        FROM pets
        INNER JOIN owners
            ON pets.owner_id = owners.owner_id
        LEFT JOIN users AS added_user
            ON pets.added_by = added_user.user_id
        LEFT JOIN users AS updated_user
            ON pets.last_updated_by = updated_user.user_id
        ORDER BY pets.pet_id DESC";

$pets = $pdo->query($sql)->fetchAll();

// Get owners for the pet registration dropdown.
$owners = $pdo->query(
    "SELECT owner_id, owner_name
     FROM owners
     ORDER BY owner_name ASC"
)->fetchAll();

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$messages = [
    "added" => "Pet registered successfully.",
    "updated" => "Pet information updated successfully.",
    "deleted" => "Pet deleted successfully.",
    "invalid" => "Please complete the required fields and select a valid owner."
];

$message = $messages[$_GET["message"] ?? ""] ?? "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pet Management | Vet Clinic</title>
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
main { max-width: 1250px; width: 92%; margin: 28px auto; }
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
input, select {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5ce;
    border-radius: 6px;
    font: inherit;
}
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
    <p>Manage registered pets and their owners</p>
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
        <h2><?= $edit_pet ? "Update Pet" : "Register New Pet" ?></h2>

        <?php if (count($owners) > 0): ?>
        <form method="POST" action="pets.php">
            <input type="hidden" name="pet_id"
                value="<?= h($edit_pet["pet_id"] ?? "") ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label for="owner_id">Pet Owner *</label>
                    <select id="owner_id" name="owner_id" required>
                        <option value="">Select an owner</option>
                        <?php foreach ($owners as $owner): ?>
                            <option value="<?= h($owner["owner_id"]) ?>"
                                <?= (string)($edit_pet["owner_id"] ?? "") === (string)$owner["owner_id"] ? "selected" : "" ?>>
                                <?= h($owner["owner_name"]) ?>
                                (ID: <?= h($owner["owner_id"]) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="pet_name">Pet Name *</label>
                    <input id="pet_name" name="pet_name" type="text"
                        value="<?= h($edit_pet["pet_name"] ?? "") ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="species">Species *</label>
                    <select id="species" name="species" required>
                        <option value="">Select species</option>
                        <?php foreach (["Dog", "Cat", "Bird", "Rabbit", "Other"] as $option): ?>
                            <option value="<?= h($option) ?>"
                                <?= ($edit_pet["species"] ?? "") === $option ? "selected" : "" ?>>
                                <?= h($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="breed">Breed</label>
                    <input id="breed" name="breed" type="text"
                        value="<?= h($edit_pet["breed"] ?? "") ?>">
                </div>

                <div class="form-group">
                    <label for="sex">Sex *</label>
                    <select id="sex" name="sex" required>
                        <option value="">Select sex</option>
                        <?php foreach (["Male", "Female", "Unknown"] as $option): ?>
                            <option value="<?= h($option) ?>"
                                <?= ($edit_pet["sex"] ?? "") === $option ? "selected" : "" ?>>
                                <?= h($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="birth_date">Birth Date</label>
                    <input id="birth_date" name="birth_date" type="date"
                        value="<?= h($edit_pet["birth_date"] ?? "") ?>">
                </div>
            </div>

            <p>
                <button type="submit">
                    <?= $edit_pet ? "Save Changes" : "Register Pet" ?>
                </button>

                <?php if ($edit_pet): ?>
                    <a class="button cancel" href="pets.php">Cancel</a>
                <?php endif; ?>
            </p>
        </form>
        <?php else: ?>
            <p>Please register an owner before adding a pet.</p>
            <a class="button" href="owners.php">Register an Owner</a>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Registered Pets</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Pet ID</th>
                        <th>Pet Name</th>
                        <th>Owner</th>
                        <th>Species</th>
                        <th>Breed</th>
                        <th>Sex</th>
                        <th>Birth Date</th>
                        <th>Date Added</th>
                        <th>Added By</th>
                        <th>Last Updated</th>
                        <th>Updated By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($pets) > 0): ?>
                    <?php foreach ($pets as $pet): ?>
                    <tr>
                        <td><?= h($pet["pet_id"]) ?></td>
                        <td><?= h($pet["pet_name"]) ?></td>
                        <td><?= h($pet["owner_name"]) ?></td>
                        <td><?= h($pet["species"]) ?></td>
                        <td><?= h($pet["breed"]) ?></td>
                        <td><?= h($pet["sex"]) ?></td>
                        <td><?= h($pet["birth_date"]) ?></td>
                        <td><?= h($pet["date_added"]) ?></td>
                        <td><?= h($pet["added_by_name"] ?? "Unknown") ?></td>
                        <td><?= h($pet["last_updated"] ?? "Never") ?></td>
                        <td><?= h($pet["updated_by_name"] ?? "Never") ?></td>
                        <td class="actions">
                            <a href="pets.php?edit=<?= h($pet["pet_id"]) ?>">Edit</a>
                            |
                            <a class="delete"
                               href="pets.php?delete=<?= h($pet["pet_id"]) ?>"
                               onclick="return confirm('Delete this pet record?');">
                               Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12">No pets registered yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
