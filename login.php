<?php
session_start();
include 'db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare(
        "SELECT id, name, password FROM users WHERE email = ?"
    );

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];

            header("Location: dashboard.php");
            exit();

        } else {
            $message = "Incorrect password!";
        }

    } else {
        $message = "Account not found! Please register.";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Login</title>

    <style>
        body {
            font-family: Arial;
            background: #eef2ff;
            text-align: center;
            padding: 50px;
        }

        .container {
            background: white;
            padding: 30px;
            width: 320px;
            margin: auto;
            border-radius: 12px;
            box-shadow: 0 4px 15px #ccc;
        }

        input {
            width: 90%;
            padding: 12px;
            margin: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        a {
            color: #4f46e5;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Student Login</h2>
    <p>Smart Resume Builder</p>

    <?php
    if (isset($_GET['registered'])) {
        echo "<p style='color:green;'>Registration successful! Please login.</p>";
    }
    ?>

    <form method="POST">

        <input type="email"
               name="email"
               placeholder="Email Address"
               required>

        <input type="password"
               name="password"
               placeholder="Password"
               required>

        <button type="submit">Login</button>

    </form>

    <p style="color:red;">
        <?php echo htmlspecialchars($message); ?>
    </p>

    <p>Don't have an account?
        <a href="register.php">Register</a>
    </p>

</div>

</body>
</html>