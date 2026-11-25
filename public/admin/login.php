<?php
session_start();

$error = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){
  $password = $_POST['password'];

  if($password == "[REDACTED]"){   // ŞİFRE BURADA
    $_SESSION['admin'] = true;
    header("Location: reservations.php");
    exit();
  } else {
    $error = "Wrong password!";
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Admin Login</title>
  <style>
    body {
      font-family: Arial;
      background: #f2f2f2;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .box {
      background: white;
      padding: 25px;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0,0,0,0.2);
      text-align: center;
      width: 300px;
    }
    input, button {
      width: 100%;
      padding: 8px;
      margin-top: 10px;
    }
    button {
      background: #27ae60;
      color: white;
      border: none;
      cursor: pointer;
    }
    .error {
      color: red;
      margin-top: 10px;
    }
  </style>
</head>

<body>

<div class="box">
  <h2>Admin Login</h2>

  <form method="POST">
    <input type="password" name="password" placeholder="Admin Password" required>
    <button type="submit">Login</button>
  </form>

  <?php if($error != "") { ?>
    <p class="error"><?= $error ?></p>
  <?php } ?>
</div>

</body>
</html>
