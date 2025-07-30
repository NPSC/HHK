
UPDATE `sys_config` SET `Show` = 0 where `Key` in ("mode", "sId");
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Show`) VALUES ('useGLCodes', 'false', 'b', 'f', '0');

-- API updates
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Oauth_Scopes', 'calendar:read', 'Read reservations and visit events from the calendar');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Oauth_Scopes', 'aggregatereports:read', 'Read aggregate reports');

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`, `Show`) VALUES
('useAPI', 'false', 'b', 'hf', '', 'Enable API Access', '', 1);

CALL new_webpage("ApiClients.php", 2, "API Users", 0, "a", "35", "", "p", "", "", CURRENT_TIMESTAMP(), "mm");



-- add Amount_Tendered for cash receipts
ALTER TABLE `trans`
ADD COLUMN IF NOT EXISTS `Amount_Tendered` decimal(10,2) not null default '0.00' AFTER `Amount`;

-- add labels
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`) VALUES ('labels_category', 'rc', 'Receipt', '65');
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('cashTendered', 'Cash Tendered', 's', 'rc', 'Default: Cash Tendered');
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('changeGiven', 'Change', 's', 'rc', 'Default: Change');


-- move prepayment relationship from invoice to invoice_line
INSERT IGNORE INTO reservation_invoice_line (Reservation_Id, Invoice_Line_Id)
SELECT
  ri.Reservation_Id,
  il.idInvoice_Line
FROM
  reservation_invoice ri
JOIN
  invoice_line il ON ri.Invoice_id = il.Invoice_Id and il.Item_Id = 10;

