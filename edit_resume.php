<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";

// Get the student's latest resume
$stmt = $conn->prepare(
    "SELECT * FROM resumes WHERE user_id = ? ORDER BY id DESC LIMIT 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resume = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$resume) {
    die("Please create a resume first.");
}

$resume_id = (int)$resume['id'];

// Fetch existing details
function fetchRows($conn, $sql, $resume_id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $resume_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

$education = fetchRows(
    $conn,
    "SELECT * FROM education WHERE resume_id = ?",
    $resume_id
);

$skills = fetchRows(
    $conn,
    "SELECT * FROM skills WHERE resume_id = ?",
    $resume_id
);

$projects = fetchRows(
    $conn,
    "SELECT * FROM projects WHERE resume_id = ?",
    $resume_id
);

$templateResult = $conn->query("SELECT * FROM templates");

// Update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $career_objective = trim($_POST['career_objective'] ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');
    $github = trim($_POST['github'] ?? '');
    $achievements = trim($_POST['achievements'] ?? '');
    $hobbies = trim($_POST['hobbies'] ?? '');
    $template_id = (int)($_POST['template_id'] ?? 1);

    $photo = $resume['photo'] ?? '';

    if ($full_name === '' || $email === '' || $phone === '') {
        $error = "Name, email and phone number are required.";
    }

    // Optional new photo upload
    if ($error === '' && isset($_FILES['photo']) &&
        $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = "Photo upload failed.";
        } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
            $error = "Photo must be smaller than 2 MB.";
        } else {
            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $fileInfo->file($_FILES['photo']['tmp_name']);

            $types = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png'
            ];

            if (!isset($types[$mime])) {
                $error = "Only JPG and PNG photos are allowed.";
            } else {
                $uploadDir = __DIR__ . "/uploads/";

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $filename = uniqid("student_", true) . "." . $types[$mime];

                if (move_uploaded_file(
                    $_FILES['photo']['tmp_name'],
                    $uploadDir . $filename
                )) {
                    $photo = "uploads/" . $filename;
                } else {
                    $error = "Could not save the new photo.";
                }
            }
        }
    }

    // Validate template
    $check = $conn->prepare("SELECT id FROM templates WHERE id = ?");
    $check->bind_param("i", $template_id);
    $check->execute();
    $validTemplate = $check->get_result()->num_rows > 0;
    $check->close();

    if ($error === '' && !$validTemplate) {
        $error = "Please select a valid template.";
    }

    if ($error === '') {
        try {
            $conn->begin_transaction();

            // Update main resume
            $sql = "UPDATE resumes SET
                full_name = ?,
                dob = NULLIF(?, ''),
                gender = ?,
                phone = ?,
                email = ?,
                address = ?,
                career_objective = ?,
                photo = ?,
                linkedin = ?,
                github = ?,
                achievements = ?,
                hobbies = ?,
                template_id = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND user_id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssssssssssssiii",
                $full_name,
                $dob,
                $gender,
                $phone,
                $email,
                $address,
                $career_objective,
                $photo,
                $linkedin,
                $github,
                $achievements,
                $hobbies,
                $template_id,
                $resume_id,
                $user_id
            );
            $stmt->execute();
            $stmt->close();

            // Replace old education records
            $stmt = $conn->prepare(
                "DELETE FROM education WHERE resume_id = ?"
            );
            $stmt->bind_param("i", $resume_id);
            $stmt->execute();
            $stmt->close();

            $educationStmt = $conn->prepare(
                "INSERT INTO education
                (resume_id, qualification, institution,
                 year_of_passing, percentage)
                VALUES (?, ?, ?, ?, ?)"
            );

            $qualifications = $_POST['qualification'] ?? [];
            $institutions = $_POST['institution'] ?? [];
            $years = $_POST['year'] ?? [];
            $percentages = $_POST['percentage'] ?? [];

            foreach ($qualifications as $i => $qualification) {
                $qualification = trim($qualification);
                $institution = trim($institutions[$i] ?? '');
                $year = trim($years[$i] ?? '');
                $percentage = trim($percentages[$i] ?? '');

                if ($qualification !== '' || $institution !== '') {
                    $educationStmt->bind_param(
                        "issss",
                        $resume_id,
                        $qualification,
                        $institution,
                        $year,
                        $percentage
                    );
                    $educationStmt->execute();
                }
            }
            $educationStmt->close();

            // Replace old skills
            $stmt = $conn->prepare(
                "DELETE FROM skills WHERE resume_id = ?"
            );
            $stmt->bind_param("i", $resume_id);
            $stmt->execute();
            $stmt->close();

            $skillStmt = $conn->prepare(
                "INSERT INTO skills (resume_id, skill_name) VALUES (?, ?)"
            );

            foreach (($_POST['skill'] ?? []) as $skill) {
                $skill = trim($skill);

                if ($skill !== '') {
                    $skillStmt->bind_param("is", $resume_id, $skill);
                    $skillStmt->execute();
                }
            }
            $skillStmt->close();

            // Replace old projects
            $stmt = $conn->prepare(
                "DELETE FROM projects WHERE resume_id = ?"
            );
            $stmt->bind_param("i", $resume_id);
            $stmt->execute();
            $stmt->close();

            $projectStmt = $conn->prepare(
                "INSERT INTO projects
                (resume_id, project_title, project_description, technologies)
                VALUES (?, ?, ?, ?)"
            );

            $titles = $_POST['project_title'] ?? [];
            $descriptions = $_POST['project_description'] ?? [];
            $technologies = $_POST['technologies'] ?? [];

            foreach ($titles as $i => $title) {
                $title = trim($title);
                $description = trim($descriptions[$i] ?? '');
                $technology = trim($technologies[$i] ?? '');

                if ($title !== '') {
                    $projectStmt->bind_param(
                        "isss",
                        $resume_id,
                        $title,
                        $description,
                        $technology
                    );
                    $projectStmt->execute();
                }
            }
            $projectStmt->close();

            $conn->commit();

            header("Location: preview.php");
            exit();

        } catch (Throwable $e) {
            $conn->rollback();
            $error = "Update failed: " . $e->getMessage();
        }
    }

    // Keep submitted values on the form if validation fails
    $resume = array_merge($resume, [
        'full_name' => $full_name,
        'dob' => $dob,
        'gender' => $gender,
        'phone' => $phone,
        'email' => $email,
        'address' => $address,
        'career_objective' => $career_objective,
        'photo' => $photo,
        'linkedin' => $linkedin,
        'github' => $github,
        'achievements' => $achievements,
        'hobbies' => $hobbies,
        'template_id' => $template_id
    ]);

    $education = [];
    foreach ($qualifications ?? [] as $i => $qualification) {
        $education[] = [
            'qualification' => $qualification,
            'institution' => $institutions[$i] ?? '',
            'year_of_passing' => $years[$i] ?? '',
            'percentage' => $percentages[$i] ?? ''
        ];
    }

    $skills = array_map(
        fn($skill) => ['skill_name' => $skill],
        $_POST['skill'] ?? []
    );

    $projects = [];
    foreach ($titles ?? [] as $i => $title) {
        $projects[] = [
            'project_title' => $title,
            'project_description' => $descriptions[$i] ?? '',
            'technologies' => $technologies[$i] ?? ''
        ];
    }
}

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Resume</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef1f7;
            padding: 20px;
        }
        .container {
            max-width: 750px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }
        h1, h2 { color: #263b67; }
        label {
            display: block;
            margin-top: 12px;
            font-weight: bold;
        }
        input, textarea, select {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            box-sizing: border-box;
        }
        textarea { min-height: 75px; }
        .item {
            border: 1px solid #ddd;
            padding: 12px;
            margin-top: 12px;
        }
        button {
            background: #263b67;
            color: white;
            padding: 11px 18px;
            border: 0;
            border-radius: 5px;
            margin-top: 15px;
            cursor: pointer;
        }
        .remove { background: #b52b35; }
        .error { color: red; }
        .current-photo {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
        }
    </style>
</head>
<body>

<div class="container">

    <h1>Edit Your Resume</h1>

    <?php if ($error !== ''): ?>
        <p class="error"><?php echo h($error); ?></p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <h2>Personal Information</h2>

        <label>Full Name</label>
        <input name="full_name" value="<?php echo h($resume['full_name']); ?>" required>

        <label>Date of Birth</label>
        <input type="date" name="dob" value="<?php echo h($resume['dob']); ?>">

        <label>Gender</label>
        <select name="gender">
            <option value="">Select Gender</option>
            <?php foreach (['Female', 'Male', 'Other'] as $g): ?>
                <option value="<?php echo $g; ?>"
                    <?php echo ($resume['gender'] === $g) ? 'selected' : ''; ?>>
                    <?php echo $g; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Phone Number</label>
        <input name="phone" value="<?php echo h($resume['phone']); ?>" required>

        <label>Email</label>
        <input type="email" name="email" value="<?php echo h($resume['email']); ?>" required>

        <label>Address</label>
        <textarea name="address"><?php echo h($resume['address']); ?></textarea>

        <label>Current Profile Photo</label>
        <?php if (!empty($resume['photo'])): ?>
            <p><img class="current-photo"
                src="<?php echo h($resume['photo']); ?>" alt="Current photo"></p>
        <?php else: ?>
            <p>No photo uploaded.</p>
        <?php endif; ?>

        <label>Choose a new photo (optional)</label>
        <input type="file" name="photo" accept="image/jpeg,image/png">

        <label>LinkedIn</label>
        <input type="url" name="linkedin" value="<?php echo h($resume['linkedin']); ?>">

        <label>GitHub</label>
        <input type="url" name="github" value="<?php echo h($resume['github']); ?>">

        <label>Career Objective</label>
        <textarea name="career_objective"><?php echo h($resume['career_objective']); ?></textarea>

        <h2>Educational Qualifications</h2>
        <div id="educationContainer">
            <?php foreach ($education as $row): ?>
                <div class="item">
                    <label>Qualification</label>
                    <input name="qualification[]" value="<?php echo h($row['qualification']); ?>">
                    <label>Institution</label>
                    <input name="institution[]" value="<?php echo h($row['institution']); ?>">
                    <label>Year of Passing</label>
                    <input name="year[]" value="<?php echo h($row['year_of_passing']); ?>">
                    <label>Percentage / CGPA</label>
                    <input name="percentage[]" value="<?php echo h($row['percentage']); ?>">
                    <button type="button" class="remove"
                        onclick="this.parentElement.remove()">Remove</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" onclick="addEducation()">Add Education</button>

        <h2>Technical Skills</h2>
        <div id="skillsContainer">
            <?php foreach ($skills as $row): ?>
                <div class="item">
                    <label>Skill</label>
                    <input name="skill[]" value="<?php echo h($row['skill_name']); ?>">
                    <button type="button" class="remove"
                        onclick="this.parentElement.remove()">Remove</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" onclick="addSkill()">Add Skill</button>

        <h2>Projects</h2>
        <div id="projectsContainer">
            <?php foreach ($projects as $row): ?>
                <div class="item">
                    <label>Project Title</label>
                    <input name="project_title[]" value="<?php echo h($row['project_title']); ?>">
                    <label>Project Description</label>
                    <textarea name="project_description[]"><?php echo h($row['project_description']); ?></textarea>
                    <label>Technologies Used</label>
                    <input name="technologies[]" value="<?php echo h($row['technologies']); ?>">
                    <button type="button" class="remove"
                        onclick="this.parentElement.remove()">Remove</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" onclick="addProject()">Add Project</button>

        <h2>Achievements</h2>
        <textarea name="achievements"><?php echo h($resume['achievements']); ?></textarea>

        <h2>Hobbies</h2>
        <textarea name="hobbies"><?php echo h($resume['hobbies']); ?></textarea>

        <h2>Resume Template</h2>
        <select name="template_id">
            <?php while ($template = $templateResult->fetch_assoc()): ?>
                <option value="<?php echo (int)$template['id']; ?>"
                    <?php echo ((int)$resume['template_id'] === (int)$template['id']) ? 'selected' : ''; ?>>
                    <?php echo h($template['template_name']); ?>
                </option>
            <?php endwhile; ?>
        </select>

        <button type="submit">Update My Resume</button>
        <a href="dashboard.php">Back to Dashboard</a>

    </form>
</div>

<script>
function addEducation() {
    document.getElementById('educationContainer').insertAdjacentHTML('beforeend', `
        <div class="item">
            <label>Qualification</label><input name="qualification[]">
            <label>Institution</label><input name="institution[]">
            <label>Year of Passing</label><input name="year[]">
            <label>Percentage / CGPA</label><input name="percentage[]">
            <button type="button" class="remove" onclick="this.parentElement.remove()">Remove</button>
        </div>`);
}

function addSkill() {
    document.getElementById('skillsContainer').insertAdjacentHTML('beforeend', `
        <div class="item">
            <label>Skill</label><input name="skill[]">
            <button type="button" class="remove" onclick="this.parentElement.remove()">Remove</button>
        </div>`);
}

function addProject() {
    document.getElementById('projectsContainer').insertAdjacentHTML('beforeend', `
        <div class="item">
            <label>Project Title</label><input name="project_title[]">
            <label>Project Description</label><textarea name="project_description[]"></textarea>
            <label>Technologies Used</label><input name="technologies[]">
            <button type="button" class="remove" onclick="this.parentElement.remove()">Remove</button>
        </div>`);
}
</script>

</body>
</html>