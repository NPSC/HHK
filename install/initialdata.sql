-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2017 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK

REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`) VALUES
('Address_Purpose','1','Home','i',''),
('Address_Purpose','2','Work','i',''),
('Address_Purpose','3','Alt','i',''),
('Address_Purpose','4','Office','o',''),
('Address_Purpose','b','Billing','o',''),

('Addnl_Charge', 'ac1', 'Lost Key', '25', 'ca'),
('Addnl_Charge', 'ac2', 'Room Damage', '100', 'ca'),

('Age_Bracket','2','Infant','','d'),
('Age_Bracket','4','Minor','','d'),
('Age_Bracket','6','Adult','','d'),
('Age_Bracket','8','Senior','','d'),
('Age_Bracket','z','Unknown','','d'),

('anomalyTypes','ba','Bad City','`City`=\'\'',''),
('anomalyTypes','bs','Bad State','`State`=\'\'',''),
('anomalyTypes','sa','Bad Street Address','`Street Address`=\'\'',''),
('anomalyTypes','z','Bad Zip Code','Zip=\'\' or Zip=\'0\' or LENGTH(Zip)<5',''),

('Attribute_Type', '1', 'Room', '',''),
('Attribute_Type', '2', 'Hospital', '',''),

('Cal_Event_Status','a','Active','',''),
('Cal_Event_Status','d','Deleted','',''),
('Cal_Event_Status','t','Logged','',''),
('Cal_Hide_Add_Members','Vol_Activities1','n','',''),
('Cal_House','Vol_Activitieshou','House Calendar','',''),
('Cal_Select','Vol_Activities1','n','',''),
('Cal_Show_Delete_Email','Vol_Activities1','y','',''),

('Campaign_Status','a','Active','',''),
('Campaign_Status','c','Closed','',''),
('Campaign_Status','d','Disabled','',''),

('Campaign_Type','as','Normal','',''),
('Campaign_Type','pct','Percent Cut Out','',''),
('Campaign_Type','ink','In Kind','',''),
('Campaign_Type', 'sch', 'Scholarship', '',''),

('Constraint_Type', 'hos', 'Hospital', '',''),
('Constraint_Type', 'rv', 'Reservation','',''),
('Constraint_Type', 'v', 'Visit', '',''),

('Category_Types', '1', 'Items', '',''),
('Category_Types', '2', 'Tax', '',''),
('Category_Types', '3', 'Penalty', '',''),

('Charge_Cards', '1', 'Visa', '',''),
('Charge_Cards', '2', 'M/C', '',''),
('Charge_Cards', '3', 'Discover','', ''),
('Charge_Cards', '4', 'Am Ex', '',''),

('Demographics', 'a', 'Age_Bracket', 'y','m'),
('Demographics', 'e', 'Ethnicity', 'y','m'),
('Demographics', 'g', 'Gender', 'y','m'),
('Demographics', 'i', 'Income_Bracket', 'y','m'),
('Demographics', 'l', 'Education_Level', 'y','m'),
('Demographics', 'sn', 'Special_Needs', '','m'),
('Demographics', 'ms', 'Media_Source', '','m'),

('Diagnosis','0','Other','','h'),
('Diagnosis','0','Breast Cancer','','h'),
('Diagnosis','0','Prostate Cancer','','h'),
('Diagnosis','0','Lung Cancer','','h'),
('Diagnosis','0','Glioma','','h'),

('Dir_Type_Selector_Code','d','Directory','',''),
('Dir_Type_Selector_Code','e','Email Addresses','',''),
('Dir_Type_Selector_Code','m','Mailing List','',''),

('Distance_Range','50','Up to 50 miles','2',''),
('Distance_Range','100','51 to 100 miles','3',''),
('Distance_Range','150','101 to 150 miles','4',''),
('Distance_Range','200','151 to 200 miles','5',''),
('Distance_Range','30000','More Than 200 miles','7',''),

('Dormant_Selector_Code','act','Active Only','',''),
('Dormant_Selector_Code','both','Dormant & Active','',''),
('Dormant_Selector_Code','dor','Dormant Only','',''),

('Education_Level','01','Highschool','','d'),
('Education_Level','02','College','','d'),
('Education_Level','z','Unknown','','d'),

('Email_Purpose','1','Home','i',''),
('Email_Purpose','2','Work','i',''),
('Email_Purpose','3','Alt','i',''),
('Email_Purpose','4','Office','o',''),

('Ethnicity','c','Caucasian','','d'),
('Ethnicity','f','African-American','','d'),
('Ethnicity','h','Hispanic','','d'),
('Ethnicity','k','Asia-Pacific','','d'),
('Ethnicity','x','Other','','d'),
('Ethnicity','z','Unknown','','d'),

('E_Shell_Status','a','Active','',''),
('E_Shell_Status','d','Disabled','',''),

('ExcessPays', 'd', 'Room Fund','','u'),
('ExcessPays', 'e', 'Hold','','u'),
('ExcessPays', 'i', 'Ignore','','u'),
('ExcessPays', 'r', 'Refund','','u'),

('FB_Status','a','Active','',''),
('FB_Status','d','Disabled','',''),
('FB_Status','w','Waiting','',''),
('FB_Status','x','Prohibited','',''),


('Gender','f','Female','','d'),
('Gender','m','Male','','d'),
('Gender','t','Other','','d'),
('Gender','z','Unknown','','d'),

('Guest_Survey', 'Survey_Date','', '',''),

('Holiday', '0', 'New Years Day','',''),
('Holiday', '1', 'Martin Luther King Birthday','',''),
('Holiday', '2', 'Washington Birthday','',''),
('Holiday', '3', 'Memorial Day','',''),
('Holiday', '4', 'Independance Day','',''),
('Holiday', '5', 'Labor Day','',''),
('Holiday', '6', 'Columbus Day','',''),
('Holiday', '7', 'Vetereans Day','',''),
('Holiday', '8', 'Thanksgiving Day','',''),
('Holiday', '9', 'Christmas Day','',''),
('Holiday', '10', 'Designated 1', '',''),
('Holiday', '11', 'Designated 2', '',''),
('Holiday', '12', 'Designated 3', '',''),
('Holiday', '13', 'Designated 4', '',''),

('Hospital_Type', 'h', 'Hospital','',''),
('Hospital_Type', 'a', 'Association','',''),

('HourReportType','d','Open & Logged','',''),
('HourReportType','l','Only Logged Hours','',''),
('HourReportType','ul','Only Open Hours','',''),

('House_Discount', 'hd1', 'Service Issue','10', 'ca'),
('House_Discount', 'hd2', 'Facilities Issue','15', 'ca'),

('Income_Bracket', 'ib1', 'Rich', '', 'd'),
('Income_Bracket', 'ib2', 'Poor', '', 'd'),
('Income_Bracket', 'z', 'Unknown', '', 'd'),

('Invoice_Status', 'p', 'Paid', '',''),
('Invoice_Status', 'up', 'Unpaid', '',''),
('Invoice_Status', 'c', 'Carried', '',''),

('Key_Deposit_Code','k0','None','0',''),
('Key_Deposit_Code','k1','House','20',''),

('Language_Proficiency', '5', 'Native','', 'h'),
('Language_Proficiency', '3', 'Professional','', 'h'),
('Language_Proficiency', '2', 'Limited','', 'h'),
('Language_Proficiency', 'l', 'Elementary','', 'h'),

('Location', 'lo1', 'Cardiac','', 'h'),
('Location', 'lo2', 'Peds','', 'h'),

('Media_Source', 'na', 'News Article','','d'),
('Media_Source', 'hs', 'Hospital Staff','','d'),
('Media_Source', 'fr', 'Friend','','d'),
('Media_Source', 'hhn', 'HHN','','d'),
('Media_Source', 'ws', 'Web Search','','d'),
('Media_Source', 'z', 'Unknown','','d'),

('Member_Basis','ai','Individual','i',''),
('Member_Basis','c','Company','o',''),
('Member_Basis','np','Non Profit','o',''),
('Member_Basis','og','Government','o',''),

('mem_status','a','Active','m',''),
('mem_status','d','Deceased','m',''),
('mem_status','in','Inactive','m',''),
('mem_status','p','Pending','',''),
('mem_status','TBD','To be deleted','',''),
('mem_status','u','Duplicate','',''),

('Name_Prefix','dr','Dr.','',''),
('Name_Prefix','mi','Miss.','',''),
('Name_Prefix','mr','Mr.','',''),
('Name_Prefix','mrs','Mrs.','',''),
('Name_Prefix','ms','Ms.','',''),
('Name_Prefix','rev','Rev.','',''),
('Name_Prefix','The','The','',''),

('Name_Suffix','D.D.S.','D.D.S.','',''),
('Name_Suffix','esq','Esq.','',''),
('Name_Suffix','ii','II','',''),
('Name_Suffix','iii','III','',''),
('Name_Suffix','jd','Jd.','',''),
('Name_Suffix','jr','Jr.','',''),
('Name_Suffix','md','MD.','',''),
('Name_Suffix','phd','Ph.D.','',''),

('NoReturnReason', 'n1', 'Irresponsible', '', 'u'),

('OOS_Codes', 'sb', 'Sewer Backup','','h'),
('OOS_Codes', 'ar', 'Appliance Repair','','h'),
('OOS_Codes', 'sr', 'Structural Repair','','h'),
('OOS_Codes', 'cd', 'Cleaning Delay','','h'),

('Order_Status','a','Active','',''),
('Order_Status','f','Finished','',''),
('Order_Status','s','Suspended','',''),
('Order_Status','sa','Suspended-Ageing','',''),

('Page_Type','c','Component','',''),
('Page_Type','p','Web Page','',''),
('Page_Type','s','Web Service','',''),

('Patient_Rel_Type','chd','Child','','d'),
('Patient_Rel_Type','frd','Friend','','d'),
('Patient_Rel_Type','par','Parent','','d'),
('Patient_Rel_Type','rltv','Relative','','d'),
('Patient_Rel_Type','sib','Sibling','','d'),
('Patient_Rel_Type','sp','Partner','','d'),
('Patient_Rel_Type','pc','Paid Caregiver','','d'),
('Patient_Rel_Type','slf','Patient','','d'),

('Pay_Status', 'c', 'Cleared', '',''),
('Pay_Status', 'p', 'Pending', '',''),
('Pay_Status', 'd', 'Denied', '',''),
('Pay_Status', 'er', 'Error', '',''),
('Pay_Status', 'v', 'Void', '',''),
('Pay_Status', 'r', 'Returned', '',''),

('Payment_Status', 's', 'Paid', '',''),
('Payment_Status', 'v', 'Void', '',''),
('Payment_Status', 'r', 'Return', '',''),
('Payment_Status', 'vr', 'Void Return', '',''),
('Payment_Status', 'd', 'Declined', '',''),

('Pay_Type','ca','Cash','1',''),
('Pay_Type','cc','Credit Card','4',''),
('Pay_Type','ck','Check','3',''),
('Pay_Type','in','Invoice','',''),
('Pay_Type','tf','Transfer','5',''),

('Period_Unit', '1', 'Day','',''),
('Period_Unit', '2', 'Week','',''),
('Period_Unit', '3', 'Month','',''),
('Period_Unit', '4', 'Year','',''),

('Phone_Type','dh','Home','i',''),
('Phone_Type','gw','Work','i',''),
('Phone_Type','hw','Office','o',''),
('Phone_Type','mc','Cell','i',''),
('Phone_Type','xf','Fax','',''),

('Price_Model','b','Basic','',''),
('Price_Model','d','Daily','',''),
('Price_Model','g', 'Guest Day','',''),
('Price_Model','ns','3 Step','',''),
('Price_Model','p','Perpetual Step','',''),
('Price_Model','bl','n-Day Block','',''),
('Price_Model','xx','None','',''),

('Rate_Block', '7', 'Wk', '1',''),
('Rate_Block', '14', '2 Weeks', '',''),

('Rate_Period', '1', 'Reduced Rate 1', '7',''),
('Rate_Period', '2', 'Reduced Rate 2', '14',''),
('Rate_Period', '3', 'Reduced Rate 3', '19999',''),

('rel_type','chd','Child','par',''),
('rel_type','par','Parent','chd',''),
('rel_type','rltv','Relative','',''),
('rel_type','sib','Sibling','sib',''),
('rel_type','sp','Partner','sp',''),
('rel_type','frd','Friend','sp',''),

('Resource_Status','a','Available','',''),
('Resource_Status','oos','Out of Service','',''),
('Resource_Status','un','Unavailable','',''),
('Resource_Status','dld','Delayed','',''),

('Resource_Type','block','Block','',''),
('Resource_Type','part','Partition','',''),
('Resource_Type','rmtroom','Remote Room','',''),
('Resource_Type','room','Room','',''),

('Role_Codes','10','Admin User','',''),
('Role_Codes','100','Web User','',''),
('Role_Codes','700','Guest','',''),

('Room_Category','dh','House','',''),
('Room_Category','gada','Hospital','',''),
('Room_Category','jph','Private Host','',''),
('Room_Category','nm','Motel','',''),

('Room_Cleaning_Days', 'a', 'Disabled', '0', 'ha'),
('Room_Cleaning_Days', 'b', '7 Days', '7', 'ha'),
('Room_Cleaning_Days', 'nz', 'Disabled', '0', 'ha'),

('Room_Status', 'a', 'Clean', '',''),
('Room_Status', 'dty', 'Dirty', '',''),
('Room_Status', 'to', 'Turn Over', '',''),

('Room_Type','r','Room','',''),
('Room_Type','s','Suite','',''),

('Salutation','fln','First &Last','',''),
('Salutation','fno','First Name','',''),
('Salutation','for','Formal','',''),
('Salutation','mm','Retro-Mr. & Mrs.','',''),

('Special_Needs','c','Cancer','','d'),
('Special_Needs','f','Dev. Challenged','','d'),
('Special_Needs','z','Unknown','','d'),

('Static_Room_Rate','rb','Regular Rate','10',''),

('Utilization_Category', 'uc1', 'Standard', '', 'h'),

('validMemStatus','a','Active','',''),
('validMemStatus','d','Deceased','',''),
('validMemStatus','in','Inactive','',''),

('Verify_User_Address','done','Verified','',''),
('Verify_User_Address','y','Waiting for verification','',''),

('Visit_Fee_Code', '1', 'Cleaning Fee','15',''),
('Visit_Fee_Code', '2', '','0',''),

('Visit_Status','a','Checked In','',''),
('Visit_Status','co','Checked Out','',''),
('Visit_Status','cp','Room Rate Changed','',''),
('Visit_Status','n','Room Changed','',''),
('Visit_Status', '1', 'On Leave','',''),

('Vol_Activities','1','Greeter','green,white',''),
('Vol_Activities','5','Fundraising','black,white',''),
('Vol_Activities','6','Special Event Planning/Organizing','',''),
('Vol_Activities','8','Lawn Care','',''),
('Vol_Activities','9','Gardening','',''),
('Vol_Activities','ccom','Cookie Committee','yellow,darkgreen',''),


('Vol_Category','Vol_Activities','Volunteer Activities','Vol_Type.Vol',''),
('Vol_Category','Vol_Skills','Volunteer Skills','Vol_Type.Vol',''),
('Vol_Category','Vol_Type','Member Type','',''),

('Vol_Rank','c','Chair','',''),
('Vol_Rank','cc','Co-Chair','',''),
('Vol_Rank','m','Member','',''),


('Vol_Skills','D','Solicitation or Fundraising','green,white',''),
('Vol_Skills','E','Cooking/Catering','',''),
('Vol_Skills','G','Handyperson','',''),
('Vol_Skills','H','Painting','',''),
('Vol_Skills','I','Electrical','',''),
('Vol_Skills','J','Plumbing','',''),
('Vol_Skills','K','Roofing','',''),
('Vol_Skills','L','Carpentry','orange,darkblue',''),

('Vol_Status','a','Active','',''),
('Vol_Status','i','Retired','',''),

('Vol_Type','d','Donor','',''),
('Vol_Type','g','Guest','',''),
('Vol_Type','p','Patient','yellow,black',''),
('Vol_Type','Vol','Volunteer','',''),
('Vol_Type', 'doc', 'Doctor','',''),
('Vol_Type', 'ra', 'Agent','',''),
('Vol_Type', 'ba', 'Billing Agent', '',''),

('Web_User_Status','a','active','',''),
('Web_User_Status','d','Disabled','',''),
('Web_User_Status','w','Waiting','',''),
('Web_User_Status','x','Prohibited','',''),

('WL_Final_Status','hf','House Full','',''),
('WL_Final_Status','lc','Lost Contact','',''),
('WL_Final_Status','se','Elsewhere','',''),
('WL_Status','a','Active','',''),
('WL_Status','in','Inactive','',''),
('WL_Status','st','Stayed','','');
-- ;


REPLACE INTO `lookups` VALUES 
('FinAppStatus','a','Granted','y','y','','','2013-11-19 17:27:35'),
('FinAppStatus','n','Not Granted','y','y','','','2013-11-19 17:27:35'),
('RegistrationAtribute','Sig_Card','Guest Signature','y','y','','','2013-11-13 00:59:31'),
('RegistrationAttribute','Guest_Ident','Guest Identification','y','y','','','2013-11-13 00:59:31'),
('RegistrationAttribute','Pamphlet','Rules Pamphlet','y','y','','','2013-11-13 00:59:31'),
('RegistrationAttribute','Referral','Hospital Referral Document','','','','','2013-11-13 00:59:31'),
('ReservStatus','a','Confirmed','y','y','','ui-icon-check','2013-11-11 15:09:04'),
('ReservStatus','uc','Unconfirmed','y','y','','ui-icon-help','2013-11-11 15:09:04'),
('ReservStatus','c','Canceled','y','y','','ui-icon-cancel','2013-11-11 15:09:04'),
('ReservStatus','ns','No Show','y','y','','ui-icon-alert','2013-11-14 17:57:58'),
('ReservStatus','co','Checked Out','y','y','','ui-icon-extlink','2013-11-14 17:57:58'),
('ReservStatus','p','New','y','y','','','2013-11-14 17:57:58'),
('ReservStatus','s','Checked In','y','y','','ui-icon-circle-check','2013-11-19 15:16:20'),
('ReservStatus','td','Turned Down','y','y','','ui-icon-arrowreturnthick-1-s','2013-11-14 17:57:58'),
('ReservStatus', 'im', 'Immediate', 'y', 'y', '', 'ui-icon-check', NULL),
('ReservStatus','w','Waitlist','y','y','','ui-icon-arrowstop-1-e','2013-11-14 17:57:58');
-- ;



--
-- insert System configuration
--
REPLACE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES 
('CardSwipe','false','b','f','Use POS terminal'),
('ConfirmFile','hhk.html','s','h','Reservation Confirmation file template name'),
('ConcatVisitNotes', 'true', 'b', 'h', 'Show notes combined from all previous visits when true.'),
('DefaultPayType','ca','s','f','Default payment type for paying today UI'),
('DefaultVisitFee', '1', 's', 'h', 'Default Visit Fee selected on new check-in and reservation pages.'),
('DefaultCkBalStmt', 'false', 'b', 'h', 'Check the Balenace Statement checkbox by default'),
('DefaultRegisterTab', '0', 'i', 'h', 'Default active tab on register page, 0 = calendar, 1 = current Guests'),
('Doctor', 'true', 'b', 'h','Track doctors'),
('EmailBlockSize','200','i','r','Number of email addresses per block.'),
('EmergContactFill', 'true', 'b', 'h', 'Insist on Filling in the emergency contact (or check the \"skip\")'),
('EmptyExtendLimit', '0', 'i', 'h','Extend visit (go on leave) limit # of days'),
('FutureLimit','1','i','v','Max years in the future volunteers can reserve shifts'),
('ForceNamePrefix', 'false', 'b', 'h', 'Force the name prefix to be entered'),
('fy_diff_Months','0','i','f','Fiscal year difference months (12 - fiscal year start month)'),
('GuestAddr', 'true', 'b', 'h', 'False = do not collect guest address'),
('IncludeLastDay','false','b','h','Include the departure day in room searches.'),
('IncomeRated', 'true', 'b', 'h','Use Income chooser rate assistance'),
('InitResvStatus', 'a', 's', 'h','Initial reservation status setting, confirmed or unconfirmed'),
('InsuranceChooser', 'true', 'b', 'h', 'Show patient insurance chooser'),
('KeyDeposit','true','b','h','Use Room or Key deposit'),
('LangChooser', 'true', 'b', 'h', 'Show member language chooser'),
('MajorDonation','500','i','d','Major donator trigger amount'),
('MaxDonate','100000','i','d','Maximum amount amount for a single payment'),
('MaxExpected','60','i','h','Maximum Expected days out for a visit'),
('MaxRepeatEvent','53','i','v','Maximum number of times to repeat a calendar event.'),
('NightsCounter', 'calYear', 's', 'h','Count nights by year (calYear) or by grand total.'),
('OpenCheckin','true','b','h','Allow walk-ups to check in'),
('PatientAddr', 'false', 'b', 'h','Collect the patient address.'),
('PatientAsGuest','true','b','h','House allows patients to stay as guests'),
('PatientBirthDate', 'true', 'b', 'h','Insist on providing the patients birthdate'),
('PayAtCkin','true','b','h','Allow/Disallow payments at check-in time'),
('PayVFeeFirst', 'false', 'b', 'h','Default check the visit fees payment checkbox'),
('PaymentLogoUrl','images/hostpaylogo.jpg','s','f','Path to payment page logo image file'),
('PreviousNights','0','i','h','Previous nights to add to nights counter'),
('RateGlideExtend', '0', 'i', 'h','# of days for the Room Rate Glide to time out after visit check-out'),
('ReferralAgent', 'true', 'b', 'h','Track referral agents/social workers'),
('RegColors','hospital','s','h','Calendar page ribbon colors based on hospital or room'),
('RegForm','1','i','h','1 = Registration form style 1, 2 = style 2'),
('RegFormNoRm', 'false', 'b', 'h','Do not show the room number on the registration form before check-in'),
('ResvEarlyArrDays', '5', 'i', 'h','# Days before reservation to show check-in button on reservation chooser'),
('Reservation','true','b','h','Use reservations'),
('RoomPriceModel', 'ns', 's', 'h','Room rate price model - Do not change!'),
('RoomsPerPatient', '2', 'i', 'h','# simultaneous rooms per patient allowed'),
('RoomRateDefault', 'e', 's', 'h', 'Default room rate category (a, b, c, d, e, x)'),
('SessionTimeout', '30', 'i', 'f', 'Number of minutes until an idle session get automatically logged out, 0 = never log out'),
('ShowDiagTB', 'false', 'b', 'h', 'Show the diagnosis textbox'),
('ShoStaysCtr', 'true', 'b', 'h', 'Show the stays counter on the House Calendar page'),
('ShowLodgDates', 'true', 'b', 'h','Show dates on lodging invoice lines'),
('SolicitBuffer','90','i','r','Timeout in days after visit checkout before solicit report will show new guests'),
('ShowUncfrmdStatusTab', 'true', 'b', 'h', 'Show the Unconfirmed reservations tab on the House Register page'),
('ShrRm', 'false', 'b', 'h','Use the share rooms feature'),
('TrackAuto','true','b','h','Track vehicles'),
('VisitFee', 'false', 'b', 'h','Use the visit fee (cleaning fee) feature'),
('VisitExcessPaid', 'i', 's', 'h','Default place for excess visit payments'),
('VerifyHospDate', 'true', 'b', 'h','Insist on hospital treatment date entry');
-- ;


replace into `item` (`idItem`, `Internal_Number`, `Entity_Id`, `Gl_Code`, `Description`) values 
(1, 'n1', 0, 1, 'Lodging'),
(2, 'c1', 0, 1, 'Cleaning Fee'),
(3, 'k1', 0, 2, 'Deposit'),
(4, 'k2', 0, 2, 'Deposit Refund'),
('5', 'I1', '0', '3', 'Carried From Inv. #'),
('6', 'd1', '0', '4', 'Discount'),
('7', 'n2', '0', '5', 'Reversal'),
('8', 'n0', '0', '6', 'Lodging Donation'),
('9', 'a1', '0', '7', ''),
('10', 'n3', '0',  '8', 'Lodging MOA'),
('11', 'd2', '0', '4', 'Waive');
-- ;


Replace INTO `item_price` (`idItem_price`, `Item_Id`, `Currency_Id`, `Price`, `ModelCode`) VALUES 
('1', '1', '0', '40.00', ''),
('2', '2', '0', '15.00', ''),
('3', '3', '0', '20.00', ''),
('4', '4', '0', '-20.00', ''),
('5', '5', '0', '0', ''),
('6', '6', '0', '0', ''),
('7', '7', '0', '0', ''),
('8', '8', '0', '0', ''),
('9', '9', '0', '0', ''),
('10', '10', '0', '0', ''),
('11', '11', '0', '0', '');
-- ;


replace into `item_type` values 
(1, 1, 'Items', 0, 0),
(2, 1, 'Tax', 0, 0),
(3, 1, 'Refundable', 0,0),
(4, 2, 'Duration', 0, 0),
(5, '1', 'Refund', 0, '0'),
(6, '1', 'Discount', 0, '0');
-- ;


INSERT INTO `item_type_map` values
(1,4),
(2,1),
(3,3),
(4,3),
(5, 1),
(6,6),
(7,5),
(8,6),
(9,1),
(10, 3);
-- ;


INSERT INTO `payment_method` (`idPayment_method`, `Method_Name`) VALUES 
('1', 'Cash'),
('2', 'Charge'),
('3', 'Check'),
('4', 'ChgAsCash'),
('5', 'Transfer');
-- ;



replace INTO invoice_line_type (id, Description, Order_Position) VALUES 
(1,'item recurring',2),
(2,'tax',6),
(3,'due invoice',1),
(4,'hold',8),
(5,'sub account',5),
(6,'item one-time',3),
('7', 'reimburse', '9');
-- ;


REPLACE INTO `insurance_type` (`idInsurance_type`, `Title`, `Is_Primary`, `Multiselect`, `List_Order`) VALUES 
('h', 'Primary', '1', '10', '10'),
('p', 'Private', '0', '1', '20');
-- ;

--
-- insert super user
--
REPLACE into name (idName, Name_Last, Member_Type, Member_Status, Record_Member)
	values (-1, 'admin', 'ai', 'a', 1);
-- ;

REPLACE INTO `w_auth` (`idName`,`Role_Id`,`Organization_Id`,`Policy_id`,`Updated_By`,`Last_Updated`,`User_Name`,`Status`) 
    VALUES (-1,'10','p',0,'admin',now(),'admin','a');
-- ;

REPLACE INTO `w_users` VALUES (-1,'admin','539e17171312c324d3c23908f85f3149','a','','','','','done',NULL,'','',NULL,now());
-- ;


--
-- Table `w_groups`
--
REPLACE INTO `w_groups` VALUES
('db','Maintenance','Configure metadata.','','','','\0','','2013-08-07 16:19:17','admin','2013-07-28 16:34:25'),
('dm','Donation Management','Donation Management','','','','\0','','2013-08-07 16:11:22','admin','2013-07-28 16:34:25'),
('dna','Donors (No Amounts)','View lists of donors but without donation amounts','','','','\0','','2013-08-07 16:16:10','admin','2013-07-28 16:34:25'),
('g','Guest Operations','Guest Operations, basic access to guest tracking site','','','','','','2013-08-07 16:19:17','admin','2013-07-28 16:34:25'),
('ga','Guest Admin','Guest Administration level access to guest tracking site','','','','\0','','2013-08-07 16:19:17','admin','2013-07-28 16:34:25'),
('mm','Member Management','Member Management, basic access to admin site.','','','','\0','','2013-08-07 16:19:40','admin','2013-07-28 16:34:25'),
('pub','Public','Public','','','','\0','','2013-08-07 16:11:22','admin','2013-07-28 16:34:25'),
('v','Volunteer','Volunteer site.','','','','\0','','2013-08-07 16:19:17','admin','2013-07-28 16:34:25');
-- ;


--
-- Dumping data for table `counter`
--
REPLACE INTO `counter` VALUES
(1,'relationship',10,NULL),
(4,'repeater',10,NULL),
(5,'codes',100,NULL),
(6, 'invoice', 1000, NULL);
-- ;


--
-- Mercury Hosted Gateway
--
REPLACE INTO `cc_hosted_gateway` (`cc_name`, `Merchant_Id`, `Password`, `Credit_Url`, `Trans_Url`, `CardInfo_Url`, `Checkout_Url`, `Mobile_CardInfo_Url`, `Mobile_Checkout_Url`) 
VALUES 
('Test', '', '', 'https://hc.mercurydev.net/hcws/hcservice.asmx?WSDL', 'https://hc.mercurydev.net/tws/TransactionService.asmx?WSDL', 'https://hc.mercurydev.net/CardInfo.aspx', 'https://hc.mercurydev.net/Checkout.aspx', 'https://hc.mercurydev.net/mobile/mCardInfo.aspx', 'https://hc.mercurydev.net/mobile/mCheckout.aspx'),
('Production', '', '', 'https://hc.mercurypay.com/hcws/hcservice.asmx?WSDL', 'https://hc.mercurypay.com/tws/transactionservice.asmx?WSDL', 'https://hc.mercurypay.com/CardInfo.aspx', 'https://hc.mercurypay.com/Checkout.aspx', 'https://hc.mercurypay.com/mobile/mCardInfo.aspx', 'https://hc.mercurypay.com/mobile/mCheckout.aspx');
-- ;


REPLACE into transaction_type values
(1, 'Sale', '', 's'),
(2, 'Void', '', 'vs'),
(3, 'Return', '', 'r'),
(4, 'Void Return', '', 'vr');
-- ;

--
-- Dumping data for table `street_suffix`
--
REPLACE INTO `street_suffix` VALUES ('ALLEE','ALY','Aly'),('ALLEY','ALY','Aly'),('ALLY','ALY','Aly'),('ALY','ALY','Aly'),('ANEX','ANX','Anx'),('ANNEX','ANX','Anx'),('ANNX','ANX','Anx'),('ANX','ANX','Anx'),('ARC','ARC','Arc'),('ARCADE','ARC','Arc'),('AV','AVE','Ave'),('AVE','AVE','Ave'),('AVEN','AVE','Ave'),('AVENU','AVE','Ave'),('AVENUE','AVE','Ave'),('AVN','AVE','Ave'),('AVNUE','AVE','Ave'),('BAYOO','BYU','Byu'),('BAYOU','BYU','Byu'),('BCH','BCH','Bch'),('BEACH','BCH','Bch'),('BEND','BND','Bnd'),('BND','BND','Bnd'),('BLF','BLF','Blf'),('BLUF','BLF','Blf'),('BLUFF','BLF','Blf'),('BLUFFS','BLFS','Blfs'),('BOT','BTM','Btm'),('BOTTM','BTM','Btm'),('BOTTOM','BTM','Btm'),('BTM','BTM','Btm'),('BLVD','BLVD','Blvd'),('BOUL','BLVD','Blvd'),('BOULEVARD','BLVD','Blvd'),('BOULV','BLVD','Blvd'),('BR','BR','Br'),('BRANCH','BR','Br'),('BRNCH','BR','Br'),('BRDGE','BRG','Brg'),('BRG','BRG','Brg'),('BRIDGE','BRG','Brg'),('BRK','BRK','Brk'),('BROOK','BRK','Brk'),('BROOKS','BRKS','Brks'),('BURG','BG','Bg'),('BURGS','BGS','Bgs'),('BYP','BYP','Byp'),('BYPA','BYP','Byp'),('BYPAS','BYP','Byp'),('BYPASS','BYP','Byp'),('BYPS','BYP','Byp'),('CAMP','CP','Cp'),('CMP','CP','Cp'),('CP','CP','Cp'),('CANYN','CYN','Cyn'),('CANYON','CYN','Cyn'),('CNYN','CYN','Cyn'),('CYN','CYN','Cyn'),('CAPE','CPE','Cpe'),('CPE','CPE','Cpe'),('CAUSEWAY','CSWY','Cswy'),('CAUSWAY','CSWY','Cswy'),('CSWY','CSWY','Cswy'),('CEN','CTR','Ctr'),('CENT','CTR','Ctr'),('CENTER','CTR','Ctr'),('CENTR','CTR','Ctr'),('CENTRE','CTR','Ctr'),('CNTER','CTR','Ctr'),('CNTR','CTR','Ctr'),('CTR','CTR','Ctr'),('CENTERS','CTRS','Ctrs'),('CIR','CIR','Cir'),('CIRC','CIR','Cir'),('CIRCL','CIR','Cir'),('CIRCLE','CIR','Cir'),('CRCL','CIR','Cir'),('CRCLE','CIR','Cir'),('CIRCLES','CIRS','Cirs'),('CLF','CLF','Clf'),('CLIFF','CLF','Clf'),('CLFS','CLFS','Clfs'),('CLIFFS','CLFS','Clfs'),('CLB','CLB','Clb'),('CLUB','CLB','Clb'),('COMMON','CMN','Cmn'),('COR','COR','Cor'),('CORNER','COR','Cor'),('CORNERS','CORS','Cors'),('CORS','CORS','Cors'),('COURSE','CRSE','Crse'),('CRSE','CRSE','Crse'),('COURT','CT','Ct'),('CRT','CT','Ct'),('CT','CT','Ct'),('COURTS','CTS','Cts'),('CTS','CTS','Cts'),('COVE','CV','Cv'),('CV','CV','Cv'),('COVES','CVS','Cvs'),('CK','CRK','Crk'),('CR','CRK','Crk'),('CREEK','CRK','Crk'),('CRK','CRK','Crk'),('CRECENT','CRES','Cres'),('CRES','CRES','Cres'),('CRESCENT','CRES','Cres'),('CRESENT','CRES','Cres'),('CRSCNT','CRES','Cres'),('CRSENT','CRES','Cres'),('CRSNT','CRES','Cres'),('CREST','CRST','Crst'),('CROSSING','XING','Xing'),('CRSSING','XING','Xing'),('CRSSNG','XING','Xing'),('XING','XING','Xing'),('CROSSROAD','XRD','Xrd'),('CURVE','CURV','Curv'),('DALE','DL','Dl'),('DL','DL','Dl'),('DAM','DM','Dm'),('DM','DM','Dm'),('DIV','DV','Dv'),('DIVIDE','DV','Dv'),('DV','DV','Dv'),('DVD','DV','Dv'),('DR','DR','Dr'),('DRIV','DR','Dr'),('DRIVE','DR','Dr'),('DRV','DR','Dr'),('DRIVES','DRS','Drs'),('EST','EST','Est'),('ESTATE','EST','Est'),('ESTATES','ESTS','Ests'),('ESTS','ESTS','Ests'),('EXP','EXPY','Expy'),('EXPR','EXPY','Expy'),('EXPRESS','EXPY','Expy'),('EXPRESSWAY','EXPY','Expy'),('EXPW','EXPY','Expy'),('EXPY','EXPY','Expy'),('EXT','EXT','Ext'),('EXTENSION','EXT','Ext'),('EXTN','EXT','Ext'),('EXTNSN','EXT','Ext'),('EXTENSIONS','EXTS','Exts'),('EXTS','EXTS','Exts'),('FALL','FALL','Fall'),('FALLS','FLS','Fls'),('FLS','FLS','Fls'),('FERRY','FRY','Fry'),('FRRY','FRY','Fry'),('FRY','FRY','Fry'),('FIELD','FLD','Fld'),('FLD','FLD','Fld'),('FIELDS','FLDS','Flds'),('FLDS','FLDS','Flds'),('FLAT','FLT','Flt'),('FLT','FLT','Flt'),('FLATS','FLTS','Flts'),('FLTS','FLTS','Flts'),('FORD','FRD','Frd'),('FRD','FRD','Frd'),('FORDS','FRDS','Frds'),('FOREST','FRST','Frst'),('FORESTS','FRST','Frst'),('FRST','FRST','Frst'),('FORG','FRG','Frg'),('FORGE','FRG','Frg'),('FRG','FRG','Frg'),('FORGES','FRGS','Frgs'),('FORK','FRK','Frk'),('FRK','FRK','Frk'),('FORKS','FRKS','Frks'),('FRKS','FRKS','Frks'),('FORT','FT','Ft'),('FRT','FT','Ft'),('FT','FT','Ft'),('FREEWAY','FWY','Fwy'),('FREEWY','FWY','Fwy'),('FRWAY','FWY','Fwy'),('FRWY','FWY','Fwy'),('FWY','FWY','Fwy'),('GARDEN','GDN','Gdn'),('GARDN','GDN','Gdn'),('GDN','GDN','Gdn'),('GRDEN','GDN','Gdn'),('GRDN','GDN','Gdn'),('GARDENS','GDNS','Gdns'),('GDNS','GDNS','Gdns'),('GRDNS','GDNS','Gdns'),('GATEWAY','GTWY','Gtwy'),('GATEWY','GTWY','Gtwy'),('GATWAY','GTWY','Gtwy'),('GTWAY','GTWY','Gtwy'),('GTWY','GTWY','Gtwy'),('GLEN','GLN','Gln'),('GLN','GLN','Gln'),('GLENS','GLNS','Glns'),('GREEN','GRN','Grn'),('GRN','GRN','Grn'),('GREENS','GRNS','Grns'),('GROV','GRV','Grv'),('GROVE','GRV','Grv'),('GRV','GRV','Grv'),('GROVES','GRVS','Grvs'),('HARB','HBR','Hbr'),('HARBOR','HBR','Hbr'),('HARBR','HBR','Hbr'),('HBR','HBR','Hbr'),('HRBOR','HBR','Hbr'),('HARBORS','HBRS','Hbrs'),('HAVEN','HVN','Hvn'),('HAVN','HVN','Hvn'),('HVN','HVN','Hvn'),('HEIGHT','HTS','Hts'),('HEIGHTS','HTS','Hts'),('HGTS','HTS','Hts'),('HT','HTS','Hts'),('HTS','HTS','Hts'),('HIGHWAY','HWY','Hwy'),('HIGHWY','HWY','Hwy'),('HIWAY','HWY','Hwy'),('HIWY','HWY','Hwy'),('HWAY','HWY','Hwy'),('HWY','HWY','Hwy'),('HILL','HL','Hl'),('HL','HL','Hl'),('HILLS','HLS','Hls'),('HLS','HLS','Hls'),('HLLW','HOLW','Holw'),('HOLLOW','HOLW','Holw'),('HOLLOWS','HOLW','Holw'),('HOLW','HOLW','Holw'),('HOLWS','HOLW','Holw'),('INLET','INLT','Inlt'),('INLT','INLT','Inlt'),('IS','IS','Is'),('ISLAND','IS','Is'),('ISLND','IS','Is'),('ISLANDS','ISS','Iss'),('ISLNDS','ISS','Iss'),('ISS','ISS','Iss'),('ISLE','ISLE','Isle'),('ISLES','ISLE','Isle'),('JCT','JCT','Jct'),('JCTION','JCT','Jct'),('JCTN','JCT','Jct'),('JUNCTION','JCT','Jct'),('JUNCTN','JCT','Jct'),('JUNCTON','JCT','Jct'),('JCTNS','JCTS','Jcts'),('JCTS','JCTS','Jcts'),('JUNCTIONS','JCTS','Jcts'),('KEY','KY','Ky'),('KY','KY','Ky'),('KEYS','KYS','Kys'),('KYS','KYS','Kys'),('KNL','KNL','Knl'),('KNOL','KNL','Knl'),('KNOLL','KNL','Knl'),('KNLS','KNLS','Knls'),('KNOLLS','KNLS','Knls'),('LAKE','LK','Lk'),('LK','LK','Lk'),('LAKES','LKS','Lks'),('LKS','LKS','Lks'),('LAND','LAND','Land'),('LANDING','LNDG','Lndg'),('LNDG','LNDG','Lndg'),('LNDNG','LNDG','Lndg'),('LA','LN','Ln'),('LANE','LN','Ln'),('LANES','LN','Ln'),('LN','LN','Ln'),('LGT','LGT','Lgt'),('LIGHT','LGT','Lgt'),('LIGHTS','LGTS','Lgts'),('LF','LF','Lf'),('LOAF','LF','Lf'),('LCK','LCK','Lck'),('LOCK','LCK','Lck'),('LCKS','LCKS','Lcks'),('LOCKS','LCKS','Lcks'),('LDG','LDG','Ldg'),('LDGE','LDG','Ldg'),('LODG','LDG','Ldg'),('LODGE','LDG','Ldg'),('LOOP','LOOP','Loop'),('LOOPS','LOOP','Loop'),('MALL','MALL','Mall'),('MANOR','MNR','Mnr'),('MNR','MNR','Mnr'),('MANORS','MNRS','Mnrs'),('MNRS','MNRS','Mnrs'),('MDW','MDW','Mdw'),('MEADOW','MDW','Mdw'),('MDWS','MDWS','Mdws'),('MEADOWS','MDWS','Mdws'),('MEDOWS','MDWS','Mdws'),('MEWS','MEWS','Mews'),('MILL','ML','Ml'),('ML','ML','Ml'),('MILLS','MLS','Mls'),('MLS','MLS','Mls'),('MISSION','MSN','Msn'),('MISSN','MSN','Msn'),('MSN','MSN','Msn'),('MSSN','MSN','Msn'),('MOTORWAY','MTWY','Mtwy'),('MNT','MT','Mt'),('MOUNT','MT','Mt'),('MT','MT','Mt'),('MNTAIN','MTN','Mtn'),('MNTN','MTN','Mtn'),('MOUNTAIN','MTN','Mtn'),('MOUNTIN','MTN','Mtn'),('MTIN','MTN','Mtn'),('MTN','MTN','Mtn'),('MNTNS','MTNS','Mtns'),('MOUNTAINS','MTNS','Mtns'),('NCK','NCK','Nck'),('NECK','NCK','Nck'),('ORCH','ORCH','Orch'),('ORCHARD','ORCH','Orch'),('ORCHRD','ORCH','Orch'),('OVAL','OVAL','Oval'),('OVL','OVAL','Oval'),('OVERPASS','OPAS','Opas'),('PARK','PARK','Park'),('PK','PARK','Park'),('PRK','PARK','Park'),('PARKS','PARK','Park'),('PARKWAY','PKWY','Pkwy'),('PARKWY','PKWY','Pkwy'),('PKWAY','PKWY','Pkwy'),('PKWY','PKWY','Pkwy'),('PKY','PKWY','Pkwy'),('PARKWAYS','PKWY','Pkwy'),('PKWYS','PKWY','Pkwy'),('PASS','PASS','Pass'),('PASSAGE','PSGE','Psge'),('PATH','PATH','Path'),('PATHS','PATH','Path'),('PIKE','PIKE','Pike'),('PIKES','PIKE','Pike'),('PINE','PNE','Pne'),('PINES','PNES','Pnes'),('PNES','PNES','Pnes'),('PL','PL','Pl'),('PLACE','PL','Pl'),('PLAIN','PLN','Pln'),('PLN','PLN','Pln'),('PLAINES','PLNS','Plns'),('PLAINS','PLNS','Plns'),('PLNS','PLNS','Plns'),('PLAZA','PLZ','Plz'),('PLZ','PLZ','Plz'),('PLZA','PLZ','Plz'),('POINT','PT','Pt'),('PT','PT','Pt'),('POINTS','PTS','Pts'),('PTS','PTS','Pts'),('PORT','PRT','Prt'),('PRT','PRT','Prt'),('PORTS','PRTS','Prts'),('PRTS','PRTS','Prts'),('PR','PR','Pr'),('PRAIRIE','PR','Pr'),('PRARIE','PR','Pr'),('PRR','PR','Pr'),('RAD','RADL','Radl'),('RADIAL','RADL','Radl'),('RADIEL','RADL','Radl'),('RADL','RADL','Radl'),('RAMP','RAMP','Ramp'),('RANCH','RNCH','Rnch'),('RANCHES','RNCH','Rnch'),('RNCH','RNCH','Rnch'),('RNCHS','RNCH','Rnch'),('RAPID','RPD','Rpd'),('RPD','RPD','Rpd'),('RAPIDS','RPDS','Rpds'),('RPDS','RPDS','Rpds'),('REST','RST','Rst'),('RST','RST','Rst'),('RDG','RDG','Rdg'),('RDGE','RDG','Rdg'),('RIDGE','RDG','Rdg'),('RDGS','RDGS','Rdgs'),('RIDGES','RDGS','Rdgs'),('RIV','RIV','Riv'),('RIVER','RIV','Riv'),('RIVR','RIV','Riv'),('RVR','RIV','Riv'),('RD','RD','Rd'),('ROAD','RD','Rd'),('RDS','RDS','Rds'),('ROADS','RDS','Rds'),('ROUTE','RTE','Rte'),('ROW','ROW','Row'),('RUE','RUE','Rue'),('RUN','RUN','Run'),('SHL','SHL','Shl'),('SHOAL','SHL','Shl'),('SHLS','SHLS','Shls'),('SHOALS','SHLS','Shls'),('SHOAR','SHR','Shr'),('SHORE','SHR','Shr'),('SHR','SHR','Shr'),('SHOARS','SHRS','Shrs'),('SHORES','SHRS','Shrs'),('SHRS','SHRS','Shrs'),('SKYWAY','SKWY','Skwy'),('SPG','SPG','Spg'),('SPNG','SPG','Spg'),('SPRING','SPG','Spg'),('SPRNG','SPG','Spg'),('SPGS','SPGS','Spgs'),('SPNGS','SPGS','Spgs'),('SPRINGS','SPGS','Spgs'),('SPRNGS','SPGS','Spgs'),('SPUR','SPUR','Spur'),('SPURS','SPUR','Spur'),('SQ','SQ','Sq'),('SQR','SQ','Sq'),('SQRE','SQ','Sq'),('SQU','SQ','Sq'),('SQUARE','SQ','Sq'),('SQRS','SQS','Sqs'),('SQUARES','SQS','Sqs'),('STA','STA','Sta'),('STATION','STA','Sta'),('STATN','STA','Sta'),('STN','STA','Sta'),('STRA','STRA','Stra'),('STRAV','STRA','Stra'),('STRAVE','STRA','Stra'),('STRAVEN','STRA','Stra'),('STRAVENUE','STRA','Stra'),('STRAVN','STRA','Stra'),('STRVN','STRA','Stra'),('STRVNUE','STRA','Stra'),('STREAM','STRM','Strm'),('STREME','STRM','Strm'),('STRM','STRM','Strm'),('ST','ST','St'),('STR','ST','St'),('STREET','ST','St'),('STRT','ST','St'),('STREETS','STS','Sts'),('SMT','SMT','Smt'),('SUMIT','SMT','Smt'),('SUMITT','SMT','Smt'),('SUMMIT','SMT','Smt'),('TER','TER','Ter'),('TERR','TER','Ter'),('TERRACE','TER','Ter'),('THROUGHWAY','TRWY','Trwy'),('TRACE','TRCE','Trce'),('TRACES','TRCE','Trce'),('TRCE','TRCE','Trce'),('TRACK','TRAK','Trak'),('TRACKS','TRAK','Trak'),('TRAK','TRAK','Trak'),('TRK','TRAK','Trak'),('TRKS','TRAK','Trak'),('TRAFFICWAY','TRFY','Trfy'),('TRFY','TRFY','Trfy'),('TR','TRL','Trl'),('TRAIL','TRL','Trl'),('TRAILS','TRL','Trl'),('TRL','TRL','Trl'),('TRLS','TRL','Trl'),('TUNEL','TUNL','Tunl'),('TUNL','TUNL','Tunl'),('TUNLS','TUNL','Tunl'),('TUNNEL','TUNL','Tunl'),('TUNNELS','TUNL','Tunl'),('TUNNL','TUNL','Tunl'),('TPK','TPKE','Tpke'),('TPKE','TPKE','Tpke'),('TRNPK','TPKE','Tpke'),('TRPK','TPKE','Tpke'),('TURNPIKE','TPKE','Tpke'),('TURNPK','TPKE','Tpke'),('UNDERPASS','UPAS','Upas'),('UN','UN','Un'),('UNION','UN','Un'),('UNIONS','UNS','Uns'),('VALLEY','VLY','Vly'),('VALLY','VLY','Vly'),('VLLY','VLY','Vly'),('VLY','VLY','Vly'),('VALLEYS','VLYS','Vlys'),('VLYS','VLYS','Vlys'),('VDCT','VIA','Via'),('VIA','VIA','Via'),('VIADCT','VIA','Via'),('VIADUCT','VIA','Via'),('VIEW','VW','Vw'),('VW','VW','Vw'),('VIEWS','VWS','Vws'),('VWS','VWS','Vws'),('VILL','VLG','Vlg'),('VILLAG','VLG','Vlg'),('VILLAGE','VLG','Vlg'),('VILLG','VLG','Vlg'),('VILLIAGE','VLG','Vlg'),('VLG','VLG','Vlg'),('VILLAGES','VLGS','Vlgs'),('VLGS','VLGS','Vlgs'),('VILLE','VL','Vl'),('VL','VL','Vl'),('VIS','VIS','Vis'),('VIST','VIS','Vis'),('VISTA','VIS','Vis'),('VST','VIS','Vis'),('VSTA','VIS','Vis'),('WALK','WALK','Walk'),('WALKS','WALK','Walk'),('WALL','WALL','Wall'),('WAY','WAY','Way'),('WY','WAY','Way'),('WAYS','WAYS','Ways'),('WELL','WL','Wl'),('WELLS','WLS','Wls'),('WLS','WLS','Wls');
-- ;


--
-- Dumping data for table `secondary_unit_desig`
--
REPLACE INTO `secondary_unit_desig` VALUES ('APARTMENT','APT','','Apt'),('BASEMENT','BSMT','\0','Bsmt'),('BUILDING','BLDG','','Bldg'),('DEPARTMENT','DEPT','','Dept'),('FLOOR','FL','','Fl'),('FRONT','FRNT','\0','Frnt'),('HANGER','HNGR','','Hngr'),('KEY','KEY','','Key'),('LOBBY','LBBY','\0','Lbby'),('LOT','LOT','','Lot'),('LOWER','LOWR','\0','Lowr'),('OFFICE','OFC','\0','Ofc'),('PENTHOUSE','PH','\0','Ph'),('PIER','PIER','','Pier'),('REAR','REAR','\0','Rear'),('SIDE','SIDE','\0','Side'),('SLIP','SLIP','','Slip'),('SPACE','SPC','','Spc'),('STOP','STOP','','Stop'),('SUITE','STE','','Ste'),('TRAILER','TRLR','','Trlr'),('UNIT','UNIT','','Unit'),('UPPER','UPPR','\0','Uppr'),('APT','APT','\0','Apt'),('BLDG','BLDG','','Bldg'),('DEPT','DEPT','','Dept'),('FL','FL','','Fl'),('FRNT','FRNT','\0','Frnt'),('HNGR','HNGR','','Hngr'),('LBBY','LBBY','\0','Lbby'),('LOWR','LOWR','\0','Lowr'),('OFC','OFC','\0','Ofc'),('PH','PH','\0','Ph'),('SPC','SPC','','Spc'),('STE','STE','','Ste'),('TRLR','TRLR','','Trlr'),('UPPR','UPPR','\0','Uppr'),('RM','RM','','Rm'),('ROOM','RM','','Rm');
-- ;



-- 
-- Hospitals
--
REPLACE INTO `hospital` (`Title`,`Type`,`Status`) values
('Hospital', 'h', 'a');
-- ;

--
-- Dumping data for table `fa_category`
--
REPLACE INTO `fa_category` (`idFa_category`, `HouseHoldSize`, `Income_A`, `Income_B`, `Income_C`, `Income_D`, `Status`) VALUES 
('1', '1', '959', '1724', '2584', '2585', 'a'),
('2', '2', '1294', '2327', '3489', '3490', 'a'),
('3', '3', '1953', '3091', '4393', '3394', 'a'),
('4', '4', '2355', '3728', '5298', '5299', 'a'),
('5', '5', '2757', '4365', '6202', '6203', 'a'),
('6', '6', '3159', '5001', '7107', '7108', 'a'),
('7', '7', '3561', '5637', '7716', '8012', 'a'),
('8', '8', '3983', '6274', '8587', '8917', 'a');
-- ;


--
-- Dumping data for table `web_sites`
--
REPLACE INTO `web_sites` VALUES
(1,'a','Admin','/admin/','mm','ui-icon ui-icon-gear','',now(),'admin','NameSch.php','index.php','localhost'),
(2,'h','House','/house/','g,ga','ui-icon ui-icon-person','',now(),'admin','register.php','index.php','localhost'),
(3,'v','Volunteer','/volunteer/','v','ui-icon ui-icon-heart','',now(),'admin','VolAction.php','index.php','localhost'),
(4,'r','Root','/','pub','','',now(),'admin','','','localhost');
-- ;


--
-- Dumping data for table `page`
--
REPLACE INTO `page` VALUES 
(1,'index.php',0,'Welcome','r','','','p','','','2011-09-28 15:52:50','2011-09-28 20:52:50'),(2,'index.php',0,'','a','','','p','','admin','2011-09-28 15:52:50','2011-09-21 16:00:18'),(3,'NameEdit.php',2,'Edit Members','a','','','p','','admin','2014-07-25 12:27:19','2011-09-21 16:01:42'),(4,'EventShells.php',2,'Repeat Events','a','35','f','p','','','0000-00-00 00:00:00','2011-09-21 18:52:06'),(5,'KeyStats.php',2,'Key Stats','a','67','g','p','','admin','2012-06-11 14:28:56','2011-09-21 18:52:06'),(6,'Misc.php',2,'Miscellaneous','a','34','a','p','','admin','2012-04-09 12:04:46','2011-09-21 18:52:06'),(7,'PageEdit.php',2,'Edit Pages','a','34','e','p','','','0000-00-00 00:00:00','2011-09-21 18:52:06'),(8,'RegisterUser.php',2,'Register Web Users','a','35','e','p','','admin','2012-03-15 08:51:37','2011-09-21 18:52:06'),(9,'CategoryEdit.php',2,'Edit Categories','a','34','d','p','','admin','2012-01-18 11:55:50','2011-09-21 18:52:06'),
(10,'VolListing.php',2,'Web Users','a','35','c','p','','admin','2011-10-31 14:41:12','2011-09-21 18:52:06'),(11,'campaignEdit.php',2,'Edit Campaigns','a','34','c','p','','','0000-00-00 00:00:00','2011-09-21 18:56:43'),(12,'campaignReport.php',2,'Campaigns','a','32','d','p','','','0000-00-00 00:00:00','2011-09-21 18:56:43'),(13,'checkDateReport.php',2,'Check Date','a','32','j','p','','','0000-00-00 00:00:00','2011-09-21 18:56:43'),(14,'directory.php',2,'Directory','a','32','a','p','','','0000-00-00 00:00:00','2011-09-21 18:56:43'),(15,'donate.php',0,'','a','','','s','','','0000-00-00 00:00:00','2011-09-21 18:56:43'),(16,'donationReport.php',2,'Donations','a','32','b','p','','admin','2011-12-12 11:32:31','2011-09-21 18:56:43'),(18,'liveGetCamp.php',0,'','a','','','s','','','0000-00-00 00:00:00','2011-09-21 18:56:43'),(19,'liveNameSearch.php',0,'','a','','','s','','','0000-00-00 00:00:00','2011-09-21 18:56:43'),
(20,'ws_Report.php',0,'','a','','','s','','','0000-00-00 00:00:00','2011-09-21 18:56:43'),(21,'ws_gen.php',0,'','a','','','s','','','0000-00-00 00:00:00','2011-09-21 18:56:43'),(22,'VolNameEdit.php',26,'My Volunteer Info','v','0','d','p','','admin','2011-09-28 15:40:54','2011-09-21 19:01:58'),(23,'forgotpw.php',26,'Forgot My Password','v','','','p','','admin','2011-09-28 15:54:43','2011-09-21 19:01:58'),(24,'gCalFeed.php',0,'','v','','','s','','','0000-00-00 00:00:00','2011-09-21 19:01:58'),(26,'index.php',0,'','v','','','p','','admin','2011-09-28 15:50:30','2011-09-21 19:01:58'),(27,'register_web.php',26,'Register','v','','','p','','admin','2011-09-28 15:53:57','2011-09-21 19:01:58'),(28,'ws_reg_user.php',0,'','v','','','s','','','0000-00-00 00:00:00','2011-09-21 19:01:58'),(29,'ws_vol.php',0,'','v','','','s','','','0000-00-00 00:00:00','2011-09-21 19:01:58'),
(31,'index.php',0,'','h','','','p','','admin','2011-09-28 15:52:16','2011-09-21 19:03:58'),(32,'_directory.php',2,'Reports','a','0','e','p','','','0000-00-00 00:00:00','2011-09-22 12:20:36'),(33,'categoryReport.php',2,'Categories','a','32','f','p','','admin','2013-12-10 13:09:01','2011-09-22 12:25:04'),(34,'_Misc.php',2,'DB Maintenance','a','0','k','p','','admin','2011-10-13 10:42:35','2011-09-22 12:26:38'),(35,'_VolListing.php',2,'Web Users','a','0','j','p','','admin','2011-10-31 14:40:58','2011-09-22 12:27:25'),(36,'NameEdit_Donations',0,'','a','','','c','','','0000-00-00 00:00:00','2011-09-23 08:07:22'),(37,'NameEdit_Maint',0,'','a','','','c','','admin','2011-09-26 15:15:27','2011-09-27 11:24:53'),(39,'ws_gen_Maint',0,'','a','','','c','','admin','2011-09-26 15:41:54','2011-09-27 13:41:54'),
(45,'VolNameSearch.php',0,'','v','','','s','','admin','2011-10-09 19:24:53','2011-10-10 17:24:53'),(46,'guestadmin',0,'','h','','','c','','admin','2013-07-23 14:26:44','2011-10-15 07:01:45'),(47,'guestaccess',0,'','v','','','c','','admin','2011-10-17 15:23:29','2011-10-18 13:23:29'),(48,'PrivacyGroup',0,'','a','','','c','','admin','2011-10-31 20:38:16','2011-11-01 18:38:16'),(49,'recent.php',2,'Recent Changes','a','67','r','p','','admin','2012-06-11 14:29:48','2011-11-03 11:20:26'),
(50,'nonReportables.php',2,'Non-Reportables','a','67','v','p','','admin','2012-06-11 14:29:29','2011-12-03 19:06:32'),(51,'donorReport.php',2,'Donors','a','32','c','p','','admin','2011-12-24 17:42:31','2011-12-13 13:59:14'),(55,'MemEdit.php',0,'','v','','none','p','','admin','2012-02-07 16:36:02','2012-02-08 18:36:02'),(56,'Cat_Donor',0,'','a','','','c','','admin','2012-02-29 11:19:02','2012-03-01 13:19:02'),(57,'anomalies.php',2,'Anomaly report','a','67','k','p','','admin','2012-06-11 14:29:18','2012-03-08 23:28:42'),(59,'ws_admin.php',0,'','h','','','s','','admin','2013-02-25 17:01:00','2012-03-11 16:33:23'),
(60,'guestaccess',0,'','a','','','c','','admin','2012-03-26 14:04:37','2012-03-27 12:04:37'),(62,'roleSearch.php',0,'','h','','','s','','admin','2013-11-12 19:35:00','2012-03-31 08:46:51'),(64,'reportWindow.php',2,'Report','a','','','p','','admin','2012-05-31 11:06:52','2012-05-30 09:28:52'),(65,'timeReport.php',2,'Time Reports','a','32','u','p','','admin','2012-06-04 13:47:31','2012-06-05 11:47:31'),(66,'NameSch.php',2,'Members','a','0','d','p','','admin','2015-01-27 11:46:08','2012-06-12 12:22:04'),(67,'_KeyStats.php',2,'Key Stats','a','0','g','p','','admin','2012-06-11 14:28:41','2012-06-12 12:28:41'),(68,'VolAction.php',26,'Activities','v','0','b','p','','admin','2012-09-03 16:37:32','2012-06-12 13:21:41'),(69,'_index.php?log=lo',0,'Log Out','a','0','z','p','','admin','2012-06-17 13:07:24','2012-06-18 11:05:10'),
(70,'_index.php?log=lo',0,'Log Out','v','0','z','p','','admin','2012-06-17 13:08:23','2012-06-18 11:05:10'),(71,'_index.php?log=lo',0,'Log Out','h','0','z','p','','admin','2012-06-17 13:08:36','2012-06-18 11:05:10'),(72,'CheckIn.php',31,'Check In','h','0','f','p','','admin','2014-09-08 09:13:58','2012-10-20 14:12:53'),(74,'register.php',31,'House Register','h','79','b','p','','admin','2013-01-02 01:49:25','2012-12-08 15:25:34'),(75,'ws_register',0,'','h','','','s','','admin','2012-12-10 16:09:36','2012-12-11 18:09:36'),(76,'ws_ckin.php',0,'','h','','','s','','admin','2012-12-20 16:42:05','2012-12-21 18:42:05'),(77,'RoomView.php',31,'Room Viewer','h','79','d','p','','admin','2013-03-31 15:17:23','2013-01-03 02:54:45'),(79,'_register.php',31,'House','h','0','d','p','','admin','2013-01-02 01:48:49','2013-01-03 03:48:49'),
(80,'HouseCal.php',26,'','v','','','p','','admin','2013-03-03 17:31:24','2013-03-04 19:30:30'),(81,'ResourceBuilder.php',31,'Resource Builder','h','79','l','p','','admin','2013-03-25 13:19:56','2013-03-26 11:19:56'),(82,'ws_resc.php',0,'','h','','','s','','admin','2013-03-25 15:01:03','2013-03-26 13:01:03'),(83,'RoomUtilization.php',31,'Room Report','h','102','e','p','','admin','2014-06-18 12:59:29','2013-04-02 11:43:00'),(84,'memberManagement',0,'','h','','','c','','admin','2013-06-06 11:17:02','2013-06-07 09:17:02'),(88,'AuthGroupEdit.php',2,'Edit Authorization','a','34','j','p','','admin','2013-08-07 15:13:41','2013-08-08 13:13:05'),(89,'Configure.php',2,'Site Configuration','a','34','g','p','','admin','2013-08-17 10:11:05','2013-08-18 08:10:27'),
(92,'GuestDemog.php',31,'Missing Demographics','h','102','f','p','','admin','2014-03-05 15:47:22','2013-09-04 08:23:02'),(93,'GuestEdit.php',31,'Guest Edit','h','0','j','p','','admin','2014-03-05 15:49:04','2013-09-04 08:25:10'),(94,'ShowRegForm.php',31,'Registration Form','h','','','p','','admin','2013-10-21 10:56:12','2013-10-22 01:58:41'),(95,'Referral.php',31,'Reservation','h','0','e','p','','admin','2014-03-15 12:39:32','2013-11-07 09:32:21'),(96,'CheckedIn.php',31,'','h','','','p','','admin','2013-12-01 20:51:20','2013-12-02 14:54:22'),(99,'PaymentResult.php',31,'','h','','','p','','admin','2013-12-16 09:08:53','2013-12-17 03:14:11'),
(100,'ShowStatement.php',31,'Guest Statement','h','','','p','','admin','2014-01-29 15:39:24','2014-01-30 09:39:16'),(101,'RoomStatus.php',31,'Housekeeping','h','79','p','p','','admin','2014-02-25 10:47:31','2014-02-25 22:46:18'),(102,'_GuestReport.php',31,'Reports','h','0','h','p','','admin','2014-03-15 12:35:58','2014-03-06 03:46:56'),(104,'ReservReport.php',31,'Reservations Report','h','102','b','p','','admin','2014-03-15 12:37:09','2014-03-15 22:37:09'),(105,'PaymentTx.php',2,'Credit Transactions Report','a','32','v','p','','admin','2015-01-27 12:19:09','2014-08-13 22:25:03'),(106,'Duplicates.php',2,'Duplicates','a','32','o','p','','admin','2014-08-27 13:43:20','2014-08-27 23:43:21'),(107,'PSGReport.php',31,'People Reports','h','102','k','p','','admin','2015-05-10 12:59:58','2014-11-14 04:25:35'),(108,'Waitlist.php',31,'Anticipated Visits','h','0','g','p','','admin','2015-01-17 14:14:40','2015-01-17 20:14:40'),(109,'PaymentReport.php',31,'Payment Report','h','102','m','p','','admin','2015-02-09 14:59:12','2015-02-09 20:59:12'),
(110,'VisitInterval.php',31,'Visit Interval Report','h','102','c','p','','admin','2015-02-28 11:07:00','2015-02-17 17:47:21'),(111,'GuestView.php',31,'Guests & Vehicles','h','79','v','p','','admin','2015-02-28 11:07:47','2015-02-19 17:53:11'),(112,'RoomRevReport.php',31,'Room Revenue','h','102','o','p','','admin','2015-03-16 12:48:59','2015-03-16 17:48:59'),(113,'DRaHospReport.php',31,'Doctors, Hospitals','h','102','l','p','','admin','2015-05-15 12:22:26','2015-05-15 17:22:26'),(114,'ShowInvoice.php',31,'Show Invoice','h','','','p','','admin','2015-06-22 13:05:44','2015-06-22 18:05:44'),(115,'InvoiceReport.php',31,'Invoice Report','h','102','n','p','','admin','2015-07-28 17:32:12','2015-07-28 22:32:12'),(116,'ShowHsKpg.php',31,'Housekeeping','h','','','p','','admin','2015-08-10 15:00:39','2015-08-10 20:00:39'),(117,'PrtRegForm.php',31,'Print Registration Forms','h','','','p','','admin','2015-09-05 11:47:07','2015-09-05 16:47:07'),(118,'occDemo.php',31,'Guest Demographics','h','102','g','p','','admin','2015-09-05 11:47:07','2015-09-05 16:47:07'),(119,'ItemReport.php',31,'Item Report','h','102','s','p','','admin','2015-09-05 11:47:07','2015-09-05 16:47:07'),
(120, 'AccessLog.php', 2, 'User Access Log', 'a', '35', 'd', 'p','','admin','2016-08-21 11:07:00', '2016-08-21 11:07:00');
-- ;

--
-- Dumping data for table `page_securitygroup`
--
REPLACE INTO `page_securitygroup` VALUES 
(1,'pub','2011-09-29 14:03:46'),(2,'pub','2011-09-24 07:14:44'),(3,'mm','2011-09-21 19:21:42'),(4,'mm','2011-09-21 19:21:42'),(5,'mm','2011-09-21 19:21:42'),(6,'db','2011-09-21 19:21:42'),(7,'db','2011-09-21 19:21:42'),(8,'mm','2011-09-21 19:21:42'),(9,'db','2011-09-21 19:21:42'),
(10,'mm','2011-09-21 19:21:42'),(11,'db','2011-09-21 19:21:42'),(12,'dm','2011-09-21 19:21:42'),(13,'mm','2011-09-21 19:21:42'),(14,'mm','2011-09-21 19:21:42'),(15,'dm','2011-09-21 19:21:42'),(16,'dm','2011-09-21 19:21:42'),(18,'mm','2011-09-21 19:21:42'),(19,'mm','2011-09-21 19:21:42'),
(20,'dm','2011-09-21 19:21:42'),(21,'mm','2011-09-21 19:21:42'),(22,'v','2011-09-21 19:24:43'),(23,'pub','2011-09-29 14:01:12'),(24,'v','2011-09-21 19:24:43'),(26,'pub','2011-09-24 07:15:17'),(27,'pub','2011-09-29 14:02:08'),(28,'pub','2011-09-29 14:02:08'),(29,'v','2011-09-21 19:24:43'),
(31,'pub','2011-09-24 07:15:48'),(32,'mm','2011-09-22 14:36:57'),(33,'ga','2013-12-11 07:12:07'),(33,'mm','2011-09-22 14:48:59'),(34,'db','2011-09-22 14:36:57'),(35,'mm','2011-09-22 14:36:57'),(36,'dm','2011-09-24 09:22:47'),(37,'db','2011-09-27 11:24:53'),(39,'db','2011-09-27 13:41:54'),
(45,'v','2011-10-10 17:24:53'),(46,'ga','2011-10-15 07:01:45'),(47,'g','2011-10-18 13:23:29'),(48,'p','2011-11-01 18:38:16'),(49,'mm','2011-11-03 11:20:26'),
(50,'mm','2011-12-03 19:06:32'),(51,'dna','2011-12-13 13:59:14'),(55,'v','2012-02-08 18:36:02'),(56,'dna','2012-03-01 13:19:02'),(57,'mm','2012-03-08 23:28:42'),(59,'g','2012-03-11 16:33:23'),(59,'ga','2013-07-30 11:07:23'),
(60,'g','2012-03-27 12:04:37'),(62,'g','2012-03-31 08:46:51'),(62,'ga','2013-07-30 11:07:35'),(64,'mm','2012-05-30 09:28:52'),(65,'mm','2012-06-05 11:47:31'),(66,'mm','2012-06-12 12:22:04'),(67,'mm','2012-06-12 12:28:41'),(68,'v','2012-06-12 13:21:41'),(69,'pub','2012-06-18 11:10:47'),
(70,'pub','2012-06-18 11:10:47'),(71,'pub','2012-06-18 11:10:47'),(72,'g','2012-10-20 14:12:53'),(72,'ga','2013-07-30 11:06:22'),(74,'g','2013-07-30 11:00:11'),(74,'ga','2013-07-29 10:25:53'),(75,'g','2012-12-11 18:09:36'),(75,'ga','2013-07-30 11:07:47'),(76,'g','2012-12-21 18:42:05'),(76,'ga','2013-07-30 11:07:55'),(77,'g','2013-01-03 02:54:45'),(77,'ga','2013-07-30 11:06:47'),(79,'g','2013-01-03 03:48:49'),(79,'ga','2013-07-30 11:06:12'),
(80,'v','2013-03-04 19:30:30'),(81,'db','2013-03-26 11:19:56'),(82,'db','2013-03-26 13:01:03'),(83,'ga','2013-08-04 07:43:13'),(84,'mm','2013-06-07 09:17:02'),(88,'db','2013-08-08 13:13:05'),(89,'db','2013-08-18 08:10:27'),
(90,'g','2013-08-23 03:34:05'),(90,'mm','2013-08-23 03:34:05'),(92,'ga','2013-09-04 08:23:02'),(93,'g','2013-09-04 08:25:10'),(93,'ga','2013-09-04 08:25:10'),(94,'g','2013-10-22 01:58:41'),(94,'ga','2013-10-22 01:58:41'),(95,'g','2013-11-07 09:32:21'),(95,'ga','2013-11-07 09:32:21'),(96,'g','2013-12-02 14:54:22'),(96,'ga','2013-12-02 14:54:22'),(99,'g','2013-12-17 03:14:12'),(99,'ga','2013-12-17 03:14:12'),(100,'g','2014-01-30 09:39:16'),
(100,'ga','2014-01-30 09:39:16'),(101,'g','2014-02-25 22:46:18'),(101,'ga','2014-02-25 22:46:18'),(102,'ga','2014-03-06 03:46:56'),(104,'ga','2014-03-15 22:37:09'),(105,'db','2014-08-13 22:25:03'),(106,'mm','2014-08-27 23:43:21'),(107,'ga','2014-11-14 04:25:35'),(108,'g','2015-01-17 20:14:40'),(108,'ga','2015-01-17 20:14:40'),(109,'ga','2015-02-09 20:59:12'),
(110,'ga','2015-02-17 17:47:21'),(111,'g','2015-02-19 17:53:11'),(111,'ga','2015-02-19 17:53:11'),(112,'ga','2015-03-16 17:48:59'),(112,'mm','2015-03-16 17:48:59'),(113,'ga','2015-05-15 17:22:26'),(114,'g','2015-06-22 18:05:44'),(114,'ga','2015-06-22 18:05:44'),(115,'ga','2015-07-28 22:32:12'),(116,'g','2015-08-10 20:00:39'),(116,'ga','2015-08-10 20:00:39'),(117,'g','2015-09-05 16:47:07'),(117,'ga','2015-09-05 16:47:07'),(118,'ga','2015-09-05 16:47:07'),(119,'ga','2015-09-05 16:47:07'),
(120, 'mm', '2016-08-21 11:07:00');
-- ;

--
-- Dumping data for table `language`
--
REPLACE INTO `language` VALUES (1,'Abkhazian','ab',0),(2,'Afar','aa',0),(3,'Afrikaans','af',0),(4,'Akan','ak',0),(6,'Albanian','sq',0),(7,'Amharic','am',0),
(10,'Arabic','ar',0),(11,'Aragonese','an',0),(14,'Armenian','hy',0),(18,'Assamese','as',0),(24,'Avaric','av',0),(25,'Avestan','ae',0),(27,'Aymara','ay',0),(28,'Azerbaijani','az',0),
(33,'Bambara','bm',0),(38,'Bashkir','ba',0),(39,'Basque','eu',0),(43,'Belarusian','be',0),(45,'Bengali','bn',0),(48,'Bihari languages','bh',0),(52,'Bislama','bi',0),
(57,'BokmÃ¥l, Norwegian','nb',0),(58,'Bosnian','bs',0),(60,'Breton','br',0),(62,'Bulgarian','bg',0),(64,'Burmese','my',0),(66,'Castilian','es',0),(67,'Catalan','ca',0),
(72,'Central Khmer','km',0),(75,'Chamorro','ch',0),(76,'Chechen','ce',0),(78,'Chewa','ny',0),(81,'Chichewa','ny',0),(82,'Chinese','zh',0),(86,'Chuang','za',0),(87,'Church Slavic','cu',0),
(88,'Church Slavonic','cu',0),(90,'Chuvash','cv',0),(96,'Cornish','kw',0),(97,'Corsican','co',0),(98,'Cree','cr',0),(106,'Croatian','hr',0),(108,'Czech','cs',0),(110,'Danish','da',0),
(114,'Dhivehi','dv',0),(118,'Divehi','dv',0),(123,'Dutch','nl',0),(126,'Dzongkha','dz',0),(133,'English','en',1),(137,'Esperanto','eo',0),(138,'Estonian','et',0),(139,'Ewe','ee',0),
(143,'Faroese','fo',0),(144,'Fijian','fj',0),(146,'Finnish','fi',0),(148,'Flemish','nl',0),(150,'French','fr',1),(154,'Fulah','ff',0),(156,'Gaelic','gd',0),(158,'Galician','gl',0),
(159,'Ganda','lg',0),(163,'Georgian','ka',0),(164,'German','de',0),(169,'Gikuyu','ki',0),(176,'Greek, Modern (1453-)','el',0),(177,'Greenlandic','kl',0),(178,'Guarani','gn',0),
(179,'Gujarati','gu',0),(182,'Haitian','ht',0),(183,'Haitian Creole','ht',0),(184,'Hausa','ha',0),(186,'Hebrew','he',0),(187,'Herero','hz',0),(190,'Hindi','hi',0),(191,'Hiri Motu','ho',0),
(194,'Hungarian','hu',0),(197,'Icelandic','is',0),(198,'Ido','io',0),(199,'Igbo','ig',0),(206,'Indonesian','id',0),(208,'Interlingua (International Auxiliary Language Association)','ia',0),
(209,'Interlingue','ie',0),(210,'Inuktitut','iu',0),(211,'Inupiaq','ik',0),(213,'Irish','ga',0),(217,'Italian','it',0),(218,'Japanese','ja',0),(219,'Javanese','jv',0),(226,'Kalaallisut','kl',0),
(229,'Kannada','kn',0),(230,'Kanuri','kr',0),(236,'Kashmiri','ks',0),(239,'Kazakh','kk',0),(243,'Kikuyu','ki',0),(245,'Kinyarwanda','rw',0),(247,'Kirghiz','ky',0),(250,'Komi','kv',0),
(251,'Kongo','kg',0),(253,'Korean','ko',0),(257,'Kuanyama','kj',0),(259,'Kurdish','ku',0),(262,'Kwanyama','kj',0),(263,'Kyrgyz','ky',0),(268,'Lao','lo',0),(269,'Latin','la',0),
(270,'Latvian','lv',0),(272,'Letzeburgesch','lb',0),(274,'Limburgan','li',0),(275,'Limburger','li',0),(276,'Limburgish','li',0),(277,'Lingala','ln',0),(278,'Lithuanian','lt',0),
(284,'Luba-Katanga','lu',0),(291,'Luxembourgish','lb',0),(293,'Macedonian','mk',0),(298,'Malagasy','mg',0),(299,'Malay','ms',0),(300,'Malayalam','ml',0),(301,'Maldivian','dv',0),
(302,'Maltese','mt',0),(308,'Manx','gv',0),(309,'Maori','mi',0),(312,'Marathi','mr',0),(314,'Marshallese','mh',0),(325,'Moldavian','ro',0),(326,'Moldovan','ro',0),(330,'Mongolian','mn',0),
(336,'Nauru','na',0),(337,'Navaho','nv',0),(338,'Navajo','nv',0),(339,'Ndebele, North','nd',0),(340,'Ndebele, South','nr',0),(341,'Ndonga','ng',0),(344,'Nepali','ne',0),
(354,'North Ndebele','nd',0),(356,'Northern Sami','se',0),(358,'Norwegian','no',0),(359,'Norwegian BokmÃ¥l','nb',0),(360,'Norwegian Nynorsk','nn',0),(363,'Nuosu','ii',0),
(365,'Nyanja','ny',0),(367,'Nynorsk, Norwegian','nn',0),(370,'Occidental','ie',0),(371,'Occitan (post 1500)','oc',0),(375,'Ojibwa','oj',0),(376,'Old Bulgarian','cu',0),
(377,'Old Church Slavonic','cu',0),(379,'Old Slavonic','cu',0),(380,'Oriya','or',0),(381,'Oromo','om',0),(383,'Ossetian','os',0),(384,'Ossetic','os',0),(388,'Pali','pi',0),
(391,'Panjabi','pa',0),(394,'Pashto','ps',0),(396,'Persian','fa',0),(402,'Polish','pl',0),(403,'Portuguese','pt',0),(406,'Punjabi','pa',0),(407,'Pushto','ps',0),
(408,'Quechua','qu',0),(414,'Romanian','ro',0),(415,'Romansh','rm',0),(417,'Rundi','rn',0),(418,'Russian','ru',0),(423,'Samoan','sm',0),(425,'Sango','sg',0),(426,'Sanskrit','sa',0),
(428,'Sardinian','sc',0),(432,'Scottish Gaelic','gd',0),(436,'Serbian','sr',0),(439,'Shona','sn',0),(440,'Sichuan Yi','ii',0),(445,'Sindhi','sd',0),(446,'Sinhala','si',0),
(447,'Sinhalese','si',0),(453,'Slovak','sk',0),(454,'Slovenian','sl',0),(456,'Somali','so',0),(461,'Sotho, Southern','st',0),(463,'South Ndebele','nr',0),(466,'Spanish','es',1),(471,'Sundanese','su',0),(473,'Swahili','sw',0),(474,'Swati','ss',0),(475,'Swedish','sv',0),(478,'Tagalog','tl',0),(479,'Tahitian','ty',0),(481,'Tajik','tg',0),(483,'Tamil','ta',0),(484,'Tatar','tt',0),(485,'Telugu','te',0),(488,'Thai','th',0),(489,'Tibetan','bo',0),(491,'Tigrinya','ti',0),(499,'Tonga (Tonga Islands)','to',0),(501,'Tsonga','ts',0),(502,'Tswana','tn',0),(505,'Turkish','tr',0),(507,'Turkmen','tk',0),(510,'Twi','tw',0),(513,'Uighur','ug',0),(514,'Ukrainian','uk',0),(519,'Urdu','ur',0),(520,'Uyghur','ug',0),(521,'Uzbek','uz',0),(523,'Valencian','ca',0),(524,'Venda','ve',0),(525,'Vietnamese','vi',0),(526,'VolapÃ¼k','vo',0),(529,'Walloon','wa',0),(532,'Welsh','cy',0),(533,'Western Frisian','fy',0),(537,'Wolof','wo',0),(538,'Xhosa','xh',0),(542,'Yiddish','yi',0),(543,'Yoruba','yo',0),(550,'Zhuang','za',0),(551,'Zulu','zu',0);
-- ;

