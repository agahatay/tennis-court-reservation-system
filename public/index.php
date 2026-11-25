<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$now = now();
$today = $now->format('Y-m-d');

$courts = get_all_courts();
$timeSlots = get_all_time_slots();

// Selected court: the requested one if it exists, otherwise the first court.
$requestedCourtId = request_int($_GET, 'court_id');
$selectedCourt = $courts[0] ?? null;
foreach ($courts as $court) {
    if ((int) $court['court_id'] === $requestedCourtId) {
        $selectedCourt = $court;
    }
}

// Selected date: must be a real date, today or later.
$selectedDate = request_string($_GET, 'date', 10);
if (parse_iso_date($selectedDate) === null || $selectedDate < $today) {
    $selectedDate = $today;
}

$bookedSlotIds = $selectedCourt === null ? [] : get_booked_time_slot_ids((int) $selectedCourt['court_id'], $selectedDate);
$hourlyRate = app_config()['hourly_rate'];

$slotOptions = [];
foreach ($timeSlots as $slot) {
    $status = 'available';
    if (in_array((int) $slot['time_slot_id'], $bookedSlotIds, true)) {
        $status = 'booked';
    } elseif (has_time_slot_started($selectedDate, $slot['start_time'], $now)) {
        $status = 'passed';
    }
    $slotOptions[] = $slot + [
        'status' => $status,
        'price' => calculate_reservation_price($slot['start_time'], $slot['end_time'], $hourlyRate),
    ];
}
$availableSlotCount = count(array_filter($slotOptions, static fn (array $slot): bool => $slot['status'] === 'available'));

['old' => $oldInput, 'errors' => $errors] = pull_form_state();
$generalErrors = array_intersect_key($errors, array_flip(['court_id', 'reservation_date']));

$statusLabels = ['available' => 'Available', 'booked' => 'Booked', 'passed' => 'Passed'];

render('header', ['title' => 'Book a Court', 'area' => 'public']);
?>
<section class="card booking-card">
    <header class="card-header">
        <h1>Book a tennis court</h1>
        <p class="muted">Choose a court and a date, then pick one of the available hours.</p>
    </header>

    <?php if ($selectedCourt === null || $timeSlots === []): ?>
        <div class="alert alert-info">Online booking is not available yet. Please check back later.</div>
    <?php else: ?>

    <form class="booking-filters" method="get" action="<?= e(url('index.php')) ?>" data-auto-submit>
        <div class="field">
            <label for="court_id">Court</label>
            <select id="court_id" name="court_id">
                <?php foreach ($courts as $court): ?>
                    <option value="<?= (int) $court['court_id'] ?>"<?= $court === $selectedCourt ? ' selected' : '' ?>>
                        <?= e($court['court_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="date">Date</label>
            <input type="date" id="date" name="date" value="<?= e($selectedDate) ?>" min="<?= e($today) ?>" required>
        </div>
        <noscript><button type="submit" class="button button-secondary">Show available times</button></noscript>
    </form>

    <?php if ($generalErrors !== []): ?>
        <div class="alert alert-error" role="alert">
            <?php foreach ($generalErrors as $message): ?><p><?= e($message) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="booking-form" method="post" action="<?= e(url('reserve.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="court_id" value="<?= (int) $selectedCourt['court_id'] ?>">
        <input type="hidden" name="reservation_date" value="<?= e($selectedDate) ?>">

        <fieldset class="slot-picker">
            <legend>
                Time slot
                <span class="muted">· <?= e($selectedCourt['court_name']) ?>, <?= e(format_date($selectedDate)) ?></span>
            </legend>
            <div class="slot-grid">
                <?php foreach ($slotOptions as $slot): ?>
                    <?php $isAvailable = $slot['status'] === 'available'; ?>
                    <label class="slot slot-<?= e($slot['status']) ?>">
                        <input type="radio" name="time_slot_id" value="<?= (int) $slot['time_slot_id'] ?>" required
                            <?= $isAvailable ? '' : ' disabled' ?>
                            <?= $isAvailable && ($oldInput['time_slot_id'] ?? '') === (string) $slot['time_slot_id'] ? ' checked' : '' ?>>
                        <span class="slot-time"><?= e(format_time_slot($slot)) ?></span>
                        <span class="slot-status"><?= e($statusLabels[$slot['status']]) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if (isset($errors['time_slot_id'])): ?>
                <p class="field-error"><?= e($errors['time_slot_id']) ?></p>
            <?php endif; ?>
        </fieldset>

        <?php if ($availableSlotCount === 0): ?>
            <div class="alert alert-info">There are no available time slots for this court on this date. Please choose another date or court.</div>
        <?php else: ?>
            <div class="form-grid">
                <div class="field">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" maxlength="<?= CUSTOMER_NAME_MAX_LENGTH ?>" autocomplete="name" required
                           value="<?= e($oldInput['name'] ?? '') ?>"<?= isset($errors['name']) ? ' aria-invalid="true"' : '' ?>>
                    <?php if (isset($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label for="phone">Phone number</label>
                    <input type="tel" id="phone" name="phone" maxlength="20" autocomplete="tel" required data-phone-input
                           placeholder="e.g. 0555 555 55 55"
                           value="<?= e($oldInput['phone'] ?? '') ?>"<?= isset($errors['phone']) ? ' aria-invalid="true"' : '' ?>>
                    <?php if (isset($errors['phone'])): ?><p class="field-error"><?= e($errors['phone']) ?></p><?php endif; ?>
                </div>
            </div>

            <div class="booking-summary">
                <p>
                    Price: <strong><?= e(format_money($hourlyRate)) ?></strong> per hour.
                    <span class="muted">The final price is calculated by the system from the selected slot.</span>
                </p>
                <button type="submit" class="button button-primary">Reserve</button>
            </div>
        <?php endif; ?>
    </form>
    <?php endif; ?>
</section>
<?php render('footer'); ?>
