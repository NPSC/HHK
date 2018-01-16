REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES
('Address_Purpose','1','Home','i','',10),
('Address_Purpose','2','Work','i','',20),
('Address_Purpose','3','Alt','i','',30),
('Address_Purpose','4','Office','o','',40),
('Address_Purpose','b','Billing','o','',50),

('Addnl_Charge', 'ac1', 'Lost Key', '25', 'ca',0),
('Addnl_Charge', 'ac2', 'Room Damage', '100', 'ca',0),

('Age_Bracket','2','Infant','','d',0),
('Age_Bracket','4','Minor','','d',0),
('Age_Bracket','6','Adult','','d',0),
('Age_Bracket','8','Senior','','d',0),
('Age_Bracket','z','Unknown','','d',1000),

('anomalyTypes','ba','Bad City','`City`=\'\'','',0),
('anomalyTypes','bs','Bad State','`State`=\'\'','',0),
('anomalyTypes','sa','Bad Street Address','`Street Address`=\'\'','',0),
('anomalyTypes','z','Bad Zip Code','Zip=\'\' or Zip=\'0\' or LENGTH(Zip)<5','',0),

('Attribute_Type', '1', 'Room', '','',0),
('Attribute_Type', '2', 'Hospital', '','',0),

('Cal_Event_Status','a','Active','','',0),
('Cal_Event_Status','d','Deleted','','',0),
('Cal_Event_Status','t','Logged','','',0),
('Cal_Hide_Add_Members','Vol_Activities1','n','','',0),
('Cal_House','Vol_Activitieshou','House Calendar','','',0),
('Cal_Select','Vol_Activities1','n','','',0),
('Cal_Show_Delete_Email','Vol_Activities1','y','','',0),

('Campaign_Status','a','Active','','',0),
('Campaign_Status','c','Closed','','',0),
('Campaign_Status','d','Disabled','','',0),

('Campaign_Type','as','Normal','','',0),
('Campaign_Type','pct','Percent Cut Out','','',0),
('Campaign_Type','ink','In Kind','','',0),
('Campaign_Type', 'sch', 'Scholarship', '','',0),

('Constraint_Type', 'hos', 'Hospital', '','',0),
('Constraint_Type', 'rv', 'Reservation','','',0),
('Constraint_Type', 'v', 'Visit', '','',0),

('Category_Types', '1', 'Items', '','',0),
('Category_Types', '2', 'Tax', '','',0),
('Category_Types', '3', 'Penalty', '','',0),

('Charge_Cards', '1', 'Visa', 'VISA','',0),
('Charge_Cards', '2', 'M/C', 'M/C','',0),
('Charge_Cards', '3', 'Discover','DCVR', '',0),
('Charge_Cards', '4', 'Am Ex', 'AMEX','',0),

('Demographics', 'Age_Bracket', 'Age Bracket', 'y','m',5),
('Demographics', 'Ethnicity', 'Ethnicity', 'y','m',10),
('Demographics', 'Gender', 'Gender', 'y','m',15),
('Demographics', 'Income_Bracket', 'Income Bracket', 'y','m',25),
('Demographics', 'Education_Level', 'Education Level', 'y','m',20),
('Demographics', 'Special_Needs', 'Special Needs', '','m',35),
('Demographics', 'Media_Source', 'Media Source', '','m',30),

('Diagnosis','0','Other','','h',0),
('Diagnosis','0','Breast Cancer','','h',0),
('Diagnosis','0','Prostate Cancer','','h',0),
('Diagnosis','0','Lung Cancer','','h',0),
('Diagnosis','0','Glioma','','h',0),

('Dir_Type_Selector_Code','d','Directory','','',0),
('Dir_Type_Selector_Code','e','Email Addresses','','',0),
('Dir_Type_Selector_Code','m','Mailing List','','',0),

('Distance_Range','50','Up to 50 miles','2','',10),
('Distance_Range','100','51 to 100 miles','3','',20),
('Distance_Range','150','101 to 150 miles','4','',30),
('Distance_Range','200','151 to 200 miles','5','',40),
('Distance_Range','30000','More Than 200 miles','7','',50),

('Dormant_Selector_Code','act','Active Only','','',0),
('Dormant_Selector_Code','both','Dormant & Active','','',0),
('Dormant_Selector_Code','dor','Dormant Only','','',0),

('Editable_Forms', '../conf/agreement.html', 'Registration Agreement','js/rte-agreement.json','',0),
('Editable_Forms', '../conf/confirmation.html', 'Confirmation Form','js/rte-confirmation.json','',0),
('Editable_Forms', '../conf/survey.html', 'Survey Form','js/rte-survey.json','',0),

('Education_Level','01','Highschool','','d',0),
('Education_Level','02','College','','d',0),
('Education_Level','z','Unknown','','d',1000),

('Email_Purpose','1','Home','i','',10),
('Email_Purpose','2','Work','i','',20),
('Email_Purpose','3','Alt','i','',30),
('Email_Purpose','4','Office','o','',40),

('Ethnicity','c','Caucasian','','d',0),
('Ethnicity','f','African-American','','d',0),
('Ethnicity','h','Hispanic','','d',0),
('Ethnicity','k','Asia-Pacific','','d',0),
('Ethnicity','x','Other','','d',0),
('Ethnicity','z','Unknown','','d',1000),

('E_Shell_Status','a','Active','','',0),
('E_Shell_Status','d','Disabled','','',0),

('ExcessPays', 'd', 'Room Fund','','u',0),
('ExcessPays', 'e', 'Hold','','u',0),
('ExcessPays', 'i', 'Ignore','','u',0),
('ExcessPays', 'r', 'Refund','','u',0),

('FB_Status','a','Active','','',0),
('FB_Status','d','Disabled','','',0),
('FB_Status','w','Waiting','','',0),
('FB_Status','x','Prohibited','','',0),

('Fund', '1', 'External Donation', '', 'u', '100'),

('Gender','f','Female','','d',0),
('Gender','m','Male','','d',0),
('Gender','t','Other','','d',0),
('Gender','z','Unknown','','d',1000),

('Guest_Survey', 'Survey_Date','', '','',0),

('Holiday', '0', 'New Years Day','','',0),
('Holiday', '1', 'Martin Luther King Birthday','','',0),
('Holiday', '2', 'Washington Birthday','','',0),
('Holiday', '3', 'Memorial Day','','',0),
('Holiday', '4', 'Independance Day','','',0),
('Holiday', '5', 'Labor Day','','',0),
('Holiday', '6', 'Columbus Day','','',0),
('Holiday', '7', 'Vetereans Day','','',0),
('Holiday', '8', 'Thanksgiving Day','','',0),
('Holiday', '9', 'Christmas Day','','',0),
('Holiday', '10', 'Designated 1', '','',0),
('Holiday', '11', 'Designated 2', '','',0),
('Holiday', '12', 'Designated 3', '','',0),
('Holiday', '13', 'Designated 4', '','',0),

('Hospital_Type', 'h', 'Hospital','','',0),
('Hospital_Type', 'a', 'Association','','',0),

('HourReportType','d','Open & Logged','','',0),
('HourReportType','l','Only Logged Hours','','',0),
('HourReportType','ul','Only Open Hours','','',0),

('House_Discount', 'hd1', 'Service Issue','10', 'ca',0),
('House_Discount', 'hd2', 'Facilities Issue','15', 'ca',0),

('Income_Bracket', 'ib1', '0 - 25,000', '', 'd',10),
('Income_Bracket', 'ib2', '26 - 50,000', '', 'd',20),
('Income_Bracket', 'ib3', '51 - 75,000', '', 'd',30),
('Income_Bracket', 'ib4', '76 - 100,000', '', 'd',40),
('Income_Bracket', 'ib5', '100 - 150,000', '', 'd',50),
('Income_Bracket', 'ib6', '200,000 & up', '', 'd',60),
('Income_Bracket', 'z', 'Unknown', '', 'd',1000),

('Invoice_Status', 'p', 'Paid', '','',0),
('Invoice_Status', 'up', 'Unpaid', '','',0),
('Invoice_Status', 'c', 'Carried', '','',0),

('Key_Deposit_Code','k0','None','0','',0),
('Key_Deposit_Code','k1','House','20','',0),

('Key_Disposition', '1', 'Retained', '', 'u',0),
('Key_Disposition', '2', 'Refunded', '', 'u',0),
('Key_Disposition', '3', 'Donated', '', 'u',0),
('Key_Disposition', '4', 'Paid Room Fees', '', 'u',0),

('Language_Proficiency', '5', 'Native','', 'h',0),
('Language_Proficiency', '3', 'Professional','', 'h',0),
('Language_Proficiency', '2', 'Limited','', 'h',0),
('Language_Proficiency', 'l', 'Elementary','', 'h',0),

('Location', 'lo1', 'Cardiac','', 'h',0),
('Location', 'lo2', 'Peds','', 'h',0),

('Media_Source', 'na', 'News Article','','d',0),
('Media_Source', 'hs', 'Hospital Staff','','d',0),
('Media_Source', 'fr', 'Friend','','d',0),
('Media_Source', 'hhn', 'HHN','','d',0),
('Media_Source', 'ws', 'Web Search','','d',0),
('Media_Source', 'z', 'Unknown','','d',1000),

('Member_Basis','ai','Individual','i','',10),
('Member_Basis','c','Company','o','',20),
('Member_Basis','np','Non Profit','o','',30),
('Member_Basis','og','Government','o','',40);
-- ;

REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES
('mem_status','a','Active','m','',10),
('mem_status','d','Deceased','m','',20),
('mem_status','in','Inactive','m','',30),
('mem_status','p','Pending','','',40),
('mem_status','TBD','To be deleted','','',50),
('mem_status','u','Duplicate','','',60),

('Name_Prefix','dr','Dr.','','',0),
('Name_Prefix','mi','Miss.','','',0),
('Name_Prefix','mr','Mr.','','',0),
('Name_Prefix','mrs','Mrs.','','',0),
('Name_Prefix','ms','Ms.','','',0),
('Name_Prefix','rev','Rev.','','',0),
('Name_Prefix','The','The','','',0),

('Name_Suffix','D.D.S.','D.D.S.','','',0),
('Name_Suffix','esq','Esq.','','',0),
('Name_Suffix','ii','II','','',0),
('Name_Suffix','iii','III','','',0),
('Name_Suffix','jd','Jd.','','',0),
('Name_Suffix','jr','Jr.','','',0),
('Name_Suffix','md','MD.','','',0),
('Name_Suffix','phd','Ph.D.','','',0),


('Newsletter', 'yes', 'Yes','', 'd', '100'),
('Newsletter', 'no', 'No','', 'd', '110'),
('Newsletter', 'z', 'Unknown', '', 'd', '1000'),

('NoReturnReason', 'n1', 'Irresponsible', '', 'h',0),

('OOS_Codes', 'sb', 'Sewer Backup','','h',0),
('OOS_Codes', 'ar', 'Appliance Repair','','h',0),
('OOS_Codes', 'sr', 'Structural Repair','','h',0),
('OOS_Codes', 'cd', 'Access Blocked','','h',0),

('Order_Status','a','Active','','',0),
('Order_Status','f','Finished','','',0),
('Order_Status','s','Suspended','','',0),
('Order_Status','sa','Suspended-Ageing','','',0),

('Page_Type','c','Component','','',0),
('Page_Type','p','Web Page','','',0),
('Page_Type','s','Web Service','','',0),

('Patient_Rel_Type','chd','Child','Guests','h',20),
('Patient_Rel_Type','frd','Friend','Guests','h',60),
('Patient_Rel_Type','par','Parent','Guests','h',30),
('Patient_Rel_Type','rltv','Relative','Guests','h',50),
('Patient_Rel_Type','sib','Sibling','Guests','h',40),
('Patient_Rel_Type','sp','Partner','Guests','h',15),
('Patient_Rel_Type','pc','Paid Caregiver','Guests','h',70),
('Patient_Rel_Type','slf','Patient','','h',10),

('Pay_Status', 'c', 'Cleared', '','',0),
('Pay_Status', 'p', 'Pending', '','',0),
('Pay_Status', 'd', 'Denied', '','',0),
('Pay_Status', 'er', 'Error', '','',0),
('Pay_Status', 'v', 'Void', '','',0),
('Pay_Status', 'r', 'Returned', '','',0),

('Payment_Status', 's', 'Paid', '','',0),
('Payment_Status', 'v', 'Void', '','',0),
('Payment_Status', 'r', 'Return', '','',0),
('Payment_Status', 'vr', 'Void Return', '','',0),
('Payment_Status', 'd', 'Declined', '','',0),

('Pay_Type','ca','Cash','1','',0),
('Pay_Type','cc','Credit Card','4','',0),
('Pay_Type','ck','Check','3','',0),
('Pay_Type','in','Invoice','','',0),
('Pay_Type','tf','Transfer','5','',0),

('Period_Unit', '1', 'Day','','',0),
('Period_Unit', '2', 'Week','','',0),
('Period_Unit', '3', 'Month','','',0),
('Period_Unit', '4', 'Year','','',0),

('Phone_Type','dh','Home','i','',10),
('Phone_Type','gw','Work','i','',20),
('Phone_Type','hw','Office','o','',30),
('Phone_Type','mc','Cell','i','',40),
('Phone_Type','xf','Fax','','',50),

('Photo_Permission', 'yes', 'Yes', '', 'd', '100'),
('Photo_Permission', 'no', 'No', '', 'd', '110'),
('Photo_Permission', 'z', 'Unknown', '', 'd', '1000'),

('Price_Model','b','Basic','','',0),
('Price_Model','d','Daily','','',0),
('Price_Model','g', 'Guest Day','','',0),
('Price_Model','ns','3 Step','','',0),
('Price_Model','p','Perpetual Step','','',0),
('Price_Model','bl','n-Day Block','','',0),
('Price_Model','xx','None','','',0),

('Rate_Block', '7', 'Wk', '1','',0),
('Rate_Block', '14', '2 Weeks', '','',0),

('Rate_Period', '1', 'Reduced Rate 1', '7','',0),
('Rate_Period', '2', 'Reduced Rate 2', '14','',0),
('Rate_Period', '3', 'Reduced Rate 3', '19999','',0),

('rel_type','chd','Child','par','',0),
('rel_type','par','Parent','chd','',0),
('rel_type','rltv','Relative','','',0),
('rel_type','sib','Sibling','sib','',0),
('rel_type','sp','Partner','sp','',0),
('rel_type','frd','Friend','sp','',0),

('Resource_Status','a','Available','','',0),
('Resource_Status','oos','Out of Service','','',0),
('Resource_Status','un','Unavailable','','',0),
('Resource_Status','dld','Delayed','','',0),

('Resource_Type','block','Block','','',0),
('Resource_Type','part','Partition','','',0),
('Resource_Type','rmtroom','Remote Room','','',0),
('Resource_Type','room','Room','','',0),

('Role_Codes','10','Admin User','','',0),
('Role_Codes','100','Web User','','',0),
('Role_Codes','700','Guest','','',0),

('Room_Category','dh','House','','',0),
('Room_Category','gada','Hospital','','',0),
('Room_Category','jph','Private Host','','',0),
('Room_Category','nm','Motel','','',0),

('Room_Cleaning_Days', 'a', '7 Days', '7', 'ha',10),
('Room_Cleaning_Days', 'b', '14 Days', '14', 'ha',20),
('Room_Cleaning_Days', 'nz', 'Disabled', '0', 'ha',1000),

('Room_Status', 'a', 'Clean', '','',0),
('Room_Status', 'dty', 'Dirty', '','',0),
('Room_Status', 'to', 'Turn Over', '','',0),

('Room_Type','r','Room','','',10),
('Room_Type','s','Suite','','',20),

('Salutation','fln','First &Last','','',0),
('Salutation','fno','First Name','','',0),
('Salutation','for','Formal','','',0),
('Salutation','mm','Retro-Mr. & Mrs.','','',0),

('Special_Needs','c','Cancer','','d',0),
('Special_Needs','f','Dev. Challenged','','d',0),
('Special_Needs','z','Unknown','','d',1000),

('Static_Room_Rate','rb','Regular Rate','10','',0),

('Utilization_Category', 'uc1', 'Standard', '', 'h',0),

('validMemStatus','a','Active','','',0),
('validMemStatus','d','Deceased','','',0),
('validMemStatus','in','Inactive','','',0),

('Verify_User_Address','done','Verified','','',0),
('Verify_User_Address','y','Waiting for verification','','',0),

('Visit_Fee_Code', '1', 'Cleaning Fee','15','',0),
('Visit_Fee_Code', '2', '','0','',0),

('Visit_Status','a','Checked In','','',0),
('Visit_Status','co','Checked Out','','',0),
('Visit_Status','cp','Room Rate Changed','','',0),
('Visit_Status','n','Room Changed','','',0),
('Visit_Status', '1', 'On Leave','','',0),

('Vol_Activities','1','Greeter','green,white','',0),
('Vol_Activities','5','Fundraising','black,white','',0),
('Vol_Activities','6','Special Event Planning/Organizing','','',0),
('Vol_Activities','8','Lawn Care','','',0),
('Vol_Activities','9','Gardening','','',0),
('Vol_Activities','ccom','Cookie Committee','yellow,darkgreen','',0),

('Vol_Category','Vol_Activities','Volunteer Activities','Vol_Type.Vol','',0),
('Vol_Category','Vol_Skills','Volunteer Skills','Vol_Type.Vol','',0),
('Vol_Category','Vol_Type','Member Type','','',0),

('Vol_Rank','c','Chair','','',0),
('Vol_Rank','cc','Co-Chair','','',0),
('Vol_Rank','m','Member','','',0),

('Vol_Skills','D','Solicitation or Fundraising','green,white','',0),
('Vol_Skills','E','Cooking/Catering','','',0),
('Vol_Skills','G','Handyperson','','',0),
('Vol_Skills','H','Painting','','',0),
('Vol_Skills','I','Electrical','','',0),
('Vol_Skills','J','Plumbing','','',0),
('Vol_Skills','K','Roofing','','',0),
('Vol_Skills','L','Carpentry','orange,darkblue','',0),

('Vol_Status','a','Active','','',0),
('Vol_Status','i','Retired','','',0),

('Vol_Type','d','Donor','','',0),
('Vol_Type','g','Guest','','',0),
('Vol_Type','p','Patient','yellow,black','',0),
('Vol_Type','Vol','Volunteer','','',0),
('Vol_Type', 'doc', 'Doctor','','',0),
('Vol_Type', 'ra', 'Agent','','',0),
('Vol_Type', 'ba', 'Billing Agent', '','',0),

('Web_User_Status','a','active','','',0),
('Web_User_Status','d','Disabled','','',0),
('Web_User_Status','w','Waiting','','',0),
('Web_User_Status','x','Prohibited','','',0),

('WL_Final_Status','hf','House Full','','',0),
('WL_Final_Status','lc','Lost Contact','','',0),
('WL_Final_Status','se','Elsewhere','','',0),
('WL_Status','a','Active','','',0),
('WL_Status','in','Inactive','','',0),
('WL_Status','st','Stayed','','',0);
-- ;


REPLACE INTO `lookups` (`Category`,`Code`,`Title`,`Use`,`Show`,`Type`,`Other`,`Timestamp`) VALUES 
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
('ReservStatus','h','To Hotel','y','y','','ui-icon-suitcase','2013-11-14 17:57:58'),
('ReservStatus', 'im', 'Immediate', 'y', 'y', '', 'ui-icon-check', NULL),
('ReservStatus','w','Waitlist','y','y','','ui-icon-arrowstop-1-e','2013-11-14 17:57:58');
-- ;


--
-- insert System configuration
--
REPLACE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES 
('CalViewWeeks','3','i','h','Number of weeks showing in the calendar view'),
('CardSwipe','false','b','f','Use POS terminal'),
('ConfirmFile','confirmation.html','s','h','Reservation Confirmation file template name'),
('ConcatVisitNotes', 'true', 'b', 'h', 'Show notes combined from all previous visits when true.'),
('county', 'false', 'b', 'h', 'Include the County in addresses.'),
('CoTod', 'false', 'b', 'h', 'Edit the time of day of a checkout.'),
('DefaultPayType','ca','s','f','Default payment type for paying today UI'),
('DefaultVisitFee', '1', 's', 'h', 'Default Visit Fee selected on new check-in and reservation pages.'),
('DefaultCkBalStmt', 'false', 'b', 'h', 'Check the Balance Statement checkbox by default'),
('DefaultDays', '21', 'i', 'h', 'The Default number of following days for date range control'),
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
('InsuranceChooser', 'false', 'b', 'h', 'Show patient insurance chooser'),
('KeyDeposit','false','b','h','Use Room or Key deposit'),
('LangChooser', 'false', 'b', 'h', 'Show member language chooser'),
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
('RateChangeAuth', 'false', 'b', 'h', 'true = Only authorized users can change the defailt room rate'),
('RateGlideExtend', '0', 'i', 'h','# of days for the Room Rate Glide to time out after visit check-out'),
('ReferralAgent', 'true', 'b', 'h','Track referral agents/social workers'),
('RegColors','hospital','s','h','Calendar page ribbon colors based on hospital or room'),
('RegForm','1','i','h','1 = Registration form style 1, 2 = style 2'),
('RegFormNoRm', 'false', 'b', 'h','Do not show the room number on the registration form before check-in'),
('ResvEarlyArrDays', '2', 'i', 'h','# Days before reservation to show check-in button on reservation chooser'),
('Reservation','true','b','h','Use reservations'),
('RoomPriceModel', 'd', 's', 'h','Room rate price model - Do not change!'),
('RoomsPerPatient', '2', 'i', 'h','# simultaneous rooms per patient allowed'),
('RoomRateDefault', 'e', 's', 'h', 'Default room rate category (a, b, c, d, e, x)'),
('SessionTimeout', '30', 'i', 'f', 'Number of minutes until an idle session get automatically logged out, 0 = never log out'),
('ShowCreatedDate', 'true', 'b', 'h', 'Show the Created Date in Register page tabs lists'),
('ShowDemographics', 'false', 'b', 'h', 'Show demographics selectors on Check in and Reservation pages'),
('ShowDiagTB', 'false', 'b', 'h', 'Show the diagnosis textbox (in addition to the diagnosis selector)'),
('ShoStaysCtr', 'true', 'b', 'h', 'Show the stays counter on the House Calendar page'),
('ShowLodgDates', 'true', 'b', 'h','Show dates on lodging invoice lines'),
('ShowTxPayType', 'false', 'b', 'h', 'Always Show the Transfer pay type'),
('SolicitBuffer','90','i','r','Timeout in days after visit checkout before solicit report will show new guests'),
('ShowUncfrmdStatusTab', 'true', 'b', 'h', 'Show the Unconfirmed reservations tab on the House Register page'),
('ShowZeroDayStays', 'false', 'b', 'h', 'Include 0-day stays and visits in Reports and Pages'),
('TrackAuto','true','b','h','Track vehicles'),
('UseWLnotes', 'false', 'b', 'h', 'Use wait list notes feature on reservations'),
('VisitFee', 'false', 'b', 'h','Use the visit fee (cleaning fee) feature'),
('VisitExcessPaid', 'd', 's', 'h','Default place for excess visit payments'),
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



replace into `item_type` (`idItem_Type`,`Category_Type`,`Type_Description`,`Internal`,`Order_Line_Type_Id`) values 
(1, 1, 'Items', 0, 0),
(2, 1, 'Tax', 0, 0),
(3, 1, 'Refundable', 0,0),
(4, 2, 'Duration', 0, 0),
(5, '1', 'Refund', 0, '0'),
(6, '1', 'Discount', 0, '0');
-- ;



REPLACE INTO `item_type_map` (`Item_Id`,`Type_Id`) values
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



REPLACE INTO `payment_method` (`idPayment_method`, `Method_Name`) VALUES 
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
-- insert users
--
REPLACE into name (idName, Name_Last, Name_First, Member_Type, Member_Status, Record_Member) values 
(-1, 'admin', '', 'ai', 'a', 1),
(10, 'Crane', 'Eric', 'ai', 'a', 1);
-- ;

REPLACE INTO `w_auth` (`idName`,`Role_Id`,`Organization_Id`,`Policy_id`,`Updated_By`,`Last_Updated`,`User_Name`,`Status`) 
    VALUES (-1,'10','p',0,'admin',now(),'admin','a');
-- ;



REPLACE INTO `w_users` 
(`idName`,`User_Name`,`Enc_PW`,`Status`,`Certificate`,`Cookie`,`Session`,`Ip`,`Verify_Address`,`Last_Login`,`Hash_PW`,`Updated_By`,`Last_Updated`,`Timestamp`)
VALUES 
(-1,'admin','539e17171312c324d3c23908f85f3149','a','','','','','done',NULL,'','',NULL,now());
-- ;


--
-- Table `w_groups`
--
REPLACE INTO `w_groups` 
(`Group_Code`,`Title`,`Description`,`Default_Access_Level`,`Max_Level`,`Min_Access_Level`,`Cookie_Restricted`,`Password_Policy`,`Last_Updated`,`Updated_By`,`Timestamp`) 
VALUES
('db','Maintenance','Configure metadata.','','','','\0','','2013-08-07 16:19:17','admin','2013-07-28 16:34:25'),
('dm','Donation Management','Donation Management','','','','\0','','2013-08-07 16:11:22','admin','2013-07-28 16:34:25'),
('dna','Donors (No Amounts)','View lists of donors but without donation amounts','','','','\0','','2013-08-07 16:16:10','admin','2013-07-28 16:34:25'),
('g','Guest Operations','Guest Operations, basic access to guest tracking site','','','','','','2013-08-07 16:19:17','admin','2013-07-28 16:34:25'),
('ga','Guest Admin','Guest Administration level access to guest tracking site','','','','\0','','2013-08-07 16:19:17','admin','2013-07-28 16:34:25'),
('mm','Member Management','Member Management, basic access to admin site.','','','','\0','','2013-08-07 16:19:40','admin','2013-07-28 16:34:25'),
('pub','Public','Public','','','','\0','','2013-08-07 16:11:22','admin','2013-07-28 16:34:25'),
('v','Volunteer','Volunteer site.','','','','\0','','2013-08-07 16:19:17','admin','2013-07-28 16:34:25');
-- ;

REPLACE INTO `neon_lists` (`Method`, `List_Name`, `List_Item`, `HHK_Lookup`) VALUES 
('account/listIndividualTypes', 'individualTypes', 'individualType', 'Vol_Type'),
('donation/listFunds', 'funds', 'fund', 'Fund'),
('common/listTenders', 'tenders', 'tender', 'Pay_Type'),
('common/listCreditCardTypes', 'creditCardTypes', 'creditCardType', 'Charge_Cards');
-- ;

--
-- Dumping data for table `counter`
--
REPLACE INTO `counter` (`seqn`,`Table_Name`,`Next`,`Last_Updated`) VALUES
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



REPLACE into `transaction_type` (`idtransaction_type`,`Title`,`Effect`,`Code`) values
(1, 'Sale', '', 's'),
(2, 'Void', '', 'vs'),
(3, 'Return', '', 'r'),
(4, 'Void Return', '', 'vr');
-- ;


--
-- Dumping data for table `street_suffix`
--
REPLACE INTO `street_suffix` (`Common`,`Standard`,`TitleCaps`) VALUES 
('ALLEE','ALY','Aly'),('ALLEY','ALY','Aly'),('ALLY','ALY','Aly'),('ALY','ALY','Aly'),('ANEX','ANX','Anx'),('ANNEX','ANX','Anx'),('ANNX','ANX','Anx'),('ANX','ANX','Anx'),('ARC','ARC','Arc'),('ARCADE','ARC','Arc'),('AV','AVE','Ave'),('AVE','AVE','Ave'),('AVEN','AVE','Ave'),('AVENU','AVE','Ave'),('AVENUE','AVE','Ave'),('AVN','AVE','Ave'),('AVNUE','AVE','Ave'),('BAYOO','BYU','Byu'),('BAYOU','BYU','Byu'),('BCH','BCH','Bch'),('BEACH','BCH','Bch'),('BEND','BND','Bnd'),('BND','BND','Bnd'),('BLF','BLF','Blf'),('BLUF','BLF','Blf'),('BLUFF','BLF','Blf'),('BLUFFS','BLFS','Blfs'),('BOT','BTM','Btm'),('BOTTM','BTM','Btm'),('BOTTOM','BTM','Btm'),('BTM','BTM','Btm'),('BLVD','BLVD','Blvd'),('BOUL','BLVD','Blvd'),('BOULEVARD','BLVD','Blvd'),('BOULV','BLVD','Blvd'),('BR','BR','Br'),('BRANCH','BR','Br'),('BRNCH','BR','Br'),('BRDGE','BRG','Brg'),('BRG','BRG','Brg'),('BRIDGE','BRG','Brg'),('BRK','BRK','Brk'),('BROOK','BRK','Brk'),('BROOKS','BRKS','Brks'),('BURG','BG','Bg'),('BURGS','BGS','Bgs'),('BYP','BYP','Byp'),('BYPA','BYP','Byp'),('BYPAS','BYP','Byp'),('BYPASS','BYP','Byp'),('BYPS','BYP','Byp'),('CAMP','CP','Cp'),('CMP','CP','Cp'),('CP','CP','Cp'),('CANYN','CYN','Cyn'),('CANYON','CYN','Cyn'),('CNYN','CYN','Cyn'),('CYN','CYN','Cyn'),('CAPE','CPE','Cpe'),('CPE','CPE','Cpe'),('CAUSEWAY','CSWY','Cswy'),('CAUSWAY','CSWY','Cswy'),('CSWY','CSWY','Cswy'),('CEN','CTR','Ctr'),('CENT','CTR','Ctr'),('CENTER','CTR','Ctr'),('CENTR','CTR','Ctr'),('CENTRE','CTR','Ctr'),('CNTER','CTR','Ctr'),('CNTR','CTR','Ctr'),('CTR','CTR','Ctr'),('CENTERS','CTRS','Ctrs'),('CIR','CIR','Cir'),('CIRC','CIR','Cir'),('CIRCL','CIR','Cir'),('CIRCLE','CIR','Cir'),('CRCL','CIR','Cir'),('CRCLE','CIR','Cir'),('CIRCLES','CIRS','Cirs'),('CLF','CLF','Clf'),('CLIFF','CLF','Clf'),('CLFS','CLFS','Clfs'),('CLIFFS','CLFS','Clfs'),('CLB','CLB','Clb'),('CLUB','CLB','Clb'),('COMMON','CMN','Cmn'),('COR','COR','Cor'),('CORNER','COR','Cor'),('CORNERS','CORS','Cors'),('CORS','CORS','Cors'),('COURSE','CRSE','Crse'),('CRSE','CRSE','Crse'),('COURT','CT','Ct'),('CRT','CT','Ct'),('CT','CT','Ct'),('COURTS','CTS','Cts'),('CTS','CTS','Cts'),('COVE','CV','Cv'),('CV','CV','Cv'),('COVES','CVS','Cvs'),('CK','CRK','Crk'),('CR','CRK','Crk'),('CREEK','CRK','Crk'),('CRK','CRK','Crk'),('CRECENT','CRES','Cres'),('CRES','CRES','Cres'),('CRESCENT','CRES','Cres'),('CRESENT','CRES','Cres'),('CRSCNT','CRES','Cres'),('CRSENT','CRES','Cres'),('CRSNT','CRES','Cres'),('CREST','CRST','Crst'),('CROSSING','XING','Xing'),('CRSSING','XING','Xing'),('CRSSNG','XING','Xing'),('XING','XING','Xing'),('CROSSROAD','XRD','Xrd'),('CURVE','CURV','Curv'),('DALE','DL','Dl'),('DL','DL','Dl'),('DAM','DM','Dm'),('DM','DM','Dm'),('DIV','DV','Dv'),('DIVIDE','DV','Dv'),('DV','DV','Dv'),('DVD','DV','Dv'),('DR','DR','Dr'),('DRIV','DR','Dr'),('DRIVE','DR','Dr'),('DRV','DR','Dr'),('DRIVES','DRS','Drs'),('EST','EST','Est'),('ESTATE','EST','Est'),('ESTATES','ESTS','Ests'),('ESTS','ESTS','Ests'),('EXP','EXPY','Expy'),('EXPR','EXPY','Expy'),('EXPRESS','EXPY','Expy'),('EXPRESSWAY','EXPY','Expy'),('EXPW','EXPY','Expy'),('EXPY','EXPY','Expy'),('EXT','EXT','Ext'),('EXTENSION','EXT','Ext'),('EXTN','EXT','Ext'),('EXTNSN','EXT','Ext'),('EXTENSIONS','EXTS','Exts'),('EXTS','EXTS','Exts'),('FALL','FALL','Fall'),('FALLS','FLS','Fls'),('FLS','FLS','Fls'),('FERRY','FRY','Fry'),('FRRY','FRY','Fry'),('FRY','FRY','Fry'),('FIELD','FLD','Fld'),('FLD','FLD','Fld'),('FIELDS','FLDS','Flds'),('FLDS','FLDS','Flds'),('FLAT','FLT','Flt'),('FLT','FLT','Flt'),('FLATS','FLTS','Flts'),('FLTS','FLTS','Flts'),('FORD','FRD','Frd'),('FRD','FRD','Frd'),('FORDS','FRDS','Frds'),('FOREST','FRST','Frst'),('FORESTS','FRST','Frst'),('FRST','FRST','Frst'),('FORG','FRG','Frg'),('FORGE','FRG','Frg'),('FRG','FRG','Frg'),('FORGES','FRGS','Frgs'),('FORK','FRK','Frk'),('FRK','FRK','Frk'),('FORKS','FRKS','Frks'),('FRKS','FRKS','Frks'),('FORT','FT','Ft'),('FRT','FT','Ft'),('FT','FT','Ft'),('FREEWAY','FWY','Fwy'),('FREEWY','FWY','Fwy'),('FRWAY','FWY','Fwy'),('FRWY','FWY','Fwy'),('FWY','FWY','Fwy'),('GARDEN','GDN','Gdn'),('GARDN','GDN','Gdn'),('GDN','GDN','Gdn'),('GRDEN','GDN','Gdn'),('GRDN','GDN','Gdn'),('GARDENS','GDNS','Gdns'),('GDNS','GDNS','Gdns'),('GRDNS','GDNS','Gdns'),('GATEWAY','GTWY','Gtwy'),('GATEWY','GTWY','Gtwy'),('GATWAY','GTWY','Gtwy'),('GTWAY','GTWY','Gtwy'),('GTWY','GTWY','Gtwy'),('GLEN','GLN','Gln'),('GLN','GLN','Gln'),('GLENS','GLNS','Glns'),('GREEN','GRN','Grn'),('GRN','GRN','Grn'),('GREENS','GRNS','Grns'),('GROV','GRV','Grv'),('GROVE','GRV','Grv'),('GRV','GRV','Grv'),('GROVES','GRVS','Grvs'),('HARB','HBR','Hbr'),('HARBOR','HBR','Hbr'),('HARBR','HBR','Hbr'),('HBR','HBR','Hbr'),('HRBOR','HBR','Hbr'),('HARBORS','HBRS','Hbrs'),('HAVEN','HVN','Hvn'),('HAVN','HVN','Hvn'),('HVN','HVN','Hvn'),('HEIGHT','HTS','Hts'),('HEIGHTS','HTS','Hts'),('HGTS','HTS','Hts'),('HT','HTS','Hts'),('HTS','HTS','Hts'),('HIGHWAY','HWY','Hwy'),('HIGHWY','HWY','Hwy'),('HIWAY','HWY','Hwy'),('HIWY','HWY','Hwy'),('HWAY','HWY','Hwy'),('HWY','HWY','Hwy'),('HILL','HL','Hl'),('HL','HL','Hl'),('HILLS','HLS','Hls'),('HLS','HLS','Hls'),('HLLW','HOLW','Holw'),('HOLLOW','HOLW','Holw'),('HOLLOWS','HOLW','Holw'),('HOLW','HOLW','Holw'),('HOLWS','HOLW','Holw'),('INLET','INLT','Inlt'),('INLT','INLT','Inlt'),('IS','IS','Is'),('ISLAND','IS','Is'),('ISLND','IS','Is'),('ISLANDS','ISS','Iss'),('ISLNDS','ISS','Iss'),('ISS','ISS','Iss'),('ISLE','ISLE','Isle'),('ISLES','ISLE','Isle'),('JCT','JCT','Jct'),('JCTION','JCT','Jct'),('JCTN','JCT','Jct'),('JUNCTION','JCT','Jct'),('JUNCTN','JCT','Jct'),('JUNCTON','JCT','Jct'),('JCTNS','JCTS','Jcts'),('JCTS','JCTS','Jcts'),('JUNCTIONS','JCTS','Jcts'),('KEY','KY','Ky'),('KY','KY','Ky'),('KEYS','KYS','Kys'),('KYS','KYS','Kys'),('KNL','KNL','Knl'),('KNOL','KNL','Knl'),('KNOLL','KNL','Knl'),('KNLS','KNLS','Knls'),('KNOLLS','KNLS','Knls'),('LAKE','LK','Lk'),('LK','LK','Lk'),('LAKES','LKS','Lks'),('LKS','LKS','Lks'),('LAND','LAND','Land'),('LANDING','LNDG','Lndg'),('LNDG','LNDG','Lndg'),('LNDNG','LNDG','Lndg'),('LA','LN','Ln'),('LANE','LN','Ln'),('LANES','LN','Ln'),('LN','LN','Ln'),('LGT','LGT','Lgt'),('LIGHT','LGT','Lgt'),('LIGHTS','LGTS','Lgts'),('LF','LF','Lf'),('LOAF','LF','Lf'),('LCK','LCK','Lck'),('LOCK','LCK','Lck'),('LCKS','LCKS','Lcks'),('LOCKS','LCKS','Lcks'),('LDG','LDG','Ldg'),('LDGE','LDG','Ldg'),('LODG','LDG','Ldg'),('LODGE','LDG','Ldg'),('LOOP','LOOP','Loop'),('LOOPS','LOOP','Loop'),('MALL','MALL','Mall'),('MANOR','MNR','Mnr'),('MNR','MNR','Mnr'),('MANORS','MNRS','Mnrs'),('MNRS','MNRS','Mnrs'),('MDW','MDW','Mdw'),('MEADOW','MDW','Mdw'),('MDWS','MDWS','Mdws'),('MEADOWS','MDWS','Mdws'),('MEDOWS','MDWS','Mdws'),('MEWS','MEWS','Mews'),('MILL','ML','Ml'),('ML','ML','Ml'),('MILLS','MLS','Mls'),('MLS','MLS','Mls'),('MISSION','MSN','Msn'),('MISSN','MSN','Msn'),('MSN','MSN','Msn'),('MSSN','MSN','Msn'),('MOTORWAY','MTWY','Mtwy'),('MNT','MT','Mt'),('MOUNT','MT','Mt'),('MT','MT','Mt'),('MNTAIN','MTN','Mtn'),('MNTN','MTN','Mtn'),('MOUNTAIN','MTN','Mtn'),('MOUNTIN','MTN','Mtn'),('MTIN','MTN','Mtn'),('MTN','MTN','Mtn'),('MNTNS','MTNS','Mtns'),('MOUNTAINS','MTNS','Mtns'),('NCK','NCK','Nck'),('NECK','NCK','Nck'),('ORCH','ORCH','Orch'),('ORCHARD','ORCH','Orch'),('ORCHRD','ORCH','Orch'),('OVAL','OVAL','Oval'),('OVL','OVAL','Oval'),('OVERPASS','OPAS','Opas'),('PARK','PARK','Park'),('PK','PARK','Park'),('PRK','PARK','Park'),('PARKS','PARK','Park'),('PARKWAY','PKWY','Pkwy'),('PARKWY','PKWY','Pkwy'),('PKWAY','PKWY','Pkwy'),('PKWY','PKWY','Pkwy'),('PKY','PKWY','Pkwy'),('PARKWAYS','PKWY','Pkwy'),('PKWYS','PKWY','Pkwy'),('PASS','PASS','Pass'),('PASSAGE','PSGE','Psge'),('PATH','PATH','Path'),('PATHS','PATH','Path'),('PIKE','PIKE','Pike'),('PIKES','PIKE','Pike'),('PINE','PNE','Pne'),('PINES','PNES','Pnes'),('PNES','PNES','Pnes'),('PL','PL','Pl'),('PLACE','PL','Pl'),('PLAIN','PLN','Pln'),('PLN','PLN','Pln'),('PLAINES','PLNS','Plns'),('PLAINS','PLNS','Plns'),('PLNS','PLNS','Plns'),('PLAZA','PLZ','Plz'),('PLZ','PLZ','Plz'),('PLZA','PLZ','Plz'),('POINT','PT','Pt'),('PT','PT','Pt'),('POINTS','PTS','Pts'),('PTS','PTS','Pts'),('PORT','PRT','Prt'),('PRT','PRT','Prt'),('PORTS','PRTS','Prts'),('PRTS','PRTS','Prts'),('PR','PR','Pr'),('PRAIRIE','PR','Pr'),('PRARIE','PR','Pr'),('PRR','PR','Pr'),('RAD','RADL','Radl'),('RADIAL','RADL','Radl'),('RADIEL','RADL','Radl'),('RADL','RADL','Radl'),('RAMP','RAMP','Ramp'),('RANCH','RNCH','Rnch'),('RANCHES','RNCH','Rnch'),('RNCH','RNCH','Rnch'),('RNCHS','RNCH','Rnch'),('RAPID','RPD','Rpd'),('RPD','RPD','Rpd'),('RAPIDS','RPDS','Rpds'),('RPDS','RPDS','Rpds'),('REST','RST','Rst'),('RST','RST','Rst'),('RDG','RDG','Rdg'),('RDGE','RDG','Rdg'),('RIDGE','RDG','Rdg'),('RDGS','RDGS','Rdgs'),('RIDGES','RDGS','Rdgs'),('RIV','RIV','Riv'),('RIVER','RIV','Riv'),('RIVR','RIV','Riv'),('RVR','RIV','Riv'),('RD','RD','Rd'),('ROAD','RD','Rd'),('RDS','RDS','Rds'),('ROADS','RDS','Rds'),('ROUTE','RTE','Rte'),('ROW','ROW','Row'),('RUE','RUE','Rue'),('RUN','RUN','Run'),('SHL','SHL','Shl'),('SHOAL','SHL','Shl'),('SHLS','SHLS','Shls'),('SHOALS','SHLS','Shls'),('SHOAR','SHR','Shr'),('SHORE','SHR','Shr'),('SHR','SHR','Shr'),('SHOARS','SHRS','Shrs'),('SHORES','SHRS','Shrs'),('SHRS','SHRS','Shrs'),('SKYWAY','SKWY','Skwy'),('SPG','SPG','Spg'),('SPNG','SPG','Spg'),('SPRING','SPG','Spg'),('SPRNG','SPG','Spg'),('SPGS','SPGS','Spgs'),('SPNGS','SPGS','Spgs'),('SPRINGS','SPGS','Spgs'),('SPRNGS','SPGS','Spgs'),('SPUR','SPUR','Spur'),('SPURS','SPUR','Spur'),('SQ','SQ','Sq'),('SQR','SQ','Sq'),('SQRE','SQ','Sq'),('SQU','SQ','Sq'),('SQUARE','SQ','Sq'),('SQRS','SQS','Sqs'),('SQUARES','SQS','Sqs'),('STA','STA','Sta'),('STATION','STA','Sta'),('STATN','STA','Sta'),('STN','STA','Sta'),('STRA','STRA','Stra'),('STRAV','STRA','Stra'),('STRAVE','STRA','Stra'),('STRAVEN','STRA','Stra'),('STRAVENUE','STRA','Stra'),('STRAVN','STRA','Stra'),('STRVN','STRA','Stra'),('STRVNUE','STRA','Stra'),('STREAM','STRM','Strm'),('STREME','STRM','Strm'),('STRM','STRM','Strm'),('ST','ST','St'),('STR','ST','St'),('STREET','ST','St'),('STRT','ST','St'),('STREETS','STS','Sts'),('SMT','SMT','Smt'),('SUMIT','SMT','Smt'),('SUMITT','SMT','Smt'),('SUMMIT','SMT','Smt'),('TER','TER','Ter'),('TERR','TER','Ter'),('TERRACE','TER','Ter'),('THROUGHWAY','TRWY','Trwy'),('TRACE','TRCE','Trce'),('TRACES','TRCE','Trce'),('TRCE','TRCE','Trce'),('TRACK','TRAK','Trak'),('TRACKS','TRAK','Trak'),('TRAK','TRAK','Trak'),('TRK','TRAK','Trak'),('TRKS','TRAK','Trak'),('TRAFFICWAY','TRFY','Trfy'),('TRFY','TRFY','Trfy'),('TR','TRL','Trl'),('TRAIL','TRL','Trl'),('TRAILS','TRL','Trl'),('TRL','TRL','Trl'),('TRLS','TRL','Trl'),('TUNEL','TUNL','Tunl'),('TUNL','TUNL','Tunl'),('TUNLS','TUNL','Tunl'),('TUNNEL','TUNL','Tunl'),('TUNNELS','TUNL','Tunl'),('TUNNL','TUNL','Tunl'),('TPK','TPKE','Tpke'),('TPKE','TPKE','Tpke'),('TRNPK','TPKE','Tpke'),('TRPK','TPKE','Tpke'),('TURNPIKE','TPKE','Tpke'),('TURNPK','TPKE','Tpke'),('UNDERPASS','UPAS','Upas'),('UN','UN','Un'),('UNION','UN','Un'),('UNIONS','UNS','Uns'),('VALLEY','VLY','Vly'),('VALLY','VLY','Vly'),('VLLY','VLY','Vly'),('VLY','VLY','Vly'),('VALLEYS','VLYS','Vlys'),('VLYS','VLYS','Vlys'),('VDCT','VIA','Via'),('VIA','VIA','Via'),('VIADCT','VIA','Via'),('VIADUCT','VIA','Via'),('VIEW','VW','Vw'),('VW','VW','Vw'),('VIEWS','VWS','Vws'),('VWS','VWS','Vws'),('VILL','VLG','Vlg'),('VILLAG','VLG','Vlg'),('VILLAGE','VLG','Vlg'),('VILLG','VLG','Vlg'),('VILLIAGE','VLG','Vlg'),('VLG','VLG','Vlg'),('VILLAGES','VLGS','Vlgs'),('VLGS','VLGS','Vlgs'),('VILLE','VL','Vl'),('VL','VL','Vl'),('VIS','VIS','Vis'),('VIST','VIS','Vis'),('VISTA','VIS','Vis'),('VST','VIS','Vis'),('VSTA','VIS','Vis'),('WALK','WALK','Walk'),('WALKS','WALK','Walk'),('WALL','WALL','Wall'),('WAY','WAY','Way'),('WY','WAY','Way'),('WAYS','WAYS','Ways'),('WELL','WL','Wl'),('WELLS','WLS','Wls'),('WLS','WLS','Wls');
-- ;



--
-- Dumping data for table `secondary_unit_desig`
--
REPLACE INTO `secondary_unit_desig` (`Common`,`Standard`,`Range_Required`,`TitleCaps`) VALUES 
('APARTMENT','APT','','Apt'),('BASEMENT','BSMT','\0','Bsmt'),('BUILDING','BLDG','','Bldg'),('DEPARTMENT','DEPT','','Dept'),('FLOOR','FL','','Fl'),('FRONT','FRNT','\0','Frnt'),('HANGER','HNGR','','Hngr'),('KEY','KEY','','Key'),('LOBBY','LBBY','\0','Lbby'),('LOT','LOT','','Lot'),('LOWER','LOWR','\0','Lowr'),('OFFICE','OFC','\0','Ofc'),('PENTHOUSE','PH','\0','Ph'),('PIER','PIER','','Pier'),('REAR','REAR','\0','Rear'),('SIDE','SIDE','\0','Side'),('SLIP','SLIP','','Slip'),('SPACE','SPC','','Spc'),('STOP','STOP','','Stop'),('SUITE','STE','','Ste'),('TRAILER','TRLR','','Trlr'),('UNIT','UNIT','','Unit'),('UPPER','UPPR','\0','Uppr'),('APT','APT','\0','Apt'),('BLDG','BLDG','','Bldg'),('DEPT','DEPT','','Dept'),('FL','FL','','Fl'),('FRNT','FRNT','\0','Frnt'),('HNGR','HNGR','','Hngr'),('LBBY','LBBY','\0','Lbby'),('LOWR','LOWR','\0','Lowr'),('OFC','OFC','\0','Ofc'),('PH','PH','\0','Ph'),('SPC','SPC','','Spc'),('STE','STE','','Ste'),('TRLR','TRLR','','Trlr'),('UPPR','UPPR','\0','Uppr'),('RM','RM','','Rm'),('ROOM','RM','','Rm');
-- ;




-- 
-- Hospitals
--
REPLACE INTO `hospital` (`Title`,`Type`,`Status`) values
('County Hospital', 'h', 'a');
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
REPLACE INTO `web_sites` 
(`idweb_sites`,`Site_Code`,`Description`,`Relative_Address`,`Required_Group_Code`,`Path_To_CSS`,`Path_To_JS`,`Last_Updated`,`Updated_By`,`Default_Page`,`Index_Page`,`HTTP_Host`)
 VALUES
(1,'a','Admin','admin/','mm','ui-icon ui-icon-gear','',now(),'admin','NameSch.php','index.php',''),
(2,'h','House','house/','g,ga','ui-icon ui-icon-person','',now(),'admin','register.php','index.php',''),
(3,'v','Volunteer','volunteer/','v','ui-icon ui-icon-heart','',now(),'admin','VolAction.php','index.php',''),
(4,'r','Root','/','pub','','',now(),'admin','','','');
-- ;



--
-- Dumping data for table `page`
--
REPLACE INTO `page` (`idPage`,`File_Name`,`Login_Page_Id`,`Title`,`Product_Code`,`Hide`,`Web_Site`,`Menu_Parent`,`Menu_Position`,`Type`,`Validity_Code`,`Updated_By`,`Last_Updated`,`Timestamp`)
VALUES (1,'index.php',0,'Welcome','',0,'r','','','p','','','2011-09-28 15:52:50','2011-09-29 06:52:50'),(2,'index.php',0,'','',0,'a','','','p','','admin','2011-09-28 15:52:50','2011-09-22 02:00:18'),(3,'NameEdit.php',2,'Edit Members','',0,'a','','','p','','admin','2014-07-25 12:27:19','2011-09-22 02:01:42'),(4,'EventShells.php',2,'Repeat Events','',0,'a','35','f','p','','','0000-00-00 00:00:00','2011-09-22 04:52:06'),(5,'KeyStats.php',2,'Key Stats','',0,'a','67','g','p','','admin','2012-06-11 14:28:56','2011-09-22 04:52:06'),(6,'Misc.php',2,'Miscellaneous','',0,'a','34','a','p','','admin','2012-04-09 12:04:46','2011-09-22 04:52:06'),(7,'PageEdit.php',2,'Edit Pages','',0,'a','34','e','p','','','0000-00-00 00:00:00','2011-09-22 04:52:06'),(8,'RegisterUser.php',2,'Register Web Users','',0,'a','35','e','p','','admin','2012-03-15 08:51:37','2011-09-22 04:52:06'),(9,'CategoryEdit.php',2,'Edit Categories','',0,'a','34','d','p','','admin','2012-01-18 11:55:50','2011-09-22 04:52:06'),
(10,'VolListing.php',2,'Web Users','',0,'a','35','c','p','','admin','2011-10-31 14:41:12','2011-09-22 04:52:06'),(11,'campaignEdit.php',2,'Edit Campaigns','',0,'a','34','c','p','','','0000-00-00 00:00:00','2011-09-22 04:56:43'),(12,'campaignReport.php',2,'Campaigns','',0,'a','32','d','p','','','0000-00-00 00:00:00','2011-09-22 04:56:43'),(13,'checkDateReport.php',2,'Check Date','',0,'a','32','j','p','','','0000-00-00 00:00:00','2011-09-22 04:56:43'),(14,'directory.php',2,'Directory','',0,'a','32','a','p','','','0000-00-00 00:00:00','2011-09-22 04:56:43'),(15,'donate.php',0,'','',0,'a','','','s','','','0000-00-00 00:00:00','2011-09-22 04:56:43'),(16,'donationReport.php',2,'Donations','',0,'a','32','b','p','','admin','2011-12-12 11:32:31','2011-09-22 04:56:43'),(18,'liveGetCamp.php',0,'','',0,'a','','','s','','','0000-00-00 00:00:00','2011-09-22 04:56:43'),(19,'liveNameSearch.php',0,'','',0,'a','','','s','','','0000-00-00 00:00:00','2011-09-22 04:56:43'),
(20,'ws_Report.php',0,'','',0,'a','','','s','','','0000-00-00 00:00:00','2011-09-22 04:56:43'),(21,'ws_gen.php',0,'','',0,'a','','','s','','','0000-00-00 00:00:00','2011-09-22 04:56:43'),(22,'VolNameEdit.php',26,'My Volunteer Info','',0,'v','0','d','p','','admin','2011-09-28 15:40:54','2011-09-22 05:01:58'),(23,'forgotpw.php',26,'Forgot My Password','',0,'v','','','p','','admin','2011-09-28 15:54:43','2011-09-22 05:01:58'),(24,'gCalFeed.php',0,'','',0,'v','','','s','','','0000-00-00 00:00:00','2011-09-22 05:01:58'),(26,'index.php',0,'','',0,'v','','','p','','admin','2011-09-28 15:50:30','2011-09-22 05:01:58'),(27,'register_web.php',26,'Register','',0,'v','','','p','','admin','2011-09-28 15:53:57','2011-09-22 05:01:58'),(28,'ws_reg_user.php',0,'','',0,'v','','','s','','','0000-00-00 00:00:00','2011-09-22 05:01:58'),(29,'ws_vol.php',0,'','',0,'v','','','s','','','0000-00-00 00:00:00','2011-09-22 05:01:58'),
(31,'index.php',0,'','',0,'h','','','p','','admin','2011-09-28 15:52:16','2011-09-22 05:03:58'),(32,'_directory.php',2,'Reports','',0,'a','0','e','p','','','0000-00-00 00:00:00','2011-09-22 22:20:36'),(33,'categoryReport.php',2,'Categories','',0,'a','32','f','p','','admin','2013-12-10 13:09:01','2011-09-22 22:25:04'),(34,'_Misc.php',2,'DB Maintenance','',0,'a','0','k','p','','admin','2011-10-13 10:42:35','2011-09-22 22:26:38'),(35,'_VolListing.php',2,'Web Users','',0,'a','0','j','p','','admin','2011-10-31 14:40:58','2011-09-22 22:27:25'),(36,'NameEdit_Donations',0,'','',0,'a','','','c','','','0000-00-00 00:00:00','2011-09-23 18:07:22'),(37,'NameEdit_Maint',0,'','',0,'a','','','c','','admin','2011-09-26 15:15:27','2011-09-27 21:24:53'),(39,'ws_gen_Maint',0,'','',0,'a','','','c','','admin','2011-09-26 15:41:54','2011-09-27 23:41:54'),
(45,'VolNameSearch.php',0,'','',0,'v','','','s','','admin','2011-10-09 19:24:53','2011-10-11 03:24:53'),(46,'guestadmin',0,'','',0,'h','','','c','','admin','2013-07-23 14:26:44','2011-10-15 17:01:45'),(47,'guestaccess',0,'','',0,'v','','','c','','admin','2011-10-17 15:23:29','2011-10-18 23:23:29'),(48,'PrivacyGroup',0,'','',0,'a','','','c','','admin','2011-10-31 20:38:16','2011-11-02 04:38:16'),(49,'recent.php',2,'Recent Changes','',0,'a','67','r','p','','admin','2012-06-11 14:29:48','2011-11-03 21:20:26'),
(50,'nonReportables.php',2,'Non-Reportables','',0,'a','67','v','p','','admin','2012-06-11 14:29:29','2011-12-04 05:06:32'),(51,'donorReport.php',2,'Donors','',0,'a','32','c','p','','admin','2011-12-24 17:42:31','2011-12-13 23:59:14'),(55,'MemEdit.php',0,'','',0,'v','','none','p','','admin','2012-02-07 16:36:02','2012-02-09 04:36:02'),(56,'Cat_Donor',0,'','',0,'a','','','c','','admin','2012-02-29 11:19:02','2012-03-01 23:19:02'),(57,'anomalies.php',2,'Anomaly report','',0,'a','67','k','p','','admin','2012-06-11 14:29:18','2012-03-09 09:28:42'),(59,'ws_admin.php',0,'','',0,'h','','','s','','admin','2013-02-25 17:01:00','2012-03-12 02:33:23'),
(60,'guestaccess',0,'','',0,'a','','','c','','admin','2012-03-26 14:04:37','2012-03-27 22:04:37'),(62,'roleSearch.php',0,'','',0,'h','','','s','','admin','2013-11-12 19:35:00','2012-03-31 18:46:51'),(65,'timeReport.php',2,'Time Reports','',0,'a','32','u','p','','admin','2012-06-04 13:47:31','2012-06-05 21:47:31'),(66,'NameSch.php',2,'Members','',0,'a','0','d','p','','admin','2015-01-27 11:46:08','2012-06-12 22:22:04'),(67,'_KeyStats.php',2,'Key Stats','',0,'a','0','g','p','','admin','2012-06-11 14:28:41','2012-06-12 22:28:41'),(68,'VolAction.php',26,'Activities','',0,'v','0','b','p','','admin','2012-09-03 16:37:32','2012-06-12 23:21:41'),(69,'_index.php?log=lo',0,'Log Out','',0,'a','0','z','p','','admin','2012-06-17 13:07:24','2012-06-18 21:05:10'),
(70,'_index.php?log=lo',0,'Log Out','',0,'v','0','z','p','','admin','2012-06-17 13:08:23','2012-06-18 21:05:10'),(71,'_index.php?log=lo',0,'Log Out','',0,'h','0','z','p','','admin','2012-06-17 13:08:36','2012-06-18 21:05:10'),(72,'CheckIn.php',31,'Check In','',0,'h','0','f','p','','admin','2014-09-08 09:13:58','2012-10-21 00:12:53'),(74,'register.php',31,'House Register','',0,'h','79','b','p','','admin','2017-02-12 15:37:50','2012-12-09 01:25:34'),(75,'ws_resv.php',0,'','',0,'h','','','s','','admin','2017-11-10 16:09:36','2017-10-12 04:09:36'),(76,'ws_ckin.php',0,'','',0,'h','','','s','','admin','2012-12-20 16:42:05','2012-12-22 04:42:05'),(77,'RoomView.php',31,'Room Viewer','',1,'h','79','d','p','','admin','2017-02-12 15:37:50','2013-01-03 12:54:45'),(79,'_register.php',31,'House','',0,'h','0','d','p','','admin','2013-01-02 01:48:49','2013-01-03 13:48:49'),
(80,'HouseCal.php',26,'','',0,'v','','','p','','admin','2013-03-03 17:31:24','2013-03-05 05:30:30'),(81,'ResourceBuilder.php',31,'Resource Builder','',0,'h','79','l','p','','admin','2017-02-12 15:37:50','2013-03-26 21:19:56'),(82,'ws_resc.php',0,'','',0,'h','','','s','','admin','2013-03-25 15:01:03','2013-03-26 23:01:03'),(83,'RoomUtilization.php',31,'Room Report','',0,'h','102','e','p','','admin','2017-02-12 15:37:50','2013-04-02 21:43:00'),(84,'memberManagement',0,'','',0,'h','','','c','','admin','2013-06-06 11:17:02','2013-06-07 19:17:02'),(87,'ws_reg.php',0,'','',0,'r','','','s','','admin','2013-08-04 13:40:29','2013-08-05 21:40:29'),(88,'AuthGroupEdit.php',2,'Edit Authorization','',0,'a','34','j','p','','admin','2013-08-07 15:13:41','2013-08-08 23:13:05'),(89,'Configure.php',2,'Site Configuration','',0,'a','34','g','p','','admin','2013-08-17 10:11:05','2013-08-18 18:10:27'),
(92,'GuestDemog.php',31,'Missing Demographics','',0,'h','102','f','p','','admin','2017-02-12 15:37:50','2013-09-04 18:23:02'),(93,'GuestEdit.php',31,'Guest Edit','',0,'h','0','j','p','','admin','2014-03-05 15:49:04','2013-09-04 18:25:10'),(94,'ShowRegForm.php',31,'Registration Form','',0,'h','','','p','','admin','2017-02-12 15:37:50','2013-10-22 11:58:41'),(95,'_Referral.php',31,'Reservation','',0,'h','0','e','p','','admin','2014-03-15 12:39:32','2013-11-07 19:32:21'),(96,'CheckedIn.php',31,'','',0,'h','','','p','','admin','2017-02-12 15:37:50','2013-12-03 00:54:22'),(99,'PaymentResult.php',31,'Payment Result','',0,'h','','','p','','admin','2017-02-12 16:23:39','2013-12-17 13:14:11'),
(100,'ShowStatement.php',31,'Guest Statement','',0,'h','','','p','','admin','2017-02-12 15:37:50','2014-01-30 19:39:16'),(101,'RoomStatus.php',31,'Housekeeping','',0,'h','79','p','p','','admin','2017-02-12 15:37:50','2014-02-26 08:46:18'),(102,'_GuestReport.php',31,'Reports','',0,'h','0','h','p','','admin','2014-03-15 12:35:58','2014-03-06 13:46:56'),(104,'ReservReport.php',31,'Reservations Report','',0,'h','102','b','p','','admin','2017-02-12 15:37:51','2014-03-16 08:37:09'),(105,'PaymentTx.php',2,'Credit Transactions Report','',0,'a','32','v','p','','admin','2015-01-27 12:19:09','2014-08-14 08:25:03'),(106,'Duplicates.php',2,'Duplicates','',0,'a','32','o','p','','admin','2014-08-27 13:43:20','2014-08-28 09:43:21'),(107,'PSGReport.php',31,'People Reports','',0,'h','102','k','p','','admin','2017-02-12 15:37:51','2014-11-14 14:25:35'),(108,'Waitlist.php',31,'Anticipated Visits','',1,'h','0','g','p','','admin','2015-01-17 14:14:40','2015-01-18 06:14:40'),(109,'PaymentReport.php',31,'Payment Report','',0,'h','102','m','p','','admin','2017-02-12 15:37:51','2015-02-10 06:59:12'),
(110,'VisitInterval.php',31,'Visit Interval Report','',0,'h','102','c','p','','admin','2017-02-12 15:37:51','2015-02-18 03:47:21'),(111,'GuestView.php',31,'Guests & Vehicles','',0,'h','79','v','p','','admin','2017-02-12 15:37:51','2015-02-20 03:53:11'),(113,'DRaHospReport.php',31,'Doctors, Hospitals','',0,'h','102','l','p','','admin','2017-02-12 15:37:51','2015-05-16 03:22:26'),(114,'ShowInvoice.php',31,'Show Invoice','',0,'h','','','p','','admin','2017-02-12 15:37:51','2015-06-23 04:05:44'),(115,'InvoiceReport.php',31,'Invoice Report','',0,'h','102','n','p','','admin','2017-02-12 15:37:51','2015-07-29 08:32:12'),(116,'ShowHsKpg.php',31,'Housekeeping','',0,'h','','','p','','admin','2017-02-12 15:37:51','2015-08-11 06:00:39'),(117,'PrtRegForm.php',31,'Print Registration Forms','',0,'h','','','p','','admin','2017-02-12 15:37:51','2015-09-06 02:47:07'),(118,'occDemo.php',31,'Guest Demographics','',0,'h','102','g','p','','admin','2017-02-12 15:37:51','2015-10-30 23:31:30'),(119,'ItemReport.php',31,'Item Report','',0,'h','102','s','p','','admin','2017-02-12 15:37:51','2015-12-04 02:28:20'),
(120,'AccessLog.php',2,'User Access Log','',0,'a','35','d','p','','admin','2016-08-21 11:37:15','2016-08-21 21:37:15'),(121,'GuestTransfer.php',31,'Guest Transfer','',1,'h','79','x','p','','admin','2017-08-11 16:10:22','2016-11-28 23:13:39'),(122,'NewGuest.php',31,'New Guests','',0,'h','102','i','p','','admin','2017-02-12 15:37:51','2017-01-03 00:28:06'),(123,'PrtWaitList.php',31,'Wait Listing','',0,'h','','','p','','admin','2017-02-12 15:37:51','2017-01-03 00:28:06'),(124,'Reserve.php',31,'Family Reservation','',1,'h','95','g','p','','admin','2017-07-26 09:23:58','2017-07-26 14:23:58'),(125,'Referral.php',31,'Reservation','',0,'h','95','j','p','','admin','2017-07-26 09:31:04','2017-07-26 14:31:04'),(126,'DailyReport.php',31,'Daily Report','',0,'h','102','p','p','','admin','2017-10-05 10:56:22','2017-10-05 15:56:22');
-- ;


--
-- Dumping data for table `page_securitygroup`
--
Replace INTO `page_securitygroup` (`idPage`,`Group_Code`,`Timestamp`) 
VALUES (1,'pub','2011-09-30 00:03:46'),(2,'pub','2011-09-24 17:14:44'),(3,'mm','2011-09-22 05:21:42'),(4,'mm','2011-09-22 05:21:42'),(5,'mm','2011-09-22 05:21:42'),(6,'db','2011-09-22 05:21:42'),(7,'db','2011-09-22 05:21:42'),(8,'mm','2011-09-22 05:21:42'),(9,'db','2011-09-22 05:21:42'),
(10,'mm','2011-09-22 05:21:42'),(11,'db','2011-09-22 05:21:42'),(12,'dm','2011-09-22 05:21:42'),(13,'mm','2011-09-22 05:21:42'),(14,'mm','2011-09-22 05:21:42'),(15,'dm','2011-09-22 05:21:42'),(16,'dm','2011-09-22 05:21:42'),(18,'mm','2011-09-22 05:21:42'),(19,'mm','2011-09-22 05:21:42'),
(20,'dm','2011-09-22 05:21:42'),(21,'g','2017-10-13 14:21:23'),(21,'ga','2017-10-13 14:21:23'),(21,'mm','2011-09-22 05:21:42'),(22,'v','2011-09-22 05:24:43'),(23,'pub','2011-09-30 00:01:12'),(24,'v','2011-09-22 05:24:43'),(26,'pub','2011-09-24 17:15:17'),(27,'pub','2011-09-30 00:02:08'),(28,'pub','2011-09-30 00:02:08'),(29,'v','2011-09-22 05:24:43'),
(31,'pub','2011-09-24 17:15:48'),(32,'mm','2011-09-23 00:36:57'),(33,'mm','2011-09-23 00:48:59'),(34,'db','2011-09-23 00:36:57'),(35,'mm','2011-09-23 00:36:57'),(36,'dm','2011-09-24 19:22:47'),(37,'db','2011-09-27 21:24:53'),(39,'db','2011-09-27 23:41:54'),
(45,'v','2011-10-11 03:24:53'),(46,'ga','2017-02-13 02:54:06'),(47,'g','2011-10-18 23:23:29'),(48,'p','2011-11-02 04:38:16'),(49,'mm','2011-11-03 21:20:26'),
(50,'mm','2011-12-04 05:06:32'),(51,'dna','2011-12-13 23:59:14'),(52,'dm','2012-01-13 05:48:53'),(55,'v','2012-02-09 04:36:02'),(56,'dna','2012-03-01 23:19:02'),(57,'mm','2012-03-09 09:28:42'),(59,'g','2017-02-13 02:54:21'),(59,'ga','2017-02-13 02:41:22'),
(60,'g','2012-03-27 22:04:37'),(62,'g','2017-02-13 02:54:37'),(62,'ga','2017-02-13 02:54:37'),(65,'mm','2012-06-05 21:47:31'),(66,'mm','2012-06-12 22:22:04'),(67,'mm','2012-06-12 22:28:41'),(68,'v','2012-06-12 23:21:41'),(69,'pub','2012-06-18 21:10:47'),
(70,'pub','2012-06-18 21:10:47'),(71,'pub','2017-02-13 02:55:11'),(72,'g','2017-02-13 02:41:25'),(72,'ga','2017-02-13 02:41:25'),(74,'g','2017-02-13 02:55:11'),(74,'ga','2017-02-13 02:55:11'),(75,'g','2012-12-12 04:09:36'),(75,'ga','2013-07-30 21:07:47'),(76,'g','2012-12-22 04:42:05'),(76,'ga','2013-07-30 21:07:55'),(77,'ga','2013-07-30 21:06:47'),(79,'g','2017-02-13 01:50:26'),(79,'ga','2013-07-30 21:06:12'),
(80,'v','2013-03-05 05:30:30'),(81,'ga','2016-03-21 19:48:52'),(82,'g','2016-03-21 19:48:31'),(82,'ga','2016-03-21 19:48:31'),(83,'ga','2013-08-04 17:43:13'),(84,'g','2017-02-13 02:37:50'),(84,'ga','2017-02-13 02:37:50'),(87,'pub','2013-08-05 21:40:29'),(88,'db','2013-08-08 23:13:05'),(89,'db','2013-08-18 18:10:27'),
(92,'ga','2017-02-13 02:38:20'),(93,'g','2017-02-13 02:41:31'),(93,'ga','2017-02-13 02:41:31'),(94,'g','2017-07-26 14:27:14'),(94,'ga','2017-07-26 14:27:14'),(95,'g','2017-07-26 14:27:14'),(95,'ga','2017-07-26 14:27:14'),(96,'g','2017-07-26 14:27:14'),(96,'ga','2017-07-26 14:27:14'),(99,'g','2017-07-26 14:27:14'),(99,'ga','2017-07-26 14:27:14'),
(100,'g','2017-07-26 14:27:14'),(100,'ga','2017-07-26 14:27:14'),(101,'g','2017-02-13 01:49:21'),(101,'ga','2014-02-26 08:46:18'),(102,'ga','2014-03-06 13:46:56'),(104,'ga','2017-02-13 01:28:45'),(105,'db','2014-08-14 08:25:03'),(106,'mm','2014-08-28 09:43:21'),(107,'ga','2014-11-14 14:25:35'),(108,'g','2017-02-13 01:51:32'),(108,'ga','2015-01-18 06:14:40'),(109,'ga','2015-02-10 06:59:12'),
(110,'ga','2015-02-18 03:47:21'),(111,'g','2017-02-13 02:41:42'),(111,'ga','2015-02-20 03:53:11'),(113,'ga','2015-05-16 03:22:26'),(114,'g','2017-10-13 14:20:00'),(114,'ga','2017-02-13 01:49:21'),(115,'ga','2015-07-29 08:32:12'),(116,'g','2017-02-13 02:41:42'),(116,'ga','2017-02-13 01:49:21'),(117,'g','2017-10-13 14:20:00'),(117,'ga','2015-09-06 02:47:07'),(118,'ga','2015-10-30 23:31:30'),(119,'ga','2015-12-04 02:28:20'),
(120,'mm','2016-08-21 21:37:15'),(121,'ga','2016-11-28 23:13:39'),(122,'ga','2017-01-03 00:28:06'),(123,'g','2017-02-13 01:30:54'),(123,'ga','2017-01-03 00:28:06'),(124,'g','2017-07-26 14:23:58'),(124,'ga','2017-07-26 14:23:58'),(125,'g','2017-07-26 14:31:04'),(125,'ga','2017-07-26 14:31:04'),(126,'ga','2017-10-05 15:56:22');
-- ;


--
-- Dumping data for table `language`
--
REPLACE INTO `language` (`idLanguage`,`Title`,`ISO_639_1`,`Display`) VALUES
 (1,'Abkhazian','ab',0),(2,'Afar','aa',0),(3,'Afrikaans','af',0),(4,'Akan','ak',0),(6,'Albanian','sq',0),(7,'Amharic','am',0),
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
(447,'Sinhalese','si',0),(453,'Slovak','sk',0),(454,'Slovenian','sl',0),(456,'Somali','so',0),(461,'Sotho, Southern','st',0),(463,'South Ndebele','nr',0),(466,'Spanish','es',1),
(471,'Sundanese','su',0),(473,'Swahili','sw',0),(474,'Swati','ss',0),(475,'Swedish','sv',0),(478,'Tagalog','tl',0),(479,'Tahitian','ty',0),(481,'Tajik','tg',0),(483,'Tamil','ta',0),
(484,'Tatar','tt',0),(485,'Telugu','te',0),(488,'Thai','th',0),(489,'Tibetan','bo',0),(491,'Tigrinya','ti',0),(499,'Tonga (Tonga Islands)','to',0),(501,'Tsonga','ts',0),
(502,'Tswana','tn',0),(505,'Turkish','tr',0),(507,'Turkmen','tk',0),(510,'Twi','tw',0),(513,'Uighur','ug',0),(514,'Ukrainian','uk',0),(519,'Urdu','ur',0),(520,'Uyghur','ug',0),
(521,'Uzbek','uz',0),(523,'Valencian','ca',0),(524,'Venda','ve',0),(525,'Vietnamese','vi',0),(526,'VolapÃ¼k','vo',0),(529,'Walloon','wa',0),(532,'Welsh','cy',0),
(533,'Western Frisian','fy',0),(537,'Wolof','wo',0),(538,'Xhosa','xh',0),(542,'Yiddish','yi',0),(543,'Yoruba','yo',0),(550,'Zhuang','za',0),(551,'Zulu','zu',0);
-- ;


--
-- Dumping data for table `country_code`
--

REPLACE INTO `country_code` (`Country_Name`,`ISO_3166-1-alpha-2`,`External_Id`) VALUES
 ('ANDORRA','AD',7),('UNITED ARAB EMIRATES','AE',223),('AFGHANISTAN','AF',3),('ANTIGUA AND BARBUDA','AG',10),('ANGUILLA','AI',242),('ALBANIA','AL',4),
('ARMENIA','AM',12),('ANGOLA','AO',8),('ANTARCTICA','AQ',9),('ARGENTINA','AR',11),('AMERICAN SAMOA','AS',6),('AUSTRIA','AT',15),('AUSTRALIA','AU',14),('ARUBA','AW',13),
('ALAND ISLANDS','AX',0),('AZERBAIJAN','AZ',16),('BOSNIA AND HERZEGOVINA','BA',28),('BARBADOS','BB',20),('BANGLADESH','BD',19),('BELGIUM','BE',22),('BURKINA FASO','BF',35),
('BULGARIA','BG',34),('BAHRAIN','BH',18),('BURUNDI','BI',36),('BENIN','BJ',24),('SAINT BARTHELEMY','BL',0),('BERMUDA','BM',25),('BRUNEI DARUSSALAM','BN',33),
('BOLIVIA, PLURINATIONAL STATE OF','BO',27),('BONAIRE, SINT EUSTATIUS AND SABA','BQ',0),('BRAZIL','BR',31),('BAHAMAS','BS',17),('BHUTAN','BT',26),('BOUVET ISLAND','BV',30),
('BOTSWANA','BW',29),('BELARUS','BY',21),('BELIZE','BZ',23),('CANADA','CA',2),('COCOS (KEELING) ISLANDS','CC',46),('CONGO, THE DEMOCRATIC REPUBLIC OF THE','CD',49),
('CENTRAL AFRICAN REPUBLIC','CF',41),('CONGO','CG',0),('SWITZERLAND','CH',206),('COTE D\'IVOIRE','CI',106),('COOK ISLANDS','CK',50),('CHILE','CL',43),('CAMEROON','CM',38),
('CHINA','CN',44),('COLOMBIA','CO',47),('COSTA RICA','CR',51),('CUBA','CU',53),('CAPE VERDE','CV',39),('CURACAO','CW',0),('CHRISTMAS ISLAND','CX',45),('CYPRUS','CY',54),
('CZECH REPUBLIC','CZ',55),('GERMANY','DE',80),('DJIBOUTI','DJ',57),('DENMARK','DK',56),('DOMINICA','DM',58),('DOMINICAN REPUBLIC','DO',59),('ALGERIA','DZ',5),('ECUADOR','EC',61),
('ESTONIA','EE',66),('EGYPT','EG',62),('WESTERN SAHARA','EH',235),('ERITREA','ER',65),('SPAIN','ES',199),('ETHIOPIA','ET',67),('FINLAND','FI',71),('FIJI','FJ',70),
('FALKLAND ISLANDS (MALVINAS)','FK',68),('MICRONESIA, FEDERATED STATES OF','FM',138),('FAROE ISLANDS','FO',69),('FRANCE','FR',73),('GABON','GA',77),('UNITED KINGDOM','GB',224),
('GRENADA','GD',86),('GEORGIA','GE',79),('FRENCH GUIANA','GF',75),('GUERNSEY','GG',0),('GHANA','GH',81),('GIBRALTAR','GI',82),('GREENLAND','GL',85),('GAMBIA','GM',78),
('GUINEA','GN',90),('GUADELOUPE','GP',87),('EQUATORIAL GUINEA','GQ',64),('GREECE','GR',84),('SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS','GS',179),('GUATEMALA','GT',89),
('GUAM','GU',88),('GUINEA-BISSAU','GW',91),('GUYANA','GY',92),('HONG KONG','HK',96),('HEARD ISLAND AND MCDONALD ISLANDS','HM',94),('HONDURAS','HN',95),('CROATIA','HR',52),
('HAITI','HT',93),('HUNGARY','HU',97),('INDONESIA','ID',100),('IRELAND','IE',103),('ISRAEL','IL',104),('ISLE OF MAN','IM',0),('INDIA','IN',99),('BRITISH INDIAN OCEAN TERRITORY','IO',32),
('IRAQ','IQ',102),('IRAN, ISLAMIC REPUBLIC OF','IR',101),('ICELAND','IS',98),('ITALY','IT',105),('JERSEY','JE',0),('JAMAICA','JM',107),('JORDAN','JO',109),('JAPAN','JP',108),
('KENYA','KE',111),('KYRGYZSTAN','KG',114),('CAMBODIA','KH',37),('KIRIBATI','KI',112),('COMOROS','KM',48),('SAINT KITTS AND NEVIS','KN',181),('KOREA, DEMOCRATIC PEOPLE\'S REPUBLIC OF','KP',0),
('KOREA, REPUBLIC OF','KR',198),('KUWAIT','KW',113),('CAYMAN ISLANDS','KY',40),('KAZAKHSTAN','KZ',110),('LAO PEOPLE\'S DEMOCRATIC REPUBLIC','LA',115),('LEBANON','LB',117),
('SAINT LUCIA','LC',182),('LIECHTENSTEIN','LI',121),('SRI LANKA','LK',200),('LIBERIA','LR',119),('LESOTHO','LS',118),('LITHUANIA','LT',122),('LUXEMBOURG','LU',123),('LATVIA','LV',116),
('LIBYA','LY',120),('MOROCCO','MA',143),('MONACO','MC',140),('MOLDOVA, REPUBLIC OF','MD',139),('MONTENEGRO','ME',0),('SAINT MARTIN (FRENCH PART)','MF',0),('MADAGASCAR','MG',126),
('MARSHALL ISLANDS','MH',132),('MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF','MK',125),('MALI','ML',130),('MYANMAR','MM',145),('MONGOLIA','MN',141),('MACAO','MO',124),
('NORTHERN MARIANA ISLANDS','MP',159),('MARTINIQUE','MQ',133),('MAURITANIA','MR',134),('MONTSERRAT','MS',142),('MALTA','MT',131),('MAURITIUS','MU',135),('MALDIVES','MV',129),
('MALAWI','MW',127),('MEXICO','MX',137),('MALAYSIA','MY',128),('MOZAMBIQUE','MZ',144),('NAMIBIA','NA',146),('NEW CALEDONIA','NC',151),('NIGER','NE',154),('NORFOLK ISLAND','NF',157),
('NIGERIA','NG',155),('NICARAGUA','NI',153),('NETHERLANDS','NL',149),('NORWAY','NO',160),('NEPAL','NP',148),('NAURU','NR',147),('NIUE','NU',156),('NEW ZEALAND','NZ',152),
('OMAN','OM',161),('PANAMA','PA',164),('PERU','PE',167),('FRENCH POLYNESIA','PF',171),('PAPUA NEW GUINEA','PG',165),('PHILIPPINES','PH',168),('PAKISTAN','PK',162),('POLAND','PL',170),
('SAINT PIERRE AND MIQUELON','PM',183),('PITCAIRN','PN',169),('PUERTO RICO','PR',173),('PALESTINE, STATE OF','PS',0),('PORTUGAL','PT',172),('PALAU','PW',163),('PARAGUAY','PY',166),
('QATAR','QA',174),('REUNION','RE',175),('ROMANIA','RO',176),('SERBIA','RS',0),('RUSSIAN FEDERATION','RU',177),('RWANDA','RW',178),('SAUDI ARABIA','SA',188),('SOLOMON ISLANDS','SB',195),
('SEYCHELLES','SC',190),('SUDAN','SD',201),('SWEDEN','SE',205),('SINGAPORE','SG',192),('SAINT HELENA, ASCENSION AND TRISTAN DA CUNHA','SH',180),('SLOVENIA','SI',194),
('SVALBARD AND JAN MAYEN','SJ',203),('SLOVAKIA','SK',193),('SIERRA LEONE','SL',191),('SAN MARINO','SM',187),('SENEGAL','SN',189),('SOMALIA','SO',196),('SURINAME','SR',202),
('SOUTH SUDAN','SS',243),('SAO TOME AND PRINCIPE','ST',184),('EL SALVADOR','SV',63),('SINT MAARTEN (DUTCH PART)','SX',245),('SYRIAN ARAB REPUBLIC','SY',207),('SWAZILAND','SZ',204),
('TURKS AND CAICOS ISLANDS','TC',219),('CHAD','TD',42),('FRENCH SOUTHERN TERRITORIES','TF',76),('TOGO','TG',212),('THAILAND','TH',211),('TAJIKISTAN','TJ',208),('TOKELAU','TK',213),
('TIMOR-LESTE','TL',60),('TURKMENISTAN','TM',218),('TUNISIA','TN',216),('TONGA','TO',214),('TURKEY','TR',217),('TRINIDAD AND TOBAGO','TT',215),('TUVALU','TV',220),
('TAIWAN, PROVINCE OF CHINA','TW',209),('TANZANIA, UNITED REPUBLIC OF','TZ',210),('UKRAINE','UA',222),('UGANDA','UG',221),('UNITED STATES MINOR OUTLYING ISLANDS','UM',226),
('UNITED STATES','US',1),('URUGUAY','UY',225),('UZBEKISTAN','UZ',227),('HOLY SEE (VATICAN CITY STATE)','VA',229),('SAINT VINCENT AND THE GRENADINES','VC',185),
('VENEZUELA, BOLIVARIAN REPUBLIC OF','VE',230),('VIRGIN ISLANDS, BRITISH','VG',232),('VIRGIN ISLANDS, U.S.','VI',233),('VIET NAM','VN',231),('VANUATU','VU',228),
('WALLIS AND FUTUNA','WF',234),('SAMOA','WS',186),('YEMEN','YE',236),('MAYOTTE','YT',136),('SOUTH AFRICA','ZA',197),('ZAMBIA','ZM',239),('ZIMBABWE','ZW',240);
-- ;
