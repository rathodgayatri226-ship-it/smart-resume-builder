<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$name = htmlspecialchars($_SESSION['user_name']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>

    <style>
        body {
            font-family: Arial;
            background: #eef2ff;
            margin: 0;
            text-align: center;
        }

        .header {
            background: #312e81;
            color: white;
            padding: 25px;
        }

        .card {
            background: white;
            padding: 30px;
            margin: 60px auto;
            width: 65%;
            border-radius: 12px;
            box-shadow: 0 4px 15px #ccc;
        }

        a {
            display: inline-block;
            background: #4f46e5;
            color: white;
            padding: 12px 22px;
            margin: 10px;
            border-radius: 6px;
            text-decoration: none;
        }

        .logout {
            background: #dc2626;
        }
    </style>
</head>

<body>

<div class="header">
    <h1>Smart Resume Builder</h1>
    <p>Student Dashboard</p>
</div>

<div class="card">

    <h2>Welcome, <?php echo $name; ?>!</h2>

    <p>Your account has been successfully logged in.</p>

    <h3>What would you like to do?</h3>

    <a href="create_resume.php">Create Resume</a>

    <a href="logout.php" class="logout">Logout</a>
    <a href="strength_score.php">
    Check Resume Strength Score
</a>
<a href="edit_resume.php">
    Edit My Resume
</a>



</div>

</body>
</html>