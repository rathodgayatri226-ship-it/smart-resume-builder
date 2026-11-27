<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

// Get available resume templates
$templateResult = $conn->query("SELECT * FROM templates");

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Personal details
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

    // Check required fields
    if ($full_name == "" || $email == "" || $phone == "") {
        $error = "Please fill in your name, email and phone number.";
    }

    // Check selected template
    $templateCheck = $conn->prepare(
        "SELECT id FROM templates WHERE id = ?"
    );
    $templateCheck->bind_param("i", $template_id);
    $templateCheck->execute();
    $templateExists = $templateCheck->get_result()->num_rows > 0;
    $templateCheck->close();

    if ($error == "" && !$templateExists) {
        $error = "Please select a valid resume template.";
    }

    // PHOTO UPLOAD
    $photo = "";

    if ($error == "" && isset($_FILES['photo']) &&
        $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = "Photo upload failed. Please select the photo again.";
        } else {

            $uploadDir = __DIR__ . "/uploads/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            if ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
                $error = "Photo size must be less than 2 MB.";
            } else {

                $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                $fileType = $fileInfo->file($_FILES['photo']['tmp_name']);

                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png'
                ];

                if (!isset($allowedTypes[$fileType])) {
                    $error = "Only JPG and PNG photos are allowed.";
                } else {

                    $fileName = uniqid("student_", true) .
                                "." . $allowedTypes[$fileType];

                    if (move_uploaded_file(
                        $_FILES['photo']['tmp_name'],
                        $uploadDir . $fileName
                    )) {
                        $photo = "uploads/" . $fileName;
                    } else {
                        $error = "Unable to save photo. Check the uploads folder.";
                    }
                }
            }
        }
    }

    // Save resume and related details
    if ($error == "") {

        try {
            $conn->begin_transaction();

            // Insert main resume details
            $sql = "INSERT INTO resumes
            (user_id, template_id, full_name, dob, gender, phone,
             email, address, career_objective, photo, linkedin,
             github, achievements, hobbies)
            VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "iissssssssssss",
                $user_id,
                $template_id,
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
                $hobbies
            );

            $stmt->execute();
            $resume_id = $conn->insert_id;
            $stmt->close();

            // Insert education details
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

            for ($i = 0; $i < count($qualifications); $i++) {

                $qualification = trim($qualifications[$i] ?? '');
                $institution = trim($institutions[$i] ?? '');
                $year = trim($years[$i] ?? '');
                $percentage = trim($percentages[$i] ?? '');

                if ($qualification != "" || $institution != "") {
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

            // Insert skills
            $skillStmt = $conn->prepare(
                "INSERT INTO skills (resume_id, skill_name)
                 VALUES (?, ?)"
            );

            $skillList = $_POST['skill'] ?? [];

            foreach ($skillList as $skill) {
                $skill = trim($skill);

                if ($skill != "") {
                    $skillStmt->bind_param("is", $resume_id, $skill);
                    $skillStmt->execute();
                }
            }

            $skillStmt->close();

            // Insert projects
            $projectStmt = $conn->prepare(
                "INSERT INTO projects
                (resume_id, project_title, project_description, technologies)
                VALUES (?, ?, ?, ?)"
            );

            $titles = $_POST['project_title'] ?? [];
            $descriptions = $_POST['project_description'] ?? [];
            $technologies = $_POST['technologies'] ?? [];

            for ($i = 0; $i < count($titles); $i++) {

                $title = trim($titles[$i] ?? '');
                $description = trim($descriptions[$i] ?? '');
                $technology = trim($technologies[$i] ?? '');

                if ($title != "") {
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

            // All records saved successfully
            $conn->commit();

            // Go to resume preview
            header("Location: preview.php");
            exit();

        } catch (Throwable $e) {

            $conn->rollback();

            $error = "Error saving resume: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Resume</title>

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
            box-shadow: 0 0 10px #ccc;
        }

        h1 {
            text-align: center;
            color: #263b67;
        }

        h2 {
            color: #263b67;
            margin-top: 25px;
        }

        label {
            display: block;
            margin-top: 12px;
            font-weight: bold;
        }

        input, textarea, select {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #bbb;
            border-radius: 5px;
            box-sizing: border-box;
        }

        textarea {
            min-height: 80px;
        }

        button {
            background: #263b67;
            color: white;
            padding: 11px 18px;
            border: none;
            border-radius: 5px;
            margin-top: 15px;
            cursor: pointer;
        }

        .item {
            border: 1px solid #ddd;
            padding: 15px;
            margin-top: 12px;
            border-radius: 6px;
        }

        .remove {
            background: #b52b35;
        }

        .error {
            color: red;
            text-align: center;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Create Your Resume</h1>

    <?php if ($error != ""): ?>
        <p class="error">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php endif; ?>

    <!-- MAIN FORM -->
    <form method="POST" enctype="multipart/form-data">

        <h2>Personal Information</h2>

        <label>Full Name</label>
        <input type="text" name="full_name" required>

        <label>Date of Birth</label>
        <input type="date" name="dob">

        <label>Gender</label>
        <select name="gender">
            <option value="">Select Gender</option>
            <option value="Female">Female</option>
            <option value="Male">Male</option>
            <option value="Other">Other</option>
        </select>

        <label>Phone Number</label>
        <input type="text" name="phone" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Address</label>
        <textarea name="address"></textarea>

        <label>Upload Profile Photo (JPG or PNG, max 2 MB)</label>
        <input type="file" name="photo"
               accept="image/jpeg,image/png">

        <label>LinkedIn Profile</label>
        <input type="url" name="linkedin">

        <label>GitHub Profile</label>
        <input type="url" name="github">

        <label>Career Objective</label>
        <textarea name="career_objective"></textarea>

        <h2>Educational Qualifications</h2>

        <div id="educationContainer">
            <div class="item">
                <label>Qualification</label>
                <input type="text" name="qualification[]"
                       placeholder="B.E. Computer Science">

                <label>Institution</label>
                <input type="text" name="institution[]">

                <label>Year of Passing</label>
                <input type="text" name="year[]">

                <label>Percentage / CGPA</label>
                <input type="text" name="percentage[]">
            </div>
        </div>

        <button type="button" onclick="addEducation()">
            Add Education
        </button>

        <h2>Technical Skills</h2>

        <div id="skillsContainer">
            <div class="item">
                <label>Skill</label>
                <input type="text" name="skill[]"
                       placeholder="Example: Python, Java, HTML">
            </div>
        </div>

        <button type="button" onclick="addSkill()">
            Add Skill
        </button>

        <h2>Projects</h2>

        <div id="projectsContainer">
            <div class="item">
                <label>Project Title</label>
                <input type="text" name="project_title[]">

                <label>Project Description</label>
                <textarea name="project_description[]"></textarea>

                <label>Technologies Used</label>
                <input type="text" name="technologies[]">
            </div>
        </div>

        <button type="button" onclick="addProject()">
            Add Project
        </button>

        <h2>Achievements</h2>
        <textarea name="achievements"
                  placeholder="Enter your achievements"></textarea>

        <h2>Hobbies</h2>
        <textarea name="hobbies"
                  placeholder="Enter your hobbies"></textarea>

        <h2>Choose Resume Template</h2>

        <select name="template_id" required>
            <?php if ($templateResult && $templateResult->num_rows > 0): ?>
                <?php while ($template = $templateResult->fetch_assoc()): ?>
                    <option value="<?php echo (int)$template['id']; ?>">
                        <?php echo htmlspecialchars($template['template_name']); ?>
                    </option>
                <?php endwhile; ?>
            <?php else: ?>
                <option value="1">Classic</option>
            <?php endif; ?>
        </select>

        <button type="submit" name="save_resume">
            Save My Resume
        </button>

    </form>

</div>

<script>

function addEducation() {
    const container = document.getElementById("educationContainer");

    const div = document.createElement("div");
    div.className = "item";

    div.innerHTML = `
        <label>Qualification</label>
        <input type="text" name="qualification[]">

        <label>Institution</label>
        <input type="text" name="institution[]">

        <label>Year of Passing</label>
        <input type="text" name="year[]">

        <label>Percentage / CGPA</label>
        <input type="text" name="percentage[]">

        <button type="button" class="remove"
                onclick="this.parentElement.remove()">Remove</button>
    `;

    container.appendChild(div);
}

function addSkill() {
    const container = document.getElementById("skillsContainer");

    const div = document.createElement("div");
    div.className = "item";

    div.innerHTML = `
        <label>Skill</label>
        <input type="text" name="skill[]">

        <button type="button" class="remove"
                onclick="this.parentElement.remove()">Remove</button>
    `;

    container.appendChild(div);
}

function addProject() {
    const container = document.getElementById("projectsContainer");

    const div = document.createElement("div");
    div.className = "item";

    div.innerHTML = `
        <label>Project Title</label>
        <input type="text" name="project_title[]">

        <label>Project Description</label>
        <textarea name="project_description[]"></textarea>

        <label>Technologies Used</label>
        <input type="text" name="technologies[]">

        <button type="button" class="remove"
                onclick="this.parentElement.remove()">Remove</button>
    `;

    container.appendChild(div);
}

</script>

</body>
</html>