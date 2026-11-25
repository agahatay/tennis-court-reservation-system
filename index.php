<?php
include "db.php";


$selectedCourt = isset($_GET['court_id']) ? (int)$_GET['court_id'] : 1;
$selectedDate  = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');


$timeSlots = [
  "08:00-09:00","09:00-10:00","10:00-11:00","11:00-12:00",
  "12:00-13:00","13:00-14:00","14:00-15:00","15:00-16:00",
  "16:00-17:00","17:00-18:00","18:00-19:00","19:00-20:00",
  "20:00-21:00"
];


$courts = [];
$cr = mysqli_query($conn, "SELECT court_id, court_name FROM courts ORDER BY court_id");
while($row = mysqli_fetch_assoc($cr)){
  $courts[] = $row;
}
if(count($courts) > 0 && $selectedCourt <= 0){
  $selectedCourt = (int)$courts[0]['court_id'];
}


$bookedTimes = [];
if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)){
  $stmt = mysqli_prepare($conn, "SELECT time FROM reservations WHERE court_id = ? AND date = ?");
  mysqli_stmt_bind_param($stmt, "is", $selectedCourt, $selectedDate);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while($r = mysqli_fetch_assoc($res)){
    $bookedTimes[] = $r['time'];
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Tennis Court Reservation</title>

  <link rel="stylesheet" href="style.css">

  <style>
    body { background:#f2f2f2; font-family: Arial, sans-serif; }
    .container{
      max-width: 520px;
      margin: 40px auto;
      background:#fff;
      padding: 22px;
      border-radius: 10px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.10);
    }
    h1 { color:#27ae60; margin: 0 0 10px; text-align:center; }
    label { display:block; font-weight:700; margin-top:12px; }
    input, select { width:100%; padding:10px; margin-top:6px; border:1px solid #ddd; border-radius:8px; }
    button { width:100%; margin-top:16px; padding:12px; background:#27ae60; color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:700; }
    button:disabled { opacity:0.6; cursor:not-allowed; }
    hr { margin:18px 0; border:0; border-top:1px solid #eee; }
    .hint { font-size:12px; opacity:0.85; margin-top:8px; text-align:center; }
    .msg-success { background:#d4edda; color:#155724; padding:10px; border-radius:8px; margin:10px 0; font-weight:700; }
    .msg-error { background:#f8d7da; color:#721c24; padding:10px; border-radius:8px; margin:10px 0; font-weight:700; }
    .toplink { text-align:center; margin-top:12px; }
    .toplink a{ color:#27ae60; text-decoration:none; font-weight:700; }
  </style>

  <script>
    function validateForm(){
      const name  = document.querySelector("input[name='name']").value.trim();
      const phone = document.querySelector("input[name='phone']").value.trim();
      const price = document.querySelector("input[name='price']").value.trim();
      const time  = document.querySelector("select[name='time']").value;

      if(!name || !phone || !price || !time){
        alert("Please fill in all fields.");
        return false;
      }

      const digits = phone.replace(/\D/g,'');
      if(digits.length < 10){
        alert("Phone number must be at least 10 digits.");
        return false;
      }

      const p = parseFloat(price);
      if(isNaN(p) || p < 0){
        alert("Price must be a valid non-negative number.");
        return false;
      }

      return true;
    }
  </script>
</head>

<body>
<div class="container">

  <?php
  
  if(isset($_GET['invalid'])){
    $msg = "INVALID";
    if($_GET['invalid'] === "missing") $msg = "INVALID: Missing fields";
    if($_GET['invalid'] === "booked")  $msg = "INVALID: Already booked";
    if($_GET['invalid'] === "time")    $msg = "INVALID: Invalid time slot";
    if($_GET['invalid'] === "db")      $msg = "INVALID: Database error";
    echo '<div class="msg-error">'.htmlspecialchars($msg).'</div>';
  }

  
  if(isset($_GET['success'])){
    echo '<div class="msg-success">Reservation successfully created.</div>';
  }
  ?>

  <h1>Tennis Court Reservation</h1>

  <!-- 1) Court & Date Selection (GET) -->
  <form method="GET">
    <label>
      Court
      <select name="court_id" onchange="this.form.submit()">
        <?php foreach($courts as $c): ?>
          <?php
            $cid = (int)$c['court_id'];
            $sel = ($cid === $selectedCourt) ? "selected" : "";
          ?>
          <option value="<?php echo $cid; ?>" <?php echo $sel; ?>>
            <?php echo htmlspecialchars($c['court_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>
      Date
      <input
        type="date"
        name="date"
        value="<?php echo htmlspecialchars($selectedDate); ?>"
        min="<?php echo date('Y-m-d'); ?>"
        onchange="this.form.submit()"
      >
    </label>

    <div class="hint">
      Selecting court/date refreshes the page and hides already booked time slots.
    </div>
  </form>

  <hr>

  <!-- 2) Reservation Form (POST) -->
  <form action="reserve.php" method="POST" onsubmit="return validateForm()" novalidate>
    <input type="hidden" name="court_id" value="<?php echo (int)$selectedCourt; ?>">
    <input type="hidden" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">

    <label>
      Name
      <input type="text" name="name" placeholder="e.g. John Doe">
    </label>

    <label>
      Phone
      <input type="text" name="phone" placeholder="e.g. 0555 555 55 55">
    </label>

    <label>
      Price
      <input type="number" name="price" min="0" step="0.01" placeholder="e.g. 400">
    </label>

    <label>
      Time Slot
      <select name="time">
        <?php
        $availableCount = 0;
        foreach($timeSlots as $slot){
          if(!in_array($slot, $bookedTimes, true)){
            $availableCount++;
            echo "<option value='".htmlspecialchars($slot)."'>".htmlspecialchars($slot)."</option>";
          }
        }
        if($availableCount === 0){
          echo "<option value='' disabled selected>No available time slots</option>";
        }
        ?>
      </select>
    </label>

    <button type="submit" <?php echo ($availableCount===0) ? "disabled" : ""; ?>>
      Reserve
    </button>

    <div class="hint">All fields are required. Phone must have at least 10 digits.</div>
  </form>

  <div class="toplink">
    <a href="list.php">View Admin Panel</a>
  </div>

</div>
</body>
</html>
