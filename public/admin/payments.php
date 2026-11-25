<?php
session_start();
if(!isset($_SESSION['admin'])){
  header("Location: login.php");
  exit();
}

require __DIR__ . "/../../src/database.php";

/* Hata görünsün (şimdilik açık kalsın, sonra kapatırız) */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$today = date('Y-m-d');

/* ---- Toggle Paid (0/1) ---- */
if(isset($_GET['toggle_paid'])){
  $rid = (int)($_GET['toggle_paid'] ?? 0);
  $val = (int)($_GET['v'] ?? 0);
  $val = ($val === 1) ? 1 : 0;

  if($rid > 0){
    $stmt = mysqli_prepare($conn, "UPDATE reservations SET is_paid = ? WHERE reservation_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $val, $rid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }

  $status = urlencode($_GET['status'] ?? 'all');
  header("Location: payments.php?status=$status");
  exit();
}

/* ---- Filters ---- */
$status = $_GET['status'] ?? 'all'; // all | paid | unpaid
$where = "";
if($status === 'paid')   $where = "WHERE COALESCE(r.is_paid,0)=1";
if($status === 'unpaid') $where = "WHERE COALESCE(r.is_paid,0)=0";

/* ---- Summary ---- */
$sumSql = "
  SELECT
    COALESCE(SUM(r.price),0) AS total_sum,
    COALESCE(SUM(CASE WHEN r.is_paid=1 THEN r.price ELSE 0 END),0) AS paid_sum,
    COALESCE(SUM(CASE WHEN COALESCE(r.is_paid,0)=0 THEN r.price ELSE 0 END),0) AS unpaid_sum
  FROM reservations r
";
$sumRes = mysqli_query($conn, $sumSql);
if(!$sumRes){
  die("SUM query error: " . mysqli_error($conn));
}
$sum = mysqli_fetch_assoc($sumRes) ?: ['total_sum'=>0,'paid_sum'=>0,'unpaid_sum'=>0];

/* ---- Table ---- */
$listSql = "
  SELECT
    r.reservation_id,
    r.date, r.time, r.price, r.is_paid,
    co.court_name,
    cu.name, cu.phone
  FROM reservations r
  LEFT JOIN courts co ON r.court_id = co.court_id
  LEFT JOIN customers cu ON r.customer_id = cu.customer_id
  $where
  ORDER BY r.date DESC, r.time DESC
";
$result = mysqli_query($conn, $listSql);
if(!$result){
  die("LIST query error: " . mysqli_error($conn));
}

$countRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM reservations");
$countRow = mysqli_fetch_assoc($countRes);
$totalRows = (int)($countRow['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Admin Panel - Payments</title>
  <style>
    body { font-family: Arial; background:#f2f2f2; padding:20px; }
    .wrap { max-width: 1200px; margin: 0 auto; }
    a { color:#27ae60; text-decoration:none; font-weight:bold; margin-right:12px; }
    .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
    .card { background:#fff; border-radius:10px; padding:14px; box-shadow:0 10px 25px rgba(0,0,0,0.10); margin-bottom:12px; }
    .tabs a { padding:6px 10px; border-radius:8px; background:#fff; box-shadow:0 6px 15px rgba(0,0,0,0.08); }
    .tabs a.active { background:#27ae60; color:#fff; }

    table { width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 10px 25px rgba(0,0,0,0.10); }
    th, td { padding:10px; border-bottom:1px solid #eee; text-align:center; font-size:13px; }
    th { background:#27ae60; color:#fff; }
    tr:nth-child(even){ background:#fafafa; }

    .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-weight:700; font-size:12px; }
    .yes { background:#d4edda; color:#155724; }
    .no  { background:#f8d7da; color:#721c24; }

    .btn { color:#27ae60; font-weight:bold; }
    .muted { opacity:.7; font-size:12px; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="topbar">
    <h2 style="margin:0;">Payments</h2>
    <div>
      <a href="dashboard.php">Dashboard</a>
      <a href="reservations.php">Reservations</a>
      <a href="schedule.php">Daily Schedule</a>
      <a href="payments.php">Payments</a>
      <a href="logout.php">Logout</a>
    </div>
  </div>

  <div class="card">
    <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
      <div>
        Total: <b><?php echo number_format((float)$sum['total_sum'], 2); ?></b>
        &nbsp;|&nbsp;
        Paid: <b><?php echo number_format((float)$sum['paid_sum'], 2); ?></b>
        &nbsp;|&nbsp;
        Unpaid: <b><?php echo number_format((float)$sum['unpaid_sum'], 2); ?></b>
        <div class="muted">DB total reservations: <?php echo $totalRows; ?></div>
      </div>

      <div class="tabs">
        <a class="<?php echo ($status==='all'?'active':''); ?>" href="payments.php?status=all">All</a>
        <a class="<?php echo ($status==='paid'?'active':''); ?>" href="payments.php?status=paid">Paid</a>
        <a class="<?php echo ($status==='unpaid'?'active':''); ?>" href="payments.php?status=unpaid">Unpaid</a>
      </div>
    </div>
  </div>

  <table>
    <tr>
      <th>Date</th><th>Time</th><th>Court</th><th>Name</th><th>Phone</th><th>Price</th><th>Paid</th><th>Action</th>
    </tr>

    <?php
      $shown = 0;
      while($row = mysqli_fetch_assoc($result)){
        $shown++;
        $rid = (int)($row['reservation_id'] ?? 0);
        $isPaid = ((int)($row['is_paid'] ?? 0) === 1);
        $nextVal = $isPaid ? 0 : 1;
        $btnText = $isPaid ? "Mark Unpaid" : "Mark Paid";
        $confirm = $isPaid ? "Mark this reservation as UNPAID?" : "Mark this reservation as PAID?";
    ?>
      <tr>
        <td><?php echo htmlspecialchars($row['date'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($row['time'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($row['court_name'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($row['name'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></td>
        <td><?php echo number_format((float)($row['price'] ?? 0), 2); ?></td>
        <td>
          <?php if($isPaid){ ?>
            <span class="badge yes">Yes</span>
          <?php } else { ?>
            <span class="badge no">No</span>
          <?php } ?>
        </td>
        <td>
          <a class="btn"
             href="payments.php?toggle_paid=<?php echo $rid; ?>&v=<?php echo $nextVal; ?>&status=<?php echo urlencode($status); ?>"
             onclick="return confirm('<?php echo $confirm; ?>')">
             <?php echo $btnText; ?>
          </a>
        </td>
      </tr>
    <?php } ?>

    <?php if($shown === 0){ ?>
      <tr>
        <td colspan="8" style="padding:18px;">
          No rows to display for this filter.
        </td>
      </tr>
    <?php } ?>
  </table>

</div>
</body>
</html>
