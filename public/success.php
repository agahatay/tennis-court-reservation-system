<?php
require __DIR__ . "/../src/database.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$info = null;
if($id > 0){
  $q = mysqli_query($conn,
    "SELECT r.reservation_id, r.date, r.time,
            c.court_name,
            cu.name, cu.phone
     FROM reservations r
     LEFT JOIN courts c ON r.court_id = c.court_id
     LEFT JOIN customers cu ON r.customer_id = cu.customer_id
     WHERE r.reservation_id = $id
     LIMIT 1"
  );
  if($q && mysqli_num_rows($q) > 0){
    $info = mysqli_fetch_assoc($q);
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Successful</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    h2 { color:#27ae60; margin-bottom:10px; }
    .box { text-align:left; font-size:14px; line-height:1.8; }
    .label { font-weight:bold; }
    a { display:inline-block; margin-top:12px; text-decoration:none; font-weight:bold; color:#27ae60; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Successful</h2>

    <?php if($info) { ?>
      <div class="box">
        <div><span class="label">Reservation ID:</span> <?php echo htmlspecialchars($info['reservation_id']); ?></div>
        <div><span class="label">Name:</span> <?php echo htmlspecialchars($info['name'] ?? '-'); ?></div>
        <div><span class="label">Phone:</span> <?php echo htmlspecialchars($info['phone'] ?? '-'); ?></div>
        <div><span class="label">Court:</span> <?php echo htmlspecialchars($info['court_name'] ?? '-'); ?></div>
        <div><span class="label">Date:</span> <?php echo htmlspecialchars($info['date']); ?></div>
        <div><span class="label">Time Slot:</span> <?php echo htmlspecialchars($info['time']); ?></div>
      </div>
    <?php } else { ?>
      <p>Your reservation has been created.</p>
    <?php } ?>

    <a href="index.php">← Make another reservation</a><br>
    <a href="admin/login.php">Admin Panel</a>
  </div>
</body>
</html>
