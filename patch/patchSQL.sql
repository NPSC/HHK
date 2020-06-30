
ALTER TABLE `payment_method` 
	ADD COLUMN `Gl_Code` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Method_Name`;
