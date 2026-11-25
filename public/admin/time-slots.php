<?php
session_start();
if(!isset($_SESSION['admin'])){
  header("Location: login.php");
  exit();
}
require __DIR__ . "/../../src/database.php";

/* Add slot */
if(isset($_POST['add'])){
  $label = trim($_POST['slot_label'] ?? '');
  $order = (int)($_POST['sort_order'] ?? 0);

  if($label !== '' && $order > 0){
    $labelSafe = mysqli_real_escape_string($conn, $label);
    mysqli_query($conn, "INSERT INTO time_slots(slot_label, sort_order) VALUES('$labelSafe', $order)");
  }
  header("Location: time-slots.php");
  exit();
}

/* Delete slot */
if(isset($_GET['delete'])){
  $id = (int)$_GET['delete'];
  mysqli_query($conn, "DELETE FROM time_slots WHERE slot_id = $id");
  header("Location: time-slots.php");
  exit();
}

$slots = mysqli_query($conn, "SELECT * FROM time_slots ORDER BY sort_order");
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Admin - Time Slots</title>
  <style>
    body { font-family: Arial; background:#f2f2f2; padding:20px; }
    .wrap { max-width: 900px; margin:0 auto; }
    .top { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
    a { color:#27ae60; text-decoration:none; font-weight:bold; margin-left:12px; }
    .box { background:#fff; padding:12px; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.10); margin-bottom:12px; }
    input, button { padding:8px; }
    button { background:#27ae60; color:#fff; border:none; cursor:pointer; }
    table { width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 10px 25px rgba(0,0,0,0.10); }
    th, td { padding:10px; border-bottom:1px solid #eee; text-align:center; }
    th { background:#27ae60; color:#fff; }
    .del { color:red; font-weight:bold; text-decoration:none; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="top">
    <h2 style="margin:0;">Time Slots Management</h2>
    <div>
      <a href="dashboard.php">Dashboard</a>
      <a href="reservations.php">Reservations</a>
      <a href="logout.php">Logout</a>
    </div>
  </div>

  <div class="box">
    <form method="POST">
      <label>Slot label (example 08:00-09:00):</label><br>
      <input type="text" name="slot_label" required>
      <input type="number" name="sort_order" placeholder="Order" required min="1" style="width:90px;">
      <button type="submit" name="add">Add Slot</button>
    </form>
  </div>

  <table>
    <tr>
      <th>Order</th>
      <th>Slot</th>
      <th>Action</th>
    </tr>
    <?php while($s = mysqli_fetch_assoc($slots)) { ?>
      <tr>
        <td><?php echo (int)$s['sort_order']; ?></td>
        <td><?php echo htmlspecialchars($s['slot_label']); ?></td>
        <td>
          <a class="del" href="time-slots.php?delete=<?php echo (int)$s['slot_id']; ?>"
             onclick="return confirm('Delete this slot?')">Delete</a>
        </td>
      </tr>
    <?php } ?>
  </table>

</div>
</body>
</html>
