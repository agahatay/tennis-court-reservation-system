-- =============================================================================
-- 002 - OPTIONAL CLEANUP: remove legacy columns kept by migration 001
-- =============================================================================
-- DESTRUCTIVE: permanently deletes the legacy columns below.
-- Run only after 001 has been applied AND the application has been verified
-- against the upgraded database, and only with a fresh backup.
--
--   reservations.time        (old "HH:MM-HH:MM" label, replaced by time_slot_id)
--   time_slots.slot_label    (replaced by start_time / end_time)
--   time_slots.sort_order    (slots are ordered by start_time)
--
-- The application does not read or write these columns, so it works the same
-- whether or not this script has been run.
-- =============================================================================

ALTER TABLE reservations DROP COLUMN `time`;
ALTER TABLE time_slots DROP COLUMN slot_label, DROP COLUMN sort_order;
