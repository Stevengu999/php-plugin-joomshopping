INSERT INTO `#__jshopping_payment_method`

SET
	`payment_code`          = 'PaynetEasy',
	`payment_class`         = 'pm_payneteasy',
	`payment_publish`       = 0,
	`payment_ordering`      = 0,
	`payment_type`          = 2,
	`price`                 = 0.00,
	`price_type`            = 1,
	`tax_id`                = -1,
	`show_descr_in_email`   = 0,
	`name_en-GB`            = 'PaynetEasy',
	`name_de-DE`            = 'PaynetEasy'
;

CREATE TABLE IF NOT EXISTS `#__jshopping_payment_method_payneteasy`
(
    `client_id`             VARCHAR(30) PRIMARY KEY,
    `paynet_id`             VARCHAR(30),
    `payment_status`        VARCHAR(15),
    `transaction_status`    VARCHAR(15),
    INDEX(`paynet_id`)
);