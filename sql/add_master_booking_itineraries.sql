-- Add full ticket itinerary and traveller classification to Master Bookings.
-- Safe to run repeatedly on MariaDB.
ALTER TABLE master_bookings
  ADD COLUMN IF NOT EXISTS flight_itinerary_json LONGTEXT DEFAULT NULL AFTER flight_number,
  ADD COLUMN IF NOT EXISTS gender CHAR(1) DEFAULT NULL AFTER flight_itinerary_json,
  ADD COLUMN IF NOT EXISTS pax_type VARCHAR(20) NOT NULL DEFAULT 'Adult' AFTER gender;
