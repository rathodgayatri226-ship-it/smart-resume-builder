<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch latest resume
$stmt = $conn->prepare(
    "SELECT * FROM resumes WHERE user_id = ? ORDER BY id DESC LIMIT 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resume = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$resume) {
    die("Please create your resume first.");
}

$resume_id = $resume['id'];

// Count education, skills and projects
function getCount($conn, $table, $resume_id) {
    $sql = "SELECT COUNT(*) AS total FROM $table WHERE resume_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $resume_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)$result['total'];
}

$educationCount = getCount($conn, "education", $resume_id);
$skillsCount = getCount($conn, "skills", $resume_id);
$projectsCount = getCount($conn, "projects", $resume_id);

// Score calculation
$score = 0;
$suggestions = [];

$checks = [
    "Full Name" => !empty($resume['full_name']),
    "Email" => !empty($resume['email']),
    "Phone Number" => !empty($resume['phone']),
    "Profile Photo" => !empty($resume['photo']),
    "Career Objective" => !empty($resume['career_objective']),
    "Educational Qualifications" => $educationCount > 0,
    "Technical Skills" => $skillsCount > 0,
    "Projects" => $projectsCount > 0,
    "Achievements" => !empty($resume['achievements']),
    "Hobbies" => !empty($resume['hobbies'])
];

foreach ($checks as $section => $completed) {
    if ($completed) {
        $score += 10;
    } else {
        $suggestions[] = "Add your " . $section . " to improve your resume.";
    }
}

if ($score >= 80) {
    $status = "Excellent";
    $message = "Your resume is highly complete. Keep improving your skills!";
} elseif ($score >= 60) {
    $status = "Good";
    $message = "Your resume is good. Add the missing sections.";
} elseif ($score >= 40) {
    $status = "Average";
    $message = "Your resume needs more information.";
} else {
    $status = "Needs Improvement";
    $message = "Complete more sections to strengthen your resume.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Resume Strength Analyzer</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef1f7;
            padding: 25px;
        }

        .container {
            max-width: 700px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 12px #ccc;
            text-align: center;
        }

        h1 {
            color: #263b67;
        }

        .score {
            font-size: 55px;
            font-weight: bold;
            color: #263b67;
            margin: 20px;
        }

        .progress {
            background: #ddd;
            height: 20px;
            border-radius: 20px;
            overflow: hidden;
        }

        .bar {
            height: 100%;
            width: <?php echo $score; ?>%;
            background: #28a745;
        }

        .suggestions {
            text-align: left;
            margin-top: 25px;
        }

        .suggestions li {
            margin: 12px 0;
        }

        .completed {
            color: green;
        }

        .missing {
            color: #d33;
        }

        button {
            background: #263b67;
            color: white;
            border: none;
            padding: 12px 22px;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Resume Strength Analyzer</h1>

    <p>Your Resume Completeness Score</p>

    <div class="score">
        <?php echo $score; ?>/100
    </div>

    <div class="progress">
        <div class="bar"></div>
    </div>

    <h2><?php echo htmlspecialchars($status); ?></h2>

    <p><?php echo htmlspecialchars($message); ?></p>

    <div class="suggestions">

        <h2>Resume Section Analysis</h2>

        <ul>
            <?php foreach ($checks as $section => $completed): ?>
                <li class="<?php echo $completed ? 'completed' : 'missing'; ?>">
                    <?php echo $completed ? '✓ ' : '✗ '; ?>
                    <?php echo htmlspecialchars($section); ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <h2>Improvement Suggestions</h2>

        <?php if (empty($suggestions)): ?>
            <p class="completed">
                Excellent! All resume sections are completed.
            </p>
        <?php else: ?>
            <ul>
                <?php foreach ($suggestions as $suggestion): ?>
                    <li><?php echo htmlspecialchars($suggestion); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

    </div>

    <button onclick="location.href='preview.php'">
        View My Resume
    </button>

    <button onclick="location.href='dashboard.php'">
        Dashboard
    </button>

</div>

</body>
</html>
