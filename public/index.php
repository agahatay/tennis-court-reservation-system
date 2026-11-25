<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

const DATE_SHORTCUT_DAYS = 7;

$now = now();
$today = $now->format('Y-m-d');
$hourlyRate = app_config()['hourly_rate'];

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
$selectedCourtId = $selectedCourt === null ? null : (int) $selectedCourt['court_id'];

// Selected date: must be a real date, today or later.
$selectedDate = request_string($_GET, 'date', 10);
if (parse_iso_date($selectedDate) === null || $selectedDate < $today) {
    $selectedDate = $today;
}

// Availability of every court on the selected date (one query).
$bookedByCourt = get_booked_time_slot_ids_by_court($selectedDate);
$bookableSlotIds = [];
foreach ($timeSlots as $slot) {
    if (!has_time_slot_started($selectedDate, $slot['start_time'], $now)) {
        $bookableSlotIds[] = (int) $slot['time_slot_id'];
    }
}
$freeSlotsByCourt = [];
foreach ($courts as $court) {
    $courtId = (int) $court['court_id'];
    $freeSlotsByCourt[$courtId] = count(array_diff($bookableSlotIds, $bookedByCourt[$courtId] ?? []));
}

// Time slots of the selected court.
$slotOptions = [];
foreach ($timeSlots as $slot) {
    $slotId = (int) $slot['time_slot_id'];
    $status = match (true) {
        in_array($slotId, $bookedByCourt[$selectedCourtId] ?? [], true) => 'booked',
        !in_array($slotId, $bookableSlotIds, true) => 'passed',
        default => 'available',
    };
    $slotOptions[] = $slot + [
        'status' => $status,
        'price' => calculate_reservation_price($slot['start_time'], $slot['end_time'], $hourlyRate),
    ];
}
$availableSlotCount = $selectedCourtId === null ? 0 : $freeSlotsByCourt[$selectedCourtId];

// Date shortcuts: the next seven days, plus the selected date if it is further away.
$dateShortcuts = [];
$todayDate = new DateTimeImmutable($today);
for ($offset = 0; $offset < DATE_SHORTCUT_DAYS; $offset++) {
    $dateShortcuts[] = $todayDate->modify("+$offset day");
}
$selectedDateObject = new DateTimeImmutable($selectedDate);
if ($selectedDateObject > end($dateShortcuts)) {
    $dateShortcuts[] = $selectedDateObject;
}

['old' => $oldInput, 'errors' => $errors] = pull_form_state();
$generalErrors = array_intersect_key($errors, array_flip(['court_id', 'reservation_date']));
$statusLabels = ['available' => 'Available', 'booked' => 'Booked', 'passed' => 'Unavailable'];

render('header', ['title' => 'Book a Court', 'area' => 'public']);
?>
<header class="page-intro">
    <h1>Book a tennis court</h1>
    <p class="muted">Pick a court, a day and a free hour, then confirm with your name and phone number.</p>
</header>

<?php if ($selectedCourt === null || $timeSlots === []): ?>
    <div class="alert alert-info">Online booking is not available yet. Please check back later.</div>
<?php else: ?>

<?php if ($generalErrors !== []): ?>
    <div class="alert alert-error" role="alert">
        <?php foreach ($generalErrors as $message): ?><p><?= e($message) ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="booking-layout">
    <div class="booking-steps">
        <section class="card step" aria-labelledby="step-court-title">
            <h2 class="step-title" id="step-court-title"><span class="step-number" aria-hidden="true">1</span>Choose a court</h2>
            <ul class="option-grid court-options">
                <?php foreach ($courts as $court): ?>
                    <?php
                    $courtId = (int) $court['court_id'];
                    $isSelected = $courtId === $selectedCourtId;
                    $freeSlots = $freeSlotsByCourt[$courtId];
                    ?>
                    <li>
                        <a class="option<?= $isSelected ? ' is-selected' : '' ?><?= $freeSlots === 0 ? ' is-full' : '' ?>"
                           href="<?= e(url('index.php', ['court_id' => $courtId, 'date' => $selectedDate])) ?>"
                           <?= $isSelected ? 'aria-current="true"' : '' ?>>
                            <span class="option-title"><?= e($court['court_name']) ?></span>
                            <span class="option-meta"><?= $freeSlots === 0 ? 'Fully booked' : $freeSlots . ' ' . ($freeSlots === 1 ? 'hour' : 'hours') . ' free' ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card step" aria-labelledby="step-date-title">
            <h2 class="step-title" id="step-date-title"><span class="step-number" aria-hidden="true">2</span>Choose a date</h2>
            <ul class="option-grid date-options">
                <?php foreach ($dateShortcuts as $index => $shortcut): ?>
                    <?php
                    $shortcutValue = $shortcut->format('Y-m-d');
                    $isSelected = $shortcutValue === $selectedDate;
                    $dayLabel = match ($index) {
                        0 => 'Today',
                        1 => 'Tomorrow',
                        default => $shortcut->format('l'),
                    };
                    ?>
                    <li>
                        <a class="option option-compact<?= $isSelected ? ' is-selected' : '' ?>"
                           href="<?= e(url('index.php', ['court_id' => $selectedCourtId, 'date' => $shortcutValue])) ?>"
                           <?= $isSelected ? 'aria-current="date"' : '' ?>>
                            <span class="option-meta"><?= e($dayLabel) ?></span>
                            <span class="option-title"><?= e($shortcut->format('j M')) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <form class="date-other" method="get" action="<?= e(url('index.php')) ?>" data-auto-submit>
                <input type="hidden" name="court_id" value="<?= $selectedCourtId ?>">
                <label for="date">Another date</label>
                <input type="date" id="date" name="date" value="<?= e($selectedDate) ?>" min="<?= e($today) ?>" required>
                <noscript><button type="submit" class="button button-secondary">Show</button></noscript>
            </form>
        </section>

        <section class="card step" aria-labelledby="step-time-title">
            <div class="step-header">
                <h2 class="step-title" id="step-time-title"><span class="step-number" aria-hidden="true">3</span>Choose a time</h2>
                <ul class="legend" aria-label="Legend">
                    <li><span class="legend-swatch swatch-available"></span>Available</li>
                    <li><span class="legend-swatch swatch-booked"></span>Booked</li>
                    <li><span class="legend-swatch swatch-unavailable"></span>Unavailable</li>
                </ul>
            </div>
            <p class="step-context"><?= e($selectedCourt['court_name']) ?> · <?= e(format_date($selectedDate)) ?></p>

            <?php if ($availableSlotCount === 0): ?>
                <div class="alert alert-info">No free hours on this court for this date. Try another court or date.</div>
            <?php endif; ?>

            <fieldset class="slot-picker">
                <legend class="visually-hidden">Time slot</legend>
                <div class="slot-grid">
                    <?php foreach ($slotOptions as $slot): ?>
                        <?php $isAvailable = $slot['status'] === 'available'; ?>
                        <label class="slot slot-<?= e($slot['status']) ?>">
                            <input type="radio" name="time_slot_id" form="booking-form" required
                                   value="<?= (int) $slot['time_slot_id'] ?>"
                                   data-label="<?= e(format_time_slot($slot)) ?>"
                                   data-price="<?= e(format_money($slot['price'])) ?>"
                                <?= $isAvailable ? '' : ' disabled' ?>
                                <?= $isAvailable && ($oldInput['time_slot_id'] ?? '') === (string) $slot['time_slot_id'] ? ' checked' : '' ?>>
                            <span class="slot-time"><?= e(format_time_slot($slot)) ?></span>
                            <span class="slot-status"><?= e($statusLabels[$slot['status']]) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <?php if (isset($errors['time_slot_id'])): ?>
                <p class="field-error" role="alert"><?= e($errors['time_slot_id']) ?></p>
            <?php endif; ?>
        </section>
    </div>

    <aside class="booking-aside">
        <form id="booking-form" class="card step booking-form" method="post" action="<?= e(url('reserve.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="court_id" value="<?= $selectedCourtId ?>">
            <input type="hidden" name="reservation_date" value="<?= e($selectedDate) ?>">

            <h2 class="step-title"><span class="step-number" aria-hidden="true">4</span>Your booking</h2>

            <dl class="summary">
                <div><dt>Court</dt><dd><?= e($selectedCourt['court_name']) ?></dd></div>
                <div><dt>Date</dt><dd><?= e(format_date($selectedDate)) ?></dd></div>
                <div><dt>Time</dt><dd data-summary-time>Select a time</dd></div>
                <div class="summary-total"><dt>Price</dt><dd data-summary-price><?= e(format_money($hourlyRate)) ?> / hour</dd></div>
            </dl>

            <div class="field">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" maxlength="<?= CUSTOMER_NAME_MAX_LENGTH ?>" autocomplete="name" required
                       value="<?= e($oldInput['name'] ?? '') ?>"<?= isset($errors['name']) ? ' aria-invalid="true" aria-describedby="name-error"' : '' ?>>
                <?php if (isset($errors['name'])): ?><p class="field-error" id="name-error"><?= e($errors['name']) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="phone">Phone number</label>
                <input type="tel" id="phone" name="phone" maxlength="20" autocomplete="tel" required data-phone-input
                       placeholder="0532 123 45 67"
                       value="<?= e($oldInput['phone'] ?? '') ?>"<?= isset($errors['phone']) ? ' aria-invalid="true" aria-describedby="phone-error"' : ' aria-describedby="phone-hint"' ?>>
                <?php if (isset($errors['phone'])): ?>
                    <p class="field-error" id="phone-error"><?= e($errors['phone']) ?></p>
                <?php else: ?>
                    <p class="field-hint" id="phone-hint">Turkish or international format.</p>
                <?php endif; ?>
            </div>

            <button type="submit" class="button button-primary button-block button-large"<?= $availableSlotCount === 0 ? ' disabled' : '' ?>>
                Reserve
            </button>
            <p class="field-hint">The price is calculated from the length of the selected time slot.</p>
        </form>
    </aside>
</div>
<?php endif; ?>
<?php render('footer'); ?>
