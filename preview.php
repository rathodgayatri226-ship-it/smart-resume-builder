<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Find latest resume
$stmt = $conn->prepare(
    "SELECT * FROM resumes WHERE user_id = ? ORDER BY id DESC LIMIT 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resume = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$resume) {
    die("No resume found. Please create your resume first.");
}

$resume_id = (int)$resume['id'];

// Save selected template
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['template_id'])) {

    $new_template_id = (int)$_POST['template_id'];

    $check = $conn->prepare(
        "SELECT id FROM templates WHERE id = ?"
    );
    $check->bind_param("i", $new_template_id);
    $check->execute();
    $valid = $check->get_result()->num_rows > 0;
    $check->close();

    if ($valid) {
        $update = $conn->prepare(
            "UPDATE resumes SET template_id = ?
             WHERE id = ? AND user_id = ?"
        );
        $update->bind_param(
            "iii",
            $new_template_id,
            $resume_id,
            $user_id
        );
        $update->execute();
        $update->close();
    }

    header("Location: preview.php");
    exit();
}

// Get selected template name
$stmt = $conn->prepare(
    "SELECT template_name FROM templates WHERE id = ?"
);
$stmt->bind_param("i", $resume['template_id']);
$stmt->execute();
$template = $stmt->get_result()->fetch_assoc();
$stmt->close();

$templateName = strtolower($template['template_name'] ?? 'classic');

if (strpos($templateName, 'modern') !== false) {
    $design = 'modern';
} elseif (strpos($templateName, 'creative') !== false) {
    $design = 'creative';
} else {
    $design = 'classic';
}

// Get all templates
$templates = $conn->query(
    "SELECT * FROM templates"
);

// Get education
$stmt = $conn->prepare(
    "SELECT * FROM education WHERE resume_id = ?"
);
$stmt->bind_param("i", $resume_id);
$stmt->execute();
$education = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get skills
$stmt = $conn->prepare(
    "SELECT * FROM skills WHERE resume_id = ?"
);
$stmt->bind_param("i", $resume_id);
$stmt->execute();
$skills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get projects
$stmt = $conn->prepare(
    "SELECT * FROM projects WHERE resume_id = ?"
);
$stmt->bind_param("i", $resume_id);
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function safe($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Check photo
$photoPath = '';

if (!empty($resume['photo'])) {
    $storedPhoto = $resume['photo'];

    if (is_file(__DIR__ . '/' . $storedPhoto)) {
        $photoPath = $storedPhoto;
    } elseif (is_file(__DIR__ . '/uploads/' . basename($storedPhoto))) {
        $photoPath = 'uploads/' . basename($storedPhoto);
    }
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Smart Resume Preview</title>

<style>

body {
    font-family: Arial, sans-serif;
    background: #e9edf5;
    margin: 0;
    padding: 25px;
    color: #333;
}

.resume {
    max-width: 850px;
    margin: auto;
    background: white;
    padding: 40px;
    box-shadow: 0 0 12px #bbb;
}

.header {
    display: flex;
    align-items: center;
    gap: 25px;
    padding-bottom: 20px;
}

.header img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
}

h1 {
    margin-bottom: 8px;
}

h2 {
    border-bottom: 1px solid #aaa;
    padding-bottom: 6px;
    margin-top: 25px;
}

p {
    line-height: 1.5;
}

.section {
    margin-top: 15px;
}

.item {
    margin-bottom: 15px;
}

.buttons {
    text-align: center;
    margin: 25px;
}

button {
    padding: 12px 22px;
    background: #263b67;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin: 5px;
}

select {
    padding: 11px;
    border-radius: 5px;
    margin: 5px;
}

/* CLASSIC TEMPLATE */

.classic {
    font-family: Arial, sans-serif;
    border-top: 6px solid #333;
}

.classic h1,
.classic h2 {
    color: #222;
}

.classic h2 {
    border-bottom: 1px solid #777;
}

/* MODERN TEMPLATE */

.modern {
    font-family: Arial, sans-serif;
    border-top: 8px solid #1769aa;
}

.modern h1,
.modern h2 {
    color: #1769aa;
}

.modern .header {
    border-bottom: 3px solid #1769aa;
}

.modern h2 {
    border-bottom: 2px solid #1769aa;
}

/* CREATIVE TEMPLATE */

.creative {
    font-family: Georgia, serif;
    border-top: 8px solid #873c9b;
    background: #fffaff;
}

.creative h1,
.creative h2 {
    color: #873c9b;
}

.creative .header {
    border-bottom: 3px solid #d5a2e5;
}

.creative h2 {
    border-bottom: 2px dashed #b77acb;
}

@media print {

    body {
        background: white;
        padding: 0;
    }

    .resume {
        box-shadow: none;
        max-width: 100%;
    }

    .buttons {
        display: none;
    }

}

</style>
</head>

<body>

<div class="buttons">

    <form method="POST">

        <label><strong>Choose Resume Template:</strong></label>

        <select name="template_id">

            <?php while ($row = $templates->fetch_assoc()): ?>

                <option
                    value="<?php echo (int)$row['id']; ?>"
                    <?php echo ((int)$row['id'] === (int)$resume['template_id']) ? 'selected' : ''; ?>
                >
                    <?php echo safe($row['template_name']); ?>
                </option>

            <?php endwhile; ?>

        </select>

        <button type="submit">Apply Template</button>

    </form>

</div>

<div class="resume <?php echo $design; ?>">

    <div class="header">

        <?php if ($photoPath !== ''): ?>

            <img src="<?php echo safe($photoPath); ?>"
                 alt="Profile Photo">

        <?php endif; ?>

        <div>

            <h1><?php echo safe($resume['full_name']); ?></h1>

            <p>
                Email: <?php echo safe($resume['email']); ?><br>
                Phone: <?php echo safe($resume['phone']); ?><br>
                Address: <?php echo nl2br(safe($resume['address'])); ?>
            </p>

            <?php if (!empty($resume['linkedin'])): ?>
                <p>LinkedIn: <?php echo safe($resume['linkedin']); ?></p>
            <?php endif; ?>

            <?php if (!empty($resume['github'])): ?>
                <p>GitHub: <?php echo safe($resume['github']); ?></p>
            <?php endif; ?>

        </div>

    </div>

    <div class="section">

        <h2>Career Objective</h2>

        <p>
            <?php echo nl2br(safe($resume['career_objective'])); ?>
        </p>

    </div>

    <div class="section">

        <h2>Personal Information</h2>

        <p>Date of Birth: <?php echo safe($resume['dob']); ?></p>
        <p>Gender: <?php echo safe($resume['gender']); ?></p>

    </div>

    <div class="section">

        <h2>Educational Qualifications</h2>

        <?php if (count($education) > 0): ?>

            <?php foreach ($education as $row): ?>

                <div class="item">

                    <strong><?php echo safe($row['qualification']); ?></strong><br>

                    Institution: <?php echo safe($row['institution']); ?><br>

                    Year of Passing: <?php echo safe($row['year_of_passing']); ?><br>

                    Percentage: <?php echo safe($row['percentage']); ?>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <p>No education details added.</p>

        <?php endif; ?>

    </div>

    <div class="section">

        <h2>Technical Skills</h2>

        <?php if (count($skills) > 0): ?>

            <ul>

                <?php foreach ($skills as $row): ?>

                    <li><?php echo safe($row['skill_name']); ?></li>

                <?php endforeach; ?>

            </ul>

        <?php else: ?>

            <p>No skills added.</p>

        <?php endif; ?>

    </div>

    <div class="section">

        <h2>Projects</h2>

        <?php if (count($projects) > 0): ?>

            <?php foreach ($projects as $row): ?>

                <div class="item">

                    <strong><?php echo safe($row['project_title']); ?></strong>

                    <p>
                        <?php echo nl2br(safe($row['project_description'])); ?>
                    </p>

                    <p>
                        <strong>Technologies:</strong>
                        <?php echo safe($row['technologies']); ?>
                    </p>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <p>No projects added.</p>

        <?php endif; ?>

    </div>

    <div class="section">

        <h2>Achievements</h2>

        <p><?php echo nl2br(safe($resume['achievements'])); ?></p>

    </div>

    <div class="section">

        <h2>Hobbies</h2>

        <p><?php echo nl2br(safe($resume['hobbies'])); ?></p>

    </div>

</div>

<div class="buttons">

    <button onclick="window.print()">
        Print / Save as PDF
    </button>

    <button onclick="window.location.href='edit_resume.php'">
        Edit Resume
    </button>

    <button onclick="window.location.href='dashboard.php'">
        Back to Dashboard
    </button>

</div>

</body>
</html>