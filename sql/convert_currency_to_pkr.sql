-- Back up the production database before running this migration.
-- Run once against the existing database; legacy SAR amounts are converted at 1 SAR = 76 PKR.
-- Existing ticket prices and agent payment receipts already stored in PKR are not converted.

ALTER TABLE `agent_adjustments` MODIFY COLUMN `amount_sar` decimal(15,2) NOT NULL;
ALTER TABLE `agents` MODIFY COLUMN `opening_balance_sar` decimal(15,2) DEFAULT 0.00;
ALTER TABLE `hotel_booking_stays`
  MODIFY COLUMN `buy_rate_per_night` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `sell_rate_per_night` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `buy_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `sell_total` decimal(15,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `hotel_bookings`
  MODIFY COLUMN `transport_buy_rate` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `transport_sell_rate` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `buy_total_sar` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `sell_total_sar` decimal(15,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `hotel_stays`
  MODIFY COLUMN `per_night_buy` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `per_night_sell` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `net_accommodation_charge` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `vat` decimal(15,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `hotel_vouchers`
  MODIFY COLUMN `buy_rate_sar` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `sell_rate_sar` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `transport_auto_buy_rate` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `transport_auto_sell_rate` decimal(15,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `master_bookings`
  MODIFY COLUMN `buy_rate_sar` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `sell_rate_sar` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `ticket_buy_rate_sar` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `ticket_sell_rate_sar` decimal(15,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `transport_bookings`
  MODIFY COLUMN `buy_rate_sar` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `sell_rate_sar` decimal(15,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `transport_transfers`
  MODIFY COLUMN `buy_rate` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `sell_rate` decimal(15,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `vendor_payments` MODIFY COLUMN `amount_sar` decimal(15,2) NOT NULL;
ALTER TABLE `vendors` MODIFY COLUMN `opening_payable_sar` decimal(15,2) DEFAULT 0.00;
ALTER TABLE `voucher_stays`
  MODIFY COLUMN `buy_rate_per_night` decimal(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `sell_rate_per_night` decimal(15,2) NOT NULL DEFAULT 0.00;

UPDATE `agent_adjustments` SET `amount_sar` = ROUND(`amount_sar` * 76, 2);
ALTER TABLE `agent_adjustments`
  CHANGE COLUMN `amount_sar` `amount_pkr` decimal(15,2) NOT NULL;

UPDATE `agents` SET `opening_balance_sar` = ROUND(`opening_balance_sar` * 76, 2);
ALTER TABLE `agents`
  CHANGE COLUMN `opening_balance_sar` `opening_balance_pkr` decimal(15,2) DEFAULT 0.00;

UPDATE `hotel_booking_stays`
SET `buy_rate_per_night` = ROUND(`buy_rate_per_night` * 76, 2),
    `sell_rate_per_night` = ROUND(`sell_rate_per_night` * 76, 2),
    `buy_total` = ROUND(`buy_total` * 76, 2),
    `sell_total` = ROUND(`sell_total` * 76, 2);

UPDATE `hotel_bookings`
SET `transport_buy_rate` = ROUND(`transport_buy_rate` * 76, 2),
    `transport_sell_rate` = ROUND(`transport_sell_rate` * 76, 2),
    `buy_total_sar` = ROUND(`buy_total_sar` * 76, 2),
    `sell_total_sar` = ROUND(`sell_total_sar` * 76, 2);
ALTER TABLE `hotel_bookings`
  CHANGE COLUMN `buy_total_sar` `buy_total_pkr` decimal(15,2) NOT NULL DEFAULT 0.00,
  CHANGE COLUMN `sell_total_sar` `sell_total_pkr` decimal(15,2) NOT NULL DEFAULT 0.00;

UPDATE `hotel_stays`
SET `per_night_buy` = ROUND(`per_night_buy` * 76, 2),
    `per_night_sell` = ROUND(`per_night_sell` * 76, 2),
    `net_accommodation_charge` = ROUND(`net_accommodation_charge` * 76, 2),
    `vat` = ROUND(`vat` * 76, 2),
    `currency` = 'PKR';

UPDATE `hotel_vouchers`
SET `buy_rate_sar` = ROUND(`buy_rate_sar` * 76, 2),
    `sell_rate_sar` = ROUND(`sell_rate_sar` * 76, 2),
    `transport_auto_buy_rate` = ROUND(`transport_auto_buy_rate` * 76, 2),
    `transport_auto_sell_rate` = ROUND(`transport_auto_sell_rate` * 76, 2);
ALTER TABLE `hotel_vouchers`
  CHANGE COLUMN `buy_rate_sar` `buy_rate_pkr` decimal(15,2) NOT NULL DEFAULT 0.00,
  CHANGE COLUMN `sell_rate_sar` `sell_rate_pkr` decimal(15,2) NOT NULL DEFAULT 0.00;

UPDATE `master_bookings`
SET `buy_rate_sar` = ROUND(`buy_rate_sar` * 76, 2),
    `sell_rate_sar` = ROUND(`sell_rate_sar` * 76, 2),
    `ticket_buy_rate_sar` = ROUND(`ticket_buy_rate_sar` * 76, 2),
    `ticket_sell_rate_sar` = ROUND(`ticket_sell_rate_sar` * 76, 2);
ALTER TABLE `master_bookings`
  CHANGE COLUMN `buy_rate_sar` `buy_rate_pkr` decimal(15,2) NOT NULL DEFAULT 0.00,
  CHANGE COLUMN `sell_rate_sar` `sell_rate_pkr` decimal(15,2) NOT NULL DEFAULT 0.00,
  CHANGE COLUMN `ticket_buy_rate_sar` `ticket_buy_rate_pkr` decimal(15,2) NOT NULL DEFAULT 0.00,
  CHANGE COLUMN `ticket_sell_rate_sar` `ticket_sell_rate_pkr` decimal(15,2) NOT NULL DEFAULT 0.00;

UPDATE `transport_bookings`
SET `buy_rate_sar` = ROUND(`buy_rate_sar` * 76, 2),
    `sell_rate_sar` = ROUND(`sell_rate_sar` * 76, 2);
ALTER TABLE `transport_bookings`
  CHANGE COLUMN `buy_rate_sar` `buy_rate_pkr` decimal(15,2) NOT NULL DEFAULT 0.00,
  CHANGE COLUMN `sell_rate_sar` `sell_rate_pkr` decimal(15,2) NOT NULL DEFAULT 0.00;

UPDATE `transport_transfers`
SET `buy_rate` = ROUND(`buy_rate` * 76, 2),
    `sell_rate` = ROUND(`sell_rate` * 76, 2),
    `currency` = 'PKR';

UPDATE `vendor_payments` SET `amount_sar` = ROUND(`amount_sar` * 76, 2);
ALTER TABLE `vendor_payments`
  CHANGE COLUMN `amount_sar` `amount_pkr` decimal(15,2) NOT NULL;

UPDATE `vendors` SET `opening_payable_sar` = ROUND(`opening_payable_sar` * 76, 2);
ALTER TABLE `vendors`
  CHANGE COLUMN `opening_payable_sar` `opening_payable_pkr` decimal(15,2) DEFAULT 0.00;

UPDATE `voucher_stays`
SET `buy_rate_per_night` = ROUND(`buy_rate_per_night` * 76, 2),
    `sell_rate_per_night` = ROUND(`sell_rate_per_night` * 76, 2);

ALTER TABLE `agent_payments`
  DROP COLUMN `exchange_rate`,
  DROP COLUMN `amount_sar`;

DELETE FROM `system_settings`
WHERE `setting_key` IN ('currency_symbol', 'default_exchange_rate');
