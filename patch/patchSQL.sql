
ALTER TABLE `stays` 
	ADD INDEX `index_idVisit` (`idVisit` ASC),
	ADD INDEX `index_Span_Start` (`Span_Start_Date` ASC),
	ADD INDEX `index_Span_End` (`Span_End_Date` ASC);

