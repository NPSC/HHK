-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2017 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK


-- --------------------------------------------------------
--
-- Procedure `get_credit_gw`
--
DROP procedure IF EXISTS `get_credit_gw`; -- ;

CREATE PROCEDURE `get_credit_gw`(
    IN visitId INT,
    IN spanId INT,
    IN regId INT)
BEGIN

    DECLARE myResc INT;

    if (visitId > 0) then
	Select ifnull(idResource, 0) into myResc from visit where idVisit = visitId and Span = spanId;
    ELSE
	select ifnull(r.idResource, 0) into myResc from reservation r join registration rg on r.idRegistration = rg.idRegistration where rg. idRegistration = regId order by r.idReservation DESC limit 0, 1;
    END IF;

    if (myResc > 0) THEN

        SELECT
            ifnull(l.Merchant, '') as `Merchant`, l.idLocation
        FROM
            resource_room rr
                LEFT JOIN
            room rm on rm.idRoom = rr.idRoom
                LEFT JOIN
            location l on l.idLocation = rm.idLocation
        where
            l.Status = 'a' and rr.idResource = myResc;

    ELSE

        SELECT
           DISTINCT ifnull(l.Merchant, '') as `Merchant`, l.idLocation
        FROM
            room rm
                LEFT JOIN
            location l  on l.idLocation = rm.idLocation
        where
            l.`Status` = 'a' or l.`Status` is null;

    END if;
END -- ;



-- --------------------------------------------------------
--
-- Procedure `gl_report`
--
DROP procedure IF EXISTS `gl_report`; -- ;

CREATE PROCEDURE `gl_report` (
	IN pmtStart VARCHAR(15),
    IN pmtEnd VARCHAR(15))
BEGIN
	create temporary table idinp (idInvoice int NOT NULL, PRIMARY KEY (idInvoice));
	create temporary table idind (idInvoice int);

	replace into idinp
		select
			`pi`.`Invoice_Id`
		FROM
			`payment` `p`
			JOIN `payment_invoice` `pi` ON `p`.`idPayment` = `pi`.`Payment_Id`
		where
            ((DATE(`p`.`Payment_Date`) >= DATE(pmtStart) && DATE(`p`.`Payment_Date`) < DATE(pmtEnd))
			OR (DATE(`p`.`Last_Updated`) >= DATE(pmtStart) && DATE(`p`.`Last_Updated`) < DATE(pmtEnd)));

	insert into idind
		select idInvoice from invoice where Delegated_Invoice_Id in (select idinvoice from idinp);

	replace into idinp select idInvoice from idind;

	select  `i`.`idInvoice`,
		`i`.`Order_Number`,
		`i`.`Suborder_Number`,
        `i`.`Amount` AS `iAmount`,
        `i`.`Balance` as `iBalance`,
        `i`.`Status` AS `iStatus`,
        `i`.`Carried_Amount` AS `icAmount`,
        `i`.`Invoice_Number` AS `iNumber`,
        `i`.`Delegated_Invoice_Id` AS `Delegated_Id`,
        `i`.`Deleted` AS `iDeleted`,
        ifnull(`v`.`Pledged_Rate`, 0) as `Pledged_Rate`,
        ifnull(`v`.`Expected_Rate`, 0) as `Expected_Rate`,
        ifnull(`v`.`idRoom_Rate`, 0) as `idRoom_Rate`,
        case when ifnull(`v`.`Expected_Rate`, 0) = 0 THEN ifnull(`rr`.`Reduced_Rate_1`, 0)
        	ELSE (1 + (`v`.`Expected_Rate` / 100)) * `rr`.`Reduced_Rate_1` END as `Rate`,
        ifnull(`il`.`idInvoice_Line`, '') as `il_Id`,
        ifnull(`il`.`Amount`, 0) as `il_Amount`,
		ifnull(`il`.`Item_Id`, 0) as `il_Item_Id`,
 		ifnull(`il`.`Type_Id`, 0) as `il_Type_Id`,
        IFNULL(`p`.`idPayment`, 0) AS `idPayment`,
        IFNULL(`p`.`Amount`, 0) AS `pAmount`,
        IFNULL(`p`.`idPayment_Method`, 0) AS `pMethod`,
        IFNULL(`p`.`Status_Code`, 0) AS `pStatus`,
        IFNULL(`p`.`Last_Updated`, '') AS `pUpdated`,
        IFNULL(`p`.`Is_Refund`, 0) AS `Is_Refund`,
        IFNULL(`p`.`idPayor`, 0) AS `idPayor`,
        IFNULL(`p`.`Payment_Date`, '') as `pTimestamp`,
        IFNULL(`pm`.`Gl_Code`, '') as `PayMethod_Gl_Code`,
		IFNULL(`it`.`Gl_Code`, '') as `Item_Gl_Code`,
		IFNULL(`nd`.`Gl_Code_Debit`, '') as `ba_Gl_Debit`,
        IFNULL(`nd`.`Gl_Code_Credit`, '') as `ba_Gl_Credit`
	from
        `invoice` `i`
        Join idinp on i.idInvoice = idinp.idInvoice
        LEFT JOIN `payment_invoice` `pi` ON `pi`.`Invoice_Id` = `i`.`idInvoice`
        LEFT JOIN `payment` `p` ON `p`.`idPayment` = `pi`.`Payment_Id`
        left join `payment_method` `pm` on `p`.`idPayment_Method` = `pm`.`idPayment_method`
        JOIN `invoice_line` `il` on `i`.`idInvoice` = `il`.`Invoice_Id` and `il`.`Deleted` < 1
        left join `visit` `v` on `i`.`Order_Number` = `v`.`idVisit` and `i`.`Suborder_Number` = `v`.`Span`
        left join `room_rate` `rr` on `v`.`idRoom_Rate` = `rr`.`idRoom_rate`
		LEFT JOIN name_demog nd on p.idPayor = nd.idName
		LEFT JOIN item it on it.idItem = il.Item_Id
	ORDER BY i.idInvoice, il.idInvoice_Line, p.idPayment;

	drop table idinp;
	drop table idind;
END -- ;



-- --------------------------------------------------------
--
-- Procedure `sum_visit_days`
--
DROP procedure IF EXISTS `sum_visit_days`; -- ;

CREATE PROCEDURE `sum_visit_days`(
    IN targetYear int
)
BEGIN

	Declare startDate varchar(12);
	Declare endDate varchar(12);
	
	Select concat_ws('-', (targetYear + 1), '01', '01') into endDate;
	Select concat_ws('-', (targetYear), '01', '01') into startDate;
	
	select sum(
		datediff(
	             case when DATE(ifnull(Span_End, NOW())) >= DATE(endDate) then DATE(endDate) else DATE(ifnull(Span_End, NOW())) end
	            , case when  DATE(Span_Start) < DATE(startDate) then DATE(startDate) else  DATE(Span_Start) end)
	    )
	    as numNites
	from visit
	Where `Status` <> 'c' and  DATE(Span_Start) < DATE(endDate) and DATE(ifnull(Span_End, NOW())) >= DATE(startDate);

END -- ;



-- --------------------------------------------------------
--
-- Procedure `sum_visit_Days_fy`
--
DROP procedure IF EXISTS `sum_visit_Days_fy`;  -- ;

CREATE PROCEDURE `sum_visit_Days_fy` (
    IN targetYear int,
    IN fy_month int
)
BEGIN
	Declare startDate datetime;
	Declare endDate datetime;
	
	Select date_sub(str_to_Date(concat_ws('-', (targetYear), '01', '01'), '%Y-%m-%d'), INTERVAL fy_month MONTH) into startDate;
	select date_sub(str_to_Date(concat_ws('-', (targetYear + 1), '01', '01'), '%Y-%m-%d'), INTERVAL fy_month MONTH) into endDate;
	
	select sum(
		datediff(
	             case when DATE(ifnull(Span_End, NOW())) > DATE(endDate) then DATE(endDate) else DATE(ifnull(Span_End, NOW())) end
	            , case when  DATE(Span_Start) < DATE(startDate) then DATE(startDate) else  DATE(Span_Start) end
                )
	    )
	    as numNites
	from visit
	Where `Status` <> 'c' and DATE(Span_Start) < DATE(endDate) and DATE(ifnull(Span_End, NOW())) >= DATE(startDate);

END -- ;


-- --------------------------------------------------------
--
-- Procedure `updt_visit_hospstay`
--
DROP procedure IF EXISTS `updt_visit_hospstay`; -- ;

CREATE PROCEDURE `updt_visit_hospstay` (
	IN idV int, 
	IN idHospitalStay int)
BEGIN
	update visit set `idHospital_stay` = idHospitalStay where `idVisit` = idV;
    
    update reservation r join visit v on r.idReservation = v.idReservation
		set r.idHospital_Stay = idHospitalStay
        where v.idVisit = idV and v.Span = 0;
END -- ;




-- --------------------------------------------------------
--
-- Procedure `sum_stay_days`
--
DROP procedure IF EXISTS `sum_stay_days`; -- ;

CREATE PROCEDURE `sum_stay_days`(
	IN targetYear int,
    IN fy_month int
)
BEGIN

	Declare startDate varchar(12);
	Declare endDate varchar(12);

	Select date_sub(str_to_Date(concat_ws('-', (targetYear), '01', '01'), '%Y-%m-%d'), INTERVAL fy_month MONTH) into startDate;
	select date_sub(str_to_Date(concat_ws('-', (targetYear + 1), '01', '01'), '%Y-%m-%d'), INTERVAL fy_month MONTH) into endDate;

	select sum(
		datediff(
             case when DATE(IFNULL(Span_End_Date, NOW())) > DATE(endDate) then DATE(endDate) else DATE(IFNULL(Span_End_Date, NOW())) end
             , case when  DATE(Span_Start_Date) < DATE(startDate) then DATE(startDate) else  DATE(IFNULL(Span_Start_Date, NOW())) end)
        )
        as numNites
	from stays
	Where `On_Leave` = 0 and DATE(Span_Start_Date) < DATE(endDate) and DATE(IFNULL(Span_End_Date, NOW())) >= DATE(startDate);

END -- ;




-- --------------------------------------------------------
--
-- Procedure `constraint_room`
--
DROP procedure IF EXISTS `constraint_room`; -- ;

CREATE PROCEDURE `constraint_room` (resvId int)
BEGIN

	Declare rId int;

	-- Pick up the reserv id's
	select
	    CASE WHEN ce3.idEntity is null THEN 0 Else r.idReservation  END
			into rId
	from hospital_stay hs
		join reservation r on hs.idHospital_stay = r.idHospital_stay
	    left join constraint_entity ce3 on ce3.idEntity = r.idReservation and ce3.`Type` = 'rv'
	where r.idReservation = resvId LIMIT 1;

	if (rId) > 0 THEN
		-- find the rooms that have the attributes.
		select idEntity, count(idEntity) as `num`
		from attribute_entity
		where idAttribute in (
			select ca.idAttribute
			from constraint_entity ce join constraint_attribute ca on ce.idConstraint = ca.idConstraint and ca.Operation = ''
			where ce.idEntity = rId)
		group by idEntity having `num` = (
			select count(ca.idAttribute)
			from constraint_entity ce join constraint_attribute ca on ce.idConstraint = ca.idConstraint and ca.Operation = ''
			where ce.idEntity = rId);
	ELSE
		-- there are no constraints.
		select 0 as `idEntity`, 0 as `num`;
	END if;

END -- ;




-- --------------------------------------------------------
--
-- Procedure `delete_inv_payment`
--
DROP procedure IF EXISTS `delete_inv_payment`; -- ;

CREATE PROCEDURE `delete_inv_payment`(IN inv_number varchar(45))
BEGIN

    DECLARE idPay int;
    DECLARE idInv int;

    select i.idInvoice into idInv from invoice i where i.Invoice_Number = inv_number;

    select p.idPayment into idPay
    from payment p left join payment_invoice pi on p.idPayment = pi.Payment_Id
    where pi.Invoice_Id = idInv;


    delete from payment where idPayment = idPay;
    delete from payment_invoice where Payment_Id = idPay;
    update invoice set Deleted = 1 where idInvoice = idInv;
    update invoice_line set Deleted = 1 where Invoice_Id = idInv;

END -- ;



-- --------------------------------------------------------
--
-- Procedure `delete_names_u_tbd`
--
DROP procedure IF EXISTS `delete_names_u_tbd`; -- ;

CREATE PROCEDURE `delete_names_u_tbd`()
-- assumes you already deleted any payments, invoices, visits and stays

BEGIN

	DECLARE exit handler for sqlexception
	BEGIN
		GET DIAGNOSTICS CONDITION 1 @text = MESSAGE_TEXT;
        IF @@in_transaction = 1
        THEN
			ROLLBACK;
		END IF;
		SELECT CONCAT('ERROR: Cannot delete names. No changes made.<br>', @text) as `error`;
	END;

    IF @@in_transaction = 0
    THEN
		START TRANSACTION;
        SET @tranLevel = 0;
	ELSE
		SET @tranLevel = @tranLevel + 1;
	END IF;

	-- collect all deletable names.
	create temporary table tids (idName int);
	insert into tids (idName) select idName from name where (Member_Status = 'u' or Member_Status = 'TBD');
	select count(*) into @numMembers from tids;
    
	delete p from photo p where p.idPhoto in (select Guest_Photo_Id from name_demog nd join tids n on nd.idName = n.idName); 
    delete na from volunteer_hours na join tids n on na.idName = n.idName;
	update donations d join tids n on d.Care_Of_Id = n.idName set d.Care_Of_Id = 0;
	update donations d join tids n on d.Assoc_Id = n.idName set d.Assoc_Id = 0;
	delete r from relationship r join tids n on (r.idName = n.idName or r.Target_Id = n.idName);
	delete wa from w_auth wa join tids n on wa.idName = n.idName;
	delete wu from w_users wu join tids n on wu.idName = n.idName;
	delete wp from w_user_passwords wp join tids n on wp.idUser = n.idName;
	delete id from id_securitygroup id join tids n on id.idName = n.idName;
	delete m from mcalendar m join tids n on m.idName = n.idName;
	delete ml from mail_listing ml join tids n on ml.id = n.idName;
	delete mn from member_note mn join tids n on mn.idName = n.idName;
	delete f from fbx f join tids n on f.idName = n.idName;
	delete mh from member_history mh join tids n on mh.idName = n.idName;
	-- delete fa from fin_application fa join tids n on fa.idGuest = n.idName;
	delete gt from guest_token gt join tids n on gt.idGuest = n.idName;
	-- remove deleted organizations from member records.
	update name n join tids n1 on n.Company_Id = n1.idName set n.Company_Id=0, n.Company='';

	-- remove from any reservation guest listing.
	delete rg from reservation_guest rg join tids n on rg.idGuest = n.idName;
	-- remove reservation_guest for any deleted reservations.
	delete rg from reservation_guest rg where rg.idReservation in (select r.idReservation from reservation r join tids n on r.idGuest = n.idName);
    -- remove notes
	delete rn from reservation_note rn where rn.Reservation_Id in (select r.idReservation from reservation r join tids n on r.idGuest = n.idName);
	delete nt from note nt where nt.idNote in (select r.idReservation from reservation r join tids n on r.idGuest = n.idName);
    delete r from reservation r join tids n on r.idGuest = n.idName;

	-- hospital stay entries.
	delete hs from hospital_stay hs join tids n on hs.idPatient = n.idName;
	update hospital_stay hs join tids n on hs.idDoctor = n.idName set hs.idDoctor = 0;
	update hospital_stay hs join tids n on hs.idPcDoctor = n.idName set hs.idPcDoctor = 0;
	update hospital_stay hs join tids n on hs.idReferralAgent = n.idName set hs.idReferralAgent = 0;

	-- delete any reports for this psg or guest
    delete rp from report rp where rp.Psg_Id in (select idPsg from psg p join tids n on p.idPatient = n.idName);
    delete rp from report rp join tids n on rp.Guest_Id = n.idName;
    
    -- delete any registrations for the psg.
    delete rg from registration rg where rg.idPsg in (select idPsg from psg p join tids n on p.idPatient = n.idName);
	-- delete any members of a deleted patient's PSG
	delete ng from name_guest ng where ng.idPsg in (select idPsg from psg p join tids n on p.idPatient = n.idName);
	-- remove deleted members from the psg
	delete ng from name_guest ng join tids n on ng.idName = n.idName;
	-- delete the deleted patient's PSG
	delete p from psg p join tids n on p.idPatient = n.idName;
	
	delete v from vehicle v join tids n on v.idName = n.idName;
    delete ec from emergency_contact ec join tids n on ec.idName = n.idName;
	
	delete na from name_address na join tids n on na.idName = n.idName;
	delete na from name_crypto na join tids n on na.idName = n.idName;
	delete na from name_demog na join tids n on na.idName = n.idName;
	delete na from name_email na join tids n on na.idName = n.idName;
	delete na from name_phone na join tids n on na.idName = n.idName;
	delete na from name_insurance na join tids n on na.idName = n.idName;
	delete na from name_language na join tids n on na.idName = n.idName;
	delete na from name_volunteer2 na join tids n on na.idName = n.idName;
	
	DELETE n from name n join tids t on n.idName = t.idName;
    
	-- Log it
	Insert into name_log (Date_Time, Log_Type, Sub_Type, WP_User_Id, idName, Log_Text)
        select Now(), 'audit', 'delete', 'delete_names_u_tbd', idName, concat('name.idName: ', idName, ' -> null') from tids;

	drop temporary table tids;

	IF @@in_transaction = 1 AND @tranLevel = 0
    THEN
		COMMIT;
        select concat(@numMembers, " members deleted.") as `msg`;
	ELSE
		IF @@in_transaction = 1 AND @tranLevel > 0
        THEN
			SET @tranLevel = @tranLevel - 1;
		END IF;
	END IF;


END -- ;



-- --------------------------------------------------------
--
-- Procedure `delete_Invoice_payments`
--

DROP procedure IF EXISTS `delete_Invoice_payments`; -- ;

CREATE PROCEDURE `delete_Invoice_payments` (idInv int, payType int)
BEGIN

create temporary table ptemp (idPay int);

insert into ptemp (
	select pi.Payment_Id from payment_invoice pi join payment p on pi.Payment_Id = p.idPayment
    where pi.Invoice_Id = idInv and p.idPayment_method = payType and p.Amount = 0);

delete from payment_invoice where Payment_Id in (select idPay from ptemp);
delete from payment where idPayment in (select idPay from ptemp);

drop temporary table ptemp;

END -- ;



-- --------------------------------------------------------
--
-- Procedure `IncrementCounter`
--
DROP procedure IF EXISTS `IncrementCounter`; -- ;

CREATE PROCEDURE `IncrementCounter`
(
    IN tabl varchar(75),
    OUT num int(11)
)
BEGIN

    UPDATE counter SET Next = (@n:= Next) + 1 WHERE Table_Name = tabl;
    select @n into num;

END -- ;





-- --------------------------------------------------------
--
-- Procedure `getVolCategoryCodes`
--
DROP procedure IF EXISTS `getVolCategoryCodes`; -- ;

CREATE PROCEDURE `getVolCategoryCodes`
(
    IN id int(11),
    IN category varchar(15)
)

BEGIN
select distinct
    g.Table_Name as `Vol_Title`,
    g.Code as `Vol_Code`,
    ifnull(nv.Vol_Notes, '') as `Vol_Notes`,
    ifnull(nv.Vol_Status, 'z') as `Vol_Status`,
    nv.Vol_Begin,
    nv.Vol_End,
    nv.Vol_Check_Date,
    ifnull(g.Description, '') as `Description`,
    ifnull(nv.Vol_Trainer, '') as `Vol_Trainer`,
    nv.Vol_Training_Date,
    ifnull(nv.Dormant_Code, '') as `Dormant_Code`,
    ifnull(nv.Vol_Rank, '') as `Vol_Rank`,
    ifnull(nv.Updated_By, '') as `Updated_By`,
    nv.Last_Updated
from
    gen_lookups g
        left join
    name_volunteer2 nv ON g.Table_Name = nv.Vol_Category and g.Code = nv.Vol_Code and nv.idName = id
where
    g.Table_Name = category
order by Vol_Status,Description;
END -- ;





-- --------------------------------------------------------
--
-- Procedure `InsertDonor`
--
DROP procedure IF EXISTS `InsertDonor`; -- ;

CREATE PROCEDURE `InsertDonor`
(
    IN id INT
)
BEGIN
declare n int;
set n=0;
select 1 into n from name_volunteer2 where Vol_Code = 'd' and Vol_Category = 'Vol_Type' and idName = id;

IF n <> 1 then
    insert into name_volunteer2 (idName, Vol_Category, Vol_Code, Vol_Begin, Vol_Rank, Vol_Status, Updated_By, Last_Updated)
        values (id, 'Vol_Type', 'd', now(), 'm', 'a', 'sp_InsertDonor', now());
    Insert into activity (idName, Status_Code, Type, Product_Code, Other_Code, Action_Codes, Effective_Date, Source_Code)
        values (id, 'a', 'vol', 'Vol_Type|d', 'm', 'join', now(), 'sp_InsertDonor');
else
    update name_volunteer2 set Vol_Status = 'a', Last_Updated = now(), Updated_By = 'sp_InsertDonor'
    where Vol_Code = 'd' and Vol_Category = 'Vol_Type' and idName = id and Vol_Status<>'a';

    if  ROW_COUNT() > 0 then
        Insert into activity (idName, Status_Code, Type, Product_Code, Action_Codes, Effective_Date, Source_Code)
            values (id, 'a', 'vol', 'Vol_Type|d', 'rejoin', now(), 'sp_InsertDonor');
    end if;

end if;

END -- ;




-- --------------------------------------------------------
--
-- Procedure `insertHistory`
--
DROP procedure IF EXISTS `insertHistory`; -- ;

CREATE PROCEDURE `insertHistory`
(
    IN myid int
)
BEGIN
    declare n int;
    set n=0;
    select count(*) into n from admin_history where Id = myid;
    if n = 0 then
    insert into admin_history (Id) values (myid);
    else
    update admin_history set Timestamp = now() where Id = myid;
    end if;
END -- ;




-- --------------------------------------------------------
--
-- Procedure `register_web_user`
--
DROP procedure IF EXISTS `register_web_user`; -- ;

CREATE PROCEDURE `register_web_user`
(
    IN id int,
    IN fbid varchar(45),
    IN uname varchar(60),
    IN appr varchar(45),
    IN orgId varchar(3),
    IN roleId varchar(3),
    IN pw varchar(100),
    IN groupcode varchar(5),
    IN resetpw varchar(1),
    IN idpId int
)
BEGIN

    -- does idName exist in the fbx table?
    declare n int;
    declare u int;
    declare m varchar(250);
    set n= -1;

    select idName into n from fbx where fb_id = fbid;

    if n >= 0 then
    -- fbid found, update id into fbx
        update fbx set idName=id, Status = 'a', Approved_By=appr, Approved_Date=now() where fb_id = fbid;

    end if;

    -- now check w_users table
    set n = 0;
    select count(*) into n from w_users where idName = id;
    
    -- check if username is taken
    set u = 0;
    select count(*) into u from w_users where User_Name = uname;

    if n >0  then
    -- update
        update w_users set Status='a', User_Name = uname, Verify_address = 'y', Enc_PW=pw, idIdp=idpId, Updated_By=appr, Last_Updated=now() where idName = id;
        if  ROW_COUNT() > 0 then
            Insert into name_log (Date_Time, Log_Type, Sub_Type, WP_User_Id, idName, Log_Text)
                values (Now(), 'audit', 'update', 'sp_reg_web_user', id, concat('w_users: -> status=a, uname=',uname));
        end if;


        update w_auth set Status='a', User_Name = uname, Organization_Id = orgId, Role_Id = roleId, Updated_By = appr, Last_Updated = now()
            where idName = id;
        if  ROW_COUNT() > 0 then
            Insert into name_log (Date_Time, Log_Type, Sub_Type, WP_User_Id, idName, Log_Text)
                values (Now(), 'audit', 'update', 'sp_reg_web_user', id, concat('w_auth: -> status=a, role_id=',roleId));
        end if;
        
	elseif u >0 then
		SIGNAL SQLSTATE '23000' SET MYSQL_ERRNO = '1062', MESSAGE_TEXT='Username is already taken';

    else
    -- insert new record
        insert into w_users (idName, User_Name, Enc_PW, Chg_PW, idIdp, Status, Verify_Address, Updated_By, Last_Updated)
            values ( id, uname, pw, resetpw, idpId, 'a', 'y', appr, now() );
        Insert into name_log (Date_Time, Log_Type, Sub_Type, WP_User_Id, idName, Log_Text)
            values (Now(), 'audit', 'new', 'sp_Reg_Web_User', id, concat('w_users: -> status=a, uname=',uname));

        insert into w_auth (idName, User_Name, Organization_Id, Role_Id, Updated_By, Last_Updated, Status)
            values ( id, uname, orgId, roleId, appr, now(), 'a');
        Insert into name_log (Date_Time, Log_Type, Sub_Type, WP_User_Id, idName, Log_Text)
            values (Now(), 'audit', 'new', 'sp_reg_web_user', id, concat('w_auth: -> status=a, role_id=',roleId));

    end if;

    -- group codes
        replace into id_securitygroup (idName, Group_Code)
            values (id, groupcode);

END -- ;





-- --------------------------------------------------------
--
-- Procedure `selectvolcategory`
--
drop procedure IF EXISTS `selectvolcategory`; -- ;

CREATE PROCEDURE `selectvolcategory`
(
    IN category varchar(45)
)
BEGIN
    select `vm`.`Id` AS `Id`,
    (case when (`vm`.`MemberRecord` = 1) then concat(`vm`.`Name_Last`,', ',`vm`.`Name_First`) else `vm`.`Company` end) AS `Name_Last_First`,
    `nv`.`Vol_Code` AS `Vol_Code`,`nv`.`Vol_Status` AS `Vol_Status`,

    case when (ifnull(`g`.`Code`,'') <> '') then ifnull(`g`.`Description`,'') end AS `Description`,

    (case when (`vm`.`Member_Type` = 'ai') then `vm`.`Fullname` when (`vm`.`Member_Type` <> 'ai') then `vm`.`Company` else '' end) AS `Name_Full`,
    (case when ((`vm`.`Exclude_Phone` = 1) and (`vm`.`Preferred_Phone` <> '')) then 'x' else `vm`.`Preferred_Phone` end) AS `PreferredPhone`,
    (case when ((`vm`.`Exclude_Email` = 1) and (`vm`.`Preferred_Email` <> '')) then 'x' else `vm`.`Preferred_Email` end) AS `PreferredEmail`,
    concat_ws(' ',`vm`.`Address_1`,`vm`.`Address_2`,`vm`.`City`,`vm`.`StateProvince`,`vm`.`PostalCode`) AS `Full_Address`,
    ifnull(`d`.`Title`,'') AS `Title`,
    cast(`d`.`Begin_Active` as date) AS `Begin_Active`,
    cast(`d`.`End_Active` as date) AS `End_Active`,
    ifnull(`nv`.`Vol_Notes`,'') AS `Vol_Notes`,
    `vm`.`Member_Type` AS `Member_Type`,`nv`.`Vol_Begin` AS `Vol_Begin`,`nv`.`Vol_End` AS `Vol_End`,
    ifnull(`nv`.`Vol_Rank`,'') AS `Vol_Rank`

    from `vmember_listing` `vm` join `name_volunteer2` `nv` on `vm`.`Id` = `nv`.`idName` and `vm`.`MemberStatus` = 'a' and nv.Vol_Category = category
    left join `dormant_schedules` `d` on `nv`.`Dormant_Code` = `d`.`Code`
    left join `gen_lookups` `g` on `nv`.`Vol_Code` = `g`.`Code` and `g`.`Table_Name` = `nv`.`Vol_Category`;

END -- ;





-- --------------------------------------------------------
--
-- Procedure `sp_move_donation`
--
drop procedure IF EXISTS `sp_move_donation`; -- ;

CREATE PROCEDURE sp_move_donation
(
    IN toid int,
    IN delid int,
    IN uname varchar(45)
)
BEGIN

    update donations set Donor_Id = toid, Updated_By=uname, Last_Updated=now() where Donor_Id = delid;

    if  ROW_COUNT() > 0 then
        insert into activity (idName, Type, Action_Codes, Effective_Date, Source_Code, Description)
           values (toid, 'don', 'xfer', now(), uname, concat('transfer donations from Id ', delid));
    end if;

END -- ;


-- --------------------------------------------------------
--
-- Procedure `sync_referral_resv_status`
--

drop procedure IF EXISTS `sync_referral_resv_status`; -- ;

CREATE PROCEDURE `sync_referral_resv_status` ()
BEGIN

	UPDATE `document` `d`
	LEFT JOIN `reservation` `r` on `d`.`idDocument` = `r`.`idReferralDoc`
	SET `d`.`Status` = 'ac'
	WHERE `r`.`Status` in ('a', 'p','uc','w');
    
	UPDATE `document` `d`
	LEFT JOIN `reservation` `r` on `d`.`idDocument` = `r`.`idReferralDoc`
	SET `d`.`Status` = 'ar'
	WHERE `r`.`Status` not in ('a', 'p','uc','w');
END -- ;


-- --------------------------------------------------------
--
-- Procedure `del_webuser`
--
DROP procedure IF EXISTS `del_webuser`; -- ;

CREATE PROCEDURE `del_webuser`
(
    IN id int,
    IN adminName varchar(45)
)
BEGIN
    delete from w_users where idName = id;
    delete from w_auth where idName = id;
    delete from fbx where idName = id;
    delete from id_securitygroup where idName = id;

    insert into name_log (Date_Time, Log_Type, Sub_Type, WP_User_Id, idName, Log_Text)
        values (now(), 'audit', 'update', adminName, id, 'Delete Webuser Account');

END -- ;





-- --------------------------------------------------------
--
-- Procedure `sp_move_vol_categories`
--
DROP procedure IF EXISTS `sp_move_vol_categories`; -- ;

CREATE PROCEDURE `sp_move_vol_categories`
(
    IN dupId int,
    IN keepId int,
    IN adminName varchar(45)
)
BEGIN
    CREATE TEMPORARY TABLE IF NOT EXISTS tbl_vol (
        Concat_String varchar(25)
    );

    insert into tbl_vol
        select concat(Vol_Category, Vol_Code) from name_volunteer2 where idName = keepId;

    update name_volunteer2 v set v.idName = keepId, Updated_By = adminName, Last_Updated = now()
        where v.idName = dupId and concat(v.Vol_Category, v.Vol_Code) not in
        (select Concat_String from tbl_vol);

    if ROW_COUNT() > 0 then
        Insert into activity (idName, Status_Code, Type, Product_Code, Action_Codes, Effective_Date, Source_Code, Description)
            values (keepId, 'a', 'vol', 'various', 'xfer', now(), adminName, concat('Transfer from Id ',dupId));

        update mcalendar set idName = keepId, Updated_By = adminName, Last_Updated = now()
            where idName = dupId and concat(E_Vol_Category, E_Vol_Code) not in
            (select Concat_String from tbl_vol);

        update mcalendar set idName2 = keepId, Updated_By = adminName, Last_Updated = now()
            where idName2 = dupId and concat(E_Vol_Category, E_Vol_Code) not in
            (select Concat_String from tbl_vol);
    end if;

    drop table tbl_vol;
END -- ;





-- --------------------------------------------------------
--
-- Procedure `remove_dup_guest`
--
drop procedure if exists `remove_dup_guest`; -- ;

CREATE PROCEDURE `remove_dup_guest`(goodId int, badId int)
BEGIN

	DECLARE exit handler for sqlexception
	BEGIN
		GET DIAGNOSTICS CONDITION 1 @text = MESSAGE_TEXT;
        IF @@in_transaction = 1
        THEN
			ROLLBACK;
		END IF;
		SELECT CONCAT('ERROR: Cannot remove duplicate guest. No changes made.<br>', @text) as `error`;
	END;

    IF @@in_transaction = 0
    THEN
		START TRANSACTION;
        SET @tranLevel = 0;
	ELSE
		SET @tranLevel = @tranLevel + 1;
	END IF;
    
    update ignore name_guest set idName = goodId where idName = badId;
    delete from name_guest where idName = badId;

    update stays set
        idName = goodId
        where idName = badId;

    update visit set
        idPrimaryGuest = goodId
        where idPrimaryGuest = badId;

    update reservation set
        idGuest = goodId
        where idGuest = badId;

    update reservation_guest set
	idGuest = goodId
        where idGuest = badId;

--    update fin_application set
-- idGuest = goodId
--        where idGuest = badId;

    update activity set
	idName = goodId
        where idName = badId;

	update link_doc set
    idGuest = goodId
		where idGuest = badId;

	update report set
    Guest_Id = goodId
		where Guest_Id = badId;

	update hospital_stay set
	idPatient = goodId
		where idPatient = badId;

    update invoice set
	Sold_To_Id = goodId
        where Sold_To_Id = badId;

    update payment set
	idPayor = goodId
        where idPayor = badId;

    update guest_token set
	idGuest = goodId
        where idGuest = badId;

    update trans set
	idName = goodId
        where idName = badId;

    update `name` set
	Member_Status = 'TBD'
        where idName = badId;

    call `delete_names_u_tbd`;

    insert into name_log (Date_Time, Log_Type, Sub_Type, WP_User_Id, idName, Log_Text)
        values (now(), 'audit', 'update', 'sp', badId, 'Remove Dup Guest');

	IF @@in_transaction = 1 AND @tranLevel = 0
    THEN
		COMMIT;
        SELECT CONCAT("Success: Duplicate guest ", badId, " removed successfully") as `msg`;
	ELSE
		IF @@in_transaction = 1 AND @tranLevel > 0
		THEN
			SET @tranLevel = @tranLevel - 1;
		END IF;
	END IF;
END -- ;




-- --------------------------------------------------------
--
-- Procedure `combinePSG`
--
drop procedure if exists `combinePSG`; -- ;

CREATE PROCEDURE `combinePSG` (keepIdPsg int(11), dupIdPsg int(11))
BEGIN

    DECLARE goodReg int;
    Declare badReg int;
    Declare goodIdP int;
    Declare badIdP int;
    
	DECLARE exit handler for sqlexception
	BEGIN
		GET DIAGNOSTICS CONDITION 1 @text = MESSAGE_TEXT;
        IF @@in_transaction = 1
        THEN
			ROLLBACK;
		END IF;
		SELECT CONCAT('ERROR: Cannot combine PSGs. No changes made.<br>', @text) as `error`;
	END;
    
    IF @@in_transaction = 0
    THEN
		START TRANSACTION;
        SET @tranLevel = 0;
	ELSE
		SET @tranLevel = @tranLevel + 1;
	END IF;

	UPDATE IGNORE name_guest 
	SET 
		idPsg = keepIdPsg
	WHERE
		idPsg = dupIdPsg;
	DELETE FROM name_guest 
	WHERE
		idPsg = dupIdPsg;

	SELECT 
		idPatient
	INTO badIdP FROM
		psg
	WHERE
		idPsg = dupIdPsg;
	SELECT 
		idPatient
	INTO goodIdP FROM
		psg
	WHERE
		idPsg = keepIdPsg;
		-- select idHospital_stay into goodHs from hospital_stay where idPsg = keepIdPsg;
	SELECT 
		idRegistration
	INTO goodReg FROM
		registration
	WHERE
		idPsg = keepIdPsg;
	SELECT 
		idRegistration
	INTO badreg FROM
		registration
	WHERE
		idPsg = dupIdPsg;

	UPDATE reservation 
	SET 
		idRegistration = goodReg
	WHERE
		idRegistration = badReg;

	UPDATE visit 
	SET 
		idRegistration = goodReg
	WHERE
		idRegistration = badReg;

	UPDATE fin_application 
	SET 
		idregistration = goodReg
	WHERE
		idRegistration = badReg;

		UPDATE link_doc 
	SET 
		idPSG = keepIdPsg
	WHERE
		idPSG = dupIdPsg;

		UPDATE report 
	SET 
		Psg_Id = keepIdPsg
	WHERE
		Psg_Id = dupIdPsg;

		UPDATE hospital_stay 
	SET 
		idPsg = keepIdPsg
	WHERE
		idPsg = dupIdPsg;

	UPDATE invoice 
	SET 
		idGroup = goodReg
	WHERE
		idGroup = badReg;

	UPDATE guest_token 
	SET 
		idRegistration = goodReg
	WHERE
		idRegistration = badReg;

	UPDATE vehicle 
	SET 
		idRegistration = goodReg
	WHERE
		idRegistration = badReg;

		UPDATE psg_note 
	SET 
		Psg_Id = keepIdPsg
	WHERE
		Psg_Id = dupIdPsg;

	DELETE FROM registration 
	WHERE
		idRegistration = badReg;
	DELETE FROM psg 
	WHERE
		idPsg = dupIdPsg;
    -- delete from hospital_stay where idPsg = dupIdPsg;

    call remove_dup_guest(goodIdP, badIdP);
    
    IF @@in_transaction = 1 AND @tranLevel = 0
    THEN
		COMMIT;
        select concat("Success: PSG ", dupIdPsg, " combined into PSG ", keepIdPsg) as `msg`;
	ELSE
		IF @@in_transaction = 1 AND @tranLevel > 0
		THEN
			SET @tranLevel = @tranLevel - 1;
		END IF;
	END IF;
END -- ;


-- --------------------------------------------------------
--
-- Procedure `delImediateResv`
--

DROP procedure IF EXISTS `delImediateResv`; -- ;

CREATE PROCEDURE `delImediateResv` ()
BEGIN
	delete from reservation_guest
		where idReservation in (Select r.idReservation from reservation r where r.`Status` = 'im' and DATE(r.Expected_Arrival) < DATE(now()));
	delete from reservation where `Status` = 'im' and DATE(Expected_Arrival) < DATE(now());
END -- ;


-- --------------------------------------------------------
--
-- Procedure `set_pagesecurity`
--
DROP procedure IF EXISTS `set_pagesecurity`; -- ;

CREATE PROCEDURE `set_pagesecurity` (
    IN pageId int,
    IN secCode varchar(5)
)
BEGIN
    declare p int;

    if pageId > 0 and secCode != '' then

        select count(`idPage`) into p from `page_securitygroup` where `idPage` = pageId and `Group_Code` = secCode;

        if p = 0 then
            insert into page_securitygroup (`idPage`, `Group_Code`) VALUES (pageId, secCode);
        end if;

    end if;

END -- ;



-- --------------------------------------------------------
--
-- Procedure `new_webpage`
--
DROP procedure IF EXISTS `new_webpage`; -- ;

CREATE PROCEDURE `new_webpage`(
    IN fileName varchar(65),
    IN loginPageId int,
    IN pageTitle varchar(45),
    IN hideMe int,
    IN website varchar(5),
    IN menuParent varchar(45),
    IN menuPosition varchar(45),
    IN pageType varchar(5),
    IN validityCode varchar(75),
    IN updatedBy varchar(45),
    IN lastUpdated datetime,
    IN secCode varchar(5)
)
BEGIN

    declare p int;
    declare n int;
    declare id int;

    select idPage into p from `page` where `File_Name` = fileName;

    if p > 0 then

        update `page` set `Login_Page_Id` = loginPageId, `Title` = pageTitle, `Hide` = hideMe, `Menu_Parent` = menuParent, `Menu_Position` = menuPosition, `Type` = pageType,
                `Validity_Code` = validityCode, `Updated_By` = updatedBy, `Last_Updated` = lastUpdated
                where idPage = p;

        Select p into id;

    else

        INSERT INTO `page`
        (`File_Name`, `Login_Page_Id`, `Title`,	`Hide`,	`Web_Site`,	`Menu_Parent`, `Menu_Position`,	`Type`,	`Validity_Code`, `Updated_By`, `Last_Updated`)
        VALUES
        (fileName, loginPageId, pageTitle, hideMe, website, menuParent, menuPosition, pageType, validityCode, updatedBy, lastUpdated);

        SELECT LAST_INSERT_ID() into id;
    end if;

    call set_pagesecurity(id, secCode);

END -- ;

-- --------------------------------------------------------
--
-- Procedure `delete_guest_photo`
--
DROP procedure IF EXISTS `delete_guest_photo`; -- ;

CREATE PROCEDURE `delete_guest_photo`(IN guest_id varchar(45))
BEGIN

    DECLARE photoId int;

    select nd.`Guest_Photo_Id` into photoId from `name_demog` nd where nd.`idName` = guest_id;

    delete from `photo` where `idPhoto` = photoId;
    update `name_demog` set `Guest_Photo_Id` = 0 where `idName` = guest_id;

END -- ;


-- --------------------------------------------------------
--
-- Procedure `incidents_report`
--
DROP procedure IF EXISTS `incidents_report`; -- ;

CREATE PROCEDURE `incidents_report`(
	IN activ varchar(3),
	IN resol varchar(3),
    IN onHold varchar(3),
	IN del varchar(3)
	)
BEGIN
    CREATE TEMPORARY TABLE IF NOT EXISTS tble (
        idPsg int,
        count_idPsg int
    );

    insert into tble
		SELECT
			Psg_Id, COUNT(Psg_Id)
		FROM
			report
		WHERE
			`Status` in (activ, resol, onHold, del)
		GROUP BY Psg_Id;


    select t.count_idPsg, r.Psg_Id, n.idName, n.Name_Full, r.Title, ifnull(r.Report_Date, '') as `Report_Date`, ifnull(r.Resolution_Date, '') as `Resolution_Date`, ifnull(g.Description, '') as `Status`
    from
		tble t join report r on t.idPsg = r.Psg_Id and  r.`Status` in (activ, resol, onHold, del)
		left join hospital_stay hs on t.idPsg = hs.idPsg
        left join name n on hs.idPatient = n.idName
        left join gen_lookups g on g.Table_Name = 'Incident_Status' and g.Code = r.`Status`
	group by r.idReport
	order by t.count_idPsg DESC, r.Psg_Id;

    drop table tble;

END -- ;
