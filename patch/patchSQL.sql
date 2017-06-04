
ALTER TABLE `stays` 
    DROP INDEX `index_idVisit` ;

ALTER TABLE `name` 
    DROP INDEX `idName_UNIQUE` ;

update gen_lookups set `Type` = 'h' where Table_Name = 'NoReturnReason';

