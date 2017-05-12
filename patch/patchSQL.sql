
ALTER TABLE `stays` 
	ADD INDEX `index_idVisit` (`idVisit` ASC);

ALTER TABLE `stays` 
	ADD INDEX `index_Span_Start` (`Span_Start_Date` ASC);

ALTER TABLE `stays` 
	ADD INDEX `index_Span_End` (`Span_End_Date` ASC);

ALTER TABLE `stays` 
	ADD INDEX `index_idName` (`idName` ASC);

