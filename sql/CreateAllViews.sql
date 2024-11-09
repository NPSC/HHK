-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2017 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK

-- Must create vmember_listing first as it is used by most of the rest of hte views
-- -----------------------------------------------------
-- View `vmember_listing`
-- -----------------------------------------------------
CREATE or replace VIEW `vmember_listing` AS
select `n`.`idName` AS `Id`,
`n`.`Name_Full` AS `Fullname`,
(case when (`n`.`Record_Company` = 0) then `n`.`Name_Last` else `n`.`Company` end) AS `Name_Last`,
`n`.`Name_First` AS `Name_First`,
`n`.`Name_Middle` AS `Name_Middle`,
`n`.`Name_Nickname` AS `Name_Nickname`,
ifnull(`g1`.`Description`,'') AS `Name_Prefix`,
ifnull(`g2`.`Description`,'') AS `Name_Suffix`,
(case when (`n`.`Exclude_Phone` = 1) then '' when (n.Preferred_Phone = 'no') then 'No Phone' else (case when (ifnull(`np`.`Phone_Extension`,'') = '')
  then ifnull(`np`.`Phone_Num`,'') else concat_ws('x',`np`.`Phone_Num`,`np`.`Phone_Extension`) end) end) AS `Preferred_Phone`,
(case when (`n`.`Exclude_Mail` = 1) then '' else ifnull(`na`.`Address_1`,'') end) AS `Address_1`,
(case when (`n`.`Exclude_Mail` = 1) then '' else ifnull(`na`.`Address_2`,'') end) AS `Address_2`,
(case when (`n`.`Exclude_Mail` = 1) then '' else ifnull(`na`.`City`,'') end) AS `City`,
(case when (`n`.`Exclude_Mail` = 1) then '' else ifnull(`na`.`State_Province`,'') end) AS `StateProvince`,
(case when (`n`.`Exclude_Mail` = 1) then '' else ifnull(`na`.`Postal_Code`,'') end) AS `PostalCode`,
(case when (`n`.`Exclude_Mail` = 1) then '' else ifnull(`na`.`Country`,'') end) AS `Country`,
(case when (`n`.`Exclude_Mail` = 1) then '' else ifnull(`na`.`Country_Code`,'') end) AS `Country_Code`,
ifnull(`na`.`Bad_Address`,'') AS `Bad_Address`,
(case when (`n`.`Exclude_Email` = 1) then '' else (case when (`n`.`Preferred_Email` = 'no') then 'No Email' else ifnull(`ne`.`Email`,'') end) end) AS `Preferred_Email`,
`n`.`Member_Status` AS `MemberStatus`,
`n`.`Gender` AS `Gender`,
`n`.`Birth_Month` AS `Birth_Month`,
`n`.`Member_Since` AS `Member_Since`,
(case when (ifnull(`r`.`idName`,0) = `n`.`idName`) then `r`.`Target_Id` when (ifnull(`r`.`Target_Id`,0) = `n`.`idName`)
then `r`.`idName` else 0 end) AS `SpouseId`,
case when `n`.`Record_Member` = 1 then '1' else '0' end AS `MemberRecord`,
`n`.`External_Id` AS `External_Id`,
`n`.`Company_Id` AS `Company_Id`,
`n`.`Company` AS `Company`,
case when `n`.`Exclude_Mail` = 1 then '1' else '0' end AS `Exclude_Mail`,
case when `n`.`Exclude_Email` = 1 then '1' else '0' end AS `Exclude_Email`,
case when `n`.`Exclude_Phone` = 1 then '1' else '0' end AS `Exclude_Phone`,
case when `n`.`Exclude_Directory` = 1 then '1' else '0' end AS `Exclude_Directory`,
`n`.`Last_Updated` AS `Last_Updated`,
`n`.`Member_Type` AS `Member_Type`,
`n`.`Title` AS `Title`,
`n`.`Company_CareOf` AS `Company_CareOf`,
ifnull(`n`.`Preferred_Mail_Address`,'') AS `Address_Code`,
ifnull(`n`.`Preferred_Email`,'') AS `Email_Code`,
ifnull(`n`.`Preferred_Phone`,'') AS `Phone_Code`
from ((((((`name` `n` left join `name_address` `na` on(((`n`.`idName` = `na`.`idName`)
  and (`n`.`Preferred_Mail_Address` = `na`.`Purpose`))))
left join `name_email` `ne` on(((`n`.`idName` = `ne`.`idName`) and (`n`.`Preferred_Email` = `ne`.`Purpose`))))
left join `name_phone` `np` on(((`n`.`idName` = `np`.`idName`) and (`n`.`Preferred_Phone` = `np`.`Phone_Code`))))
left join `gen_lookups` `g1` on(((`n`.`Name_Prefix` = `g1`.`Code`) and (`g1`.`Table_Name` = 'Name_Prefix'))))
left join `gen_lookups` `g2` on(((`n`.`Name_Suffix` = `g2`.`Code`) and (`g2`.`Table_Name` = 'Name_Suffix'))))
left join `relationship` `r` on(((`r`.`Relation_Type` = 'sp') and (`r`.`Status` = 'a')
  and ((`n`.`idName` = `r`.`idName`) or (`n`.`idName` = `r`.`Target_Id`)))))
where ((`n`.`idName` > 0) and (`n`.`Member_Status` in ('a','d','in')));




-- -----------------------------------------------------
-- View `vmember_listing_blackout`
-- -----------------------------------------------------
CREATE or replace VIEW `vmember_listing_blackout` AS
select vm.*, ifnull(s.idVisit,0) as idVisit, max(ifnull(s.Span_End_Date, now())) as spanEnd
from vmember_listing vm
left join stays s on vm.Id = s.idName and s.Status in ('co', 'a')
group by vm.Id;




-- -----------------------------------------------------
-- View `vmember_listing_noex`
-- -----------------------------------------------------
CREATE or replace VIEW `vmember_listing_noex` AS
select `n`.`idName` AS `Id`,
`n`.`Name_Full` AS `Fullname`,
(case when (`n`.`Record_Company` = 0) then `n`.`Name_Last` else `n`.`Company` end) AS `Name_Last`,
`n`.`Name_First` AS `Name_First`,
`n`.`Name_Middle` AS `Name_Middle`,
`n`.`Name_Nickname` AS `Name_Nickname`,
ifnull(`g1`.`Description`,'') AS `Name_Prefix`,
ifnull(`g2`.`Description`,'') AS `Name_Suffix`,
(case  when (n.Preferred_Phone = 'no') then 'No Phone'  when (ifnull(`np`.`Phone_Extension`,'') = '')
  then ifnull(`np`.`Phone_Num`,'') else concat_ws('x',`np`.`Phone_Num`,`np`.`Phone_Extension`) end) AS `Preferred_Phone`,
(case when (`n`.`Exclude_Mail` = 1 && n.Company_CareOf <> 'y') then '' else ifnull(`na`.`Address_1`,'') end) AS `Address_1`,
(case when (`n`.`Exclude_Mail` = 1 && n.Company_CareOf <> 'y') then '' else ifnull(`na`.`Address_2`,'') end) AS `Address_2`,
(case when (`n`.`Exclude_Mail` = 1 && n.Company_CareOf <> 'y') then '' else ifnull(`na`.`City`,'') end) AS `City`,
(case when (`n`.`Exclude_Mail` = 1 && n.Company_CareOf <> 'y') then '' else ifnull(`na`.`State_Province`,'') end) AS `StateProvince`,
(case when (`n`.`Exclude_Mail` = 1 && n.Company_CareOf <> 'y') then '' else ifnull(`na`.`Postal_Code`,'') end) AS `PostalCode`,
(case when (`n`.`Exclude_Mail` = 1 && n.Company_CareOf <> 'y') then '' else ifnull(`na`.`Country`,'') end) AS `Country`,
(case when (`n`.`Exclude_Mail` = 1 && n.Company_CareOf <> 'y') then '' else ifnull(`na`.`Country_Code`,'') end) AS `Country_Code`,
ifnull(`na`.`Bad_Address`,'') AS `Bad_Address`,
(case when (`n`.`Preferred_Email` = 'no') then 'No Email' else ifnull(`ne`.`Email`,'') end) AS `Preferred_Email`,
`n`.`Member_Status` AS `MemberStatus`,
`n`.`Gender` AS `Gender`,
`n`.`Birth_Month` AS `Birth_Month`,
`n`.`Member_Since` AS `Member_Since`,
(case when (ifnull(`r`.`idName`,0) = `n`.`idName`) then `r`.`Target_Id` when (ifnull(`r`.`Target_Id`,0) = `n`.`idName`)
then `r`.`idName` else 0 end) AS `SpouseId`,
case when `n`.`Record_Member` = 1 then '1' else '0' end AS `MemberRecord`,
`n`.`External_Id` AS `External_Id`,
`n`.`Company_Id` AS `Company_Id`,
`n`.`Company` AS `Company`,
case when `n`.`Exclude_Mail` = 1 then '1' else '0' end AS `Exclude_Mail`,
case when `n`.`Exclude_Email` = 1 then '1' else '0' end AS `Exclude_Email`,
case when `n`.`Exclude_Phone` = 1 then '1' else '0' end AS `Exclude_Phone`,
case when `n`.`Exclude_Directory` = 1 then '1' else '0' end AS `Exclude_Directory`,
`n`.`Last_Updated` AS `Last_Updated`,
`n`.`Member_Type` AS `Member_Type`,
`n`.`Title` AS `Title`,
`n`.`Company_CareOf` AS `Company_CareOf`,
ifnull(`n`.`Preferred_Mail_Address`,'') AS `Address_Code`,
ifnull(`n`.`Preferred_Email`,'') AS `Email_Code`,
ifnull(`n`.`Preferred_Phone`,'') AS `Phone_Code`
from `name` `n` left join `name_address` `na` on `n`.`idName` = `na`.`idName`
  and `n`.`Preferred_Mail_Address` = `na`.`Purpose`
left join `name_email` `ne` on `n`.`idName` = `ne`.`idName` and `n`.`Preferred_Email` = `ne`.`Purpose`
left join `name_phone` `np` on `n`.`idName` = `np`.`idName` and `n`.`Preferred_Phone` = `np`.`Phone_Code`
left join `gen_lookups` `g1` on `n`.`Name_Prefix` = `g1`.`Code` and `g1`.`Table_Name` = 'Name_Prefix'
left join `gen_lookups` `g2` on `n`.`Name_Suffix` = `g2`.`Code` and `g2`.`Table_Name` = 'Name_Suffix'
left join `relationship` `r` on `r`.`Relation_Type` = 'sp' and `r`.`Status` = 'a'
  and (`n`.`idName` = `r`.`idName` or `n`.`idName` = `r`.`Target_Id`)
where `n`.`idName` > 0 and `n`.`Member_Status` in ('a','d','in');


-- -----------------------------------------------------
-- View `vdoc_notes`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vdoc_notes` AS
    SELECT
        n.idNote AS `Note_Id`,
        n.idNote AS `Action`,
        n.flag,
        n.User_Name,
        n.Title,
        n.Note_Text,
        dn.Doc_Id,
        n.`Timestamp`
    FROM
        note n
            JOIN
        doc_note dn ON n.idNote = dn.Note_Id
    WHERE
        dn.Doc_Id > 0 && n.`Status` = 'a';

-- -----------------------------------------------------
-- View `vstaff_notes`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vstaff_notes` AS
    SELECT
        n.idNote AS `Note_Id`,
        n.idNote AS `Action`,
        n.flag,
        n.User_Name,
        n.Category,
        n.Title,
        n.Note_Text,
        n.`Timestamp`
    FROM
        note n
            JOIN
        staff_note sn ON n.idNote = sn.Note_Id
    WHERE
        n.`Status` = 'a';


-- -----------------------------------------------------
-- View `vmem_notes`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vmem_notes` AS
    SELECT
        n.idNote AS `Note_Id`,
        n.idNote AS `Action`,
        n.flag,
        n.User_Name,
        n.Title,
        n.Note_Text,
        mn.idName,
        n.`Timestamp`
    FROM
        note n
            JOIN
        member_note mn ON n.idNote = mn.Note_Id
    WHERE
        n.`Status` = 'a';


-- -----------------------------------------------------
-- View `vadditional_guests`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vadditional_guests` AS
    SELECT
        `s`.`idVisit` AS `idVisit`,
        `s`.`idName` AS `idName`,
        `s`.`Status` AS `Status`,
        IFNULL(`n`.`Name_First`, '') AS `Name_First`,
        IFNULL(`n`.`Name_Last`, '') AS `Name_Last`,
        IFNULL(`r`.`Title`, '') AS `Title`,
        IFNULL(`g2`.`Description`, '') AS `Age`,
        `s`.`Checkin_Date` AS `Checkin_Date`,
        `s`.`Expected_Co_Date` AS `Expected_Co_Date`,
        IFNULL(`ng`.`Relationship_Code`, '') AS `Relationship_Code`,
        IFNULL(`g1`.`Description`, '') AS `Relationship`
    FROM
        ((((((`stays` `s`
        LEFT JOIN `name_guest` `ng` ON ((`s`.`idName` = `ng`.`idName`)))
        LEFT JOIN `name` `n` ON ((`s`.`idName` = `n`.`idName`)))
        LEFT JOIN `name_demog` `nd` ON ((`s`.`idName` = `nd`.`idName`)))
        LEFT JOIN `room` `r` ON ((`s`.`idRoom` = `r`.`idRoom`)))
        LEFT JOIN `gen_lookups` `g1` ON (((`g1`.`Table_Name` = 'Patient_Rel_Type')
            AND (`g1`.`Code` = `ng`.`Relationship_Code`))))
        LEFT JOIN `gen_lookups` `g2` ON (((`g2`.`Table_Name` = 'Age_Bracket')
            AND (`g2`.`Code` = `nd`.`Age_Bracket`))));





-- -----------------------------------------------------
-- View `vadmin_history_records`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vadmin_history_records` AS
    SELECT
        `m`.`Id` AS `Id`,
        (CASE
            WHEN (`m`.`MemberRecord` = 1) THEN `m`.`Fullname`
            ELSE `m`.`Company`
        END) AS `Fullname`,
        `m`.`Preferred_Phone` AS `Preferred_Phone`,
        `m`.`Preferred_Email` AS `Preferred_Email`,
        (CASE
            WHEN (`m`.`Bad_Address` = 'true') THEN '*(Bad Address)*'
            ELSE `m`.`Address_1`
        END) AS `Address_1`,
        (CASE
            WHEN (`m`.`Bad_Address` = 'true') THEN ''
            ELSE `m`.`Address_2`
        END) AS `Address_2`,
        (CASE
            WHEN (`m`.`Bad_Address` = 'true') THEN ''
            ELSE `m`.`City`
        END) AS `City`,
        (CASE
            WHEN (`m`.`Bad_Address` = 'true') THEN ''
            ELSE `m`.`StateProvince`
        END) AS `StateProvince`,
        (CASE
            WHEN (`m`.`Bad_Address` = 'true') THEN ''
            ELSE `m`.`Country_Code`
        END) AS `Country_Code`,
        (CASE
            WHEN (`m`.`Bad_Address` = 'true') THEN ''
            ELSE `m`.`PostalCode`
        END) AS `PostalCode`,
        (CASE
            WHEN (`m`.`MemberRecord` = 1) THEN `m`.`Company`
            ELSE ''
        END) AS `Company`
    FROM
        (`member_history` `g`
        JOIN `vmember_listing` `m` ON ((`g`.`idName` = `m`.`Id`)))
    ORDER BY `g`.`Admin_Access_Date` DESC
    LIMIT 12;



-- -----------------------------------------------------
-- View `vaudit_log`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vaudit_log` AS
    (SELECT
        `name_log`.`Timestamp` AS `LogDate`,
        `name_log`.`Log_Type` AS `LogType`,
        `name_log`.`Sub_Type` AS `Subtype`,
        `name_log`.`WP_User_Id` AS `User`,
        `name_log`.`idName` AS `idName`,
        `name_log`.`Log_Text` AS `LogText`
    FROM
        `name_log`)
    UNION (SELECT
        `a`.`Effective_Date` AS `LogDate`,
        'Volunteer' AS `LogType`,
        `a`.`Action_Codes` AS `Subtype`,
        `a`.`Source_Code` AS `User`,
        `a`.`idName` AS `idName`,
        IFNULL(CONCAT(`g2`.`Description`,
                        ' - ',
                        `g`.`Description`,
                        ', Rank = ',
                        IFNULL(`g3`.`Description`, '')),
                '(category Deleted)') AS `LogText`
    FROM
        (((`activity` `a`
        LEFT JOIN `gen_lookups` `g` ON (((SUBSTRING_INDEX(`a`.`Product_Code`, '|', 1) = `g`.`Table_Name`)
            AND (SUBSTRING_INDEX(`a`.`Product_Code`, '|', -(1)) = `g`.`Code`))))
        LEFT JOIN `gen_lookups` `g2` ON (((`g2`.`Table_Name` = 'Vol_Category')
            AND (SUBSTRING_INDEX(`a`.`Product_Code`, '|', 1) = `g2`.`Code`))))
        LEFT JOIN `gen_lookups` `g3` ON (((`g3`.`Table_Name` = 'Vol_Rank')
            AND (`g3`.`Code` = `a`.`Other_Code`))))
    WHERE
        (`a`.`Type` = 'vol'));


-- -----------------------------------------------------
-- View `vcamp_vol`
-- -----------------------------------------------------
CREATE or REPLACE VIEW `vcamp_vol` AS
select
    n.idName,
    n.Member_Status,
    n.Record_Member,
    ifnull(nv.Vol_Category,'') as Vol_Category,
    ifnull(`nv`.`Vol_Code`, '') AS `Vol_Code`,
    '' as Campaign_Code
from
    name n
    left join name_volunteer2 nv ON n.idName = nv.idName and nv.Vol_Status = 'a'
where
    n.Member_Status in ('a', 'in')

union
select distinct
    n.idName,
    n.Member_Status,
    n.Record_Member,
    '' as Vol_Category,
    '' as Vol_Code,
    ifnull(d.Campaign_Code,'') as Campaign_Code
from
    name n
    join donations d on (n.idName = d.Donor_Id or n.idName = d.Assoc_Id) and d.Status = 'a'
where n.Member_Status in ('a', 'in');


-- vcategory_listing must be before vcategory_events
-- -----------------------------------------------------
-- View `vcategory_listing`
-- -----------------------------------------------------
CREATE or replace VIEW `vcategory_listing` AS
select
    g.Table_Name as `Vol_Category`,
    g.Code as `Vol_Code`,
    gc.Description as `Vol_Category_Title`,
    g.Description as `Vol_Code_Title`,
    g.Substitute as `Colors`,
    ifnull(g3.Description, 'n') as `Show_Email_Delete`,
    ifnull(g4.Description, 'y') as `Hide_Add_Members`,
    ifnull(g5.Description, 'n') as `Show_AllCategory`,
    ifnull(g2.Description, 'n') as `Cal_House`
from
    gen_lookups g
        join
    gen_lookups gc ON g.Table_Name=gc.Code and gc.Table_Name = 'Vol_Category'
        left join
    gen_lookups g3 ON g3.Table_Name = 'Cal_Show_Delete_Email' and g3.Code = concat(g.Table_Name, g.Code)
        left join
    gen_lookups g4 ON g4.Table_Name = 'Cal_Hide_Add_Members' and g4.Code = concat(g.Table_Name, g.Code)
        left join
    gen_lookups g5 ON g5.Table_Name = 'Cal_Show_AllCategory' and g5.Code = concat(g.Table_Name, g.Code)
        left join
    gen_lookups g2 on g2.Table_Name = 'Cal_House' and g2.Code = concat(g.Table_Name, g.Code);



-- -----------------------------------------------------
-- View `vcleaning_log`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vcleaning_log` AS
    SELECT
        IFNULL(`rr`.`idResource`, 0) AS `idResource`,
        `c`.`idRoom` AS `idRoom`,
        IFNULL(`r`.`Title`, '') AS `Title`,
        IFNULL(`r`.`Cleaning_Cycle_Code`, '') AS `Cleaning_Cycle_Code`,
        `c`.`Type` AS `Type`,
        `c`.`Status` AS `Status`,
        `c`.`Last_Cleaned` AS `Last_Cleaned`,
		`c`.`Last_Deep_Clean` as `Last_Deep_Clean`,
        `c`.`Notes` AS `Notes`,
        IFNULL(`g`.`Description`, '') AS `Status_Text`,
        `c`.`Username` AS `Username`,
        `c`.`Timestamp` AS `Timestamp`
    FROM
        (((`cleaning_log` `c`
        LEFT JOIN `room` `r` ON ((`c`.`idRoom` = `r`.`idRoom`)))
        LEFT JOIN `resource_room` `rr` ON ((`c`.`idRoom` = `rr`.`idRoom`)))
        LEFT JOIN `gen_lookups` `g` ON (((`g`.`Table_Name` = 'Room_Status')
            AND (`g`.`Code` = `c`.`Status`))));


-- -----------------------------------------------------
-- View `vcredit_payments`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vcredit_payments` AS
    SELECT
        IFNULL(`pa`.`idPayment_auth`, 0) AS `idPayment_auth`,
        `p`.`idPayment` AS `idPayment`,
        IFNULL(`pa`.`Approved_Amount`, 0) AS `Approved_Amount`,
        IFNULL(`pa`.`Approval_Code`, '') AS `Approval_Code`,
        IFNULL(`pa`.`Invoice_Number`, '') AS `Invoice_Number`,
        IFNULL(`pa`.`Acct_Number`, '') AS `Acct_Number`,
        IFNULL(`pa`.`Card_Type`, '') AS `Card_Type`,
        IFNULL(`pa`.`AVS`, '') AS `AVS`,
        IFNULL(`pa`.`CVV`, '') AS `CVV`,
        IFNULL(`pa`.`Last_Updated`, '') AS `Last_Updated`,
        `p`.`idPayor` AS `idPayor`,
        IFNULL(`gt`.`CardHolderName`, '') AS `CardHolderName`
    FROM
        ((`payment` `p`
        LEFT JOIN `payment_auth` `pa` ON ((`p`.`idPayment` = `pa`.`idPayment`)))
        LEFT JOIN `guest_token` `gt` ON ((`p`.`idToken` = `gt`.`idGuest_token`)))
    WHERE
        ((`p`.`idPayment_Method` = 2)
            AND (`p`.`Status_Code` = 's'));



-- -----------------------------------------------------
-- View `vcron_log`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vcron_log` AS
	SELECT
		`l`.`idLog`,
		`l`.`idJob`,
        `c`.`Title` as "Job",
        `l`.`Log_Text`,
        `l`.`Status`,
        `l`.`timestamp`
	FROM
		`cron_log` l
	LEFT JOIN `cronjobs` `c` ON `l`.`idJob` = `c`.`idJob`;


-- -----------------------------------------------------
-- View `vcurrent_residents`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vcurrent_residents` AS
    SELECT
        `s`.`idName` AS `Id`,
        CONCAT((CASE
                    WHEN (IFNULL(`m`.`Name_Nickname`, '') = '') THEN IFNULL(`m`.`Name_First`, '')
                    ELSE IFNULL(`m`.`Name_Nickname`, '')
                END),
                ' ',
                IFNULL((CASE
                            WHEN (`m`.`Name_Suffix` = '') THEN `m`.`Name_Last`
                            ELSE CONCAT(`m`.`Name_Last`, ', ', IFNULL(g.Description, ''))
                        END),
                        '')) AS `Guest`,
        (CASE WHEN (IFNULL(`m`.`Name_Nickname`, '') = '') THEN IFNULL(`m`.`Name_First`, '')
              ELSE IFNULL(`m`.`Name_Nickname`, '')
              END) as `Guest First`,
        (CASE WHEN (`m`.`Name_Suffix` = '') THEN `m`.`Name_Last`
              ELSE CONCAT(`m`.`Name_Last`, ', ', IFNULL(g.Description, ''))
              END) as `Guest Last`,
		(case when (`m`.`Preferred_Phone` = 'no') then 'No Phone' else  IFNULL(`np`.`Phone_Num`, '') END) AS `Phone`,
        (CASE
            WHEN (`v`.`Ext_Phone_Installed` = 1) THEN 'Y'
            ELSE ''
        END) AS `Use House Phone`,
        IFNULL(`r`.`Phone`,'') AS `Room Phone`,
        IFNULL(`s`.`Status`, '') AS `Stay_Status`,
        IFNULL(`s`.`On_Leave`, 0) AS `On_Leave`,
        IFNULL(`s`.`Checkin_Date`, '') AS `Checked-In`,
        IFNULL(`s`.`Expected_Co_Date`, '') AS `Expected Depart`,
        IFNULL(`s`.`Span_Start_Date`, '') AS `Span_Start_Date`,
        IFNULL(`r`.`Title`, '') AS `Room`,
        IFNULL(`r`.`Status`, '') AS `Room_Status`,
        IFNULL(`r`.`idRoom`, 0) AS `RoomId`,
        IFNULL(`r`.`Cleaning_Cycle_Code`, '') AS `Cleaning_Cycle_Code`,
        `mp`.`Name_Last_First` as `Patient`,
		`mp`.`idName` as `PatientId`,
        'hhk-curres' AS `Action`,
        IFNULL(`re`.`Background_Color`, 'white') AS `backColor`,
        IFNULL(`re`.`Text_Color`, 'black') AS `textColor`,
        `v`.`idVisit` AS `idVisit`,
        `v`.`Span` AS `Span`,
        `v`.`idRegistration` AS `idRegistration`,
        `v`.`idReservation` AS `idReservation`,
        `v`.`idRoom_Rate` AS `idRoom_rate`,
        `v`.`Pledged_Rate` AS `Pledged_Rate`,
        `v`.`Expected_Rate` AS `Expected_Rate`,
        `v`.`Rate_Category` AS `Rate_Category`,
        `v`.`Amount_Per_Guest` AS `Amount_Per_Guest`,
        IFNULL(`hs`.`idPsg`, 0) AS `idPsg`,
        IFNULL(`hs`.`idAssociation`, 0) AS `idAssociation`,
        IFNULL(`hs`.`idHospital`, 0) AS `idHospital`,
        IFNULL(`m`.`Gender`, "") AS `Gender`
    FROM
        `stays` `s`
        LEFT JOIN `visit` `v` ON `s`.`idVisit` = `v`.`idVisit`
            AND `s`.`Visit_Span` = `v`.`Span`
        LEFT JOIN `name` `m` ON `s`.`idName` = `m`.`idName`
        LEFT JOIN `name_phone` `np` ON `np`.`idName` = `m`.`idName` and `np`.`Phone_Code` = `m`.`Preferred_Phone`
        LEFT JOIN `room` `r` ON `s`.`idRoom` = `r`.`idRoom`
        LEFT JOIN `hospital_stay` `hs` ON `v`.`idHospital_stay` = `hs`.`idHospital_stay`
        LEFT JOIN `name` `mp` ON `hs`.`idPatient` = `mp`.`idName`
        LEFT JOIN `resource` `re` ON `re`.`idResource` = `v`.`idResource`
        LEFT JOIN `gen_lookups` `g` on `g`.`Table_Name` = 'Name_Suffix' and `g`.`Code` = `m`.`Name_Suffix`
    WHERE
        (`s`.`Status` = 'a')
    ORDER BY `v`.`idVisit`;


-- -----------------------------------------------------
-- View `v_docs`
-- -----------------------------------------------------

CREATE or replace VIEW `v_docs`
AS SELECT distinct
   `d`.`Timestamp` AS `Timestamp`,
   `d`.`Created_By` AS `Created_By`,
   `d`.`Title` AS `Title`,
   `d`.`idDocument` AS `Doc_Id`,
   `d`.`idDocument` AS `Action`,
   `d`.`idDocument` AS `ViewDoc`,
   ifnull(`n`.`Name_Full`, '') AS `Guest`,
   `ld`.`idGuest` AS `Guest_Id`,
   `ld`.`idPSG` AS `PSG_Id`
FROM ((`link_doc` `ld` join `document` `d` on((`ld`.`idDocument` = `d`.`idDocument`))) left join `name` `n` on((`ld`.`idGuest` = `n`.`idName`))) where (`d`.`Status` not in ('d'));

-- -----------------------------------------------------
-- View `v_signed_reg_forms`
-- -----------------------------------------------------

CREATE OR REPLACE VIEW `v_signed_reg_forms` AS
    SELECT 
        `d`.`idDocument` AS `Doc_Id`,
        `d`.`Mime_Type` AS `Mime_Type`,
        `d`.`Doc` AS `Doc`,
        `d`.`userData` AS `Signatures`,
        JSON_VALUE(`d`.`Abstract`, '$.idResv') AS `Resv_Id`,
        JSON_VALUE(`d`.`Abstract`, '$.idVisit') AS `Visit_Id`,
        `ld`.`idGuest` AS `Guest_Id`,
        `ld`.`idPSG` AS `PSG_Id`,
        `d`.`Timestamp` AS `timestamp`
    FROM
        `document` `d`
        JOIN `link_doc` `ld` on `d`.`idDocument` = `ld`.`idDocument`
        LEFT JOIN `name` `n` ON `ld`.`idGuest` = `n`.`idName`
    WHERE
        `d`.`Status` <> 'd'
            AND `d`.`Type` = 'reg'
            AND `d`.`Abstract` <> ''
  ORDER BY `d`.`idDocument` DESC;


-- -----------------------------------------------------
-- View `vdaily_waitlist`
-- -----------------------------------------------------

CREATE or replace VIEW `vdaily_waitlist` AS
select
	`r`.`idReservation`,
    `hs`.`MRN`,
    `p`.`Name_First` as "Patient First",
    `p`.`Name_Last` as "Patient Last",
    `r`.`Expected_Arrival`,
    `r`.`Expected_Departure`,
    `hs`.`Room` as "Room Number",
    `ra`.`Name_Full` as "Referral Agent",
    `e`.`Description` as "Ethnicity",
    `ib`.`Description` as "Income_Bracket",
    `ab`.`Description` as "Age_Bracket",
    `r`.`Checkin_Notes` as "Waitlist Notes"
from reservation r
	join `hospital_stay` hs on `r`.`idHospital_Stay` = `hs`.`idHospital_stay`
    join `name` p on `hs`.`idPatient` = `p`.`idName`
    left join `name_demog` d on `p`.`idName` = `d`.`idName`
    left join `gen_lookups` e on `d`.`Ethnicity` = `e`.`Code` and `e`.`Table_Name` = "Ethnicity"
    left join `gen_lookups` ib on `d`.`Income_Bracket` = `ib`.`Code` and `ib`.`Table_Name` = "Income_Bracket"
    left join `gen_lookups` ab on `d`.`Age_Bracket` = `ab`.`Code` and `ab`.`Table_Name` = "Age_Bracket"
    left join `name` ra on `hs`.`idReferralAgent` = `ra`.`idName`
where `r`.`Status` = 'w' and `hs`.`idHospital` not in (2);

-- -----------------------------------------------------
-- View `vdonation_view`
-- -----------------------------------------------------
CREATE or replace VIEW `vdonation_view` AS
select `d`.`iddonations` AS `iddonations`,
`d`.`Donor_Id` AS `Donor_Id`,
`d`.`Amount` AS `Amount`,
ifnull(`c`.`Title`,'') AS `Campaign_Code`,
`d`.`Date_Entered` AS `Date_Entered`,
`d`.`Member_type` AS `Member_Type`,
`d`.`Care_Of_Id` AS `Care_Of_Id`,
`d`.`Assoc_Id` AS `Assoc_Id`,
`d`.`Date_Acknowledged` AS `Date_Acknowledged`,
`d`.`Note` as `Note`,
case when `n`.`Record_Member` = 1 then '1' else '0' end AS `Record_Member`,
ifnull(`ns`.`Name_Last`,'') AS `Name_Last`,
ifnull(`ns`.`Name_First`,'') AS `Name_First`,
case when n.Record_Member = 1 then n.Name_Full else n.Company end as Donor_Name,
ifnull(c.Campaign_Type, '') as Campaign_Type,
d.Fund_Code
from (((`donations` `d` left join `campaign` `c` on((lcase(trim(`d`.`Campaign_Code`)) = lcase(trim(`c`.`Campaign_Code`)))))
left join `name` `n` on((`d`.`Donor_Id` = `n`.`idName`)))
left join `name` `ns` on((`d`.`Care_Of_Id` = `ns`.`idName`)))
where (`d`.`Status` = 'a');



-- -----------------------------------------------------
-- View `vdump_address`
-- -----------------------------------------------------
CREATE or replace VIEW `vdump_address` AS
select `a`.`idName` AS `Id`,
`g`.`Description` AS `Purpose`,
`a`.`Address_1` AS `Address 1`,
`a`.`Address_2` AS `Address 2`,
`a`.`City` AS `City`,
`a`.`State_Province` AS `State`,
`a`.`Postal_Code` AS `Zip`,
`a`.`Country` AS `Country`,
`a`.`Status` AS `Status`,
`a`.`Bad_Address` AS `Bad Address`,
`a`.`Last_Updated` AS `Last_Updated`,
`a`.`Timestamp` AS `Created On`
from (`name_address` `a` left join `gen_lookups` `g` on(((`a`.`Purpose` = `g`.`Code`) and (`g`.`Table_Name` = 'Address_Purpose'))))
where ((`a`.`City` <> '') or (`a`.`Address_1` <> '')) order by `a`.`idName`, a.Purpose;



-- -----------------------------------------------------
-- View `vdump_badaddress`
-- -----------------------------------------------------
CREATE or replace VIEW `vdump_badaddress` AS
select
    n.idName AS Id,
    (case
        when (n.Record_Company = 0) then n.Name_Last
        else n.Company
    end) AS `Last Name`,
    n.Name_First AS First,
    g.Description AS `Member Status`,
    ga.Description AS Purpose,
    (case
        when (n.Preferred_Mail_Address = na.Purpose) then 'Prefd'
        else ''
    end) AS Pref,
    (case
        when (na.Bad_Address <> '') then 'Bad Addr'
        else ''
    end) AS `Bad Addr`,
    (case
        when
            (ifnull(na.Address_2, '') <> '')
        then
            concat(ifnull(na.Address_1, ''),
                    ', ',
                    ifnull(na.Address_2, ''))
        else ifnull(na.Address_1, '')
    end) AS `Street Address`,
    ifnull(na.City, '') AS City,
    ifnull(na.State_Province, '') AS State,
    ifnull(na.Postal_Code, '') AS Zip,
    n.Member_Status AS `Member|Status`
from
    (((name_address na
    left join name n ON ((n.idName = na.idName)))
    left join gen_lookups g ON (((n.Member_Status = g.Code) and (g.Table_Name = 'mem_status'))))
    left join gen_lookups ga ON (((na.Purpose = ga.Code) and (ga.Table_Name = 'Address_Purpose'))))
where
    ((n.idName > 0)
        and (n.Member_Status in ('a','d', 'in'))
        and (
            (na.Bad_Address <> '')
            or (trim(na.Address_1) = '')
            or (trim(na.City) = '')
            or (trim(na.State_Province) = '')
            or (length(na.State_Province) > 2)
            or (length(trim(na.Postal_Code)) < 5)
        )
    )
order by (case
    when (n.Record_Company = 0) then n.Name_Last
    else n.Company
end);



-- -----------------------------------------------------
-- View `vdump_campaigns`
-- -----------------------------------------------------
CREATE  OR REPLACE VIEW `vdump_campaigns` AS
select c.Title,
c.Description,
c.Campaign_Code as `Code`,
gstat.Description as `Status`,
c.Start_Date as `Start`,
c.End_Date as `End`,
case when c.Target = 0 then '' else c.Target end as `Target`,
case when c.Min_Donation = 0 then '' else c.Min_Donation end as `Min`,
case when c.Max_Donation = 0 then '' else c.Max_Donation end as `Max`,
gtype.Description as `Type`,
case when c.Campaign_Type = 'pct' then c.Percent_Cut else '' end as `% Cut`,
c.Campaign_Merge_Code as `Merge Code`,
c.Category,
c.Updated_By as `Updated By`,
c.Last_Updated as `Last Updated`,
c.Timestamp
from campaign c left join gen_lookups gstat on gstat.Table_Name = 'Campaign_Status' and c.Status = gstat.Code
left join gen_lookups gtype on gtype.Table_Name = 'Campaign_Type' and gtype.Code = c.Campaign_Type;




-- -----------------------------------------------------
-- View `vdump_donations`
-- -----------------------------------------------------
CREATE or replace VIEW `vdump_donations` AS
select `d`.`Donor_Id` AS `Id`,
`d`.`Care_Of_Id` AS `Sponsor Id`,
`d`.`Assoc_Id` AS `Assoc Id`,
`d`.`Date_Entered` AS `Date`,
`d`.`Amount` AS `Amount`,
ifnull(`gpt`.`Description`,'') AS `Pay Type`,
ifnull(`gcod`.`Description`,'') AS `Salutation Code`,
ifnull(`genv`.`Description`,'') AS `Envelope Code`,
ifnull(c.Title, '') as Campaign,
`d`.`Updated_By` AS `Updated By`,
`d`.`Last_Updated` AS `Last_Updated`,
`d`.`Timestamp` AS `Created On`
from `donations` `d` left join `gen_lookups` `gpt` on `d`.`Pay_Type` = `gpt`.`Code` and `gpt`.`Table_Name` = 'Pay_Type'
left join `gen_lookups` `gcod` on `d`.`Salutation_Code` = `gcod`.`Code` and `gcod`.`Table_Name` = 'salutation'
left join `gen_lookups` `genv` on `d`.`Envelope_Code` = `genv`.`Code` and `genv`.`Table_Name` = 'salutation'
left join campaign c on d.Campaign_Code = c.Campaign_Code
where d.Status = 'a'
order by `d`.`Donor_Id`;


-- -----------------------------------------------------
-- View `vdump_email`
-- -----------------------------------------------------
CREATE or replace VIEW `vdump_email` AS
select `e`.`idName` AS `Id`,
`gc`.`Description` AS `Purpose`,
`e`.`Email` AS `Email`,
`e`.`Last_Updated` AS `Last_Updated`,
`e`.`Updated_By` AS `Updated By`,
`e`.`Timestamp` AS `Created On`
from (`name_email` `e` left join `gen_lookups` `gc` on(((`e`.`Purpose` = `gc`.`Code`) and (`gc`.`Table_Name` = 'Email_Purpose'))))
where (`e`.`idName` > 0) order by `e`.`idName`;



-- -----------------------------------------------------
-- View `vdump_name`
-- -----------------------------------------------------
CREATE or replace VIEW `vdump_name` AS
select `n`.`idName` AS `Id`,
`gt`.`Description` AS `Member Basis`,
(case when (`n`.`Record_Member` = 1) then `n`.`Name_Full` else `n`.`Company` end) AS `Name / Organization`,
`n`.`Name_Nickname` AS `Nickname`,
`n`.`Name_Previous` AS `Previous Name`,
(case when (`n`.`Record_Member` = 1) then `n`.`Company` else '' end) AS `Company`,
n.Company_CareOf as `C/O`,
`n`.`Title` AS `Title`,
`n`.`Web_Site` AS `Web Site`,
`gs`.`Description` AS `Member Status`,
ifnull(`gpm`.`Description`,'') AS `Preferred Address`,
ifnull(`gpe`.`Description`,'') AS `Preferred Email`,
ifnull(`gpp`.`Description`,'') AS `Preferred Phone`,
(case when (`n`.`Exclude_Directory` = 1) then 'x' else '' end) AS `Excl Directory`,
(case when (`n`.`Exclude_Mail` = 1) then 'x' else '' end) AS `Excl Address`,
(case when (`n`.`Exclude_Email` = 1) then 'x' else '' end) AS `Excl Email`,
(case when (`n`.`Exclude_Phone` = 1) then 'x' else '' end) AS `Excl Phone`,
ifnull(`gg`.`Description`,'') AS `Gender`,
(case when (`n`.`Birth_Month` = 0) then '' else `n`.`Birth_Month` end) AS `Birth Month`,
`n`.`Updated_By` AS `Updated By`,
`n`.`Last_Updated` AS `Last_Updated`,
`n`.`Timestamp` AS `Created On`
from ((((((`name` `n` left join `gen_lookups` `gt` on(((`n`.`Member_Type` = `gt`.`Code`) and (`gt`.`Table_Name` = 'Member_Basis'))))
left join `gen_lookups` `gs` on(((`n`.`Member_Status` = `gs`.`Code`) and (`gs`.`Table_Name` = 'mem_status'))))
left join `gen_lookups` `gpm` on(((`n`.`Preferred_Mail_Address` = `gpm`.`Code`) and (`gpm`.`Table_Name` = 'Address_Purpose'))))
left join `gen_lookups` `gpe` on(((`n`.`Preferred_Email` = `gpe`.`Code`) and (`gpe`.`Table_Name` = 'Email_Purpose'))))
left join `gen_lookups` `gpp` on(((`n`.`Preferred_Phone` = `gpp`.`Code`) and (`gpp`.`Table_Name` = 'Phone_Type'))))
left join `gen_lookups` `gg` on(((`n`.`Gender` = `gg`.`Code`) and (`gg`.`Table_Name` = 'Gender'))))
where (`n`.`idName` > 0);


-- -----------------------------------------------------
-- View `vdump_phone`
-- -----------------------------------------------------
CREATE or replace VIEW `vdump_phone` AS
select `p`.`idName` AS `Id`,
`gc`.`Description` AS `Purpose`,
CASE WHEN `p`.`Phone_Code` = 'no' then 'No Phone' else `p`.`Phone_Num` END AS `Number`,
`p`.`Phone_Extension` AS `Extn`,
(case when (`p`.`Status` = 'a') then 'Active' else '' end) AS `Status`,
`p`.`Last_Updated` AS `Last_Updated`,
`p`.`Updated_By` AS `Updated By`,
`p`.`Timestamp` AS `Created On`
from (`name_phone` `p` left join `gen_lookups` `gc` on(((`p`.`Phone_Code` = `gc`.`Code`) and (`gc`.`Table_Name` = 'Phone_Type'))))
where (`p`.`idName` > 0) order by `p`.`idName`;




-- -----------------------------------------------------
-- View `vdump_volunteer`
-- -----------------------------------------------------
-- CREATE or replace VIEW `vdump_volunteer` AS
-- select `v`.`idName` AS `Id`,
-- `g`.`Description` AS `Category`,
-- `g2`.`Description` AS `Code`,
-- `v`.`Vol_Status` AS `Status`,
-- ifnull(`v`.`Vol_Notes`,'') AS `Notes`,
-- ifnull(`v`.`Vol_Begin`,'') AS `Begin`,
-- ifnull(`v`.`Vol_End`,'') AS `End`,
-- ifnull(`v`.`Vol_Check_Date`,'') AS `Check Date`,
-- ifnull(`gdor`.`Description`,'') AS `Dormancy`,
-- ifnull(`g3`.`Description`,'') AS `Rank`,
-- `v`.`Updated_By` AS `Updated By`,
-- `v`.`Last_Updated` AS `Last_Updated`,
-- `v`.`Timestamp` AS `Created on`
-- from ((((`name_volunteer2` `v` left join `gen_lookups` `g` on(((`v`.`Vol_Category` = `g`.`Code`) and (`g`.`Table_Name` = 'Vol_Category'))))
-- left join `gen_lookups` `g2` on(((`v`.`Vol_Code` = `g2`.`Code`) and (`g2`.`Table_Name` = `v`.`Vol_Category`))))
-- left join `gen_lookups` `g3` on(((`g3`.`Code` = `v`.`Vol_Rank`) and (`g3`.`Table_Name` = 'Vol_Rank'))))
-- left join `dormant_schedules` `gdor` on((`gdor`.`Code` = `v`.`Dormant_Code`)))
-- where (`v`.`idName` > 0) order by `v`.`idName`,`g`.`Description`,`g2`.`Description`;




-- -----------------------------------------------------
-- View `vdump_webuser`
-- -----------------------------------------------------
CREATE or replace VIEW `vdump_webuser` AS
select `u`.`idName` AS `Id`,
`u`.`User_Name` AS `Username`,
`gwr`.`Description` AS `Web Role`,
`gws`.`Description` AS `Web Status`,
`u`.`Verify_Address` AS `Verify Address`,
(case when (`a`.`Timestamp` > `u`.`Timestamp`) then `a`.`Timestamp` else `u`.`Timestamp` end) AS `Created On`,
(case when (`a`.`Last_Updated` > `u`.`Last_Updated`) then `a`.`Last_Updated` else `u`.`Last_Updated` end) AS `Last_Updated`
from (((`w_users` `u` join `w_auth` `a` on((`u`.`idName` = `a`.`idName`)))
left join `gen_lookups` `gws` on(((`u`.`Status` = `gws`.`Code`) and (`gws`.`Table_Name` = 'Web_User_Status'))))
left join `gen_lookups` `gwr` on(((`a`.`Role_Id` = `gwr`.`Code`) and (`gwr`.`Table_Name` = 'Role_Codes'))))
where (`u`.`idName` > 0);



-- -----------------------------------------------------
-- View `vemail_directory`
-- -----------------------------------------------------
CREATE or replace VIEW `vemail_directory` AS
    select
        n.idName, `ne`.`Email` AS `Email`, `n`.`Member_Type` AS `Member_Type`,
		case when n.Record_Member = 1 then concat(n.Name_First, ' ',n.Name_Last) else n.Company end as `Name`
    from
        (`name_email` `ne`
        join `name` `n` ON (((`ne`.`idName` = `n`.`idName`)
            and (`n`.`Preferred_Email` = `ne`.`Purpose`))))
    where
        ((`n`.`Member_Status` = 'a')
            and (`n`.`Exclude_Email` = 0)
            and (`ne`.`Email` <> ''));


-- -----------------------------------------------------
-- View `vfind_guests`
-- -----------------------------------------------------
CREATE  OR REPLACE VIEW `vfind_guests` AS
    SELECT
        `hs`.`idPsg` AS `idPsg`,
        `rg`.`idGuest` AS `idGuest`,
        `rg`.`idReservation` AS `idReservation`,
        IFNULL(`r`.`Actual_Arrival`, `r`.`Expected_Arrival`) AS `Arrival_Date`,
        IFNULL(`r`.`Actual_Departure`, `r`.`Expected_Departure`) AS `Departure_Date`,
        `r`.`Status` AS `Status`,
        `r`.`idResource` AS `idResource`,
        'r' AS `Source`
    FROM
        ((`reservation_guest` `rg`
        LEFT JOIN `reservation` `r` ON (`rg`.`idReservation` = `r`.`idReservation`))
        LEFT JOIN `hospital_stay` `hs` ON (`r`.`idHospital_Stay` = `hs`.`idHospital_stay`))
    WHERE
        `r`.`Status` IN ('a' , 'uc', 'w', 's', 'co')
    UNION SELECT
        `hs`.`idPsg` AS `idPsg`,
        `s`.`idName` AS `idGuest`,
        `v`.`idReservation` AS `idReservation`,
        `s`.`Span_Start_Date` AS `Arrival_Date`,
        IFNULL(`s`.`Span_End_Date`, DATEDEFAULTNOW(`s`.`Expected_Co_Date`)) AS `Departure_Date`,
        's' AS `Status`,
        `v`.`idResource` AS `idResource`,
        'v' AS `Source`
    FROM
        ((`stays` `s`
        LEFT JOIN `visit` `v` ON (`s`.`idVisit` = `v`.`idVisit`
            AND `s`.`Visit_Span` = `v`.`Span`))
        LEFT JOIN `hospital_stay` `hs` ON (`v`.`idHospital_stay` = `hs`.`idHospital_stay`))
    WHERE
        `s`.`Status` IN ('a' , 'co', 'n', 'cp', '1');



-- -----------------------------------------------------
-- View `vform_listing`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vform_listing` AS
    SELECT
        `d`.`idDocument` AS `idDocument`,
        IFNULL(JSON_VALUE(`d`.`userData`, '$.patient.firstName'), '') AS `patientFirstName`,
        IFNULL(JSON_VALUE(`d`.`userData`, '$.patient.lastName'), '') AS `patientLastName`,
        IFNULL(JSON_VALUE(`d`.`userData`, '$.checkindate'), '') AS `ExpectedCheckin`,
        IFNULL(JSON_VALUE(`d`.`userData`, '$.checkoutdate'), '') AS `ExpectedCheckout`,
        IFNULL(`h`.`Title`, '') as `hospitalName`,
        `d`.`Status` AS `status ID`,
        `g`.`Description` AS `status`,
        `r`.`idReservation` AS `idResv`,
        `r`.`Status` AS `resvStatus`,
        `rs`.`Title` AS `resvStatusName`,
        `d`.`Timestamp` AS `Timestamp`,
        `d`.`Title` AS `FormTitle`,
        IFNULL(JSON_VALUE(`d`.`abstract`, '$.enableReservation'), 'true') AS `enableReservation`
    FROM
        (`document` `d`
        LEFT JOIN `gen_lookups` `g` ON (`d`.`Status` = `g`.`Code`
            AND `g`.`Table_Name` = 'Referral_Form_Status')
		LEFT JOIN `hospital` `h` ON (JSON_VALUE(`d`.`userData`, '$.hospital.idHospital') = `h`.`idHospital`)
        LEFT JOIN `reservation` `r` ON (`d`.`idDocument` = `r`.`idReferralDoc`)
        LEFT JOIN `lookups` `rs` ON (`r`.`Status` = `rs`.`Code` AND `rs`.`Category` = 'ReservStatus'))
    WHERE
        `d`.`Type` = 'json'
            AND `d`.`Category` = 'form'
	ORDER BY `d`.`Timestamp` DESC , `r`.`idReservation` DESC;

-- -----------------------------------------------------
-- View `vgetIncidentlisting`
-- -----------------------------------------------------
CREATE or replace VIEW `vgetIncidentlisting`
AS SELECT
   `r`.`idReport` AS `idReport`,
   `r`.`idReport` AS `Action`,
   `n`.`Name_Full` AS `Guest`,
   `r`.`Title` AS `Title`,
   `r`.`Report_Date` AS `Date`,
   `r`.`Description` AS `Description`,
   `r`.`Author` AS `Author`,(case `r`.`Status` when 'a' then 'Active' when 'r' then 'Resolved' when 'h' then 'On Hold' end) AS `Status`,
   `r`.`Timestamp` AS `Timestamp`,
   `r`.`Guest_Id` AS `Guest_Id`,
   `r`.`Psg_Id` AS `Psg_Id`
FROM
        ((`report` `r`
        LEFT JOIN `name_guest` `ng` ON (`r`.`Guest_Id` = `ng`.`idName`
            AND `r`.`Psg_Id` = `ng`.`idPsg`))
        LEFT JOIN `name` `n` ON (`ng`.`idName` = `n`.`idName`))
    WHERE
        `r`.`idReport` > 0
            AND `r`.`Status` IN ('a' , 'r', 'h');



-- -----------------------------------------------------
-- View `vguest_audit_log`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vguest_audit_log` AS
    SELECT
        `Timestamp` AS `LogDate`,
        `Log_Type` AS `LogType`,
        `Sub_Type` AS `Subtype`,
        `WP_User_Id` AS `User`,
        `idName`,
        `Log_Text` AS `LogText`
    FROM
        `name_log`
    UNION ALL SELECT
        `Timestamp` AS `LogDate`,
        `Log_Type` AS `LogType`,
        `Sub_Type` AS `Subtype`,
        `User_Name` AS `User`,
        `idName`,
        `Log_Text` AS `LogText`
    FROM
        `visit_log`
    WHERE
        `Log_Type` = 'nameguest' and idName > 0;




-- -----------------------------------------------------
-- View `vguest_export`
-- -----------------------------------------------------
create or replace view `vguest_export` as
Select
    ng.idName as `Guest_Id`,
    ifnull(rv.idReservation, '') as `Reservation_Id`,
    ifnull(l.`Title`, '') as `Status_Title`,
    ifnull(l.`Code`, '') as `Status_Code`,
    ifnull(n.Name_First, '') as `First_Name`,
    ifnull(n.Name_Last, '') as `Last_Name`,
    ifnull(g.Description, '') as `Gender`,
    'SSR' as `Building`,
    ifnull(re.Title, '') as `Room`,
    ifnull(rv.Expected_Arrival, '') as `Expected_Arrival`,
    ifnull(rv.Expected_Departure, '') as `Expected_Departure`,
    ifnull(rv.Actual_Arrival, '') as `Actual_Arrival`,
    IFNULL(rv.Actual_Departure, '') as `Actual_Departure`,
    case when (`n`.`Preferred_Email` = 'no') then 'No Email' else ifnull(`ne`.`Email`,'') end as `Email`,
    case when (`n`.`Preferred_Phone` = 'no') then 'No Phone' else ifnull(`np`.`Phone_Num`, '') end as `Phone`
from
    name_guest ng
            left join
    registration rg on ng.idPsg = rg.idPsg
            left join
    reservation rv on rg.idRegistration = rv.idRegistration
            left join
    `name` n on ng.idName = n.idName
            left join
    name_email ne on ng.idName = ne.idName and n.Preferred_Email = ne.Purpose
            left join
    name_phone np on ng.idName = np.idName and n.Preferred_Phone = np.Phone_Code
            left join
    gen_lookups g on g.`Table_Name` = 'Gender' and g.`Code` = n.Gender
            left join
    resource re on rv.idResource = re.idResource
            left join
    lookups l on l.Category = 'ReservStatus' and l.`Code` = rv.`Status`;



-- -----------------------------------------------------
-- View `vguest_history_records`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vguest_history_records` AS
    SELECT
        `m`.`Id` AS `Id`,
        (CASE
            WHEN (`m`.`MemberRecord` = 1) THEN `m`.`Fullname`
            ELSE `m`.`Company`
        END) AS `Fullname`,
        `m`.`Preferred_Phone` AS `Preferred_Phone`,
        `m`.`Preferred_Email` AS `Preferred_Email`,
        (CASE
            WHEN (`m`.`Bad_Address` = 'true') THEN '*(Bad Address)*'
            ELSE `m`.`Address_1`
        END) AS `Address_1`,
        (CASE
            WHEN (`m`.`Bad_Address` = 'true') THEN ''
            ELSE `m`.`Address_2`
        END) AS `Address_2`,
        (CASE
            WHEN (`m`.`Bad_Address` = 'true') THEN ''
            ELSE `m`.`City`
        END) AS `City`,
        (CASE
            WHEN (`m`.`Bad_Address` = 'true') THEN ''
            ELSE `m`.`StateProvince`
        END) AS `StateProvince`,
        (CASE
            WHEN (`m`.`Bad_Address` = 'true') THEN ''
            ELSE `m`.`Country_Code`
        END) AS `Country_Code`,
        (CASE
            WHEN (`m`.`Bad_Address` = 'true') THEN ''
            ELSE `m`.`PostalCode`
        END) AS `PostalCode`,
        (CASE
            WHEN (`m`.`MemberRecord` = 1) THEN `m`.`Company`
            ELSE ''
        END) AS `Company`
    FROM
        (`member_history` `g`
        JOIN `vmember_listing` `m` ON ((`g`.`idName` = `m`.`Id`)))
    ORDER BY `g`.`Guest_Access_Date` DESC
    LIMIT 12;



-- -----------------------------------------------------
-- View `vguest_listing`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vguest_listing` AS
    SELECT
        `n`.`idName` AS `Id`,
        IFNULL(`g1`.`Description`, '') AS `Prefix`,
        `n`.`Name_First` AS `First`,
        `n`.`Name_Middle` AS `Middle`,
        `n`.`Name_Last` AS `Last`,
        IFNULL(`g2`.`Description`, '') AS `Suffix`,
        (CASE WHEN n.Preferred_Phone = 'no' THEN 'No Phone'
            WHEN (IFNULL(`np`.`Phone_Extension`, '') = '') THEN IFNULL(`np`.`Phone_Num`, '')
            ELSE CONCAT_WS('x',
                    `np`.`Phone_Num`,
                    `np`.`Phone_Extension`)
        END) AS `Phone`,
        (case when (`n`.`Preferred_Email` = 'no') then 'No Email' else ifnull(`ne`.`Email`,'') end) AS `Email`,
        (CASE
            WHEN (IFNULL(`na`.`Address_2`, '') = '') THEN IFNULL(`na`.`Address_1`, '')
            ELSE CONCAT(IFNULL(`na`.`Address_1`, ''),
                    ', ',
                    `na`.`Address_2`)
        END) AS `Address`,
        IFNULL(`na`.`City`, '') AS `City`,
        IFNULL(`na`.`County`, '') AS `County`,
        IFNULL(`na`.`State_Province`, '') AS `State`,
        IFNULL(`na`.`Postal_Code`, '') AS `Zip`,
        IFNULL(`na`.`Country_Code`, '') AS `Country`,
        IFNULL(`n`.`BirthDate`, '') AS `BirthDate`,
        IFNULL(`g3`.`Description`, '') AS `Patient Rel.`,
        `ng`.`Relationship_Code` AS `Relationship_Code`,
        `ng`.`idPsg` AS `idPsg`,
        `nd`.`No_Return` AS `ngStatus`,
        IFNULL(`n`.`External_Id`, '') AS `External_Id`
    FROM
        ((((((((`name_guest` `ng`
        LEFT JOIN `name` `n` ON ((`ng`.`idName` = `n`.`idName`)))
        LEFT JOIN `name_address` `na` ON (((`ng`.`idName` = `na`.`idName`)
            AND (`n`.`Preferred_Mail_Address` = `na`.`Purpose`))))
        LEFT JOIN `name_email` `ne` ON (((`ng`.`idName` = `ne`.`idName`)
            AND (`n`.`Preferred_Email` = `ne`.`Purpose`))))
        LEFT JOIN `name_phone` `np` ON (((`ng`.`idName` = `np`.`idName`)
            AND (`n`.`Preferred_Phone` = `np`.`Phone_Code`))))
        LEFT JOIN `name_demog` `nd` ON ((`ng`.`idName` = `nd`.`idName`)))
        LEFT JOIN `gen_lookups` `g1` ON (((`n`.`Name_Prefix` = `g1`.`Code`)
            AND (`g1`.`Table_Name` = 'Name_Prefix'))))
        LEFT JOIN `gen_lookups` `g2` ON (((`n`.`Name_Suffix` = `g2`.`Code`)
            AND (`g2`.`Table_Name` = 'Name_Suffix'))))
        LEFT JOIN `gen_lookups` `g3` ON (((`g3`.`Table_Name` = 'Patient_Rel_Type')
            AND (`g3`.`Code` = `ng`.`Relationship_Code`))))
    WHERE
        ((`ng`.`idName` > 0)
            AND (`n`.`Record_Member` = 1)
            AND (`n`.`Member_Status` IN ('a' , 'd', 'in')));


-- -----------------------------------------------------
-- View `vguest_search_sf`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vguest_search_sf` AS
    SELECT
        `n`.`idName` AS `HHK_idName__c`,
        `n`.`External_Id` AS `Id`,
        `n`.`Name_First` AS `FirstName`,
        `n`.`Name_Middle` AS `Middle_Name__c`,
        `n`.`Name_Last` AS `LastName`,
        IFNULL(`g2`.`Description`, '') AS `Suffix__c`,
        n.Name_Nickname AS `Nickname__c`,
        IFNULL(DATE_FORMAT(`n`.`BirthDate`, '%Y-%m-%d'),
                '') AS `Birthdate`,
        IFNULL(`ne`.`Email`, '') AS `Email`,
        IFNULL(np.Phone_Num, '') AS `HomePhone`,
        TRIM(CONCAT_WS(" ", IFNULL(na.Address_1, ''), IFNULL(na.Address_2, ''))) as `MailingStreet`,
        IFNULL(`na`.`City`, '') AS `MailingCity`,
        IFNULL(`na`.`State_Province`, '') AS `MailingState`,
        IFNULL(`na`.`Postal_Code`, '') AS `MailingPostalCode`,
        IFNULL(`na`.`Last_Updated`, '') AS `Addr_Updated`,
        IFNULL(`na`.`Last_Verified`, '') AS `Addr_Verified`
    FROM
        `name` `n`
        LEFT JOIN `name_address` `na` ON (`n`.`idName` = `na`.`idName`
            AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`)
        LEFT JOIN `name_email` `ne` ON (`n`.`idName` = `ne`.`idName`
            AND `n`.`Preferred_Email` = `ne`.`Purpose`)
        LEFT JOIN `name_phone` `np` ON (`n`.`idName` = `np`.`idName`
            AND `n`.`Preferred_Phone` = `np`.`Phone_Code`)
        LEFT JOIN `gen_lookups` `g2` ON (`n`.`Name_Suffix` = `g2`.`Code`
            AND `g2`.`Table_Name` = 'Name_Suffix')
    WHERE
        `n`.`idName` > 0
            AND `n`.`idName` IN (SELECT
                `name_guest`.`idName`
            FROM
                `name_guest`)
            AND `n`.`Record_Member` = 1
            AND `n`.`Member_Status` IN ('a' , 'd', 'in');


-- -----------------------------------------------------
-- View `vguest_data_sf`
-- -----------------------------------------------------
CREATE  OR REPLACE VIEW `vguest_data_sf` AS
     SELECT
        `n`.`idName` AS `HHK_idName__c`,
        `n`.`External_Id` AS `Id`,
        IFNULL(`g1`.`Description`, '') AS `Salutation`,
        `n`.`Name_First` AS `FirstName`,
        `n`.`Name_Last` AS `LastName`,
        `n`.`Name_Middle` AS `Middle_Name__c`,
        IFNULL(`g2`.`Description`, '') AS `Suffix__c`,
        `n`.`Name_Nickname` AS `Nickname__c`,
        IFNULL(`g3`.`Description`, '')  AS `Gender__c`,
        IFNULL(`ne`.`Email`, '') AS `Email`,
        IFNULL(np.Phone_Num, '') AS `HomePhone`,
        IFNULL(concat_ws(' ', na.Address_1, na.Address_2), '')  as `MailingStreet`,
        IFNULL(`na`.`City`, '') AS `MailingCity`,
        IFNULL(`na`.`State_Province`, '') AS `MailingState`,
        IFNULL(`na`.`Postal_Code`, '') AS `MailingPostalCode`,
        IFNULL(`cc`.`Country_Name`, '') AS `MailingCountry`,
        IFNULL(DATE_FORMAT(`n`.`BirthDate`, '%Y-%m-%d'), '') as `Birthdate`,
        CASE WHEN IFNULL(`ng1`.`Relationship_Code`, '') = '' THEN 'Family Member' ELSE 'Patient' END AS `Contact_Type__c`,
        CASE WHEN IFNULL(`n`.`Date_Deceased`, '') = '' THEN 'false' ELSE 'true' END AS `Deceased__c`
    FROM
        `name` `n`
        LEFT JOIN `name_address` `na` ON `n`.`idName` = `na`.`idName`
            AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`
        LEFT JOIN `name_phone` `np` ON `n`.`idName` = `np`.`idName`
            AND `n`.`Preferred_Phone` = `np`.`Phone_Code`
        LEFT JOIN `name_email` `ne` ON `n`.`idName` = `ne`.`idName`
            AND `n`.`Preferred_Email` = `ne`.`Purpose`
        LEFT JOIN `gen_lookups` `g1` ON `n`.`Name_Prefix` = `g1`.`Code`
            AND `g1`.`Table_Name` = 'Name_Prefix'
        LEFT JOIN `gen_lookups` `g2` ON `n`.`Name_Suffix` = `g2`.`Code`
            AND `g2`.`Table_Name` = 'Name_Suffix'
        LEFT JOIN `gen_lookups` `g3` ON `n`.`Gender` = `g3`.`Code`
            AND `g3`.`Table_Name` = 'Gender'
        LEFT JOIN `country_code` `cc` on `na`.`Country_Code` = `cc`.`ISO_3166-1-alpha-2`
        LEFT JOIN `name_guest` `ng1` ON `n`.`idName` = `ng1`.idName
            AND `ng1`.`Relationship_Code` = 'slf'
    WHERE
        `n`.`idName` > 0
            AND `n`.`idName` IN (SELECT
                `name_guest`.`idName`
            FROM
                `name_guest`)
            AND `n`.`Record_Member` = 1
            AND `n`.`Member_Status` IN ('a' , 'd', 'in');

-- CREATE  OR REPLACE VIEW `vguest_data_sf` AS
--     SELECT
--         `n`.`idName` AS `HHK_idName__c`,
--         `n`.`External_Id` AS `Id`,
--         IFNULL(`g1`.`Description`, '') AS `Salutation`,
--         `n`.`Name_First` AS `FirstName`,
--         `n`.`Name_Last` AS `LastName`,
--         `n`.`Name_Middle` AS `Middle_Name__c`,
--         IFNULL(`g2`.`Description`, '') AS `Suffix__c`,
--         `n`.`Name_Nickname` AS `Nickname__c`,
--         IFNULL(`g3`.`Description`, '') AS `Gender__c`,
--         IFNULL(`ne`.`Email`, '') AS `Email`,
--         IFNULL(`np`.`Phone_Num`, '') AS `HomePhone`,
--         IFNULL(CONCAT_WS(' ', `na`.`Address_1`, `na`.`Address_2`), '') AS `MailingStreet`,
--         IFNULL(`na`.`City`, '') AS `MailingCity`,
--         IFNULL(`na`.`State_Province`, '') AS `MailingState`,
--         IFNULL(`na`.`Postal_Code`, '') AS `MailingPostalCode`,
--         IFNULL(`cc`.`Country_Name`, '') AS `MailingCountry`,
--         IFNULL(DATE_FORMAT(`n`.`BirthDate`, '%Y-%m-%d'), '') AS `Birthdate`,
--         CASE
--             WHEN `n`.`Member_Status` = 'd' THEN 'true'
--             ELSE 'false'
--         END AS `Deceased__c`,
--         `ng`.`Relationship_Code`,
--         `p`.`idPatient` AS `PatientId`
--     FROM
-- 		`name_guest` ng
-- 		JOIN `name` `n` ON `n`.`idName` = `ng`.`idName`
--         LEFT JOIN `name_address` `na` ON (`n`.`idName` = `na`.`idName`
--             AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`)
--         LEFT JOIN `name_phone` `np` ON (`n`.`idName` = `np`.`idName`
--             AND `n`.`Preferred_Phone` = `np`.`Phone_Code`)
--         LEFT JOIN `name_email` `ne` ON (`n`.`idName` = `ne`.`idName`
--             AND `n`.`Preferred_Email` = `ne`.`Purpose`)
--         LEFT JOIN `gen_lookups` `g1` ON (`n`.`Name_Prefix` = `g1`.`Code`
--             AND `g1`.`Table_Name` = 'Name_Prefix')
--         LEFT JOIN `gen_lookups` `g2` ON (`n`.`Name_Suffix` = `g2`.`Code`
--             AND `g2`.`Table_Name` = 'Name_Suffix')
--         LEFT JOIN `gen_lookups` `g3` ON (`n`.`Gender` = `g3`.`Code`
--             AND `g3`.`Table_Name` = 'Gender')
--         LEFT JOIN `country_code` `cc` ON (`na`.`Country_Code` = `cc`.`ISO_3166-1-alpha-2`)
--         LEFT JOIN `psg` `p` ON `p`.`idPsg` = `ng`.`idPsg`
--     WHERE
--         `n`.`idName` > 0
-- 		AND `n`.`Record_Member` = 1
-- 		AND `n`.`Member_Status` IN ('a' , 'd', 'in');


-- -----------------------------------------------------
-- View `vguest_search_neon`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vguest_search_neon` AS
    SELECT
        `n`.`idName` AS `HHK_ID`,
        `n`.`External_Id` AS `Account Id`,
        IFNULL(`g1`.`Description`, '') AS `Prefix`,
        `n`.`Name_First` AS `First Name`,
        `n`.`Name_Middle` AS `Middle Name`,
        `n`.`Name_Last` AS `Last Name`,
        IFNULL(`g2`.`Description`, '') AS `Suffix`,
        IFNULL(`ne`.`Email`, '') AS `Email`,
        IFNULL(`na`.`City`, '') AS `City`,
        IFNULL(`na`.`Postal_Code`, '') AS `Zip Code`
    FROM
        `name` `n`
        LEFT JOIN `name_address` `na` ON `n`.`idName` = `na`.`idName`
            AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`
        LEFT JOIN `name_email` `ne` ON `n`.`idName` = `ne`.`idName`
            AND `n`.`Preferred_Email` = `ne`.`Purpose`
        LEFT JOIN `gen_lookups` `g1` ON `n`.`Name_Prefix` = `g1`.`Code`
            AND `g1`.`Table_Name` = 'Name_Prefix'
        LEFT JOIN `gen_lookups` `g2` ON `n`.`Name_Suffix` = `g2`.`Code`
            AND `g2`.`Table_Name` = 'Name_Suffix'
    WHERE
        ((`n`.`idName` > 0)
            AND `n`.`idName` IN (SELECT
                `name_guest`.`idName`
            FROM
                `name_guest`)
            AND (`n`.`Record_Member` = 1)
            AND (`n`.`Member_Status` IN ('a' , 'd', 'in')));



-- -----------------------------------------------------
-- View `vguest_data_neon`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vguest_data_neon` AS
    SELECT
        `n`.`idName` AS `HHK_ID`,
        `n`.`External_Id` AS `accountId`,
        IFNULL(`g1`.`Description`, '') AS `prefix`,
        `n`.`Name_First` AS `firstName`,
        `n`.`Name_Middle` AS `middleName`,
        `n`.`Name_Last` AS `lastName`,
        `n`.`Name_Nickname` AS `preferredName`,
        IFNULL(DATE_FORMAT(`n`.`BirthDate`, '%Y-%m-%d'),
                '') AS `dob`,
        IFNULL(DATE_FORMAT(`n`.`Date_Deceased`, '%Y-%m-%d'),
                '') AS `Deceased_Date`,
        (CASE
            WHEN (`n`.`Member_Status` = 'd') THEN 'true'
            ELSE ''
        END) AS `deceased`,
        IFNULL(`g2`.`Description`, '') AS `suffix`,
        IFNULL(`g5`.`Description`, '') AS `gender.name`,
        IFNULL(`np`.`Phone_Search`, '') AS `phone1`,
        (CASE
            WHEN (`np`.`Phone_Code` = 'mc') THEN 'Mobile'
            WHEN (`np`.`Phone_Code` = 'dh') THEN 'Home'
            WHEN (`np`.`Phone_Code` = 'gw') THEN 'Mobile'
            WHEN (IFNULL(`np`.`Phone_Code`, '') = '') THEN ''
            ELSE 'Home'
        END) AS `phone1Type`,
        (CASE
            WHEN (`na`.`Purpose` = '1') THEN 'Home'
            WHEN (`na`.`Purpose` = '2') THEN 'Business'
            WHEN (IFNULL(`na`.`Purpose`, '') = '') THEN ''
            ELSE 'Home'
        END) AS `addressType.name`,
        IFNULL(`ne`.`Email`, '') AS `email1`,
        IFNULL(`na`.`Address_1`, '') AS `addressLine1`,
        IFNULL(`na`.`Address_2`, '') AS `addressLine2`,
        IFNULL(`na`.`City`, '') AS `city`,
        IFNULL(`na`.`County`, '') AS `county`,
        (CASE
            WHEN (`cc`.`External_Id` > 2) THEN `na`.`State_Province`
            ELSE ''
        END) AS `province`,
        (CASE
            WHEN
                ((`cc`.`External_Id` = 1)
                    OR (`cc`.`External_Id` = 2))
            THEN
                `na`.`State_Province`
            ELSE ''
        END) AS `state.code`,
        IFNULL(`cc`.`External_Id`, '') AS `country.id`,
        IFNULL(`na`.`Postal_Code`, '') AS `zipCode`,
        IFNULL(`ni`.`Neon_Type_Code`, '') AS `individualType.id`,
        IFNULL(`g4`.`Description`, '') AS `No_Return`,
        'HHK' AS `source.name`
    FROM
        `name` `n`
        LEFT JOIN `name_address` `na` ON `n`.`idName` = `na`.`idName`
            AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`
        LEFT JOIN `name_email` `ne` ON `n`.`idName` = `ne`.`idName`
            AND `n`.`Preferred_Email` = `ne`.`Purpose`
        LEFT JOIN `name_phone` `np` ON `n`.`idName` = `np`.`idName`
            AND `n`.`Preferred_Phone` = `np`.`Phone_Code`
        LEFT JOIN `name_demog` `nd` ON `n`.`idName` = `nd`.`idName`
        LEFT JOIN `country_code` `cc` ON `na`.`Country_Code` = `cc`.`ISO_3166-1-alpha-2`
        LEFT JOIN `gen_lookups` `g1` ON `n`.`Name_Prefix` = `g1`.`Code`
            AND `g1`.`Table_Name` = 'Name_Prefix'
        LEFT JOIN `gen_lookups` `g2` ON `n`.`Name_Suffix` = `g2`.`Code`
            AND `g2`.`Table_Name` = 'Name_Suffix'
        LEFT JOIN `gen_lookups` `g4` ON `nd`.`No_Return` = `g4`.`Code`
            AND `g4`.`Table_Name` = 'NoReturnReason'
        LEFT JOIN `gen_lookups` `g5` ON `n`.`Gender` = `g5`.`Code`
            AND `n`.`Gender` IN ('m' , 'f')
            AND `g5`.`Table_Name` = 'Gender'
        LEFT JOIN `name_volunteer2` `nv` ON `n`.`idName` = `nv`.`idName`
            AND `nv`.`Vol_Category` = 'Vol_Type'
            AND `nv`.`Vol_Code` IN ('p' , 'g')
        LEFT JOIN `neon_type_map` `ni` ON ni.Neon_Name = 'individualType' and `nv`.`Vol_Code` = `ni`.`HHK_Type_Code`
    WHERE
        `n`.`idName` > 0
        AND (`n`.`Record_Member` = 1)
        AND (`n`.`Member_Status` IN ('a' , 'd', 'in'));

-- -----------------------------------------------------
-- View `vguest_demog`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vguest_demog` AS
	SELECT DISTINCT
        `n`.`Name_Full` AS `Name_Full`,
        `np`.`Name_Full` AS `Patient_Name`,
        `n`.`Gender` AS `Gender`,
        `nd`.*
    FROM
        ((((`name_guest` `ng`
        JOIN `name_demog` `nd` ON ((`ng`.`idName` = `nd`.`idName`)))
        LEFT JOIN `name` `n` ON ((`n`.`idName` = `ng`.`idName`)))
        LEFT JOIN `psg` `p` ON ((`ng`.`idPsg` = `p`.`idPsg`)))
        LEFT JOIN `name` `np` ON ((`p`.`idPatient` = `np`.`idName`)))
    WHERE
        (`n`.`Member_Status` IN ('a' , 'in', 'd'))
    ORDER BY `n`.`idName` DESC;


-- -----------------------------------------------------
-- View `vguest_neon_payment`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vguest_neon_payment` AS
SELECT
    p.idPayment,
    p.idPayor as `hhkId`,
    n.External_Id AS `accountId`,
    IFNULL(DATE_FORMAT(p.Payment_Date, '%Y-%m-%d'), '') AS `date`,
    'HHK' AS `source.name`,
    p.Amount AS `amount`,
    IFNULL(np.Neon_Type_Code, '') AS `fund.id`,
    IFNULL(nt.Neon_Type_Code, '') AS `tenderType.id`,
    p.Notes AS `note`,
    IFNULL(pa.Acct_Number, '') AS `cardNumber`,
    IFNULL(nc.Neon_Type_Name, '') AS `cardType.name`,
    IFNULL(gt.CardHolderName, '') AS `cardHolder`,
    IFNULL(pc.Check_Number, '') AS `CheckNumber`
FROM
    payment p
        LEFT JOIN
    payment_auth pa ON p.idPayment = pa.idPayment
        LEFT JOIN
    payment_info_check pc ON p.idPayment = pc.idPayment
        LEFT JOIN
    `name` n ON p.idPayor = n.idName
        LEFT JOIN
    guest_token gt ON p.idToken = gt.idGuest_token
        LEFT JOIN
    neon_type_map np ON np.List_Name = 'funds'
		AND np.HHK_Type_Code = 'p'
        LEFT JOIN
    neon_type_map nt ON nt.List_Name = 'tenders'
        AND nt.HHK_Type_Code = p.idPayment_method
        LEFT JOIN
    gen_lookups gc ON gc.`Table_Name` = 'Charge_Cards'
        AND gc.Substitute = pa.Card_Type
        LEFT JOIN
    neon_type_map nc ON nc.List_Name = 'creditCardTypes'
        AND nc.HHK_Type_Code = gc.Code

WHERE
    p.Status_Code = 's' and p.Is_Refund = 0 and p.External_Id = '' and n.Record_Member = 1 and n.idName > 0 and p.Amount > 0
        AND p.idPayment_Method IN (1 , 2, 3, 4);



-- -----------------------------------------------------
-- View `vneon_payment_display`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vneon_payment_display` AS
SELECT
    p.idPayment as `HHK Payment Id`,
    p.idPayor as `HHK Id`,
    n.Name_Full as `Name`,
    n.External_Id AS `Account Id`,
    IFNULL(DATE_FORMAT(p.Payment_Date, '%Y-%m-%d'), '') AS `Payment Date`,
    p.Amount AS `Amount`,
    IFNULL(np.Neon_Type_Name, '') AS `Neon Fund`,
    IFNULL(nt.Neon_Type_Name, '') AS `Payment Method`,
    CASE
        WHEN p.idPayment_Method = 1 THEN ''
        WHEN p.idPayment_Method = 3 THEN IFNULL(pc.Check_Number, '')
        ELSE CONCAT(IFNULL(pa.Acct_Number, ''),
                ', ',
                IFNULL(nc.Neon_Type_Name, ''),
                ', ',
                IFNULL(gt.CardHolderName, ''))
    END AS `Detail`,
    p.Notes AS `Notes`
FROM
    payment p
        LEFT JOIN
    payment_auth pa ON p.idPayment = pa.idPayment
        LEFT JOIN
    payment_info_check pc ON p.idPayment = pc.idPayment
        LEFT JOIN
    `name` n ON p.idPayor = n.idName
        LEFT JOIN
    guest_token gt ON p.idToken = gt.idGuest_token
        LEFT JOIN
    neon_type_map np ON np.List_Name = 'funds'
	AND np.HHK_Type_Code = 'p'
        LEFT JOIN
    neon_type_map nt ON nt.List_Name = 'tenders'
        AND nt.HHK_Type_Code = p.idPayment_method
        LEFT JOIN
    gen_lookups gc ON gc.`Table_Name` = 'Charge_Cards'
        AND gc.Substitute = pa.Card_Type
        LEFT JOIN
    neon_type_map nc ON nc.List_Name = 'creditCardTypes'
        AND nc.HHK_Type_Code = gc.Code
WHERE
    p.Status_Code = 's' and p.Is_Refund = 0 and p.External_Id = '' and n.Record_Member = 1 and n.idName > 0 and p.Amount > 0
        AND p.idPayment_Method IN (1 , 2, 3, 4);


-- -----------------------------------------------------
-- View `vguest_transfer`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vguest_transfer` AS
    SELECT
        `n`.`External_Id` AS `External Id`,
        `n`.`idName` AS `HHK Id`,
        CASE
            WHEN `ng`.`Relationship_Code` = 'slf' THEN 'Yes'
            ELSE ''
        END AS `Patient`,
        TRIM(CONCAT_WS(' ',
                    IFNULL(`g1`.`Description`, ''),
                    `n`.`Name_First`,
                    `n`.`Name_Middle`,
                    `n`.`Name_Last`,
                    IFNULL(`g2`.`Description`, ''))) AS `Name`,
        CASE
            WHEN IFNULL(`na`.`Address_1`, '') = '' THEN ''
            WHEN IFNULL(`na`.`Bad_Address`, '') <> '' THEN 'Bad Address'
            ELSE TRIM(CONCAT_WS(', ',
                        CASE
                            WHEN IFNULL(`na`.`Address_2`, '') = '' THEN IFNULL(`na`.`Address_1`, '')
                            ELSE CONCAT(IFNULL(`na`.`Address_1`, ''),
                                    ', ',
                                    `na`.`Address_2`)
                        END,
                        IFNULL(`na`.`City`, ''),
                        IFNULL(`na`.`State_Province`, ''),
                        IFNULL(`na`.`Postal_Code`, ''),
                        IFNULL(`na`.`Country_Code`, '')))
        END AS `Address`,
        CASE
            WHEN IFNULL(`np`.`Phone_Extension`, '') = '' THEN IFNULL(`np`.`Phone_Num`, '')
            ELSE CONCAT_WS('x',
                    `np`.`Phone_Num`,
                    `np`.`Phone_Extension`)
        END AS `Phone`,
        IFNULL(`ne`.`Email`, '') AS `Email`,
        IFNULL(DATE_FORMAT(`n`.`BirthDate`, '%m-%d-%Y'),
                '') AS `Birthdate`,
        IFNULL(`gn`.`Description`, '') AS `No Return`,
        MAX(IFNULL(`s`.`Span_Start_Date`, '')) AS `Arrival`,
        IFNULL(`s`.`Span_End_Date`, '') AS `Departure`,
        IFNULL(`na`.`Bad_Address`, '') AS `Bad Addr`
    FROM
        `stays` `s`
        JOIN `visit` `v` ON (`s`.`idVisit` = `v`.`idVisit`
            AND `s`.`Visit_Span` = `v`.`Span`)
        JOIN `name_guest` `ng` ON (`s`.`idName` = `ng`.`idName`)
        LEFT JOIN `name` `n` ON (`ng`.`idName` = `n`.`idName`)
        LEFT JOIN `name_address` `na` ON (`ng`.`idName` = `na`.`idName`
            AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`)
        LEFT JOIN `name_email` `ne` ON (`ng`.`idName` = `ne`.`idName`
            AND `n`.`Preferred_Email` = `ne`.`Purpose`)
        LEFT JOIN `name_phone` `np` ON (`ng`.`idName` = `np`.`idName`
            AND `n`.`Preferred_Phone` = `np`.`Phone_Code`)
        LEFT JOIN `name_demog` `nd` ON (`ng`.`idName` = `nd`.`idName`)
        LEFT JOIN `gen_lookups` `g1` ON (`n`.`Name_Prefix` = `g1`.`Code`
            AND `g1`.`Table_Name` = 'Name_Prefix')
        LEFT JOIN `gen_lookups` `g2` ON (`n`.`Name_Suffix` = `g2`.`Code`
            AND `g2`.`Table_Name` = 'Name_Suffix')
        LEFT JOIN `gen_lookups` `g3` ON (`g3`.`Table_Name` = 'Patient_Rel_Type'
            AND `g3`.`Code` = `ng`.`Relationship_Code`)
		LEFT JOIN `gen_lookups` `gn` ON `gn`.`Table_Name` = 'NoReturnReason'
			AND `gn`.`Code` = `nd`.`No_Return`

    WHERE
        `ng`.`idName` > 0
            AND `n`.`Record_Member` = 1
            AND `n`.`Member_Status` IN ('a' , 'd', 'in')
    GROUP BY `s`.`idName`
    ORDER BY `ng`.`idPsg`;


-- -----------------------------------------------------
-- View `vguest_view`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vguest_view` AS
    SELECT
		 `s`.`idName`,
        IFNULL(CASE
                    WHEN `n`.`Name_Suffix` = '' THEN `n`.`Name_Last`
                    ELSE CONCAT(`n`.`Name_Last`, ' ', `g`.`Description`)
                END,
                '') AS `Last Name`,
        IFNULL(`n`.`Name_First`, '') AS `First Name`,
        IFNULL(`rm`.`Title`, '') AS `Room`,
        CASE
            WHEN `n`.`Preferred_Phone` = 'no' THEN 'No Phone'
            ELSE IFNULL(`np`.`Phone_Num`, '')
        END AS `Phone`,
        `s`.`Checkin_Date` AS `Arrival`,
        CASE
            WHEN `s`.`Expected_Co_Date` < CURRENT_TIMESTAMP() THEN CURRENT_TIMESTAMP()
            ELSE `s`.`Expected_Co_Date`
        END AS `Expected Departure`,
        `s`.`On_Leave` AS `On_Leave`,
        TO_DAYS(CURRENT_TIMESTAMP()) - TO_DAYS(`s`.`Checkin_Date`) AS `Nights`,
        `hosp`.`Title` AS `Hospital`,
        `diag`.`Description` AS `Diagnosis`,
        `loc`.`Description` AS `Location`,
        CONCAT(IFNULL(`ec`.`Name_First`, ''),
                ' ',
                IFNULL(`ec`.`Name_Last`, '')) AS `EC Name`,
        IFNULL(`ec`.`Phone_Home`, '') AS `EC Phone Home`,
        IFNULL(`ec`.`Phone_Alternate`, '') AS `EC Phone Alternate`,
        IFNULL(`v`.`Make`, '') AS `Make`,
        IFNULL(`v`.`Model`, '') AS `Model`,
        IFNULL(`v`.`Color`, '') AS `Color`,
        IFNULL(`v`.`State_Reg`, '') AS `State Reg.`,
        IFNULL(`v`.`License_Number`, '') AS `License Plate`,
        IFNULL(`v`.`Note`, '') AS `Note`
    FROM
        (((((((((((`stays` `s`
        LEFT JOIN `name` `n` ON (`n`.`idName` = `s`.`idName`))
        LEFT JOIN `name_phone` `np` ON (`n`.`idName` = `np`.`idName`
            AND `n`.`Preferred_Phone` = `np`.`Phone_Code`))
        LEFT JOIN `visit` `vs` ON (`s`.`idVisit` = `vs`.`idVisit`
            AND `s`.`Visit_Span` = `vs`.`Span`))
        LEFT JOIN `hospital_stay` `hs` ON (`vs`.`idHospital_stay` = `hs`.`idHospital_stay`))
        LEFT JOIN `hospital` `hosp` ON (`hs`.`idHospital` = `hosp`.`idHospital`))
        LEFT JOIN `gen_lookups` `diag` ON (`diag`.`Table_Name` = 'Diagnosis'
            AND `diag`.`Code` = `hs`.`Diagnosis`))
        LEFT JOIN `gen_lookups` `loc` ON (`loc`.`Table_Name` = 'Location'
            AND `loc`.`Code` = `hs`.`Location`))
        LEFT JOIN `vehicle` `v` ON (`vs`.`idRegistration` = `v`.`idRegistration`))
        LEFT JOIN `emergency_contact` `ec` ON (`n`.`idName` = `ec`.`idName`))
        LEFT JOIN `room` `rm` ON (`s`.`idRoom` = `rm`.`idRoom`))
        LEFT JOIN `gen_lookups` `g` ON (`g`.`Table_Name` = 'Name_Suffix'
            AND `g`.`Code` = `n`.`Name_Suffix`))
    WHERE
        `s`.`Status` = 'a';



-- -----------------------------------------------------
-- View `vhospitalstay_log`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vhospitalstay_log` AS
    SELECT
        `l`.`Sub_Type` AS `Sub_Type`,
        `l`.`User_Name` AS `User_Name`,
        `l`.`idName` AS `idName`,
        `l`.`idReservation` AS `idReservation`,
        `l`.`idPsg` AS `idPsg`,
        `l`.`idAgent` AS `idAgent`,
        `l`.`idHospital_stay` AS `idHospital_stay`,
        `l`.`Log_Text` AS `Log_Text`,
        `l`.`Timestamp` AS `Timestamp`,
        IFNULL(`n`.`Name_First`,'') AS `Name_First`,
        IFNULL(`n`.`Name_Last`,'') AS `Name_Last`,
        IFNULL(`na`.`Name_First`,'') AS `Agent_First`,
        IFNULL(`na`.`Name_Last`,'') AS `Agent_Last`
    FROM
        ((`reservation_log` `l`
        LEFT JOIN `name` `n` ON ((`l`.`idName` = `n`.`idName`)))
        LEFT JOIN `name` `na` ON ((`l`.`idAgent` = `na`.`idName`)))
    WHERE
        (`l`.`Log_Type` = 'hospitalStay');



-- -----------------------------------------------------
-- View `vindividual_donations`
-- -----------------------------------------------------
CREATE or replace VIEW `vindividual_donations` AS
    select
        `vm`.`Id` AS `id`,
        `vm`.`Name_Last` AS `Donor_Last`,
        `vm`.`Name_First` AS `Donor_First`,
        `vm`.`Name_Nickname` AS `Donor_Nickname`,
        `vm`.`Name_Prefix` AS `Donor_Prefix`,
        `vm`.`Name_Suffix` AS `Donor_Suffix`,
        `vm`.`Name_Middle` AS `Donor_Middle`,
        `vm`.`Title` AS `Donor_Title`,
        `vm`.`Gender` AS `Donor_Gender`,
        `vm`.`MemberStatus` AS `Donor_Status`,
        `vm`.`SpouseId` AS `Donor_Partner_Id`,
        `vm`.`Company` AS `Donor_Company`,
        `vm`.`Address_Code` AS `Donor_Preferred_Addr_Code`,
        `vm`.`Preferred_Email` as `Email`,
        (case
            when `vm`.`MemberRecord` then ifnull(`vp`.`Name_First`, '')
            else ifnull(`ve`.`Name_First`, '')
        end) AS `Assoc_First`,
        (case
            when `vm`.`MemberRecord` then ifnull(`vp`.`Name_Last`, '')
            else ifnull(`ve`.`Name_Last`, '')
        end) AS `Assoc_Last`,
        (case
            when `vm`.`MemberRecord` then ifnull(`vp`.`Name_Nickname`, '')
            else ifnull(`ve`.`Name_Nickname`, '')
        end) AS `Assoc_Nickname`,
        (case
            when `vm`.`MemberRecord` then ifnull(`vp`.`Name_Prefix`, '')
            else ifnull(`ve`.`Name_Prefix`, '')
        end) AS `Assoc_Prefix`,
        (case
            when `vm`.`MemberRecord` then ifnull(`vp`.`Name_Suffix`, '')
            else ifnull(`ve`.`Name_Suffix`, '')
        end) AS `Assoc_Suffix`,
        (case
            when `vm`.`MemberRecord` then ifnull(`vp`.`Name_Middle`, '')
            else ifnull(`ve`.`Name_Middle`, '')
        end) AS `Assoc_Middle`,
        (case
            when `vm`.`MemberRecord` then ''
            else ifnull(`ve`.`Title`, '')
        end) AS `Assoc_Title`,
        (case
            when `vm`.`MemberRecord` then ''
            else ifnull(`ve`.`Company`, '')
        end) AS `Assoc_Company`,
        (case
            when `vm`.`MemberRecord` then ifnull(`vp`.`Gender`, '')
            else ifnull(`ve`.`Gender`, '')
        end) AS `Assoc_Gender`,
        (case
            when `vm`.`MemberRecord` then ifnull(`vp`.`MemberStatus`, '')
            else ifnull(`ve`.`MemberStatus`, '')
        end) AS `Assoc_Status`,
        (case
            when `vm`.`MemberRecord` then ifnull(`vp`.`SpouseId`, 0)
            else ifnull(`ve`.`Company_Id`, 0)
        end) AS `Assoc_Partner_Id`,
        (case
            when `vm`.`MemberRecord` then ifnull(`vp`.`Address_Code`, '')
            else ifnull(`ve`.`Address_Code`, '')
        end) AS `Assoc_Preferred_Addr_Code`,
        (case
            when `vm`.`MemberRecord` then `vm`.`Preferred_Email`
            else ifnull(`ve`.`Preferred_Email`, '')
        end) AS `Assoc_Email`,
        (case
            when (lcase(`vm`.`Bad_Address`) = 'true') then '*(Bad Address)'
            else ifnull(`vm`.`Address_1`, '')
        end) AS `Address_1`,
        (case
            when (lcase(`vm`.`Bad_Address`) = 'true') then ''
            else ifnull(`vm`.`Address_2`, '')
        end) AS `Address_2`,
        (case
            when (lcase(`vm`.`Bad_Address`) = 'true') then ''
            else ifnull(`vm`.`City`, '')
        end) AS `City`,
        (case
            when (lcase(`vm`.`Bad_Address`) = 'true') then ''
            else ifnull(`vm`.`StateProvince`,  '')
        end) AS `State`,
        (case
            when (lcase(`vm`.`Bad_Address`) = 'true') then ''
            else ifnull(`vm`.`PostalCode`,  '')
        end) AS `Zipcode`,
        (case
            when (lcase(`vp`.`Bad_Address`) = 'true') then '*(Bad Address)'
            else ifnull(`vp`.`Address_1`, '')
        end) AS `Assoc_Address_1`,
        (case
            when (lcase(`vp`.`Bad_Address`) = 'true') then ''
            else ifnull(`vp`.`Address_2`, '')
        end) AS `Assoc_Address_2`,
        (case
            when (lcase(`vp`.`Bad_Address`) = 'true') then ''
            else ifnull(`vp`.`City`, '')
        end) AS `Assoc_City`,
        (case
            when (lcase(`vp`.`Bad_Address`) = 'true') then ''
            else ifnull(`vp`.`StateProvince`, '')
        end) AS `Assoc_State`,
        (case
            when (lcase(`vp`.`Bad_Address`) = 'true') then ''
            else ifnull( `vp`.`PostalCode`, '')
        end) AS `Assoc_Zipcode`,
        `d`.`Amount` AS `Amount`,
        `g`.`Description` as `Pay Type`,
        (case
            when
                (ifnull(`c`.`Campaign_Type`, '') = 'pct')
            then
                round((`d`.`Amount` - ((`d`.`Amount` * `c`.`Percent_Cut`) / 100)),
                        2)
            else `d`.`Amount`
        end) AS `Tax_Free`,
        (case
            when (ifnull(`c`.`Campaign_Type`, '') = 'pct') then `c`.`Percent_Cut`
            else 0
        end) AS `Percent_Cut`,
        ifnull(`c`.`Title`, '') AS `Campaign_Title`,
        ifnull(`c`.`Campaign_Merge_Code`, '') AS `Mail_Merge_Code`,
        `d`.`Date_Entered` AS `Effective_Date`,
        `d`.`Salutation_Code` AS `Salutation_Code`,
        `d`.`Campaign_Code` AS `Campaign_Code`,
        `d`.`Envelope_Code` AS `Envelope_Name_Code`,
        (case
            when `vm`.`MemberRecord` then 0
            else 1
        end) AS `isCompany`,
        `d`.`Care_Of_Id` AS `Care_Of_Id`,
        `d`.`Assoc_Id` AS `Assoc_Id`,
        `d`.`Member_type` AS `Member_Type`,
        c.Campaign_Type,
        d.Fund_Code,
        d.Note
    from
        `donations` `d`
        left join `campaign` `c` ON lcase(trim(`d`.`Campaign_Code`)) = lcase(trim(`c`.`Campaign_Code`))
        left join `vmember_listing_noex` `vm` ON `vm`.`Id` = `d`.`Donor_Id`
        left join `vmember_listing_noex` `vp` ON `vp`.`Id` = `d`.`Assoc_Id`
        left join `vmember_listing_noex` `ve` ON `ve`.`Id` = `d`.`Care_Of_Id`
        left join gen_lookups g on g.Table_Name = 'Pay_Type' and g.Code = `d`.`Pay_Type`

    where
        `d`.`Status` = 'a';




-- -----------------------------------------------------
-- View `vitem_list`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vitem_list` AS
    SELECT
        `i`.`idItem` AS `idItem`,
        `i`.`Percentage` AS `Percentage`,
        `i`.`Deleted` AS `Deleted`,
        `i`.`Has_Decimals` AS `Has_Decimals`,
        `i`.`Gl_Code` AS `Gl_Code`,
        `i`.`Description` AS `Description`,
        `it`.`idItem_Type` AS `idItem_Type`,
        `it`.`Category_Type` AS `Category_Type`,
        `it`.`Type_Description` AS `Type_Description`,
        `it`.`Internal` AS `Internal`,
        `it`.`Order_Line_Type_Id` AS `Order_Line_Type_Id`,
        `ip`.`idItem_price` AS `idItem_price`,
        `ip`.`Item_Id` AS `Item_Id`,
        `ip`.`Currency_Id` AS `Currency_Id`,
        `ip`.`Price` AS `Price`,
        `ip`.`ModelCode` AS `ModelCode`
    FROM
        (((`item_type_map` `itm`
        LEFT JOIN `item` `i` ON ((`itm`.`Item_Id` = `i`.`idItem`)))
        LEFT JOIN `item_type` `it` ON ((`itm`.`Type_Id` = `it`.`idItem_Type`)))
        LEFT JOIN `item_price` `ip` ON ((`itm`.`Item_Id` = `ip`.`Item_Id`)));




-- -----------------------------------------------------
-- View `vlist_inv_pments`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vlist_inv_pments` AS
   SELECT
        `i`.`idInvoice` AS `idInvoice`,
        `i`.`Invoice_Number` AS `Invoice_Number`,
        `i`.`Amount` AS `Invoice_Amount`,
        `i`.`Sold_To_Id` AS `Sold_To_Id`,
        IFNULL(`nv`.`Vol_Status`, '') AS `Bill_Agent`,
        `i`.`idGroup` AS `idGroup`,
        `i`.`Order_Number` AS `Order_Number`,
        `i`.`Suborder_Number` AS `Suborder_Number`,
        `i`.`Invoice_Date` AS `Invoice_Date`,
        `i`.`Status` AS `Invoice_Status`,
        `i`.`Carried_Amount` AS `Carried_Amount`,
        `i`.`Balance` AS `Invoice_Balance`,
        `i`.`Delegated_Invoice_Id` AS `Delegated_Invoice_Id`,
        `i`.`Notes` AS `Notes`,
        `i`.`Deleted` AS `Deleted`,
        `i`.`Updated_By` AS `Invoice_Updated_By`,
        ifnull(`il`.`idInvoice_Line`, '') as `il_Id`,
        ifnull(`il`.`Description`, '') as `il_Description`,
        ifnull(`il`.`Amount`, 0) as `il_Amount`,
        IFNULL(`g1`.`Description`, '') AS `Invoice_Status_Title`,
        IFNULL(`p`.`idPayment`, 0) AS `idPayment`,
        IFNULL(`p`.`Amount`, 0) AS `Payment_Amount`,
        IFNULL(`p`.`Balance`, 0) AS `Payment_Balance`,
        IFNULL(`p`.`idPayment_Method`, 0) AS `idPayment_Method`,
        IFNULL(`pm`.`Method_Name`, '') AS `Payment_Method_Title`,
        IFNULL(`p`.`Status_Code`, 0) AS `Payment_Status`,
        IFNULL(`g2`.`Description`, '') AS `Payment_Status_Title`,
        IFNULL(`p`.`Payment_Date`, 0) AS `Payment_Date`,
        IFNULL(`p`.`Last_Updated`, '') AS `Payment_Last_Updated`,
        IFNULL(`p`.`Is_Refund`, 0) AS `Is_Refund`,
        IF(`rp`.`idPayment` > 0, 1, 0) AS `Has_ReturnPayment`,
        IFNULL(`p`.`idPayor`, 0) AS `Payment_idPayor`,
        IFNULL(`p`.`Updated_By`, '') AS `Payment_Updated_By`,
        IFNULL(`p`.`Created_By`, '') AS `Payment_Created_By`,
        IFNULL(`p`.`Notes`, '') AS `Payment_Note`,
        IFNULL(`p`.`External_Id`, '') AS `Payment_External_Id`,
        IFNULL(`p`.`idToken`, 0) as `Payment_idToken`,
        IFNULL(`p`.`Timestamp`, '') as `Payment_Timestamp`,
        IFNULL(`pa`.`idPayment_auth`, 0) AS `idPayment_auth`,
        IFNULL(`pa`.`Customer_Id`, 0) AS `Charge_Customer_Id`,
        IFNULL(`pa`.`Merchant`, '') AS `Merchant`,
        IFNULL(`pa`.`Acct_Number`, `p`.`Data2`) AS `Masked_Account`,
        IFNULL(`pa`.`Card_Type`, '') AS `Card_Type`,
        IFNULL(`pa`.`Approved_Amount`, '') AS `Approved_Amount`,
        IFNULL(`pa`.`Approval_Code`, '') AS `Approval_Code`,
        IFNULL(`pa`.`Status_Code`, '') AS `PA_Status_Code`,
        IFNULL(`pa`.`Last_Updated`, '') as `Auth_Last_Updated`,
        IFNULL(`pc`.`Check_Number`, '') AS `Check_Number`
    FROM
        `invoice` `i`
        LEFT JOIN `invoice_line` `il` on `i`.`idInvoice` = `il`.`Invoice_Id` and `il`.`Item_Id` = 11 and `il`.`Deleted` < 1
        LEFT JOIN `payment_invoice` `pi` ON `i`.`idInvoice` = `pi`.`Invoice_Id`
        LEFT JOIN `payment` `p` ON `pi`.`Payment_Id` = `p`.`idPayment`
        LEFT JOIN `payment` `rp` ON `p`.`idPayment` = `rp`.`parent_idPayment` and `rp`.`Is_Refund` > 0
        LEFT JOIN `payment_auth` `pa` ON `pi`.`Payment_Id` = `pa`.`idPayment`
        LEFT JOIN `payment_info_check` `pc` ON `pi`.`Payment_Id` = `pc`.`idPayment`
        LEFT JOIN `payment_method` `pm` ON `p`.`idPayment_Method` = `pm`.`idPayment_method`
        LEFT JOIN `name_volunteer2` `nv` ON `i`.`Sold_To_Id` = `nv`.`idName`
            AND (`nv`.`Vol_Category` = 'Vol_Type')
            AND (`nv`.`Vol_Code` = 'ba')
        LEFT JOIN `gen_lookups` `g2` ON `p`.`Status_Code` = `g2`.`Code`
            AND (`g2`.`Table_Name` = 'Payment_Status')
        LEFT JOIN `gen_lookups` `g1` ON `i`.`Status` = `g1`.`Code`
            AND (`g1`.`Table_Name` = 'Invoice_Status')
    ORDER BY i.idInvoice, p.idPayment, pa.idPayment_auth;


-- -----------------------------------------------------
-- View `vlist_pments`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vlist_pments` AS
   SELECT
        ifnull(`i`.`idInvoice` ,0) AS `idInvoice`,
        ifnull(`i`.`Invoice_Number`, '') AS `Invoice_Number`,
        `i`.`Amount` AS `Invoice_Amount`,
        `i`.`Sold_To_Id` AS `Sold_To_Id`,
        IFNULL(`nv`.`Vol_Status`, '') AS `Bill_Agent`,
        `i`.`idGroup` AS `idGroup`,
        `i`.`Order_Number` AS `Order_Number`,
        `i`.`Suborder_Number` AS `Suborder_Number`,
        `i`.`Invoice_Date` AS `Invoice_Date`,
        `i`.`Status` AS `Invoice_Status`,
        `i`.`Carried_Amount` AS `Carried_Amount`,
        `i`.`Balance` AS `Invoice_Balance`,
        `i`.`Delegated_Invoice_Id` AS `Delegated_Invoice_Id`,
        `i`.`Notes` AS `Notes`,
        `i`.`Deleted` AS `Deleted`,
        `i`.`Updated_By` AS `Invoice_Updated_By`,
        ifnull(`il`.`idInvoice_Line`, '') as `il_Id`,
        ifnull(`il`.`Description`, '') as `il_Description`,
        ifnull(`il`.`Amount`, 0) as `il_Amount`,
        IFNULL(`g1`.`Description`, '') AS `Invoice_Status_Title`,
        IFNULL(`p`.`idPayment`, 0) AS `idPayment`,
        IFNULL(`p`.`Amount`, 0) AS `Payment_Amount`,
        IFNULL(`p`.`Balance`, 0) AS `Payment_Balance`,
        IFNULL(`p`.`idPayment_Method`, 0) AS `idPayment_Method`,
        IFNULL(`pm`.`Method_Name`, '') AS `Payment_Method_Title`,
        IFNULL(`p`.`Status_Code`, 0) AS `Payment_Status`,
        IFNULL(`g2`.`Description`, '') AS `Payment_Status_Title`,
        IFNULL(`p`.`Payment_Date`, '') AS `Payment_Date`,
        IFNULL(`p`.`Last_Updated`, '') AS `Payment_Last_Updated`,
        IFNULL(`p`.`Is_Refund`, 0) AS `Is_Refund`,
        IF(`rp`.`idPayment` > 0, 1, 0) AS `Has_ReturnPayment`,
        IFNULL(`p`.`idPayor`, 0) AS `Payment_idPayor`,
        IFNULL(`p`.`Updated_By`, '') AS `Payment_Updated_By`,
        IFNULL(`p`.`Created_By`, '') AS `Payment_Created_By`,
        IFNULL(`p`.`Notes`, '') AS `Payment_Note`,
        IFNULL(`p`.`External_Id`, '') AS `Payment_External_Id`,
        IFNULL(`p`.`idToken`, 0) as `Payment_idToken`,
        IFNULL(`p`.`Timestamp`, '') as `Payment_Timestamp`,
        IFNULL(`pa`.`idPayment_auth`, 0) AS `idPayment_auth`,
        IFNULL(`pa`.`Customer_Id`, 0) AS `Charge_Customer_Id`,
        IFNULL(`pa`.`Merchant`, '') AS `Merchant`,
        IFNULL(`pa`.`Acct_Number`, `p`.`Data2`) AS `Masked_Account`,
        IFNULL(`pa`.`Card_Type`, '') AS `Card_Type`,
        IFNULL(`pa`.`Approved_Amount`, '') AS `Approved_Amount`,
        IFNULL(`pa`.`Approval_Code`, '') AS `Approval_Code`,
        IFNULL(`pa`.`Status_Code`, '') AS `PA_Status_Code`,
        IFNULL(`pa`.`Last_Updated`, '') as `Auth_Last_Updated`,
        IFNULL(`pc`.`Check_Number`, '') AS `Check_Number`
    FROM
        `payment` `p`
        LEFT JOIN `payment` `rp` ON `p`.`idPayment` = `rp`.`parent_idPayment` and `rp`.`Is_Refund` > 0
        LEFT JOIN `payment_auth` `pa` ON `p`.`idPayment` = `pa`.`idPayment`
        LEFT JOIN `payment_info_check` `pc` ON `p`.`idPayment` = `pc`.`idPayment`
        LEFT JOIN `payment_method` `pm` ON `p`.`idPayment_Method` = `pm`.`idPayment_method`
        LEFT JOIN `payment_invoice` `pi` ON `p`.`idPayment` = `pi`.`Payment_Id`
        LEFT JOIN `invoice` `i` ON `pi`.`Invoice_Id` = `i`.`idInvoice`
        LEFT JOIN `invoice_line` `il` on `i`.`idInvoice` = `il`.`Invoice_Id` and `il`.`Item_Id` = 11 and `il`.`Deleted` < 1
        LEFT JOIN `name_volunteer2` `nv` ON `p`.`idPayor` = `nv`.`idName`
            AND (`nv`.`Vol_Category` = 'Vol_Type')
            AND (`nv`.`Vol_Code` = 'ba')
        LEFT JOIN `gen_lookups` `g2` ON `p`.`Status_Code` = `g2`.`Code`
            AND (`g2`.`Table_Name` = 'Payment_Status')
        LEFT JOIN `gen_lookups` `g1` ON `i`.`Status` = `g1`.`Code`
            AND (`g1`.`Table_Name` = 'Invoice_Status')
    ORDER BY i.idInvoice, p.idPayment, pa.idPayment_auth;


-- -----------------------------------------------------
-- View `vlist_first_visit`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vlist_first_visit` AS
select v.* from visit v
join registration reg on v.idRegistration = reg.idRegistration

where v.Status in ("a", "co") and v.Arrival_Date = (select min(vv.Arrival_Date) from visit vv join registration reg2 on vv.idRegistration = reg2.idRegistration where reg.idPsg = reg2.idPsg and not date(vv.Arrival_Date) <=> date(vv.Actual_Departure));


-- -----------------------------------------------------
-- View `vlocation_listing`
-- -----------------------------------------------------
CREATE or REPLACE VIEW `vlocation_listing` AS
Select
    l.idLocation as Id,
    l.Title,
    l.Description,
    l.Status,
    l.Address,
    l.Merchant,
    l.Owner_Id as `Owner Id`,
    ifnull(v.Name_Last, '') AS Owner,
    l.Updated_By as `Updated By`,
    ifnull(l.Last_Updated, '') AS `Last Updated`,
    concat('<a target="blank" href="',
            l.Map,
            '">View</a>') as Link,
    l.Map as `Map URL`
FROM
    location l
        left join
    vmember_listing v ON l.Owner_Id = v.Id;



-- -----------------------------------------------------
-- View `vmailing_list`
-- -----------------------------------------------------
CREATE or replace VIEW `vmailing_list` AS
select `ml`.`id` AS `idName`,
vm.`Address_1` as Street,
`vm`.`City` AS `City`,
`vm`.`StateProvince` AS `State`,
`vm`.`PostalCode` AS `Zip`,
(case when (`vm`.`MemberRecord` = 0) then ifnull(`ve`.`Name_Last`,'') else `vm`.`Name_Last` end) AS `Name_Last`,
(case when (`vm`.`MemberRecord` = 0) then ifnull(`ve`.`Name_First`,'') else `vm`.`Name_First` end) AS `Name_First`,
(case when (`vm`.`MemberRecord` = 0) then ifnull(`ve`.`Name_Nickname`,'') else `vm`.`Name_Nickname` end) AS `Name_Nickname`,
(case when (`vm`.`MemberRecord` = 0) then ifnull(`ve`.`Name_Prefix`,'') else `vm`.`Name_Prefix` end) AS `Name_Prefix`,
(case when (`vm`.`MemberRecord` = 0) then ifnull(`ve`.`Name_Suffix`,'') else `vm`.`Name_Suffix` end) AS `Name_Suffix`,
(case when (`vm`.`MemberRecord` = 0) then ifnull(`ve`.`Name_Middle`,'') else `vm`.`Name_Middle` end) AS `Name_Middle`,
(case when (`vm`.`MemberRecord` = 0) then ifnull(`ve`.`Title`,'') else `vm`.`Title` end) AS `Name_Title`,
(case when (`vm`.`MemberRecord` = 0) then ifnull(`ve`.`Gender`,'') else `vm`.`Gender` end) AS `Name_Gender`,
ifnull(`vp`.`Name_First`,'') AS `Partner_First`,
ifnull(`vp`.`Name_Last`,'') AS `Partner_Last`,
ifnull(`vp`.`Name_Nickname`,'') AS `Partner_Nickname`,
ifnull(`vp`.`Name_Prefix`,'') AS `Partner_Prefix`,
ifnull(`vp`.`Name_Suffix`,'') AS `Partner_Suffix`,
ifnull(`vp`.`Name_Middle`,'') AS `Partner_Middle`,
ifnull(`vp`.`Gender`,'') AS `Partner_Gender`,
vm.Address_Code,
`vm`.`Company` AS `Company`,
`vm`.`Company_Id` AS `Company_Id`,
case when sp.Relation_Type is null then ifnull(ve.Id,vm.Id) else '' end as Family_Mem,
count(`ml`.`adr_frag`) AS `Address_Count`
from `mail_listing` `ml` left join `vmember_listing` `vm` on `ml`.`id` = `vm`.`Id`
left join `vmember_listing` `vp` on `vp`.`Id` = `vm`.`SpouseId` and `vp`.`MemberStatus` = 'a'
left join `vmember_listing` `ve` on `ve`.`Company_Id` = `vm`.`Id` and `ve`.`Company_CareOf` = 'y' and `ve`.`MemberStatus` = 'a'
left join relationship sp on  (vm.Id = sp.idName or vm.Id = sp.Target_Id) and sp.Relation_Type = 'sp' and sp.Status = 'a'
group by concat(`ml`.`adr_frag`, `fm`);


-- -----------------------------------------------------
-- View `vmember_categories`
-- -----------------------------------------------------
CREATE or replace VIEW `vmember_categories` AS
select
    nv.idName AS `idName`,
    nv.Vol_Code AS `Vol_Code`,
    nv.Vol_Category AS `Vol_Category`,
    c.Vol_Category_Title AS `Vol_Category_Title`,
    c.Vol_Code_Title AS `Vol_Code_Title`,
    c.Colors AS `Colors`,
    nv.Vol_Status AS `Vol_Status`,
    '' AS `Dormant_Begin_Active`,
    '' AS `Dormant_End_Active`,
    '' as `Dormant_Title`,
    ifnull(nv.Vol_Notes, '') AS `Vol_Notes`,
    nv.Vol_Begin AS `Vol_Begin`,
    nv.Vol_End AS `Vol_End`,
    nv.Vol_Check_Date AS `Vol_Check_Date`,
    nv.Vol_Rank AS `Vol_Rank`,
    ifnull(gr.Description, '') as `Vol_Rank_Title`,
    ifnull(gstat.Description, '') as `Vol_Status_Title`
from
    name_volunteer2 nv
    left join vcategory_listing c ON nv.Vol_Category = c.Vol_Category and nv.Vol_Code = c.Vol_Code
    -- left join dormant_schedules d ON nv.Dormant_Code = d.Code and d.Status = 'a'
    left join gen_lookups gr ON gr.Table_Name = 'Vol_Rank' and gr.Code = nv.Vol_Rank
    left join gen_lookups gstat ON gstat.Table_Name = 'Vol_Status' and gstat.Code = nv.Vol_Status
    left join name on nv.idName = name.idName
    where name.Member_Status = 'a';



-- -----------------------------------------------------
-- View `vmember_directory`
-- -----------------------------------------------------
CREATE or replace VIEW `vmember_directory` AS
    select
        `vm`.`Id` AS `Id`,
        `vm`.`Fullname` AS `Fullname`,
        `vm`.`Name_Last` AS `Name_Last`,
        `vm`.`Name_First` AS `Name_First`,
        `vm`.`Name_Middle` AS `Name_Middle`,
        `vm`.`Name_Nickname` AS `Name_Nickname`,
        `vm`.`Name_Prefix` AS `Name_Prefix`,
        `vm`.`Name_Suffix` AS `Name_Suffix`,
        `vm`.`Preferred_Phone` AS `Preferred_Phone`,
        `vm`.`Address_1` AS `Address_1`,
        `vm`.`Address_2` AS `Address_2`,
        `vm`.`City` AS `City`,
        `vm`.`StateProvince` AS `StateProvince`,
        `vm`.`PostalCode` AS `PostalCode`,
        `vm`.`Bad_Address` AS `Bad_Address`,
        `vm`.`Preferred_Email` AS `Preferred_Email`,
        `vm`.`MemberStatus` AS `MemberStatus`,
        `vm`.`Gender` AS `Gender`,
        `vm`.`SpouseId` AS `SpouseId`,
--		(select count(Target_Id)  from relationship where idName = vm.Id and Relation_Type = 'par') as `Parents`,
--		(select count(idName)  from relationship where Target_Id = vm.Id and Relation_Type = 'par') as `Children`,
--		(select count(idName)  from relationship where Relation_Type = 'sib' and Group_Code = (Select Group_Code from relationship where Relation_Type='sib' and Status='a' and idName = vm.Id)) as `Siblings`,
--		(select count(idName)  from relationship where Relation_Type = 'rltv' and Group_Code = (Select Group_Code from relationship where Relation_Type='rltv' and Status='a' and idName = vm.Id)) as `Relatives`,
        `vm`.`MemberRecord` AS `MemberRecord`,
        `vm`.`Company_Id` AS `Company_Id`,
        `vm`.`Company` AS `Company`,
        `vm`.`Exclude_Mail` AS `Exclude_Mail`,
        `vm`.`Exclude_Email` AS `Exclude_Email`,
        `vm`.`Exclude_Phone` AS `Exclude_Phone`,
        `vm`.`Member_Type` AS `Member_Type`,
        `vm`.`Title` AS `Title`,
        `vm`.`Company_CareOf` AS `Company_CareOf`,
        `vm`.`Address_Code` AS `Address_Code`,
        `vm`.`Email_Code` AS `Email_Code`,
        `vm`.`Phone_Code` AS `Phone_Code`
    from
        `vmember_listing` `vm`
    where `vm`.`MemberStatus` = 'a' and `vm`.`Exclude_Directory` = 0;



-- -----------------------------------------------------
-- View `vname_list`
-- -----------------------------------------------------
create or replace view `vname_list` as
    SELECT
        `n`.`idName` AS `Id`,
        IFNULL(`g1`.`Description`, '') AS `Prefix`,
        `n`.`Name_First` AS `First`,
        `n`.`Name_Middle` AS `Middle`,
        `n`.`Name_Last` AS `Last`,
        IFNULL(`g2`.`Description`, '') AS `Suffix`,
        (CASE WHEN (`n`.`Preferred_Phone` = 'no') THEN 'No Phone'
            WHEN (IFNULL(`np`.`Phone_Extension`, '') = '') THEN IFNULL(`np`.`Phone_Num`, '')
            ELSE CONCAT_WS('x',
                    `np`.`Phone_Num`,
                    `np`.`Phone_Extension`)
        END) AS `Phone`,
        (case when (`n`.`Preferred_Email` = 'no') then 'No Email' else ifnull(`ne`.`Email`,'') end) AS `Email`,
        (CASE
            WHEN (IFNULL(`na`.`Address_2`, '') = '') THEN IFNULL(`na`.`Address_1`, '')
            ELSE CONCAT(IFNULL(`na`.`Address_1`, ''),
                    ', ',
                    `na`.`Address_2`)
        END) AS `Address`,
        IFNULL(`na`.`City`, '') AS `City`,
        IFNULL(`na`.`County`, '') AS `County`,
        IFNULL(`na`.`State_Province`, '') AS `State`,
        IFNULL(`na`.`Postal_Code`, '') AS `Zip`,
        IFNULL(`na`.`Country_Code`, '') AS `Country`,
        IFNULL(`na`.`Meters_From_House`, '') AS `Meters_From_House`,
        IFNULL(`na`.`Bad_Address`, '') AS `Bad_Address`,
        IFNULL(`n`.`BirthDate`, '') AS `BirthDate`,
        IFNULL(`n`.`Date_Deceased`, '') AS `Date_Deceased`,
        `n`.`Member_Status` AS `Member_Status`,
        IFNULL(`n`.`External_Id`, '') AS `External_Id`
    FROM
        `name` `n`
        LEFT JOIN `name_address` `na` ON `n`.`idName` = `na`.`idName`
            AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`
        LEFT JOIN `name_email` `ne` ON `n`.`idName` = `ne`.`idName`
            AND `n`.`Preferred_Email` = `ne`.`Purpose`
        LEFT JOIN `name_phone` `np` ON `n`.`idName` = `np`.`idName`
            AND `n`.`Preferred_Phone` = `np`.`Phone_Code`
        LEFT JOIN `name_demog` `nd` ON `n`.`idName` = `nd`.`idName`
        LEFT JOIN `gen_lookups` `g1` ON `n`.`Name_Prefix` = `g1`.`Code`
            AND `g1`.`Table_Name` = 'Name_Prefix'
        LEFT JOIN `gen_lookups` `g2` ON `n`.`Name_Suffix` = `g2`.`Code`
            AND `g2`.`Table_Name` = 'Name_Suffix'
    WHERE
        `n`.`idName` > 0
            AND `n`.`Record_Member` = 1
            AND `n`.`Member_Status` IN ('a' , 'd', 'in');



-- -----------------------------------------------------
-- View `vnon_reporting_list`
-- -----------------------------------------------------
create or replace view vnon_reporting_list as
Select n.idName as Id,
case when n.Record_Company = 1 then 'T' else '' end as `Is Org`,
case when n.Record_Member = 1 then n.Name_Last_First else n.Company end as `Name`,
case when n.Exclude_Directory = 1 then 'T' else '' end as `Directory`,
case when n.Exclude_Mail = 1 then 'T' else '' end as `Mail`,
case when n.Exclude_Phone = 1 then 'T' else '' end as `Phone`,
case when n.Exclude_Email = 1 then 'T' else '' end as `E-mail`,
case when n.Member_Status <> 'a' then ifnull(g.Description,'') else '' end as `Status`,
case when na.Bad_Address = 'true' then 'T' else '' end as `Bad Address`,
case when w.Status <> 'a' then ifnull(gw.Description,'') else '' end as `Web Status`
from name n left join gen_lookups g on g.Table_Name = 'mem_status' and g.Code = n.Member_Status
left join name_address na on n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose
left join w_users w on n.idName = w.idName
left join gen_lookups gw on gw.Table_Name = 'Web_User_Status' and gw.Code = w.Status
where (n.Exclude_Directory = 1 or n.Exclude_Mail = 1 or n.Exclude_Phone = 1 or n.Exclude_Email = 1
    or n.Member_Status <> 'a' or na.Bad_Address = 'true' or w.Status <> 'a')
and n.idName > 0
order by n.Name_Last_First;



-- -----------------------------------------------------
-- View `vresv_notes`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vresv_notes` AS
    SELECT
        n.idNote AS `Note_Id`,
        n.idNote AS `Action`,
        n.flag,
        n.User_Name,
        n.Title,
        n.Note_Text,
        rn.Reservation_Id,
        reg.idPsg,
        n.`Timestamp`
    FROM
        note n
    JOIN
        reservation_note rn ON n.idNote = rn.Note_Id
    JOIN
        reservation r ON rn.Reservation_Id = r.idReservation
    JOIN
	registration reg on r.idRegistration = reg.idRegistration
    WHERE
        rn.Reservation_Id > 0 && n.`Status` = 'a';


-- -----------------------------------------------------
-- View `vvisit_notes`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vvisit_notes` AS
    SELECT
        n.idNote AS `Note_Id`,
        n.idNote AS `Action`,
        n.flag,
        n.User_Name,
        n.Title,
        n.Note_Text,
        v.idVisit,
        reg.idPsg,
        n.`Timestamp`
    FROM
        note n
            JOIN
        reservation_note rn ON n.idNote = rn.Note_Id
            join
        visit v on v.idReservation = rn.Reservation_Id and v.Span = 0
			join
		reservation r ON rn.Reservation_Id = r.idReservation
			join
		registration reg on r.idRegistration = reg.idRegistration
    WHERE
        rn.Reservation_Id > 0 && n.`Status` = 'a' && n.flag = '0' and v.`Status` <> 'c'
	UNION SELECT
        n.idNote AS `Note_Id`,
        n.idNote AS `Action`,
        n.flag,
        n.User_Name,
        n.Title,
        n.Note_Text,
        "",
        reg.idPsg,
        n.`Timestamp`
    FROM
        note n
            JOIN
        reservation_note rn ON n.idNote = rn.Note_Id
            join
        visit v on v.idReservation = rn.Reservation_Id and v.Span = 0
			join
		reservation r ON rn.Reservation_Id = r.idReservation
			join
		registration reg on r.idRegistration = reg.idRegistration
    WHERE
        rn.Reservation_Id > 0 && n.`Status` = 'a' && flag = '1' and v.`Status` <> 'c';


-- -----------------------------------------------------
-- View `vpsg_notes`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vpsg_notes` AS
    SELECT
        n.idNote AS `Note_Id`,
        n.idNote AS `Action`,
        n.flag,
        n.User_Name,
        n.Title,
        n.Note_Text,
        pn.Psg_Id,
        n.`Timestamp`
    FROM
        note n
            JOIN
        psg_note pn ON n.idNote = pn.Note_Id
    WHERE
        pn.Psg_Id > 0 && n.`Status` = 'a';

-- -----------------------------------------------------
-- View `vpsg_notes_concat`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW `vpsg_notes_concat` AS
    SELECT
        n.idNote AS `Note_Id`,
        n.idNote AS `Action`,
        n.flag,
        n.User_Name,
        n.Title,
        n.Note_Text,
        pn.Psg_Id,
        n.`Timestamp`
    FROM
        note n
            JOIN
        psg_note pn ON n.idNote = pn.Note_Id
    WHERE
        pn.Psg_Id > 0 && n.`Status` = 'a'
    UNION DISTINCT SELECT
        n.idNote AS `Note_Id`,
        n.idNote AS `Action`,
        n.flag,
        n.User_Name,
        n.Title,
        n.Note_Text,
        reg.idPsg,
        n.`Timestamp`
    FROM
        note n
            JOIN
        reservation_note rn ON n.idNote = rn.Note_Id
			join
		reservation r ON rn.Reservation_Id = r.idReservation
			join
		registration reg on r.idRegistration = reg.idRegistration
    WHERE
        rn.Reservation_Id > 0 && n.`Status` = 'a';

-- -----------------------------------------------------
-- View `vpsg_guest`
-- -----------------------------------------------------
CREATE or replace VIEW `vpsg_guest` AS
Select
    p.idPsg,
    ng.Relationship_Code,
    ng.idName as `idGuest`,
    ng.Legal_Custody,
    g.Description as `Relationship_Title`,
    (case when n.Name_Nickname = '' then n.Name_First else n.Name_Nickname end) as Name_First,
    (case when n.Name_Suffix = '' then n.Name_Last else concat(n.Name_Last, ' ', n.Name_Suffix) end) as Name_Last,
    gs.Description as Member_Status,
    n.Preferred_Phone,
    case when p.idPatient = ng.idName then 1 else 0 end as isPatient
from
    `psg` p
        left join
    `name_guest` ng ON p.idPsg = ng.idPsg
        left join
    `vmember_listing` `n` ON ng.idName = n.Id
        left join
    gen_lookups g ON g.Table_Name = 'Patient_Rel_Type' and g.Code = ng.Relationship_Code
        left join
    gen_lookups gs ON gs.Table_Name = 'mem_status' and gs.Code = n.MemberStatus;



-- -----------------------------------------------------
-- View `vregister`
-- -----------------------------------------------------
CREATE or replace VIEW `vregister` AS
   SELECT
        CONCAT(`v`.`idVisit`, `v`.`Span`) AS `id`,
        `v`.`idVisit` AS `idVisit`,
        `v`.`Span` AS `Span`,
        `v`.`idRegistration` AS `idRegistration`,
        `v`.`idResource` AS `idResource`,
        `v`.`idReservation` AS `idReservation`,
        `v`.`Status` AS `Visit_Status`,
        `v`.`Span_Start` AS `Span_Start`,
        `v`.`Expected_Departure` AS `Expected_Departure`,
        `v`.`Span_End` AS `Span_End`,
        `v`.`Notes` AS `Ribbon_Note`,
        `gv`.`Description` AS `Status_Text`,
        IFNULL(`hs`.`idHospital`, 0) AS `idHospital`,
        IFNULL(`hs`.`idAssociation`, 0) AS `idAssociation`,
        IFNULL(CASE
                    WHEN `n`.`Name_Suffix` = '' THEN `n`.`Name_Last`
                    ELSE CONCAT(`n`.`Name_Last`, ' ', `gs`.`Description`)
                END,
                '') AS `Guest Last`,
        `n`.`Name_Full` AS `Name_Full`,
        `n`.`Gender` AS `Gender`,
        `nd`.`Newsletter` AS `Newsletter`,
        `nd`.`Photo_Permission` AS `Photo_Permission`,
        `nd`.`Media_Source` AS `Media_Source`,
        `nd`.`Ethnicity` AS `Ethnicity`,
        `nd`.`Income_Bracket` AS `Income_Bracket`,
        `nd`.`Age_Bracket` AS `Age_Bracket`,
        `nd`.`Education_Level` AS `Education_Level`,
        `nd`.`Special_Needs` AS `Special_Needs`
    FROM
        (((((`visit` `v`
        LEFT JOIN `hospital_stay` `hs` ON (`v`.`idHospital_stay` = `hs`.`idHospital_stay`))
        LEFT JOIN `name` `n` ON (`v`.`idPrimaryGuest` = `n`.`idName`))
        LEFT JOIN `name_demog` `nd` ON (`v`.`idPrimaryGuest` = `nd`.`idName`))
        LEFT JOIN `gen_lookups` `gs` ON (`gs`.`Table_Name` = 'Name_Suffix'
            AND `gs`.`Code` = `n`.`Name_Suffix`))
        LEFT JOIN `gen_lookups` `gv` ON (`gv`.`Table_Name` = 'Visit_Status'
            AND `gv`.`Code` = `v`.`Status`));



-- -----------------------------------------------------
-- view vregister_resv
-- -----------------------------------------------------
CREATE or Replace VIEW `vregister_resv` AS
    select
        `r`.`idReservation` AS `idReservation`,
        `r`.`idResource` AS `idResource`,
        `r`.`Status` AS `Status`,
        `r`.`Expected_Arrival`,
        `r`.`Expected_Departure` AS `Expected_Departure`,
        `r`.`idGuest` AS `idGuest`,
        ifnull((case when n.Name_Suffix = '' then n.Name_Last else concat(n.Name_Last, ' ', gs.Description) end), '') AS `Guest Last`,
        ifnull(`hs`.`idHospital`, 0) AS `idHospital`,
        ifnull(hs.idAssociation, 0) as `idAssociation`,
        n.Name_Full,
        gv.Title as `Status_Text`,
        r.idRegistration,
        n.Gender,
        nd.Newsletter,
        nd.Photo_Permission,
        nd.Media_Source,
        nd.Ethnicity,
        nd.Income_Bracket,
        nd.Age_Bracket,
        nd.Education_Level,
        nd.Special_Needs,
        r.Notes as 'Ribbon_Note'
    from
        `reservation` `r`
            left join
        `hospital_stay` `hs` ON `r`.`idHospital_Stay` = `hs`.`idHospital_stay`
            left join
        `name` `n` ON `r`.`idGuest` = `n`.`idName`
            left join
	`name_demog` nd on `r`.`idGuest` = nd.idName
            left join
        gen_lookups gs on gs.`Table_Name` = 'Name_Suffix' and gs.`Code` = n.Name_Suffix
            left join
        lookups gv on gv.Category = 'ReservStatus' and gv.Code = r.Status;




-- -----------------------------------------------------
-- View `vregistration`
-- -----------------------------------------------------
create or replace view vregistration as
select
    r.idPsg,
    r.idRegistration,
    r.Date_Registered,
    r.Status as Reg_Status,
    r.Notes as Reg_Notes,
    r.Sig_Card,
    r.Pamphlet,
    r.Referral,
    ifnull(c.idVehicle,0) as idVehicle,
    ifnull(c.Make,'') as Make,
    ifnull(c.Model,'')as Model,
    ifnull(c.Color,'') as Color,
    ifnull(c.State_Reg,'') as State_Reg
from
    registration r
        left join
    vehicle c ON r.idRegistration = c.idRegistration;




-- -----------------------------------------------------
-- View `vreservation_events`
-- -----------------------------------------------------
CREATE or Replace VIEW `vreservation_events` AS
    select
        `r`.`idReservation` AS `idReservation`,
        `r`.`idResource` AS `idResource`,
        `r`.`Status` AS `Status`,
        `r`.`Expected_Arrival`, -- AS `Arrival_Date`,
        `r`.`Expected_Departure` AS `Expected_Departure`,
        `r`.`idGuest` AS `idGuest`,
        `r`.`Number_Guests` AS `Number_Guests`,
        `r`.`Room_Rate_Category` AS `Rate`,
        `r`.`idRoom_rate`,
        `r`.`Fixed_Room_Rate`,
        ifnull(`n`.`Name_Full`, '') AS `Guest Name`,
        ifnull(`n`.`Name_First`, '') AS `Guest First`,
        ifnull((case when n.Name_Suffix = '' then n.Name_Last else concat(n.Name_Last, ' ', gs.Description) end), '') AS `Guest Last`,
        CASE WHEN (np.Phone_Code = 'no') THEN 'No Phone' ELSE ifnull(np.Phone_Num, '') END as `Phone`,
        ifnull((case when n2.Name_Suffix = '' then `n2`.`Name_Last_First` else concat(`n2`.`Name_Last_First`, ', ', gs2.Description) end), '') AS `Patient Name`,
        ifnull(`hs`.`idHospital`, 0) AS `idHospital`,
        case when ifnull(hs.idAssociation, 0) > 0 and h.Title = '(None)' then 0 else ifnull(hs.idAssociation, 0) end
         as `idAssociation`,
		ifnull(gl.Description, '') as `Location`,
		ifnull(gd.Description, '') as `Diagnosis`,
        ifnull(`re`.`Title`, '') AS `Room Title`,
        r.idRegistration,
        r.Confirmation,
        r.Expected_Pay_Type,
        r.`Timestamp`,
        r.`Updated_By`,
        r.`Checkin_Notes`,
        ifnull(hs.idPsg, 0) as `idPsg`,
        ifnull(rg.idGuest, 0) as `Patient_Staying`,
        CASE WHEN s.Value = 'true' AND r.`Status` in ('a', 'uc',  'w') THEN ifnull(
	        (select sum(invoice_line.Amount)
			from
	        invoice_line
	            join
	        invoice ON invoice_line.Invoice_Id = invoice.idInvoice
			        AND invoice_line.Item_Id = 10
			        AND invoice_line.Deleted = 0
	            join
	    	reservation_invoice ON invoice.idInvoice = reservation_invoice.Invoice_Id
		    where
		        invoice.Deleted = 0
		        AND invoice.Order_Number = 0
		        AND reservation_invoice.Reservation_Id = r.idReservation
		        AND invoice.`Status` = 'p'), 0)
		ELSE 0 END as `PrePaymt`,
		ifnull(na.Set_Incomplete, 0) as `Incomplete_Address`
    from
        `reservation` `r`
            left join
        `hospital_stay` `hs` ON `r`.`idHospital_Stay` = `hs`.`idHospital_stay`
            left join
        `reservation_guest` rg on r.idReservation = rg.idReservation and hs.idPatient = rg.idGuest
            left join
        `hospital` `h` on hs.idAssociation = h.idHospital
            left join
        `resource` re ON r.idResource = re.idResource
            left join
        `name` `n` ON `r`.`idGuest` = `n`.`idName`
            left join
		`name_phone` np on n.idName = np.idName and n.Preferred_Phone = np.Phone_Code
            left join
        `name_address` na on n.idName = na.idName and na.Purpose = 1
        	left join
        `name` n2 ON hs.idPatient = n2.idName
            left join
        gen_lookups gs on gs.`Table_Name` = 'Name_Suffix' and gs.`Code` = n.Name_Suffix
            left join
        gen_lookups gs2 on gs2.`Table_Name` = 'Name_Suffix' and gs2.`Code` = n2.Name_Suffix
            left join
        gen_lookups gl on gl.`Table_Name` = 'Location' and gl.`Code` = hs.Location
            left join
        gen_lookups gd on gd.`Table_Name` = 'Diagnosis' and gd.`Code` = hs.Diagnosis
        , sys_config s
	where
		s.Key = 'AcceptResvPaymt';



-- -----------------------------------------------------
-- View `vresv_guest`
-- -----------------------------------------------------
CREATE or replace VIEW `vresv_guest` AS
select
    hs.idPsg,
    rg.idGuest as `idGuest`,
    rg.idReservation,
    r.Expected_Arrival as `Arrival_Date`,
    r.Expected_Departure as `Departure_Date`,
    r.`Status`,
    r.idResource,
    'r' as `Source`
from reservation_guest rg
	left join
    reservation r on rg.idReservation = r.idReservation
	left join
    hospital_stay hs on r.idHospital_Stay = hs.idHospital_stay
where r.`Status` in ('a', 'uc', 'w')
union
select
    hs.idPsg,
    s.idName as `idGuest`,
    v.idReservation,
    s.Span_Start_Date as `Arrival_Date`,
    datedefaultnow(s.Expected_Co_Date)  as `Departure_Date`,
    's' as `Status`,
    v.idResource,
    'v' as `Source`
from stays s
	left join
    visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
	left join
    hospital_stay hs on v.idHospital_Stay = hs.idHospital_stay
where s.`Status` = 'a';



-- -----------------------------------------------------
-- View `vreservation_guests`
-- -----------------------------------------------------
create or replace view `vreservation_guests` as
SELECT
    r.idReservation,
    r.idGuest,
    ng.idPsg,
    n.Name_Full,
    CASE WHEN (np.Phone_Code = 'no') THEN 'No Phone' ELSE ifnull(np.phone_Num, '') END as `Phone_Num`,
    r.`Primary_Guest`,
    g.Description AS `Relationship_Code`
FROM
    reservation_guest r
        LEFT JOIN
    `name` n ON r.idGuest = n.idName
        LEFT JOIN
    `name_phone` np ON r.idGuest = np.idName
        AND n.Preferred_Phone = np.Phone_Code
        LEFT JOIN
    name_guest ng ON r.idGuest = ng.idName
        LEFT JOIN
    gen_lookups g ON g.Table_Name = 'Patient_Rel_Type'
        AND g.Code = ng.Relationship_Code;



-- -----------------------------------------------------
-- View `vreservation_log`
-- -----------------------------------------------------
create or replace view vreservation_log as
select
	l.Sub_Type,
	l.User_Name,
	l.idName,
	l.idReservation,
	l.idRoom_Rate,
	l.idAgent,
	l.idResource,
	l.Log_Text,
	l.Timestamp,
	n.Name_First,
	n.Name_Last,
	n2.Name_First as `Agent_First`,
	n2.Name_Last as `Agent_Last`,
	r.Title
from reservation_log l left join name n on l.idName = n.idName
	left join name n2 on l.idAgent = n2.idname
	left join resource r on l.idResource = r.idResource
where l.log_Type = 'reservation'
order by l.idReservation, l.Timestamp;




-- -----------------------------------------------------
-- View `vresources_listing`
-- -----------------------------------------------------
Create or replace view vresources_listing as
select
    re.idResource as `idRes`,
    re.`Title`,
    sum(g.Substitute) as `Rate`,
    sum(g2.Substitute) as `Key_Deposit`,
    re.`Type` as `Resource_Type`,
    re.`Category`,
    re.Util_Priority,
    re.Status,
    re.Background_Color,
    re.Text_Color,
    re.Border_Color,
    case
        when re.Type = 'part' then re.Partition_Size
        else sum(r.Max_Occupants)
    end as Max_Occupants,
    count(r.idRoom) as Rooms
from
    room r
        join
    resource_room rr ON r.idRoom = rr.idRoom
        join
    resource re ON rr.idResource = re.idResource
        left join
    gen_lookups g ON g.Table_Name = 'Static_Room_Rate' and g.Code = r.Rate_Code
        left join
    gen_lookups g2 ON g2.Table_Name = 'Key_Deposit_Code' and g2.Code = r.Key_Deposit_Code
group by re.idResource
order by re.Util_Priority;





-- -----------------------------------------------------
-- View `vresources_ready`
-- -----------------------------------------------------
CREATE or replace VIEW `vresources_ready` AS
    select
        `re`.`idResource` AS `idRes`,
        `re`.`Title` AS `Title`,
    sum(g.Substitute) as `Rate`,
    sum(g2.Substitute) as `Key_Deposit`,
        `re`.`Type` AS `Resource_Type`,
        (case
            when (`re`.`Type` = 'part') then `re`.`Partition_Size`
            else sum(`r`.`Max_Occupants`)
        end) AS `Max_Occupants`,
        count(`r`.`idRoom`) AS `Rooms`
    from
        ((`room` `r`
        join `resource_room` `rr` ON ((`r`.`idRoom` = `rr`.`idRoom` and r.`Availability` = 'a' and r.`State` = 'a')))
        join `resource` `re` ON ((`rr`.`idResource` = `re`.`idResource`)))
        left join
    gen_lookups g ON g.Table_Name = 'Static_Room_Rate' and g.Code = r.Rate_Code
        left join
    gen_lookups g2 ON g2.Table_Name = 'Key_Deposit_Code' and g2.`Code` = r.`Key_Deposit_Code`
    where re.`Status` = 'a'  and
        (case
            when
                (`re`.`Type` = 'part')
            then
                (`re`.`Partition_Size` <= (`r`.`Max_Occupants` - (select
                        count(`stays`.`idRoom`)
                    from
                        `stays`
                    where
                        ((`stays`.`idRoom` = `rr`.`idRoom`)
                            and (`stays`.`Status` = 'a')))))
            else (not (`rr`.`idRoom` in (select
                    `stays`.`idRoom`
                from
                    `stays`
                where
                    (`stays`.`Status` = 'a'))))
        end)
    group by `re`.`idResource`
    having (case
        when
            (`Resource_Type` = 'block')
        then
            (`Rooms` = (select
                    count(`resource_room`.`idRoom`)
                from
                    `resource_room`
                where
                    (`resource_room`.`idResource` = `idRes`)))
        else (`Rooms` > 0)
    end)
    order by `re`.`Type` desc , `re`.`Util_Priority`;


-- -----------------------------------------------------
-- View `vresv_patient`
-- -----------------------------------------------------
CREATE or replace VIEW `vresv_patient` AS
        select
        `r`.`idReservation` AS `idReservation`,
        `r`.`idRegistration` AS `idRegistration`,
        `r`.`idGuest` AS `idGuest`,
        `r`.`idHospital_Stay` AS `idHospital_Stay`,
        `r`.`idResource` AS `idResource`,
        `r`.`Resource_Suitable` AS `Resource_Suitable`,
        `r`.`Room_Rate_Category` AS `Room_Rate_Category`,
        `r`.`Fixed_Room_Rate` AS `Fixed_Room_Rate`,
        `r`.`Rate_Adjust` AS `Rate_Adjust`,
        `r`.`Visit_Fee` AS `Visit_Fee`,
        `r`.`idRoom_rate` AS `idRoom_rate`,
        `r`.`Confirmation` AS `Confirmation`,
        ifnull(`re`.`Title`, '') AS `Title`,
        `r`.`Type` AS `Type`,
        `r`.`Expected_Pay_Type` AS `Expected_Pay_Type`,
        `r`.`Expected_Arrival` AS `Expected_Arrival`,
        `r`.`Expected_Departure` AS `Expected_Departure`,
        `r`.`Actual_Arrival` AS `Actual_Arrival`,
        `r`.`Actual_Departure` AS `Actual_Departure`,
        `r`.`Number_Guests` AS `Number_Guests`,
        `r`.`Add_Room` AS `Add_Room`,
        `r`.`Notes` AS `Notes`,
        `r`.`Checkin_Notes` AS `Checkin_Notes`,
        `r`.`Status` AS `Status`,
        `r`.`Updated_By` AS `Updated_By`,
        `r`.`Last_Updated` AS `Last_Updated`,
        `r`.`Timestamp` AS `Timestamp`,
        ifnull(`n`.`Name_Full`, '') AS `Patient_Name`,
        ifnull(`n`.`idName`, 0) AS `idPatient`,
        ifnull(`h`.`idPsg`, 0) AS `idPsg`,
        ifnull(v.idVisit, 0) as idVisit,
        ifnull(v.Span, 0) as Span,
        ifnull(v.Status, '') as Visit_Status,
        CASE WHEN s.Value = 'true' and r.`Status` in ('a', 'uc', 'w')
          THEN ifnull((select sum(il.Amount)
			from
			    invoice_line il
			        join
			    invoice i ON il.Invoice_Id = i.idInvoice  and i.Deleted = 0 and i.Status = 'p'
					join
				reservation_invoice ri ON i.idInvoice = ri.Invoice_Id
			where
			    il.Item_Id = 10
					AND i.Order_Number = 0
			        and il.Deleted = 0
                    AND `ri`.`Reservation_Id` = `r`.`idReservation`), 0)
		  ELSE 0 END as `PrePaymt`
    from
        `reservation` `r`
        left join visit v on r.idReservation = v.idReservation and v.Status = 'a'
        left join `hospital_stay` `h` ON `r`.`idHospital_Stay` = `h`.`idHospital_stay`
        left join resource re on r.idResource = re.idResource
        left join `name` `n` ON `h`.`idPatient` = `n`.`idName`
        left join `sys_config` `s` ON `s`.`Key` = 'AcceptResvPaymt';




-- -----------------------------------------------------
-- View `vroom_listing`
-- -----------------------------------------------------
create or replace view vroom_listing as
SELECT
    r.idRoom as `Room Id`,
    r.Title,
    ifnull(r.Description, '') as Description,
    r.Type,
    r.Util_Priority as Priority,
    r.Status,
    ifnull(l.Title, '') as Location,
    ifnull(v.Name_Last, '') as Owner,
    g.Description as Rate,
    ifnull(r.Last_Updated, '') as `Last Updated`,
    r.Updated_By as `Updated By`
FROM
    room r
        left join
    location l ON r.idLocation = l.idLocation
        left join
    vmember_listing v ON r.Owner_id = v.Id
        left join
    gen_lookups g ON g.Table_Name = 'Rate_Code' and g.Code = r.Rate_Code
order by r.Util_Priority,r.Title;



-- -----------------------------------------------------
-- View `vroom_paygateway`
-- -----------------------------------------------------
CREATE  OR REPLACE VIEW `vroom_paygateway` AS
Select r.idRoom, ifnull(l.Merchant, '') as `Merchant`
from
    room r
        left join
    location l on r.idLocation = l.idLocation;



-- -----------------------------------------------------
-- View `vspan_listing`
-- -----------------------------------------------------
CREATE or replace VIEW `vspan_listing` AS
    select
        `v`.`idVisit`,
        `v`.`Span`,
        `r`.`idPsg` AS `idPsg`,
        `r`.`Pref_Token_Id`,
        `v`.`idRegistration`,
        `v`.`idReservation`,
        `v`.`idResource`,
        `v`.`idHospital_stay`,
        `v`.`idPrimaryGuest`,
        `v`.`Arrival_Date`,
        `v`.`Key_Dep_Disposition`,
        `v`.`DepositPayType`,
        `v`.`Expected_Departure`,
        `v`.`Actual_Departure`,
        `v`.`Span_Start`,
        `v`.`Span_End`,
        (to_days(ifnull(`v`.`Actual_Departure`, now())) - to_days(`v`.`Arrival_Date`)) AS `Actual_Nights`,
        (to_days(`v`.`Expected_Departure`) - to_days(`v`.`Arrival_Date`)) AS `Expected_Nights`,
        (to_days(ifnull(`v`.`Span_End`, now())) - to_days(`v`.`Span_Start`)) AS `Actual_Span_Nights`,
        (to_days(`v`.`Expected_Departure`) - to_days(`v`.`Span_Start`)) AS `Expected_Span_Nights`,
        `v`.`Return_Date`,
        `v`.`Notice_to_Checkout`,
        `v`.`Ext_Phone_Installed`,
        `v`.`OverRideMaxOcc`,
        '' as `Visit_Notes`,
        `v`.`Notes` AS `Notes`,
        `v`.`Status`,
        ifnull(`g2`.`Description`, '') AS `Status_Title`,
        `v`.`Updated_By`,
        `v`.`Last_Updated`,
        ifnull(`re`.`Title`, '') AS `Title`,
        v.Expected_Rate,
        `v`.`Pledged_Rate`,
        `v`.`Rate_Category`,
        v.idRoom_Rate,
        v.idRateAdjust,
        v.Rate_Glide_Credit,
        case when ifnull(hs.MRN, '') = '' then ifnull(n.Name_Full, '') else concat( ifnull(n.Name_Full, '') ,' (' , ifnull(hs.MRN, '') , ')') end
         as `Patient_Name`,
	ifnull(hs.idHospital, 0) as `idHospital`,
	ifnull(hs.idAssociation, 0) as `idAssociation`
    from
        `visit` `v`
        left join `resource` `re` ON `v`.`idResource` = `re`.`idResource`
        left join `registration` `r` ON `v`.`idRegistration` = `r`.`idRegistration`
        left join `hospital_stay` `hs` ON `v`.`idHospital_stay` = `hs`.`idHospital_stay`
        left join `name` n on hs.idPatient = n.idName
        left join `gen_lookups` `g2` ON `g2`.`Table_Name` = 'Visit_Status'
            and `g2`.`Code` = `v`.`Status`
	WHERE v.`Status` <> 'c';


-- -----------------------------------------------------
-- View `vstays_listing`
-- -----------------------------------------------------
create or replace view vstays_listing as
select
    s.idName,
    s.idStays,
    s.idVisit,
    s.Visit_Span,
    s.idRoom,
    ifnull(r.Title,'') as `Room`,
    (case when n.Name_Nickname = '' then n.Name_First else n.Name_Nickname end) as Name_First,
    (case when n.Name_Suffix = '' then n.Name_Last else concat(n.Name_Last, ' ', n.Name_Suffix) end) as `Name_Last`,
    s.Status,
    v.Status as `Visit_Status`,
    v.idRegistration as `idRegistration`,
    v.DepositPayType as DepositPayType,
    g.Description as `Status_Title`,
    s.Checkin_Date,
    s.Checkout_Date,
    s.Expected_Co_Date,
    s.Span_Start_Date,
    s.Span_End_Date,
    s.On_Leave,
    rg.idPsg,
    ng.Relationship_Code
from
    stays s left join `name` n ON s.idName = n.idName
            left join visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
            left join room r on s.idRoom = r.idRoom
            left join registration rg on v.idRegistration = rg.idRegistration
            left join name_guest ng on s.idName = ng.idName and rg.idPsg = ng.idPsg
            left join gen_lookups g on s.Status = g.`Code` and g.`Table_Name` = 'Visit_Status';




-- -----------------------------------------------------
-- View `vstays_log`
-- -----------------------------------------------------
create or replace view vstays_log as
select
	l.Sub_Type,
	l.User_Name,
	l.idName,
	l.idVisit,
	l.Span,
	l.idStay,
	l.idRr,
	l.Log_Text,
	l.Timestamp,
	n.Name_First,
	n.Name_Last,
	r.Title,
        rg.idPsg
from visit_log l left join name n on l.idName = n.idName
	left join room r on l.idRr = r.idRoom
        left join registration rg on l.idRegistration = rg.idRegistration
where l.log_Type = 'stay'
order by l.idVisit, l.Span, l.idStay, l.Timestamp;


-- -----------------------------------------------------
-- View `vsyslog`
-- -----------------------------------------------------
CREATE  OR REPLACE VIEW `vsyslog` AS
    SELECT
        `house_log`.`Log_Type` AS `Log_Type`,
        `house_log`.`Sub_Type` AS `Sub_Type`,
        `house_log`.`User_Name` AS `User_Name`,
        `house_log`.`Id1` AS `Id1`,
        `house_log`.`Str1` AS `Str1`,
        `house_log`.`Str2` AS `Str2`,
        `house_log`.`Log_Text` AS `Log_Text`,
        `house_log`.`Timestamp` AS `Timestamp`
    FROM
        `house_log`;

-- -----------------------------------------------------
-- View `vvisit_patient`
-- -----------------------------------------------------
CREATE OR REPLACE VIEW vvisit_patient AS
    SELECT
        v.idReservation,
        v.idPrimaryGuest,
        v.Arrival_Date,
        v.Expected_Departure,
        r.Title as `Room`,
        hs.idPsg,
        hs.idPatient,
        n.Name_Full AS `Patient_Name`,
        (SELECT
                COUNT(*)
            FROM
                stays
            WHERE
                idVisit = v.idVisit
                    AND Visit_Span = v.Span
                    AND `Status` = 'a') AS `NumberGuests`
    FROM
        visit v
            LEFT JOIN
        resource r on v.idResource = r.idResource
            LEFT JOIN
        hospital_stay hs ON v.idHospital_stay = hs.idHospital_stay
            LEFT JOIN
        name n ON hs.idPatient = n.idName
    WHERE
        v.`Status` = 'a';



-- -----------------------------------------------------
-- View `vvisit_stmt`
-- -----------------------------------------------------
CREATE or replace VIEW `vvisit_stmt` AS
select
    v.idVisit,
    v.Span,
    v.idRegistration,
    v.idPrimaryGuest,
    v.idResource,
    ifnull(v.Arrival_Date, '') as `Arrival_Date`,
    ifnull(v.Expected_Departure, '') as `Expected_Departure`,
    ifnull(v.Actual_Departure, '') as `Actual_Departure`,
    ifnull(v.Span_Start, '') as `Span_Start`,
    ifnull(v.Span_End, '') as `Span_End`,
    v.`Status`,
    v.Rate_Glide_Credit,
    ifnull(rm.Title, '') as `Title`,
    ifnull(g.Substitute, 0) as Deposit_Amount,
    v.DepositPayType,
    v.Pledged_Rate,
    v.Rate_Category,
    v.idRoom_Rate,
    v.Expected_Rate,
    rv.Visit_Fee as `Visit_Fee_Amount`,
    DATEDIFF(ifnull(v.Span_End, now()), v.Span_Start) as `Actual_Span_Nights`,
    ifnull(hs.idPsg, 0) as `idPsg`,
    ifnull(hs.idHospital, 0) as `idHospital`,
    ifnull(hs.idAssociation, 0) as `idAssociation`
from
    visit v
        left join
    reservation rv on v.idReservation = rv.idReservation
        left join
    hospital_stay hs on v.idHospital_stay = hs.idHospital_stay
        left join
    resource_room re ON v.idResource = re.idResource
        left join
    room rm on re.idRoom = rm.idRoom
        left join
    gen_lookups g on g.`Table_Name` = 'Key_Deposit_Code' and g.`Code` = rm.Key_Deposit_Code
WHERE v.`Status` <> 'c';





-- -----------------------------------------------------
-- View `vvol_categories2`
-- -----------------------------------------------------
CREATE or replace VIEW `vvol_categories2` AS
select `vm`.`Id` AS `Id`,
(case when (`vm`.`MemberRecord` = 1) then `vm`.`Name_Last` else `vm`.`Company` end) AS `Name_Last`,
(case when (`vm`.`MemberRecord` = 1) then `vm`.`Name_First` else '' end) AS `Name_First`,
`nv`.`Vol_Code` AS `Vol_Code`,
`gc`.`Description` AS `Category`,
`nv`.`Vol_Category` AS `Category_Code`,
(case when (ifnull(`g`.`Code`,'') <> '') then ifnull(`g`.`Description`,'') end) AS `Description`,
`nv`.`Vol_Status` AS `Vol_Status`,
(case when (`vm`.`Member_Type` = 'ai') then `vm`.`Fullname` when (`vm`.`Member_Type` <> 'ai') then `vm`.`Company` else '' end) AS `Name_Full`,
`vm`.`Preferred_Phone` AS `PreferredPhone`,
`vm`.`Preferred_Email` AS `PreferredEmail`,
concat_ws(' ',`vm`.`Address_1`,`vm`.`Address_2`,`vm`.`City`,`vm`.`StateProvince`,`vm`.`PostalCode`) AS `Full_Address`,
concat_ws(' ',`vm`.`Address_1`,`vm`.`Address_2`) AS `Address`,
`vm`.`City` AS `City`,
`vm`.`StateProvince` AS `State`,
`vm`.`PostalCode` AS `Zip`,
'' AS `Title`,
'' AS `Begin_Active`,
'' AS `End_Active`,
ifnull(`nv`.`Vol_Notes`,'') AS `Vol_Notes`,
`vm`.`Member_Type` AS `Member_Type`,
`nv`.`Vol_Begin` AS `Vol_Begin`,
`nv`.`Vol_End` AS `Vol_End`,
ifnull(`gr`.`Description`,'') AS `Vol_Rank`,
`nv`.`Vol_Check_Date` AS `Check_Date`,
`nv`.`Vol_Rank` AS `Vol_Rank_Code`
from `vmember_listing` `vm` join `name_volunteer2` `nv` on `vm`.`Id` = `nv`.`idName` and `vm`.`MemberStatus` = 'a'
-- left join `dormant_schedules` `d` on(((`nv`.`Dormant_Code` = `d`.`Code`) and (`d`.`Status` = 'a'))))
left join `gen_lookups` `g` on `nv`.`Vol_Code` = `g`.`Code` and `g`.`Table_Name` = `nv`.`Vol_Category`
left join `gen_lookups` `gr` on `nv`.`Vol_Rank` = `gr`.`Code` and `gr`.`Table_Name` = 'Vol_Rank'
left join `gen_lookups` `gc` on `nv`.`Vol_Category` = `gc`.`Code` and `gc`.`Table_Name` = 'Vol_Category';



-- -----------------------------------------------------
-- View `vweb_users`
-- -----------------------------------------------------
CREATE or replace VIEW `vweb_users` AS
select
    v.idName AS Id,
    concat_ws(' ', v.Name_First, v.Name_Last) AS Name,
    u.User_Name AS Username,
    ifnull(i.Name, "Local") as "Type",
    gs.Description AS Status,
    gr.Description AS Role,
    u.Default_Page AS `Default Page`,
    wg.Title as `Authorization Code`,
    ifnull(u.Last_Login, '') AS `Last Login`,
    IF(`u`.`idIdp` > 0, '', ifnull(`u`.`PW_Change_Date`, '')) AS `Password Changed`,
    CASE
        WHEN `u`.`idIdp` > 0 THEN CONCAT('Managed by ', `i`.`Name`)
        WHEN `u`.`Chg_PW` THEN 'Next Login'
        WHEN (`u`.`pass_rules` = 0 || `sc`.`Value` = 0) THEN 'Never'
        WHEN `u`.`PW_Change_Date`
            THEN DATE_ADD(`u`.`PW_Change_Date`, INTERVAL `sc`.`Value` DAY)
        WHEN `u`.`Timestamp`
            THEN DATE_ADD(`u`.`Timestamp`, INTERVAL `sc`.`Value` DAY)
        ELSE ''
    END AS `Password Expires`,
    CASE
		WHEN `u`.`idIdp` > 0 THEN 'N/A'
        WHEN (`u`.`totpSecret` = '' AND `u`.`emailSecret` = '' AND `u`.`backupSecret` = '') THEN 'No'
        ELSE 'Yes'
	END AS `Two Factor Enabled`,
    u.Updated_By AS `Updated By`,
    ifnull(u.Last_Updated, '') AS `Last Updated`
from
    ((((w_users u
    left join `name` v ON ((u.idName = v.idName)))
    left join w_auth a ON ((u.idName = a.idName)))
	left join id_securitygroup s on s.idName = u.idName
	left join w_groups wg on s.Group_Code = wg.Group_Code
    left join gen_lookups gr ON (((a.Role_Id = gr.Code) and (gr.Table_Name = 'Role_Codes'))))
    left join gen_lookups gs ON (((u.Status = gs.Code) and (gs.Table_Name = 'Web_User_Status')))
    left join sys_config sc ON (sc.Key = 'passResetDays')
    left join w_idp i on (u.idIdp = i.idIdp));


CREATE OR REPLACE VIEW `vcurrent_operating_hours` AS
select * from operating_schedules where End_Date is null group by Day having max(idDay);