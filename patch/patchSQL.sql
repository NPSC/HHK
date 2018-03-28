
ALTER TABLE `invoice` 
    ADD INDEX `Index_Date` (`Invoice_Date` ASC);

ALTER TABLE `payment` 
    ADD INDEX `Index_Date` (`Payment_Date` ASC);


