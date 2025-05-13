<?php
session_start();
require_once 'db_connection.php'; // Ensure this function throws exceptions on failure

$login_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $acc_id = $_POST['acc_id'];
    $acc_pass = $_POST['acc_pass'];

    try {
        $con = getDatabaseConnection(); // This function should throw an exception on failure

        $stmt = $con->prepare("SELECT acc_pass, role FROM acc WHERE acc_id = ?");
        $stmt->bind_param("i", $acc_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            if ($row['acc_pass'] === $acc_pass) {
                $_SESSION['acc_id'] = $acc_id;
                $_SESSION['role'] = $row['role'];

                if ($row['role'] === 'admin') {
                    header("Location: ARCU-Dashboard.php");
                } else {
                    header("Location: ARCU-Student-Dashboard.php");
                }
                exit();
            } else {
                $login_error = "ERROR: Incorrect Credentials.";
            }
        } else {
            $login_error = "ERROR: Account not Found!";
        }

        $stmt->close();
        $con->close();
    } catch (Exception $e) {
        // Log the error message to a file
        error_log("Database error: " . $e->getMessage());

        // Display a generic error message to the user
        $login_error = "An unexpected error occurred. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARCU-Login</title>
    <link rel="icon" href="../img/ARCULOGO.png"/>
    <link rel="stylesheet" href="main.css">
    <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>

<style>

@import url('https://fonts.googleapis.com/css2?family=Noto+Serif:ital,wght@0,100..900;1,100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

*{
    font-family: "Poppins", sans-serif;
    font-weight: 400;
    font-style: normal;
}
body{
    background: linear-gradient(135deg, rgba(57, 66, 77, 0.5) 0%, rgba(117, 6, 12, 0.9) 100%), url('../img/ARCUBG.jpg');
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center;
}
.right-box{
    padding: 40px 30px 40px 30px;
}
.log-txt{
    color: #fff;
    font-size: 40px;
    font-weight: 700;
}
.welcome-txt{
    color: #fff;
    font-weight: 500;
}
::placeholder{
    font-size: 16px;
}
.log-btn-txt {
    font-weight: 700;
    background: linear-gradient(to right, rgb(194, 105, 105) 0%, rgb(123, 21, 21) 100%);
    border: none;
    transition: background 0.3s ease, transform 0.2s ease;
}
.log-btn-txt:hover {
    background: linear-gradient(to right, rgb(214, 125, 125), rgb(153, 31, 31));
    transform: scale(1.02);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
}
.log-btn-txt:active {
    transform: scale(0.98);
}
input[type="text"]:hover,
input[type="password"]:hover {
    background-color: #f0f0f0;
    transform: scale(1.01);
}
input[type="text"],
input[type="password"] {
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
}

.left-box img:hover {
    transform: scale(1.05) rotate(-2deg);
    transition: transform 0.3s ease;
}

@media only screen and (max-width: 768px) {
    .box-area{
        margin: 0 10px;
    }
    .left-box {
        height: auto;
        padding: 20px;
        overflow: visible;
    }
    .right-box{
        padding: 20px;
    }
}
.error-message {
    min-height: 2em;
    width: 18rem;
    color: #fff;
    visibility: hidden;
    transition: visibility 0.3s ease;
}
.error-message.visible {
    background-color: #DC143C;
    visibility: visible;
}
.error-msg{
    margin-left: 1rem;
}

</style>

</head>
<body>
    
    <!-- MAIN CONTAINER -->

    <div class="container d-flex justify-content-center align-items-center min-vh-100">
    
        <!-- LOGIN CONTAINER -->

        <div class="row border rounded-5 p-3 bg-white shadow box-area">

            <!-- LEFT BOX -->

            <div class="col-md-6 d-flex justify-content-center align-items-center flex-column left-box">
                <div class="featured-image mb-3 text-center w-100">
                    <img src="../img/ARCULOGO.png" alt="ARCU Logo" class="img-fluid" style="max-width: 250px; height: auto;">
                </div>
            </div>

            <!-- RIGHT BOX -->

            <div class="col-md-6 rounded-4 right-box" style="background: linear-gradient(140deg, rgb(72, 25, 25) 25%, rgba(10, 10, 10, 1) 60%, rgba(187, 201, 189, 1) 80%);">
                <div class="row align-items-center">
                    <div class="header-text mb-4">
                        <h1 class="log-txt">LOG IN</h1>
                        <h5 class="welcome-txt">Welcome to ARTS AND CULTURE</h5>
                    </div>

                    <form action="ARCU-Login.php" method="POST">

                        <div class="d-flex align-items-center rounded-2 mb-3 error-message <?php echo !empty($login_error) ? 'visible' : ''; ?>">
                            <div class="error-msg"><?php echo htmlspecialchars($login_error); ?></div>
                        </div>

                        <div class="input-group mb-3">
                            <input type="text" name="acc_id" class="form-control form-control-lg bg-light fs-6" placeholder="ID Number">
                        </div>

                        <div class="input-group mb-3">
                            <input type="password" name="acc_pass" class="form-control form-control-lg bg-light fs-6" placeholder="Password">
                        </div>

                        <div class="input-group mb-5 d-flex">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="formCheck">
                                <label for="form-check" class="form-check-label text-light"><small>Remember Me</small></label>
                            </div>
                        </div>

                        <div class="input-group mb-3">
                            <button type="submit" class="btn btn-lg btn-primary w-100 fs-5 log-btn-txt">LOG IN</button>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>

</body>
</html>
