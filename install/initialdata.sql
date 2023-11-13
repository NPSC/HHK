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
('Campaign_Status','d','Disabled','','',0),

('Campaign_Type','as','Normal','','',0),
('Campaign_Type','pct','Percent Cut Out','','',0),
('Campaign_Type','ink','In Kind','','',0),

('Category_Types', '1', 'Items', '','',0),
('Category_Types', '2', 'Tax', '','',0),
('Category_Types', '3', 'Penalty', '','',0),

('Charge_Cards', '1', 'Visa', 'VISA','',0),
('Charge_Cards', '2', 'M/C', 'M/C','',0),
('Charge_Cards', '3', 'Discover','DCVR', '',0),
('Charge_Cards', '4', 'Am Ex', 'AMEX','',0),

('Cm_Custom_Fields', 'HHK_ID', '','','',0),
('Cm_Custom_Fields', 'Deceased_Date', '','','',0),
('Cm_Custom_Fields', 'Diagnosis', '','','',0),
('Cm_Custom_Fields', 'Hospital', '','','',0),

('Constraint_Type', 'hos', 'Hospital', '','',0),
('Constraint_Type', 'rv', 'Reservation','','',0),
('Constraint_Type', 'v', 'Visit', '','',0),

('cronJobTypes', 'SendPostCheckoutEmailJob', 'Send Post Check In/Out Email', '','', 0),
('cronJobTypes', 'EmailReportJob', 'Send Report Email', '','', 0),

('dayIncrements', '', 'Never', '','', '6'),
('dayIncrements', '30', '30 days', '','', '1'),
('dayIncrements', '60', '60 days', '','', '2'),
('dayIncrements', '90', '90 days', '','', '3'),
('dayIncrements', '180', '180 days', '','', '4'),
('dayIncrements', '365', '365 days', '','', '5'),

('Default_Reg_Tab', '0', 'Calendar Tab', '','', '0'),
('Default_Reg_Tab', '1', 'Current Guests Tab', '','', '0'),

('Demographics', 'Age_Bracket', 'Age Bracket', 'y','m',5),
('Demographics', 'Ethnicity', 'Ethnicity', 'y','m',10),
('Demographics', 'Gender', 'Gender Identity', 'y','m',15),
('Demographics', 'Income_Bracket', 'Income Bracket', 'y','m',25),
('Demographics', 'Education_Level', 'Education Level', 'y','m',20),
('Demographics', 'Special_Needs', 'Special Needs', '','m',35),
('Demographics', 'Media_Source', 'Media Source', '','m',30),

('Diagnosis','d1','Other','','h',0),
('Diagnosis','d2','Cardiac','','h',0),
('Diagnosis','d3','Prostate Cancer','','h',0),
('Diagnosis','d4','NICU','','h',0),
('Diagnosis','d5','Glioma','','h',0),

('Diagnosis_Category', 'o', 'Oncology', '', 'h', 0),
('Diagnosis_Category', 'n', 'Neurology', '', 'h', 0),
('Diagnosis_Category', 'c', 'Cardiac', '', 'h', 0),

('DistCalculator', 'zip', 'Nautical (Approx)','', '', 10),
('DistCalculator', 'google', 'Driving', '', '', 20),

('Dir_Type_Selector_Code','d','Directory','','',0),
('Dir_Type_Selector_Code','e','Email Addresses','','',0),
('Dir_Type_Selector_Code','m','Mailing List','','',0),

('Distance_Range','50','Up to 50 miles','2','',10),
('Distance_Range','100','51 to 100 miles','3','',20),
('Distance_Range','150','101 to 150 miles','4','',30),
('Distance_Range','200','151 to 200 miles','5','',40),
('Distance_Range','30000','More Than 200 miles','7','',50),

('Document_Category', 'form', 'Form','','',0),
('Document_Category', 'stat', 'Static','','',0),

('Document_Type', 'md', 'Markdown','','',0),
('Document Type', 'text', 'Text','','',0),

('Dormant_Selector_Code','act','Active Only','','',0),
('Dormant_Selector_Code','both','Dormant & Active','','',0),
('Dormant_Selector_Code','dor','Dormant Only','','',0),

('Editable_Forms', '../conf/agreement.txt', 'Registration Agreement','js/rte-agreement.json','',0),
('Editable_Forms', '../conf/confirmation.txt', 'Confirmation Form','js/rte-confirmation.json','',0),
('Editable_Forms', '../conf/survey.txt', 'Survey Form','js/rte-survey.json','',0),

('Education_Level','01','Highschool','','d',0),
('Education_Level','02','College','','d',0),
('Education_Level','z','Unknown','','d',1000),

('Email_Purpose','1','Home','i','',10),
('Email_Purpose','2','Work','i','',20),
('Email_Purpose','4','Office','o','',40),

('Email_Server', '', '(None)','','',0),
('Email_Server', 'SMTP', 'SMTP','','',0),
('Email_Server', 'Mail', 'Mail','','',0),

('Ethnicity','c','Caucasian','','d',10),
('Ethnicity','f','African American','','d',30),
('Ethnicity','h','Hispanic','','d',20),
('Ethnicity','k','Asian','','d',40),
('Ethnicity','n','Native Hawaiian or other Pacific Islander','','d',50),
('Ethnicity','a','American Indian or Alaska Native','','d',60),
('Ethnicity','m','Middle Eastern or North African','','d',70),
('Ethnicity','x','Other race or ethnicity not listed here','','d',80),
('Ethnicity','z','Unknown','','d',1000),

('ExcessPays', 'd', 'Donate','','u',0),
('ExcessPays', 'e', 'Hold (MOA)','','u',0),
('ExcessPays', 'i', 'Ignore','','u',0),
('ExcessPays', 'r', 'Refund','','u',0),

('ExternalCrm', '', '(none)','','',0),
('ExternalCrm', 'neon', 'Neon CRM','','',0),
('ExternalCrm', 'sf', 'Salesforce CRM','','',0),

('FB_Status','a','Active','','',0),
('FB_Status','d','Disabled','','',0),
('FB_Status','w','Waiting','','',0),
('FB_Status','x','Prohibited','','',0),

('Form_Upload', 'c', 'Confirmation', 'Resv_Conf', '', 0),
('Form_Upload', 'ra', 'Registration Agreement', 'Reg_Agreement', '', 0),
('Form_Upload', 's', 'Survey Form', 'Survy_Form', '', 0),

('Fund', '1', 'External Donation', '', 'u', '100'),

('Gender','f','Female','','d',0),
('Gender','m','Male','','d',10),
('Gender','nb','Non-Binary','','d',20),
('Gender','t','Other Gender not listed here','','d',30),
('Gender','z','Unknown','','d',1000),

('Guest_Survey', 'Survey_Date','', '','',0),

('Holiday', '0', 'New Years Day','','',0),
('Holiday', '1', 'Martin Luther King Birthday','','',0),
('Holiday', '2', 'Washington Birthday','','',0),
('Holiday', '3', 'Memorial Day','','',0),
('Holiday', '14', 'Juneteenth', '','',0),
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

('House_Discount', 'hd1', 'Service Issue','10', 'ca',0),
('House_Discount', 'hd2', 'Facilities Issue','15', 'ca',0),

('HouseKpgSteps', '1', '1 Step','','',0),
('HouseKpgSteps', '2', '2 Steps','','',0),

('Incident_Status', 'a', 'Active', '', 'u', 1),
('Incident_Status', 'r', 'Resolved', '', 'u', 7),
('Incident_Status', 'd', 'Deleted', '', 'u', 10),
('Incident_Status', 'h', 'On Hold', '', 'u', 4),


('Income_Bracket', 'ib1', '0 - 25,000', '', 'd',10),
('Income_Bracket', 'ib2', '26 - 50,000', '', 'd',20),
('Income_Bracket', 'ib3', '51 - 75,000', '', 'd',30),
('Income_Bracket', 'ib4', '76 - 100,000', '', 'd',40),
('Income_Bracket', 'ib5', '100 - 150,000', '', 'd',50),
('Income_Bracket', 'ib6', '200,000 & up', '', 'd',60),
('Income_Bracket', 'z', 'Unknown', '', 'd',1000),

('Init_Reserv_Status', 'a', 'Confirmed', '', '',0),
('Init_Reserv_Status', 'uc', 'Unconfirmed', '', '',0),

('Invoice_Status', 'p', 'Paid', '','',0),
('Invoice_Status', 'up', 'Unpaid', '','',0),
('Invoice_Status', 'c', 'Carried', '','',0),

('Key_Deposit_Code','k0','None','0','',0),
('Key_Deposit_Code','k1','House','20','',0),

('labels_category', 'rg', 'Register', '', '', 10),
('labels_category', 'rf', 'Referral', '', '', 20),
('labels_category', 'vi', 'Visit', '', '', 25),
('labels_category', 'h', 'Hospital', '', '', 30),
('labels_category', 'mf', 'MomentFormats', '', '', 40),
('labels_category', 'ck', 'Checkin', '', '', 50),
('labels_category', 'pc', 'PaymentChooser', '', '', 60),
('labels_category', 'mt', 'MemberType', '', '', 70),
('labels_category', 'g', 'GuestEdit', '', '', 80),
('labels_category', 'r', 'ResourceBuilder', '', '', 90),
('labels_category', 's', 'Statement', '', '', 100),

('Language_Proficiency', '5', 'Native','', '',0),
('Language_Proficiency', '3', 'Professional','', '',0),
('Language_Proficiency', '2', 'Limited','', '',0),
('Language_Proficiency', 'l', 'Elementary','', '',0),

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
('NoReturnReason', 'n2', 'Smokes in Room', '', 'h',0),

('Note_Category', 'ncr', 'Reservation', '', '', 0),
('Note_Category', 'ncf', 'PSG', '', '', 0),
('Note_Category', 'ncv', 'Visit', '', '', 0),
('Note_Category', 'ncg', 'Guest', '', '', 0),
('Note_Category', 'ncp', 'Patient', '', '', 0),

('Note_Type', 'ntxt', 'Text', '', '', 0),

('OOS_Codes', 'sb', 'Sewer Backup','','h',0),
('OOS_Codes', 'ar', 'Appliance Repair','','h',0),
('OOS_Codes', 'sr', 'Structural Repair','','h',0),
('OOS_Codes', 'cd', 'Access Blocked','','h',0),

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

('Pay_Gateway_Name', 'instamed', 'Instamed','','',0),
('Pay_Gateway_Name', 'vantiv', 'Worldpay','','',0),


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
('Payment_Status', 'rv', 'Reversed', '','',0),

('Pay_Type','ca','Cash','1','',0),
('Pay_Type','cc','Credit Card','2','',0),
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
('Phone_Type','xf','Fax','o','',50),
('Phone_Type','no','No Phone','i','', '100'),

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

('Referral_Form_Status', 'n', 'New','ui-icon ui-icon-mail-closed','',10),
('Referral_form_Status', 'ip', 'In-Process','ui-icon ui-icon-mail-open','',20),
('Referral_Form_Status', 'ac', 'Accepted','ui-icon ui-icon-check','',30),
('Referral_Form_Status', 'ar', 'Archived','ui-icon ui-icon-folder-open','',40),
('Referral_Form_Status', 'd', 'Deleted','ui-icon ui-icon-trash','',50),

('Reg_Agreement', 'en', 'English', '1','',0),

('registration', 'Sig_Card', 'Signature', 'y', 'm', 10),
('registration', 'Pamphlet', 'Pamphlet', 'y', 'm', 20),
('registration', 'Referral', 'Referral', 'y', 'm', 310),
('registration', 'Guest_Ident', 'Guest Id', 'y', 'm', 40),

('Resv_Conf', 'en', 'English', '2','',0),

('rel_type','chd','Child','par','',0),
('rel_type','par','Parent','chd','',0),
('rel_type','rltv','Relative','rltv','',0),
('rel_type','sib','Sibling','sib','',0),
('rel_type','sp','Partner','sp','',0),
('rel_type','frd','Friend','frd','',0),

('Resource_Status','a','Available','','',0),
('Resource_Status','oos','Out of Service','','',0),
('Resource_Status','un','Unavailable','','',0),

('Resource_Type','rmtroom','Remote Room','','',0),
('Resource_Type','room','Room','','',0),

('RibbonColors', '','None', '', '', 0),
('RibbonColors', 'hospital','Hospital', '', '', 1),

('RoomColors', '','None', '', '', 0),
('RoomColors', 'room','Room', '', '', 1),
('RoomColors', 'housekeeping','Housekeeping', '', '', 2),

('Role_Codes','10','Admin User','','',0),
('Role_Codes','100','Web User','','',0),
('Role_Codes','700','Guest','','',0),

('Room_Category','none','(none)','','',0),
('Room_Category','dh','House','','',0),
('Room_Category','gada','Hospital','','',0),
('Room_Category','jph','Private Host','','',0),
('Room_Category','nm','Motel','','',0),

('Room_Cleaning_Days', 'a', '7 Days', '7', 'ha',10),
('Room_Cleaning_Days', 'b', '14 Days', '14', 'ha',20),
('Room_Cleaning_Days', 'nz', 'Disabled', '0', 'ha',1000),

('Room_Group', 'Type', 'Room Type', 'Room_Type','',0),
('Room_Group', 'Category', 'Room Category', 'Room_Category','',0),
('Room_Group', 'Report_Category', 'Report Category', 'Room_Rpt_Cat','',0),

('Room_Rate_Adjustment', 'ra1', '10%', '-10','ca', '0'),


('Room_Rpt_Cat', '1', '1st Floor', '', 'h', 0),
('Room_Rpt_Cat', '2', '2nd Floor', '', 'h', 0),

('Room_Status', 'a', 'Clean', '','u',0),
('Room_Status', 'dty', 'Dirty', '','u',0),
('Room_Status', 'to', 'Turn Over', '','u',0),
('Room_Status', 'r', 'Ready', '', 'u', 0),

('Room_Type','r','Room','','',10),
('Room_Type','s','Suite','','',20),

('Salutation','fln','First & Last','','',0),
('Salutation','fno','First Name','','',0),
('Salutation','for','Formal','','',0),
('Salutation','mm','Retro-Mr. & Mrs.','','',0),

('Site_Mode', 'dev', 'Development','','',0),
('Site_Mode', 'demo', 'Demonstration','','',0),
('Site_Mode', 'live', 'Production','','',0),

('Special_Needs','c','Cancer','','d',0),
('Special_Needs','f','Dev. Challenged','','d',0),
('Special_Needs','z','Unknown','','d',1000),

('Staff_Note_Category', 'g', 'General', '', 'h', 0),

('Static_Room_Rate','rb','Regular Rate','10','',0),

('Sys_Config_Category', 'd', 'Donation','','',50),
('Sys_Config_Category', 'f', 'Financial','','',15),
('Sys_Config_Category', 'h', 'House','','',30),
('Sys_Config_Category', 'a', 'General','','',5),
('Sys_Config_Category', 'g', 'Guest','','',20),
('Sys_Config_Category', 'es', 'Email Server','','',60),
('Sys_Config_Category', 'fg', 'Payment Gateway','','',0),
('Sys_Config_Category', 'pr', 'Password Rules','','',70),
('Sys_Config_Category', 'sso', 'SAML SSO','','',80),
('Sys_Config_Category', 'c', 'Calendar','','',10),
('Sys_Config_Category', 'ha', 'House Email Addresses','','',32),
('Sys_Config_Category', 'p', 'Patient','','',25),
('Sys_Config_Category', 'hf', 'House Features','','', 28),
('Sys_Config_Category', 'ga', 'Google APIs', '', '', '80'),
('Sys_Config_Category', 'cm', 'Contact Manager (CRM)', '', '', '35'),

('Time_Zone', 'America/Chicago', 'Central','','',0),
('Time_Zone', 'America/New_York', 'Eastern','','',0),
('Time_Zone', 'America/Denver', 'Moutain Daylight (Denver)','','',0),
('Time_Zone', 'America/Phoenix', 'Moutain Standard (Phoenix)','','',0),
('Time_Zone', 'America/Los_Angeles', 'Pacific','','',0),

('Utilization_Category', 'uc1', 'Standard', '', 'h',0),

('validMemStatus','a','Active','','',0),
('validMemStatus','d','Deceased','','',0),
('validMemStatus','in','Inactive','','',0),

('Verify_User_Address','done','Verified','','',0),
('Verify_User_Address','y','Waiting for verification','','',0),

('Visit_Fee_Code', '1', 'Cleaning Fee','15','',0),
('Visit_Fee_Code', '2', 'No Fee','0','',0),

('Visit_Status','a','Checked In','','',0),
('Visit_Status','co','Checked Out','','',0),
('Visit_Status','cp','Room Rate Changed','','',0),
('Visit_Status','n','Room Changed','','',0),
('Visit_Status', '1', 'On Leave','','',0),
('Visit_Status', 'c', 'Cancelled','','',0),
('Visit_Status', 'p', 'Pending','','',0),


('Vol_Category','Vol_Type','Member Type','','',0),

('Vol_Rank','c','Chair','','',0),
('Vol_Rank','cc','Co-Chair','','',0),
('Vol_Rank','m','Member','','',0),


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

('Web_User_Actions', 'L', 'Login', '', '', '0'),
('Web_User_Actions', 'PS', 'Set Password', '', '', '0'),
('Web_User_Actions', 'PC', 'Password Change', '', '', '0'),
('Web_User_Actions', 'PL', 'Locked Out', '', '', '0'),
('Web_User_Actions', 'E', 'Password Expired', '', '', '0'),
('Web_User_Actions', 'LF', 'Login Failure', '', '', '0'),
('Web_User_Actions', 'LOI', 'Log out for inactivicy', '', '', '0');
-- ;


REPLACE INTO `lookups` (`Category`,`Code`,`Title`,`Use`,`Show`,`Type`,`Other`) VALUES
('FinAppStatus','a','Granted','y','y','',''),
('FinAppStatus','n','Not Granted','y','y','',''),

('ReservStatus','a','Confirmed','y','y','a','ui-icon-check'),
('ReservStatus','uc','Unconfirmed','y','y','a','ui-icon-help'),
('ReservStatus','w','Waitlist','y','y','a','ui-icon-arrowstop-1-e'),
('ReservStatus','c','Guest Canceled','y','y','c','ui-icon-cancel'),
('ReservStatus','td','Turned Away','y','y','c','ui-icon-arrowreturnthick-1-s'),
('ReservStatus','ns','No Show','y','y','c','ui-icon-alert'),
('ReservStatus','c1','Canceled 1','y','y','c','ui-icon-cancel'),
('ReservStatus','c2','Canceled 2','n','n','c','ui-icon-cancel'),
('ReservStatus','c3','Canceled 3','n','n','c','ui-icon-cancel'),
('ReservStatus','c4','Canceled 4','n','n','c','ui-icon-cancel'),
('ReservStatus','c5','Canceled 5','n','n','c','ui-icon-cancel'),
('ReservStatus','c6','Canceled 6','n','n','c','ui-icon-cancel'),
('ReservStatus','co','Checked Out','y','n','','ui-icon-extlink'),
('ReservStatus','s','Checked In','y','n','','ui-icon-circle-check');
-- ;


--
-- insert System configuration
--
REPLACE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`, `Show`) VALUES
('AcceptResvPaymt','false','b','h','', 'Accept payments at Reservation Comfirmation','', '1'),
('Auto_Email_Address','','ea','ha','','Notified for each batch of automatic emails','',1),
('BatchSettlementHour','03:00','s','fg','','Batch settlement time of day for auto-settlements','',0),
('BccAddress','','ea','g','','Any email addresses listed here (comma delimited) will get a BCC of any receipts mailed to valid guest email accounts.','',1),
('CalDateIncrement','1','i','c','','Number of weeks to increment Calendar view, auto = calViewWeeks','',1),
('CalExpandResources','true','b','c','','Initially expand room categories on the calendar','',1),
('CalRescColWidth','105px','s','c','','The width of the rooms column on the calendar page as percent of the overall width or pixels','',1),
('CalResourceGroupBy','Type','lu','c','','Calendar resource grouping parameter','Room_Group',1),
('CalViewWeeks','3','i','c','','Number of weeks showing in the calendar view','',1),
('CCAgentConf', 'false', 'b','h','','CC referral agent on reservation confirmation email','',1),
('CheckInTime','16','i','h','','Normal House Check in time of day in 24-hour format, hh','',1),
('CheckOutTime','10','i','h','','Normal House Checkout time of day in 24-hour format, hh','',1),
('ConcatVisitNotes','true','b','h','','Show notes combined from all previous visits when true','',1),
('ContactManager','','lu','hf','','Integrate an External CRM/Fund Raiser App','ExternalCrm',1),
('CoTod','false','b','hf','','Edit the time of day of a checkout','',1),
('county','true','b','h','','Include the County for addresses','',1),
('CssValidationService', 'https://jigsaw.w3.org/css-validator/validator?output=soap12&text=', 'url', 'a', '', 'CSS validator service', '',0),
('DefaultCalEventColor', '', 's', 'c', '', 'Default event ribbon color for the calendar', '',1),
('DefaultDays','21','i','h','','The Default number of following days for date range control','',1),
('DefaultPayType','cc','s','f','','Use the Resource Builder','',0),
('DefaultRegisterTab','0','lu','h','','Default active tab on register page','Default_Reg_Tab',1),
('DefaultVisitFee','1','s','h','','Use the Resource Builder','',0),
('DefCalEventTextColor', 'black', 's', 'c', '', 'Default calendar event ribbon text color', '',1),
('distCalculator', '', 'lu', 'hf', '', 'Distance calculator method', 'DistCalculator', '1'),
('DKIMdomain', '', 's', 'es', '', 'Domain name of sender (must match FromAddress and NoReplyAddr domains)', '',1),
('Doctor','true','b','hf','','Track doctors','',1),
('EmailType','','lu','es','','Email protocol','Email_Server',1),
('EmergContactFill','false','b','h','','Insist on Filling in the emergency contact','',1),
('EmergContactReserv', 'false', 'b', 'h', '', 'Collect Emergency Contact on Reservation','',1),
('EmptyExtendLimit','0','i','hf','','Extend visit (go on leave) default number of days','',1),
('Error_Report_Email', 'support@nonprofitsoftwarecorp.org', 's', 'a', '', 'Email for reporting server errors', '',0),
('Enforce2fa', 'false', 'b', 'pr', '', 'Force users to use Two factor authentication','',1),
('ExtendToday','0','i','h','','Extend the Check in day by this many hours into tomorrow','',1),
('ForceNamePrefix','false','b','h','','Force  name prefixes to be entered','',1),
('FromAddress','','ea','g','','House from address for guest emails','',1),
('fy_diff_Months','0','i','f','','Fiscal year difference months (12 - fiscal year start month)','',1),
('Guest_Register_Email','','ea','ha','','If present, a guest register is sent here once a day (also must edit the cron job)','',1),
('Guest_Track_Address','','ea','ha','','If present, these addresess receive all notices of check-in, out, room change, etc.','',1),
('GuestAddr','true','b','g','','Enable guest address','',1),
('HouseKeepingEmail','','ea','ha','','This address receives all room turn-over notices','',1),
('HouseKeepingSteps','1','lu','hf','','Number of steps to cleaning/preparing rooms for new guests','HouseKpgSteps',1),
('IncludeLastDay','false','b','h','','Include the departure day in room searches','',1),
('IncomeRated','true','b','hf','','Enable guest income chooser rate assistance','',1),
('InitResvStatus','a','lu','h','','Initial reservation status setting, confirmed or unconfirmed','Init_Reserv_Status',1),
('InsistCkinDemog','false','b','h','','Insist that user fill in the demographics on the check in page','',1),
('InsistCkinPayAmt','true','b','h','','Insist the user fills in the payment amount on checkin page','',1),
('InsistGuestBD', 'false', 'b', 'g', '', 'Insist on user filling in guest birthdates', '',1),
('InsistPatBD','true','b','p','','Insist on user filling in the patients birthdate','',1),
('InsuranceChooser','false','b','hf','','Enable insurance chooser','',1),
('InvoiceTerm','30','i','f','','Invoice payment terms in days','',1),
('KeyDeposit','true','b','hf','','Enable room or key deposit','',1),
('keyPath', '/etc/pki/hhkapp', 's', 'a', '', 'Filesystem path to SAML and DKIM keys', '',0),
('LangChooser','false','b','hf','','Enable member language chooser','',1),
('loginFeedURL', 'https://manage.hospitalityhousekeeper.net/tips/current', 'url', 'a', '', 'Feed for login pages', '','0'),
('MajorDonation','500','i','d','','Major donator trigger amount','',1),
('MaxAutoEmail','100','i','h','','Maximum number of automatic email messages to send per batch','',1),
('MaxDonate','100000','i','d','','Maximum amount amount for a single donation','',1),
('MaxExpected','260','i','h','','Maximum Expected days out for a visit','',1),
('MemberImageSizePx','75','i','h','','Guest image thumbnail size','',1),
('merchantReceipt', 'false', 'b', 'f', '', 'Print customer and merchant receipt on single page','',1),
('mode', 'demo', 'lu', 'a', '', 'Site Operational Mode', 'Site_Mode',1),
('NewsletterURL', 'https://nonprofitsoftwarecorp.us18.list-manage.com/subscribe?u=473b86d29e0f6f7ba7434f9a2&id=b986c7beaa', 'url', 'a','', 'Newsletter iframe URL', '', 0),
('NightsCounter','calYear','s','c','','Count nights by year (calYear) or by grand total','',1),
('NoReplyAddr','','ea','ha','','No reply email address','',1),
('noticetoCheckout', 'false', 'b', 'h', '', 'Show Notice to Checkout date box on visits','',1),
('NotificationAddress','','ea','f','','Gets financial data upload notifications','',1),
('OpenCheckin','true','b','h','','Allow walk-ups to check in','',1),
('passResetDays','365','lu','pr','','Number of days between automatic password resets','dayIncrements',1),
('PatientAddr','true','b','p','','Enable the patient address.','',1),
('PatientAsGuest','true','b','p','','House allows patients to stay as guests','',1),
('PayAtCkin','true','b','h','','Allow/Disallow payments at check-in time','',1),
('PaymentDisclaimer','The amount of your donation that is deductible for Federal tax purposes is limited to the excess of the amount of your donation over the value of the goods and services provided to you by (House Name).  Because the estimated value of the goods and services provided to you by (House Name) exceeds the value of your donation, no part of your donation is deductible for Federal tax purposes.','t','f','','Shows on receipts and statements','',1),
('PaymentGateway','','lu','fg','','Credit Payment Gateway','Pay_Gateway_Name',0),
('PayVFeeFirst','true','b','h','','Default check the visit fees payment checkbox','',1),
('PreviousNights','0','i','c','','Previous (to HHK) nights to add to nights counter','',1),
('printScale', '100', 'i','h','','% Default print scale','',1),
('PriorPasswords','0','i','pr','','Number of prior passwords user cannot reuse','',1),
('RateChangeAuth','false','b','h','','True = only authorized users can change the defailt room rate','',1),
('RateGlideExtend','0','i','hf','','(Deprecated) Number of days for the Room Rate Glide to time out after visit check-out','',0),
('receiptLogoFile','../conf/receiptlogo.png','url','f','','Path to the receipt logo file','',1),
('receiptLogoWidth','150','i','f','','in px','',1),
('ReferralAgent','true','b','hf','','Track referral agents/social workers','',1),
('referralFormEmail', '', 's', 'ha', '', 'Notify this address when a new referral form is submitted', '','1'),
('RegForm','3','i','h','',' Registration form style (1 or 2) Use 3 for signing regforms','',0),
('RegFormNoRm','false','b','h','','Do not show the room number on the registration form before check-in','',0),
('RegNoMinorSigLines', 'false', 'b', 'h', '', 'On Registrations, minors will not show up in the signature section', '', '1'),
('resourceURL','','url','a','','URL to HHK root','',0),
('rememberTwoFA','','lu','pr','','Number of days users can save a device and skip two factor authentication','dayIncrements',1),
('ReplyTo','','ea','g','','The reply to address for any email sent to guests.','',1),
('ResvEarlyArrDays','2','i','h','','Number of days before reservation to show check-in button on reservation chooser','',1),
('RibbonBottomColor', '', 'lu', 'c', '', 'Ribbon bottom-bar color source', 'RibbonColors', '1'),
('RibbonColor','hospital','lu','c','','Ribbon Background color source','RibbonColors',1),
('Room_Colors', '', 'lu', 'c','', 'Use Room Color or housekeeping status for Rooms column on calendar', 'RoomColors', '1'),
('RoomPriceModel','d','lu','h','','Room rate price model - Do not change!','Price_Model',0),
('RoomRateDefault','e','s','h','','Use the Resource Builder','',0),
('RoomsPerPatient','2','i','h','','Number of simultaneous rooms per patient allowed','',1),
('RoomOccCat', 'none', 'lu', 'c', '', 'Only include this Room Category in room occupancy percentage on calendar', 'Room_Category', '1'),
('Run_As_Test', 'false', 'b', 'a', '', 'Run As Test flag', '',0),
('keyPath', '/etc/pki/hhkapp', 's', 'a', '', 'Filesystem path to SAML and DKIM keys', '','0'),
('searchMRN', 'true', 'b', 'hf', '', 'Allow search by MRN', '',1),
('SessionTimeout','30','i','a','','Number of minutes until an idle session get automatically logged out, default 30','',1),
('ShoStaysCtr','true','b','c','','Show the stays counter on the House Calendar page','',1),
('showAddressReceipt', 'false', 'b', 'h', '', 'Show primary guest address on receipts', '','1'),
('ShowBirthDate','true','b','h','','Show birthdate for patients and guests','',1),
('ShowCreatedDate','true','b','h','','Show the Created Date in Register page tabs lists','',1),
('ShowDemographics','false','b','h','','Show demographics selectors on Check in and Reservation pages','',1),
('ShowDiagOnStmt', 'false', 'b', 'h', '', 'Show the patient diagnoses on the statements', '', '1'),
('ShowDiagTB','false','b','h','','Show the diagnosis textbox (in addition to the diagnosis selector)','',1),
('showCurrentGuestPhotos', 'false', 'b', 'hf', '', 'Show Guest Photos on Current Guests tab', '', '1'),
('UseDiagSearch', 'false', 'b', 'h', '', 'Use Autocomplete search in place of Diagnosis drop down', '', '1'),
('ShowGuestPhoto','true','b','hf','','Enable guest photos','',1),
('ShowLodgDates','true','b','h','','Show dates on lodging invoice lines','',1),
('ShowRateDetail','false','b','f','','Show Rate detail on statements','',1),
('ShowRoomOcc', 'false', 'b', 'c', '', 'Show current occupancy percentage on calendar','','1'),
('ShowTxPayType','false','b','h','','Always Show the Transfer pay type','',1),
('ShowUncfrmdStatusTab','false','b','h','','Show the Unconfirmed reservations tab on the House Register page','',1),
('ShowZeroDayStays','false','b','h','','Include 0-day stays and visits in Reports and Pages','',1),
('showRegEmptyFields', 'false', 'b', 'h', '', 'On Registrations, show empty fields', '', '1'),
('Show_Holidays', 'false', 'b', 'c', '', 'Indicate holidays on the calendar','', '1'),
('Show_Closed', 'false', 'b', 'c', '', 'Indicate closed days on the calendar','', '1'),
('sId','11','i','a','','House organization Id','',1),
('siteName','Hospitality HouseKeeper','s','a','','House or organization  name','',1),
('SMTP_Auth_Required','true','b','es','','SMTP Authorization required','',1),
('SMTP_Debug','0','i','es','','0 = off; 1; 2; 3;  4 = low level','',1),
('SMTP_Host','','s','es','','SMTP Host','',1),
('SMTP_Password','','op','es','','SMTP user password (Obfuscated)','',1),
('SMTP_Port','587','i','es','','','',1),
('SMTP_Secure','tls','s','es','','SMTP Security, normally tls','',1),
('SMTP_Username','','s','es','','','',1),
('SolicitBuffer','9','i','a','','Timeout in days after visit checkout before solicit report will show new guests','',1),
('SSL', 'true', 'b', 'a', '', 'Use SSL flag', '',0),
('StartGuestFeesYr','16','i','g','','For by-guest pricing, anyone younger does not get charged.','',1),
('StartYear','2010','i','a','','Start year for reports, etc.','',1),
('statementLogoFile','../conf/registrationLogo.png','url','f','','','',1),
('statementLogoWidth','220','i','f','','in px','',1),
('subsidyId','11','i','f','','Member Id to use for House Discount payment source','',1),
('TrackAuto','true','b','hf','','Track vehicles','',1),
('Training_URL', 'https://hospitalityhousekeeper.net/training/', 's', 'a', '', 'HHK Training site URL', '',0),
('Tutorial_URL', 'https://www.youtube.com/channel/UC_Sp1kHz_c0Zet0LrO91SbQ/videos/', 's', 'a', '', 'Tutorial YouTube page', '',0),
('tz','America/Chicago','lu','a','','House Time Zone','Time_Zone',1),
('UseDocumentUpload','true','b','hf','','Enable Document Uploads','',1),
('UseHouseWaive','true','b','hf','','Show the house waive checkbox on checkout','',1),
('UseIncidentReports','true','b','hf','','Enable the Incident Reports feature','',1),
('userInactiveDays','365','lu','pr','','Number of days of inactivity before user becomes disabled','dayIncrements',1),
('useOnlineReferral', 'false', 'b', 'hf','','Enable public online referrals', '', 1),
('UseRepeatResv', 'false', 'b', 'h', '','Allow repeating reservations','',0),
('UseWLnotes','false','b','hf','','Enable wait list notes feature on reservations','',1),
('UseCleaningBOdays', 'false', 'b', 'hf','', 'Set holidays as housekeeping black-out days','', '1'),
('vehicleReportEmail', '', 'ea', 'ha', '', 'Notified of Vehicle Report (configured in Job Scheduler)','', 1),
('VerifyHospDate','false','b','h','','Insist on hospital treatment date entry','',1),
('VisitExcessPaid','d','lu','h','','Default place for excess visit payments','ExcessPays',1),
('VisitFee','false','b','hf','','Enable the visit fee (cleaning fee) feature','',1),
('VisitFeeDelayDays','0','i','hf','','Number of days before cleaning fee is charged','',1),
('Volunteers','false','b','a','','Enable the HHK Volunteer Manager site','',0),
('Zip_Code','60115','s','a','','Organization zip code, used for distance calculations','',1),
('googleProjectID', 'helical-clock-316420', 's', 'ga', '', 'Google API Project ID', '',0),
('recaptchaApiKey', 'bTVWSFUyRXBQU3RHRTlCV0M4WkhGcnh6RC9tbTk5eXp1c3B1NU9JYm1zMVRTcytsemRJSjhtS2w5dnNkZWZKVw==', 's', 'ga', '', 'Google API Key for Recaptcha', '',0),
('recaptchaSiteKey', '6LemLyQbAAAAAKKaz91-FZCSI8cRs-l9DCYmEadO', 's', 'ga', '', 'Google API Site Key for Recaptcha', '',0);
-- ;

--
-- insert Labels
--
REPLACE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`) VALUES
('reservationTab','Confirmed Reservations','s','rg','','Default: Confirmed Reservations'),
('unconfirmedTab','UnConfirmed Reservations','s','rg','','Default: UnConfirmed Reservations'),
('waitlistTab','Wait Listed','s','rg','','Default: Wait Listed'),
('recentPayTab','Recent Payments','s','rg','','Default: Recent Payments'),
('rateTitle','Room Rate','s','rg','','Default: Room Rate'),
('onlineReferralTab', 'Referrals', 's', 'rg','','Default: Referrals'),
('onlineReferralTitle', 'Referral Form', 's', 'rg', '', 'Default: Referral Form'),

('notesLabel','Reservation Notes','s','rf','','Default: Reservation Notes'),
('waitlistNotesLabel','Waitlist Notes','s','rf','','Default: Waitlist Notes'),
('statusLabel','Reservation Status','s','rf','','Default: Reservation Status'),
('reservationChooserTitle','Reservation Chooser','s','rf','','Default: Reservation Chooser'),
('newButtonLabel','New Reservation','s','rf','','Default: New Reservation'),
('licensePlate','License Plate','s','rf','','Default: License Plate'),
('agreementTitle','Agreement','s','rf','','Default: Agreement'),
('Survey_Subject','Checkout Survey','s','rf','','Default: Checkout Survey'),
('VisitFeeConfirmLabel','Cleaning Fee:','s','rf','','Default: Cleaning Fee:'),
('specialNoteConfEmail', 'Special Note', 's', 'rf', '', 'Default: Special Note'),
('drivingdistancePrompt', 'Distance', 's', 'rf', '', 'Default: Distance'),

('noticeToCheckout', 'Notice to Checkout', 's', 'vi', '', 'Default: Notice to Checkout'),

('referralAgent','Referral Agent','s','h','','Default: Referral Agent'),
('diagnosis','Diagnosis','s','h','','Default: Diagnosis'),
('diagnosisDetail','Diagnosis Details','s','h','','Default: Diagnosis Details'),
('location','Unit','s','h','','Default: Unit'),
('hospital','Hospital','s','h','','Default: Hospital'),
('treatmentStart','Treatment Start','s','h','','Default: Treatment Start'),
('treatmentEnd','Treatment End','s','h','','Default: Treatment End'),
('roomNumber','Room','s','h','','Default: Room'),
('MRN', 'MRN', 's', 'h','',''),

('report','MMM D, YYYY','s','mf','','Default: MMM D, YYYY'),
('reportDay','ddd, MMM D YYYY','s','mf','','Default: ddd, MMM D YYYY'),
('dateTime','MMM D YYYY h:mm a','s','mf','','Default: MMM D YYYY h:mm a'),

('guestInstructions','Type in the guest name to check in.','s','ck','',''),

('amtTenderedPrompt','Amount Tendered','s','pc','',''),
('housePays','House pays difference','s','pc','',''),
('rtnTenderedPrompt','Return Amount','s','pc','',''),
('rtnDeposit', 'Deposit Refund', 's', 'pc','',''),
('RmFeesPledged', '	Room fees pledged to-date',	's', 'pc', '', ''),
('PayRmFees', 'Pay room fees', 's', 'pc', '', ''),
('RoomCharges',	'Room Charges',	's', 'pc', '', ''),
('Credit',	'Credit',	's', 'pc', '', ''),
('ExtraPayment', 'Extra Payment', 's', 'pc', '', ''),


('patient','Patient','s','mt','','Default: Patient'),
('guest','Guest','s','mt','','Default: Guest'),
('visitor','Guest','s','mt','','Default: Guest'),
('primaryGuest', 'Primary Guest', 's', 'mt', '', 'Default: Primary Guest'),
('primaryGuestAbrev', 'PG', 's', 'mt', '', 'Default: PG'),
('namePrefix', 'Prefix', 's', 'mt', '', 'Default: Prefix'),
('nickname', 'Nickname', 's', 'mt', '', 'Default: Nickname'),
('staff', 'Staff', 's', 'mt', '', 'Default: Staff'),


('reservationTab','Reservations','s','g','',''),
('reservationTitle','Reservation','s','g','',''),
('psgTab','Patient Support Group','s','g','',''),
('referralFormTitle','Referral Form', 's','g','',''),

('hospitalsTab','Hospital','s','r','',''),
('keyDepositLabel','Deposit','s','r','',''),

('datesChargesCaption','Visit Dates & Room Pledges','s','s','',''),
('rateHeader','Rate','s','s','','Default: Rate'),
('chargeHeader','Pledge','s','s','','Default: Pledge'),
('roomTotalLabel','Room Pledges Total','s','s','','Default: Room Pledges Total'),
('TotalLabel','Total Pledges','s','s','','Default: Total Pledges'),
('cleaningFeeLabel','Cleaning Fee','s','s','','Default: Cleaning Fee'),
('paymentsCaption','Guest Payments','s','s','','Default: Guest Payments'),
('paymentHeader','Payment','s','s','','Default: Payment'),
('paymentTotalLabel','Payment Total (Thank You!)','s','s','','Default: Payment Total (Thank You!)'),
('noPaymentsRecordedLabel','No Payments Recorded','s','s','','Default: No Payments Recorded'),
('zeroBalanceLabel','Current Balance','s','s','','Default: Current Balance'),
('balanceDueLabel','Current Balance Due','s','s','','Default: Current Balance Due'),
('guestCreditLabel','Current Guest Credit','s','s','','Default: Current Guest Credit'),
('psgLabel','Patient Support Group','s','s','','Default: Patient Support Group'),
('psgAbrev','PSG','s','s','','Default: PSG'),
('psgPlural','PSGs','s','s','','Default: PSGs'),
('houseSubsidy','House Discount','s','s','','Default: House Discount'),
('thirdParty','3rd Party','s','s','','Default: 3rd Party'),
('roomFund','Over-Payments','s','s','','Default: Over-Payments'),
('lodgingMOA','Lodging MOA','s','s','','Default: Lodging MOA');
-- ;

REPLACE INTO `operating_schedules` (`idDay`, `Day`, `Start_Date`, `Open_At`, `Closed_At`, `Non_Cleaning`, `Closed`, `Timestamp`) VALUES
(1, '0', current_timestamp, '09:00:00', '21:00:00', '0', '0', current_timestamp),
(2, '1', current_timestamp, '09:00:00', '21:00:00', '0', '0', current_timestamp),
(3, '2', current_timestamp, '09:00:00', '21:00:00', '0', '0', current_timestamp),
(4, '3', current_timestamp, '09:00:00', '21:00:00', '0', '0', current_timestamp),
(5, '4', current_timestamp, '09:00:00', '21:00:00', '0', '0', current_timestamp),
(6, '5', current_timestamp, '09:00:00', '21:00:00', '0', '0', current_timestamp),
(7, '6', current_timestamp, '09:00:00', '21:00:00', '0', '0', current_timestamp);


REPLACE INTO `template_tag` VALUES
(6,'c','Guest Name','${GuestName}',''),
(7,'c','Expected Arrival','${ExpectedArrival}',''),
(8,'c','Expected Departure','${ExpectedDeparture}',''),
(9,'c','Date Today','${DateToday}',''),
(10,'c','Nights','${Nites}',''),
(11,'c','Total Amount','${Amount}',''),
(12,'c','Notes','${Notes}',''),
(13,'c','Visit Fee Notice','${VisitFeeNotice}',''),
(14,'s','First Name','${FirstName}',''),
(15,'s','Last Name','${LastName}',''),
(16,'s','Name Suffix','${NameSuffix}',''),
(17,'s','Name Prefix','${NamePrefix}',''),
(18,'c','Guest Address Line 1','${GuestAddr1}',''),
(19,'c','Guest Address Line 2','${GuestAddr2}',''),
(20,'c','Guest City','${GuestCity}',''),
(21,'c','Guest State','${GuestState}',''),
(22,'c','Guest Zip Code','${GuestZip}',''),
(23,'c','Guest Phone','${GuestPhone}',''),
(24,'c','Room','${Room}',''),
(25,'c','Room Rate Title','${RoomRateTitle}',''),
(26,'c','Room Rate (pre tax)','${RoomRateAmount}',''),
(27,'c','Rate Adjustment Percent','${RateAdjust}',''),
(28,'c','Nightly Rate (pre tax)','${NightlyRate}','');
-- ;

replace into `item` (`idItem`, `Description`) values
(1, 'Lodging'),
(2, 'Cleaning Fee'),
(3, 'Deposit'),
(4, 'Deposit Refund'),
(5, 'Carried From Inv. #'),
(6, 'Discount'),
(7, 'Reversal'),
(8, 'Lodging Donation'),
(9, ''),
(10, 'Lodging MOA'),
(11, 'Waive');
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
(10, 3),
(11, 6);
-- ;



REPLACE INTO `payment_method` (`idPayment_method`, `Method_Name`) VALUES
('1', 'Cash'),
('2', 'Charge'),
('3', 'Check'),
('4', 'Charge'),
('5', 'Transfer');
-- ;

replace INTO `invoice_line_type` (`id`, `Description`, `Order_Position`) VALUES
(1,'item recurring',2),
(2,'tax',6),
(3,'due invoice',1),
(4,'hold',8),
(5,'waive',5),
(6,'item one-time',3),
(7, 'reimburse', 4);
-- ;

Replace into `location` (`idLocation`, `Status`) VALUES
(1, 'a');
-- ;

REPLACE INTO `insurance_type` (`idInsurance_type`, `Title`, `Is_Primary`, `List_Order`) VALUES
(1, 'Primary', '1', '10'),
(2, 'Private', '0', '20');
-- ;

REPLACE INTO `insurance` (`idInsurance`, `idInsuranceType`, `Title`, `Opens_Type`) VALUES
(1, 1, 'Aetna', ''),
(2, 1, 'Blue Cross', ''),
(3, 1, 'Private Insurance', 'p'),
(4, 2, 'Cigna', ''),
(5, 2, 'Kaser', ''),
(6, 1, 'No Ins.', '');
-- ;


--
-- insert users
--
REPLACE into `name` (idName, Name_Last, Name_First, Member_Type, Member_Status, Record_Member, Record_Company, Company) values
(-1, 'admin', 'the', 'ai', 'a', 1, 0, ''),
(10, 'User', 'NPSC', 'ai', 'a', 1, 0, ''),
(11, '', '', 'np', 'a', 0, 1, 'Hospitality House');
-- ;

REPLACE INTO `w_auth` (`idName`,`Role_Id`,`Organization_Id`,`Policy_id`,`Updated_By`,`Last_Updated`,`User_Name`,`Status`) VALUES
(-1,'10','p',0,'admin',now(),'admin','a'),
(10,'10','p',0,'admin',now(),'npscuser','a');
-- ;



REPLACE INTO `w_users`
(`idName`,`User_Name`,`Enc_PW`, `Chg_PW`, `pass_rules`, `Status`,`Certificate`,`Cookie`,`Session`,`Ip`,`Verify_Address`,`Last_Login`,`Hash_PW`,`Updated_By`,`Last_Updated`,`Timestamp`)
VALUES
(-1,'admin','539e17171312c324d3c23908f85f3149',false, false,'a','','','','','done',NULL,'','',NULL,now()),
(10,'npscuser','VEFhRldOWVFqVmZ5bjhENVZvd29ldz09',false, false,'a','','','','','done',NULL,'','',NULL,now());
-- ;


--
-- Table `w_groups`
--
REPLACE INTO `w_groups`
(`Group_Code`,`Title`,`Description`,`Default_Access_Level`,`Max_Level`,`Min_Access_Level`,`Cookie_Restricted`,`Password_Policy`)
VALUES
('db','Maintenance','Configure metadata.','','','','\0',''),
('dm','Donation Management','Donation Management','','','','\0',''),
('dna','Donors (No Amounts)','View lists of donors but without donation amounts','','','','\0',''),
('g','Guest Operations','Guest Operations, basic access to guest tracking site','','','','\0',''),
('gr','Guest Reports','Guest Reports','','','','\0',''),
('ga','Guest Admin','Guest Administration level access to guest tracking site','','','','\0',''),
('mm','Member Management','Member Management, basic access to admin site.','','','','\0',''),
('pub','Public','Public','','','','\0','');
-- ;

REPLACE INTO `neon_lists` (`Method`, `List_Name`, `List_Item`, `HHK_Lookup`) VALUES
('account/listIndividualTypes', 'individualTypes', 'individualType', 'Vol_Type'),
('donation/listFunds', 'funds', 'fund', 'Fund'),
('common/listTenders', 'tenders', 'tender', 'Pay_Type'),
('common/listCreditCardTypes', 'creditCardTypes', 'creditCardType', 'Charge_Cards'),
('account/listRelationTypes', 'relationTypes', 'relationType', 'Patient_Rel_Type');
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



REPLACE into `transaction_type` (`idtransaction_type`,`Title`,`Effect`,`Code`) values
(1, 'Sale', '', 's'),
(2, 'Void', '', 'vs'),
(3, 'Return', '', 'r'),
(4, 'Void Return', '', 'vr'),
(5, 'Reverse', '', 'rv'),
(6, 'undoRetrn', '', 'ur'),
(7, 'ZeroAuth', '', 'za');
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
-- Dumping data for table map_relations
--
REPLACE INTO `map_relations`(`idmap_relations`,`PG_Patient`,`Guest_Patient`,`Patient_PG`,`Guest_PG`)VALUES
('1', 'sp', 'chd', 'sp', 'chd'),('2', 'sp', 'sib', 'sp', 'rltv'),('3', 'sp', 'par', 'sp', 'rltv'),('4', 'sp', 'sp', 'sp', 'sp'),('5', 'sib', 'chd', 'sib', 'rltv'),('6', 'sib', 'sib', 'sib', 'sib'),
('7', 'sib', 'par', 'sib', 'par'),('8', 'sib', 'sp', 'sib', 'rltv'),('9', 'chd', 'chd', 'par', 'sib'),('10', 'chd', 'sib', 'par', 'rltv'),('11', 'chd', 'par', 'par', 'rltv'),('12', 'chd', 'sp', 'par', 'par'),
('13', 'par', 'chd', 'chd', 'rltv'),('14', 'par', 'sib', 'chd', 'chd'),('15', 'par', 'par', 'chd', 'sp'),('15', 'par', 'par', 'chd', 'sp'),('16', 'par', 'sp', 'chd', 'rltv'),('17', 'slf', 'sp', 'slf', 'sp'),
('18', 'slf', 'sib', 'slf', 'sib'),('19', 'slf', 'chd', 'slf', 'chd'),('20', 'slf', 'par', 'slf', 'par'),('21', 'sp', 'rltv', 'sp', 'rltv'),('22', 'sib', 'rltv', 'sib', 'rltv'),('23', 'chd', 'rltv', 'par', 'rltv'),
('24', 'par', 'rltv', 'chd', 'rltv'),('25', 'sp', 'frd', 'sp', 'frd'),('26', 'sib', 'frd', 'seb', 'frd'),('27', 'chd', 'frd', 'par', 'frd'),('28', 'par', 'frd', 'chd', 'frd'),('29', 'rltv', 'sp', 'rltv', 'rltv'),
('30', 'rltv', 'sib', 'rltv', 'rltv'),('31', 'rltv', 'chd', 'rltv', 'rltv'),('32', 'rltv', 'par', 'rltv', 'rltv'),('33', 'frd', 'sp', 'frd', 'frd'),('34', 'frd', 'sib', 'frd', 'frd'),('35', 'frd', 'chd', 'frd', 'frd'),
('36', 'frd', 'par', 'frd', 'frd'),('37', 'pc', 'sp', 'pc', 'pc'),('38', 'pc', 'sib', 'pc', 'pc'),('39', 'pc', 'chd', 'pc', 'pc'),('40', 'pc', 'par', 'pc', 'pc'),('41', 'sp', 'pc', 'sp', 'pc'),
('42', 'sib', 'pc', 'sib', 'pc'),('43', 'chd', 'pc', 'par', 'pc'),('44', 'par', 'pc', 'chd', 'pc'),('45', 'slf', 'frd', 'slf', 'frd'),('46', 'slf', 'rltv', 'slf', 'rltv'),
('47', 'slf', 'slf', 'slf', 'slf'),('48', 'chd', 'slf', 'par', 'par'),('49', 'frd', 'slf', 'frd', 'frd'),('50', 'par', 'slf', 'chd', 'chd'),('51', 'sib', 'slf', 'sib', 'sib'),('52', 'sp', 'slf', 'sp', 'sp'),
('53', 'rltv', 'slf', 'rltv', 'rltv'),('54', 'pc', 'slf', 'slf', 'pc');
-- ;


--
-- Hospitals
--
REPLACE INTO `hospital` (`idHospital`,`Title`,`Type`,`Status`) values
('1','County Hospital', 'h', 'a'),
('2','City Hospital', 'h', 'a');
-- ;


--
-- Dumping data for table `rate_breakpoint`
--
Replace INTO `rate_breakpoint` (`idrate_breakpoint`, `Household_Size`, `Rate_Category`, `Breakpoint`, `Timestamp`) VALUES
	(1, 1, 'a', 959, '2022-06-16 10:38:43'),
	(2, 1, 'b', 1724, '2022-06-16 10:40:02'),
	(3, 1, 'c', 2584, '2022-06-16 10:40:02'),
	(4, 1, 'd', 2584, '2022-06-16 10:40:02'),
	(5, 2, 'a', 1294, '2022-06-16 10:48:14'),
	(6, 2, 'b', 2327, '2022-06-16 10:48:14'),
	(7, 2, 'c', 3449, '2022-06-16 10:48:14'),
	(8, 2, 'd', 3449, '2022-06-16 10:48:14'),
	(9, 3, 'a', 1953, '2022-06-16 10:48:14'),
	(10, 3, 'b', 3091, '2022-06-16 10:48:14'),
	(11, 3, 'c', 4393, '2022-06-16 10:48:14'),
	(12, 3, 'd', 4393, '2022-06-16 10:48:14'),
	(13, 4, 'a', 2355, '2022-06-16 10:48:14'),
	(14, 4, 'b', 3728, '2022-06-16 10:48:14'),
	(15, 4, 'c', 5298, '2022-06-16 10:48:14'),
	(16, 4, 'd', 5298, '2022-06-16 10:48:14'),
	(17, 5, 'a', 2757, '2022-06-16 10:48:14'),
	(18, 5, 'b', 4365, '2022-06-16 10:48:14'),
	(19, 5, 'c', 6202, '2022-06-16 10:48:14'),
	(20, 5, 'd', 6202, '2022-06-16 10:48:14'),
	(21, 6, 'a', 3159, '2022-06-16 10:48:14'),
	(22, 6, 'b', 5001, '2022-06-16 10:48:14'),
	(23, 6, 'c', 7107, '2022-06-16 10:48:14'),
	(24, 6, 'd', 7107, '2022-06-16 10:48:14'),
	(25, 7, 'a', 3561, '2022-06-16 10:48:14'),
	(26, 7, 'b', 5637, '2022-06-16 10:48:14'),
	(27, 7, 'c', 7716, '2022-06-16 10:48:14'),
	(28, 7, 'd', 7716, '2022-06-16 10:48:14'),
	(29, 8, 'a', 3983, '2022-06-16 10:48:14'),
	(30, 8, 'b', 6274, '2022-06-16 10:48:14'),
	(31, 8, 'c', 8587, '2022-06-16 10:48:14'),
	(32, 8, 'd', 8587, '2022-06-16 10:48:14');
-- ;


--
-- Dumping data for table `web_sites`
--
REPLACE INTO `web_sites`
(`idweb_sites`,`Site_Code`,`Description`,`Relative_Address`,`Required_Group_Code`,`Path_To_CSS`,`Path_To_JS`,`Last_Updated`,`Updated_By`,`Default_Page`,`Index_Page`,`HTTP_Host`)
 VALUES
(1,'a','Admin','admin/','mm','ui-icon ui-icon-gear','',now(),'admin','NameSch.php','index.php',''),
(2,'h','House','house/','g,ga','ui-icon ui-icon-person','',now(),'admin','register.php','index.php',''),
(4,'r','Root','/','pub','','',now(),'admin','','','');
-- ;



--
-- Dumping data for table `page`
--
LOCK TABLES `page` WRITE;
REPLACE INTO `page` (`idPage`,`File_Name`,`Login_Page_Id`,`Title`,`Product_Code`,`Hide`,`Web_Site`,`Menu_Parent`,`Menu_Position`,`Type`) VALUES
(1,'index.php',0,'Welcome','',0,'r','','','p'),(2,'index.php',0,'','',0,'a','','','p'),(3,'NameEdit.php',2,'Edit Members','',0,'a','','','p'),(5,'KeyStats.php',2,'Key Stats','',0,'a','67','g','p'),(6,'Misc.php',2,'Miscellaneous','',0,'a','34','a','p'),(7,'PageEdit.php',2,'Edit Pages','',0,'a','34','e','p'),(9,'CategoryEdit.php',2,'Edit Categories','',0,'a','34','d','p'),(10,'VolListing.php',2,'Web Users','',0,'a','35','c','p'),
(11,'campaignEdit.php',2,'Edit Campaigns','',0,'a','34','c','p'),(12,'campaignReport.php',2,'Campaigns','',0,'a','32','d','p'),(14,'directory.php',2,'Directory','',0,'a','32','a','p'),(15,'donate.php',0,'','',0,'a','','','s'),(16,'donationReport.php',2,'Donations','',0,'a','32','b','p'),(18,'liveGetCamp.php',0,'','',0,'a','','','s'),(19,'liveNameSearch.php',0,'','',0,'a','','','s'),
(20,'ws_Report.php',0,'','',0,'a','','','s'),(21,'ws_gen.php',0,'','',0,'a','','','s'),
(31,'index.php',0,'','',0,'h','','','p'),(32,'_directory.php',2,'Reports','',0,'a','0','e','p'),(33,'categoryReport.php',2,'Categories','',0,'a','32','f','p'),(34,'_Misc.php',2,'DB Maintenance','',0,'a','0','k','p'),(35,'_VolListing.php',2,'Web Users','',0,'a','0','j','p'),(36,'NameEdit_Donations',0,'','',0,'a','','','c'),(37,'NameEdit_Maint',0,'','',0,'a','','','c'),(39,'ws_gen_Maint',0,'','',0,'a','','','c'),
(46,'guestadmin',0,'','',0,'h','','','c'),(47,'guestaccess',0,'','',0,'v','','','c'),(49,'recent.php',2,'Recent Changes','',0,'a','67','r','p'),
(50,'nonReportables.php',2,'Non-Reportables','',0,'a','67','v','p'),(51,'donorReport.php',2,'Donors','',0,'a','32','c','p'),(55,'MemEdit.php',0,'','',0,'v','','none','p'),(56,'Cat_Donor',0,'','',0,'a','','','c'),(57,'anomalies.php',2,'Anomaly report','',0,'a','67','k','p'),(59,'ws_admin.php',0,'','',0,'h','','','s'),
(60,'guestaccess',0,'','',0,'a','','','c'),(62,'roleSearch.php',0,'','',0,'h','','','s'),(65,'timeReport.php',2,'Time Reports','',0,'a','32','u','p'),(66,'NameSch.php',2,'Members','',0,'a','0','d','p'),(67,'_KeyStats.php',2,'Key Stats','',0,'a','0','g','p'),(69,'_index.php?log=lo',0,'Log Out','',0,'a','0','z','p'),
(70,'_index.php?log=lo',0,'Log Out','',0,'v','0','z','p'),(71,'_index.php?log=lo',0,'Log Out','',0,'h','0','z','p'),(72,'CheckIn.php',31,'Check In','',0,'h','0','f','p'),(74,'register.php',31,'House Register','',0,'h','79','b','p'),(75,'ws_resv.php',0,'','',0,'h','','','s'),(76,'ws_ckin.php',0,'','',0,'h','','','s'),(79,'_register.php',31,'House','',0,'h','0','d','p'),
(81,'ResourceBuilder.php',31,'Resource Builder','',0,'h','79','l','p'),(82,'ws_resc.php',0,'','',0,'h','','','s'),(83,'RoomUtilization.php',31,'Room Report','',0,'h','102','e','p'),(84,'memberManagement',0,'','',0,'h','','','c'),(88,'AuthGroupEdit.php',2,'Edit Authorization','',0,'a','34','j','p'),(89,'Configure.php',2,'Site Configuration','',0,'a','34','g','p'),
(92,'GuestDemog.php',31,'Missing Demographics','',0,'h','102','f','p'),(93,'GuestEdit.php',31,'Guest Edit','',0,'h','0','j','p'),(94,'ShowRegForm.php',31,'Registration Form','',0,'h','','','p'),(95,'Reserve.php',31,'Reservation','',0,'h','0','e','p'),(96,'CheckedIn.php',31,'','',0,'h','','','p'),(99,'PaymentResult.php',31,'Payment Result','',0,'h','','','p'),
(100,'ShowStatement.php',31,'Guest Statement','',0,'h','','','p'),(101,'RoomStatus.php',31,'Housekeeping','',0,'h','79','p','p'),(102,'GuestReport.php',31,'Reports','',0,'h','0','h','p'),(104,'ReservReport.php',31,'Reservations Report','',0,'h','102','b','p'),(105,'PaymentTx.php',2,'Credit Transactions Report','',0,'a','32','v','p'),(106,'Duplicates.php',2,'Duplicates','',0,'a','32','o','p'),(107,'PSGReport.php',31,'People Reports','',0,'h','102','k','p'),(109,'PaymentReport.php',31,'Payment Report','',0,'h','102','m','p'),
(110,'VisitInterval.php',31,'Visit Interval Report','',0,'h','102','c','p'),(111,'GuestView.php',31,'Guests & Vehicles','',0,'h','79','v','p'),(113,'DRaHospReport.php',31,'Doctors, Hospitals','',0,'h','102','l','p'),(114,'ShowInvoice.php',31,'Show Invoice','',0,'h','','','p'),(115,'InvoiceReport.php',31,'Invoice Report','',0,'h','102','n','p'),(116,'ShowHsKpg.php',31,'Housekeeping','',0,'h','','','p'),(117,'PrtRegForm.php',31,'Print Registration Forms','',0,'h','','','p'),(118,'occDemo.php',31,'Guest Demographics','',0,'h','102','g','p'),(119,'ItemReport.php',31,'Item Report','',0,'h','102','s','p'),
(120,'AccessLog.php',2,'User Access Log','',0,'a','35','d','p'),(121,'GuestTransfer.php',31,'Guest Transfer','',1,'h','79','x','p'),(122,'NewGuest.php',31,'New Guests','',0,'h','102','i','p'),(123,'PrtWaitList.php',31,'Wait Listing','',0,'h','','','p'),(126,'DailyReport.php',31,'Daily Report','',0,'h','102','p','p'),(127,'Help.php',31,'Help','',1,'h','71','f','p'),(128,'ws_calendar.php',31,'','',0,'h','','','s'),(129,'ws_update.php',2,'','',0,'a','','','s'),
(130,'DiagnosisBuilder.php',31,'Diagnosis Builder','',1,'h','79','n','p'),(131,'CheckingIn.php',31,'Checking In','',0,'h','','','p'),
(132,'IncmStmt.php',31,'Income Statement','',0,'h','102','t','p'),(133,'ws_forms.php',31,'','',0,'h','','','s'),(134,'showReferral.php',31,'Referral Form','',0,'h','','','p'),(135,'GuestReferral.php',31,'Guest Referral','',0,'h','','','p'),(136,'ws_reportFilter.php',31,'','',0,'h','','','s'),(137,'RecentActivity.php',31,'Recent Activity','',0,'h','102','w','p');
-- ;

UNLOCK TABLES;

--
-- Dumping data for table `page_securitygroup`
--
LOCK TABLES `page_securitygroup` WRITE;
REPLACE INTO `page_securitygroup` (`idPage`,`Group_Code`) VALUES
(1,'pub'),(2,'pub'),(3,'mm'),(4,'mm'),(5,'mm'),(6,'db'),(7,'db'),(8,'mm'),(9,'db'),(10,'mm'),(11,'db'),(12,'dm'),(14,'mm'),(15,'dm'),(16,'dm'),
(18,'mm'),(19,'mm'),(20,'dm'),(21,'g'),(21,'ga'),(21,'mm'),(23,'pub'),(26,'pub'),(27,'pub'),(28,'pub'),(29,'v'),(31,'pub'),(32,'mm'),(33,'mm'),
(34,'db'),(35,'mm'),(36,'dm'),(37,'db'),(39,'db'),(46,'ga'),(47,'g'),(49,'mm'),(50,'mm'),(51,'dna'),(52,'dm'),(56,'dna'),(57,'mm'),(59,'g'),(59,'ga'),(59, 'mm'),(59, 'gr'),
(60,'g'),(62,'g'),(62,'ga'),(65,'mm'),(66,'mm'),(67,'mm'),(69,'pub'),(70,'pub'),(71,'pub'),(72,'g'),(72,'ga'),(74,'g'),(74,'ga'),(75,'g'),(75,'ga'),
(76,'g'),(76,'ga'),(79,'g'),(79,'ga'),(81,'ga'),(82,'g'),(82,'ga'),(83,'ga'),(84,'g'),(84,'ga'),(88,'db'),(89,'db'),(92,'ga'),(93,'g'),(93,'ga'),(94,'g'),(94,'ga'),
(95,'g'),(95,'ga'),(96,'g'),(96,'ga'),(99,'g'),(99,'ga'),(100,'g'),(100,'ga'),(101,'g'),(101,'ga'),(102,'ga'),(104,'ga'),(105,'db'),(106,'mm'),(107,'ga'),(109,'ga'),
(110,'ga'),(111,'g'),(111,'ga'),(113,'ga'),(114,'g'),(114,'ga'),(115,'ga'),(116,'g'),(116,'ga'),(117,'g'),(117,'ga'),(118,'ga'),(119,'ga'),(120,'mm'),(121,'ga'),
(122,'ga'),(123,'g'),(123,'ga'),(126,'ga'),(127,'g'),(127,'ga'),(128,'g'),(128,'ga'),(129,'db'),(130,'ga'),(131,'g'),(131,'ga'), (132,'ga'),(133,'pub'),(134,'pub'),(135,'g'),(135,'ga'),(136,'ga'),(136,'gr'),(136,'g'),(137,'ga'),(137,'gr');
-- ;
UNLOCK TABLES;

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
-- Dumping data for table `document`
--
REPLACE INTO `document` VALUES
(1,'Registration Form','','form','html','','','en',NULL,'<p style=\"margin-bottom:10px;\">The (House Name) is a not-for-profit healthcare hospitality house. The Guest House is <span style=\"font-style: italic;\">strictly a lodging facility for referred patients that are actively receiving care at our partner institutions and their families/friends.</span></p>\r\n<ul style=\"list-style-type:disc;margin-left: 20px;\">\r\n    <li><span style=\"font-weight: bold;\">All guests must register at the Front Desk</span></li>\r\n    <li><span style=\"font-weight: bold;\">Smoking is strictly prohibited</span></li>\r\n    <li><span style=\"font-weight: bold;\">Staff must have access to room</span> to perform regular cleaning and maintenance. Rooms must be kept orderly to ensure that they are cleaned properly.</li>\r\n    <li><span style=\"font-weight: bold;\">In case of an emergency,</span> call ###.</li>\r\n    <li><span style=\"font-weight: bold;\">Pets are prohibited.</span> If a pet is found in a guest room, staff will ask that it be removed and may ask guest to leave the facility.</li>\r\n    <li>Do not burn candles, incense or any other flammable objects in the rooms.</li>\r\n    <li><span style=\"font-weight: bold;\">Food must be stored properly</span> in sealed containers or in the refrigerator. Dispose of food properly at time of check out.</li>\r\n    <li><span style=\"font-weight: bold;\">Do not remove anything from the rooms.</span>  Everything has been generously donated to us and is for the comfort of all our guests.  If you find an item missing, please contact our office so that we can replace it before the next guest.</li>\r\n    <li><span style=\"font-weight: bold;\">Do not try to make any repairs yourself.</span> Please contact the House Manager or Front Desk for any problems with appliances, electrical outlets, or plumbing.</li>\r\n    <li>The House strives to provide a supportive, welcoming community for its guests; <span style=\"font-weight: bold;\">please help us by being respectful of all staff, volunteers, fellow guests, and residents.</span></li>\r\n    <li><span style=\"font-weight: bold;\">Check out time is 10 AM.</span> Drop off your key(s) at the Front Desk.</li>\r\n</ul>\r\n<p style=\"margin-top:10px;\">Failure to follow any or all of these policies or to abuse the privilege of staying at the House in anyway can result in the forfeiture of the family&rsquo;s stay. Guests are responsible to communicate any issues or problems directly to (staff).</p>\r\n<p style=\"margin-top:10px;\"><span style=\"font-weight: bold;\">Disclaimer:</span>  I have executed this release willingly and understand that by signing this release. I give up any right I may have to sue or make any claim or demand on my behalf or on the behalf of any family member for any injuries incurred during the course of residency at the House.  I understand and intend that this release cover all injuries, even if such injuries are a result of the negligence of the (House) or any person associated with the House.  The authorization and release constitutes the entire agreement between the House and myself regarding the subjects addressed in this document.  The House reserves the right to inspect any room at any time.</p>\r\n<p style=\"font-weight: bold;margin-top:10px; font-style: italic;\">I/We have read, understand, and agree to all the conditions of this agreement that I/we received today. By signing this guest registration form I/WE agree to abide by the rules and regulations of the House and will communicate this to the other members of my party.</p>\r\n','','','a','2019-10-12 14:57:39','','patch','2019-10-12 19:57:39'),
(2,'Reservation Confirmation','','form','html','','en','en',NULL,'<p>Dear ${GuestName}:</p><p>Thank you for your reservation. This is a confirmation for the following dates: ${ExpectedArrival} until ${ExpectedDeparture} for ${Nites} nights for an estimated $${Amount}.</p><p>${VisitFeeNotice}</p><p>Should your plans change, please let us know as soon as possible so that we can serve others in need accommodations.</p><h4>Please enter the facility at:</h4><blockquote><p>(address)</p><p>(City, State, Zip)</p></blockquote><p><b><br></b></p><p><b>Check-in:</b> 4pm-12am</p><p><b>Check-out:</b> Before 10am.</p><h4><br></h4><h4>Parking</h4><p>(Parking Instructions)</p><h4><br></h4><h4>Before You Arrive</h4><p>(Arrival Instructions)</p><p>Should you have any questions or comments or need to change your reservation, please call our office at (phone).</p><p>We look forward to seeing you,</p><p>Hospitality House Staff</p>${Notes}<br>','','','a','2019-10-12 14:57:39','','patch','2019-10-12 19:57:39');
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
