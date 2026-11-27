<?php
include 'db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if ($name == "" || $email == "" || $password == "") {
        $message = "Please fill all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $message = "Password must contain at least 6 characters.";
    } else {

        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "Email already registered!";
        } else {

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, password)
                 VALUES (?, ?, ?)"
            );

            $stmt->bind_param(
                "sss",
                $name,
                $email,
                $hashedPassword
            );

            if ($stmt->execute()) {
                header("Location: login.php?registered=1");
                exit();
            } else {
                $message = "Registration failed!";
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Registration</title>
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

    <h2>Create Account</h2>
    <p>Smart Resume Builder</p>

    <form method="POST">

        <input type="text"
               name="name"
               placeholder="Full Name"
               required>

        <input type="email"
               name="email"
               placeholder="Email Address"
               required>

        <input type="password"
               name="password"
               placeholder="Password (minimum 6 characters)"
               minlength="6"
               required>

        <button type="submit">Register</button>

    </form>

    <p style="color:red;">
        <?php echo htmlspecialchars($message); ?>
    </p>

    <p>Already registered?
        <a href="login.php">Login</a>
    </p>

</div>

</body>
</html>