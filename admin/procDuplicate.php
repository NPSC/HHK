// <?php
// /**
//  * procDuplicate.php
//  *
//  * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
//  * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
//  * @license   MIT
//  * @link      https://github.com/NPSC/HHK
//  */
// require_once ("AdminIncludes.php");

// require_once ('functions' . DS . 'DuplicateManager.php');

// $wInit = new webInit();

// $pageTitle = $wInit->pageTitle;
// $testVersion = $wInit->testVersion;

// // get session instance
// $uS = Session::getInstance();

// $menuMarkup = $wInit->generatePageMenu();

// $uname = $uS->username;
// $dupId = 0;
// $keepId = 0;

//  addslashesextended($_POST);
// /*
//  * called with get parameters id=x, load that id.
//  */
// if (isset($_GET["id"])) {
//     $idRaw = filter_var($_GET["id"], FILTER_SANITIZE_NUMBER_INT);
//     $dupId = intval($idRaw, 10);
// }

// if (isset($_POST["hdnDupId"])) {
//     $idRaw = filter_var($_POST["hdnDupId"], FILTER_SANITIZE_NUMBER_INT);
//     $dupId = intval($idRaw, 10);
// }


// if ($dupId == 0) {
//     header('Location: NameEdit.php');
// }



// // Member Search
// if (isset($_POST["textLink"])) {
//     $inpt = $_POST["textLink"];
//     $idRaw = filter_var($inpt, FILTER_SANITIZE_NUMBER_INT);
//     $keepId = intval($idRaw, 10);
//     if (isset($_POST["selectLink"])) {
//         $idRaw = filter_var($_POST["selectLink"], FILTER_SANITIZE_NUMBER_INT);
//         $keepId = intval($idRaw, 10);
//     }

// }

// // Instantiate the alert message control
// $alertMsg = new alertMessage("divAlert1");


// /*
//  * This is the main SAVE submit button.  It checks for a change in any data field
//  * and updates the database accordingly.
//  */
// if (isset($_POST["btnSubmit"])) {

//     if (isset($_POST["txtId"])) {
//         $idRaw = filter_var($_POST["txtId"], FILTER_SANITIZE_NUMBER_INT);
//         $keepId = intval($idRaw, 10);
//     }

//     $pErr = updateDupOriginal($uname, $dupId, $keepId);
//     //$id = $pErr->theId;
//     if ($pErr->errorOccured) {
//         // errors
//         $alertMsg->set_Context(alertMessage::Alert);
//         $alertMsg->set_Text($pErr->errMessage);
//         $resultMessage = $alertMsg->createMarkup();
//     } else {
//         // success
//         $alertMsg->set_Context(alertMessage::Success);
//         $alertMsg->set_Text($pErr->errMessage);
//         $resultMessage = $alertMsg->createMarkup();
//     }
// }





// //
// // Read out the Duplicate record.
// //
// $dupName = new nameClass($dbcon, $dupId);

// $basisArray = readGenLookups($dbcon, "Member_Basis");
// $basisLookup = array();
// if ($dupName->get_companyRcrd()) {
//     // exclude any individual options (specified in "Substitute")
//     foreach ($basisArray as $item) {
//         if (strtolower($item["Substitute"]) == "o") {
//             $basisLookup[] = $item;
//         }
//     }
// } else if ($dupId != 0) {
//     // individual
//     foreach ($basisArray as $item) {
//         if (strtolower($item["Substitute"]) == "i") {
//             $basisLookup[] = $item;
//         }
//     }
// } else {
//     $basisLookup = $basisArray;
// }

// $dmbrTypeOpt = doOptionsMkup($basisLookup, $dupName->get_type(), false);

// // Build company-specific controls
// $emps = array();
// $numDupEmplMU = "Employees";
// if ($dupName->get_companyRcrd()) {
//     $emps = getEmployeeData($dbcon, $dupName->get_idName());

//     //$shoDupCareOfEmployee = getEmployees($emps, false, true);

//     $shoDupEmployee = getEmployees($emps, false, true);

//     if (count($emps) == 1) {
//         $numDupEmplMU = count($emps) . " Employee";
//     } else {
//         $numDupEmplMU = count($emps) . " Employees";
//     }
// }



// // Read ouut the Keeper record
// $keepName = new nameClass($dbcon, $keepId);
// if ($keepId > 0) {
//     $statusOpt = doLookups($dbcon, "Mem_Status", $keepName->get_status(), false);
//     $prefixOpt = doLookups($dbcon, "Name_Prefix", $keepName->get_prefix());
//     $suffixOpt = doLookups($dbcon, "Name_Suffix", $keepName->get_suffix());

//     $dbo = new Dbo('');

//     // Volunteer info
//     $arrParams = array("ii", $dupId, $keepId);
//     $query = "select gcat.Description as Category, gcod.Description as Type, grank.Description as Role,
// gs.Description as Status, v.Vol_Begin as Begin, v.Vol_End as End, v.Vol_Notes as Notes
// from name_volunteer2 v left join gen_lookups gs on gs.Table_Name = 'Vol_Status' and gs.Code = v.Vol_Status
// left join gen_lookups gcat on gcat.Table_Name = 'Vol_Category' and gcat.Code = v.Vol_Category
// left join gen_lookups gcod on gcod.Table_Name = v.Vol_Category and gcod.Code = v.Vol_Code
// left join gen_lookups grank on grank.Table_Name = 'Vol_Rank' and grank.Code = v.Vol_Rank
// where v.idName = ? and concat(v.Vol_Category, v.Vol_Code) not in
// (select concat(Vol_Category, Vol_Code) from name_volunteer2 where idName = ? )";
//     $markup = $dbo->markupQueryResults($query, $arrParams);
//     if ($markup != "") {
//         $volTableMarkup = "<div id='volDiv' style='margin-top: 15px;'>
//                         <input type='checkbox' id='cbVol' name='cbVol' title='Check to move to Original member'/>
//                         <label for='cbVol' title='Check to move to Original member'> Volunteer groups that the duplicate has but the original does not have.</label><table>" . $markup . "</table></div>";
//     }


//     // Donation Info
//     $arrParams = array("iii", $dupId, $dupId, $dupId);
//     $query = "Select Donor_Id as Donor, Assoc_Id as Associate, Care_Of_Id as `Care/Of`, Campaign_Code as Campaign,
// Date_Entered as `Date`, Amount
// from vdonation_view where Donor_Id = ? or Assoc_Id = ? or Care_Of_Id = ?
// order by Date_Entered desc";
//     $markup = $dbo->markupQueryResults($query, $arrParams);
//     if ($markup != "") {
//         $donTableMarkup = "<div id='donDiv' style='margin-top: 15px;'>
//                         <input type='checkbox' id='cbDon' name='cbDon' title='Check to move to Original member'/>
//                         <label for='cbDon' title='Check to move to Original member'> Donations that the duplicate has but the original does not.</label><table>" . $markup . "</table></div>";
//     }

//     if ($dupName->get_companyRcrd()) {
//         // Employee
//         $arrParams = array("i", $dupId);
//         $query = "select n.idName as `Id`, n.name_Last_First as `Name` from name n where n.Company_id = ?";
//         $markup = $dbo->markupQueryResults($query, $arrParams);
//         if ($markup != "") {
//             $empTableMarkup = "<div id='empDiv' style='margin-top: 15px;'><input type='checkbox' id='cbEmp' name='cbEmp' title='Check to move to Original member'/>";
//             $empTableMarkup .= "<label for='cbEmp' title='Check to move to Original member'> Employees that the duplicate has but the original does not have.</label>
//                 <table>" . $markup . "</table></div>";
//         }
//     }

//     if ($dupName->get_memberRcrd()) {
//         // Company
//         $arrParams = array("ii", $dupId, $keepId);
//         $query = "select n.Company_Id as `Company Id`, n.Company from name n where n.idName=?
// and n.Company_Id <> 0 and n.Company_Id not in (select Company_Id from name where idName = ?)";
//         $markup = $dbo->markupQueryResults($query, $arrParams);
//         if ($markup != "") {
//             $orgTableMarkup = "<div id='orgDiv' style='margin-top: 15px;'><input type='checkbox' id='cbCompany' name='cbCompany' title='Check to move to Original member'/><label for='cbCompany' title='Check to move to Original member'> Organizations that the duplicate has but the original does not have.</label>
//                 <table>" . $markup . "</table></div>";
//         }


//         // Partner Info
//         $arrParams = array("ii", $dupId, $dupId);
//         $query = "select n.idName as Id, g.Description as `Relation`, n.Name_Last_First as `Name`
// from name n left join relationship r on (n.idName = r.idName and r.Target_Id = ?) or (n.idName = r.Target_Id and r.idName=?)
// left join gen_lookups g on g.Table_Name = 'rel_type' and g.Code = r.Relation_Type
// where r.Relation_Type='sp'";
//         $markup = $dbo->markupQueryResults($query, $arrParams);
//         if ($markup != "") {
//             $partnerTableMarkup = "<div id='partnerDiv' style='margin-top: 15px;'><input type='checkbox' id='cbpartner' name='cbpartner' title='Check to move to Original member'/><label for='cbpartner' title='Check to move to Original member'> A Partner that the duplicate has but the original does not have.</label><table>" .
//                     $markup . "</table></div>";
//         }

//         // Parent/Child
//         $arrParams = array("ii", $dupId, $dupId);
//         $query = "select n.idName as Id,
// case when n.idName = r.idName then 'Child' else 'Parent' end as `Relation`, n.Name_Last_First as `Name`
// from name n left join relationship r on (n.idName = r.idName and r.Target_Id = ?) or (n.idName = r.Target_Id and r.idName=?)
// where r.Relation_Type='par'";
//         $markup = $dbo->markupQueryResults($query, $arrParams);
//         if ($markup != "") {
//             $childTableMarkup = "<div id='childDiv' style='margin-top: 15px;'><input type='checkbox' id='cbChild' name='cbChild' title='Check to move to Original member'/><label for='cbChild' title='Check to move to Original member'> Parents or Children that the duplicate has but the original does not have.</label><table>" .
//                     $markup . "</table></div>";
//         }

//         // Sibling
//         $arrParams = array("ii", $dupId, $keepId);
//         $query = "select n.idName as Id, g.Description as `Relation`, n.Name_Last_First as `Name` , r.Group_Code
// from name n left join relationship r on (n.idName = r.idName)
// left join gen_lookups g on g.Table_Name = 'rel_type' and g.Code = r.Relation_Type
// where r.Relation_Type='sib' and
// r.Group_Code in (Select Group_Code from relationship where idName = ? and Relation_Type = 'sib')
// and r.Group_Code not in (Select Group_Code from relationship where idName = ? and Relation_Type = 'sib')";
//         $markup = $dbo->markupQueryResults($query, $arrParams);
//         if ($markup != "") {
//             $sibTableMarkup = "<div id='sibDiv' style='margin-top: 15px;'><input type='checkbox' id='cbSib' name='cbSib' title='Check to move to Original member'/><label for='cbSeb' title='Check to move to Original member'> Siblings That the duplicate has but the original does not have.</label><table>"
//                     . $markup . "</table></div>";
//         }

//         // Unspecified Relations
//         $arrParams = array("ii", $dupId, $keepId);
//         $query = "select n.idName as Id, g.Description as `Relation`, n.Name_Last_First as `Name` , r.Group_Code
// from name n left join relationship r on (n.idName = r.idName)
// left join gen_lookups g on g.Table_Name = 'rel_type' and g.Code = r.Relation_Type
// where r.Relation_Type='rltv' and
// r.Group_Code in (Select Group_Code from relationship where idName = ? and Relation_Type = 'rltv')
// and r.Group_Code not in (Select Group_Code from relationship where idName = ? and Relation_Type = 'rltv')";
//         $markup = $dbo->markupQueryResults($query, $arrParams);
//         if ($markup != "") {
//             $rltvTableMarkup = "<div id='rltvDiv' style='margin-top: 15px;'><input type='checkbox' id='cbrltv' name='cbrltv' title='Check to move to Original member'/><label for='cbrltv' title='Check to move to Original member'> Unspecified Relatives that the duplicate has but the original does not have.</label><table>"
//                     . $markup . "</table></div>";
//         }
//     }

//     // Web access
// //    $arrParams = array("ii", $dupId, $keepId);
// //    $query = "Select w.idName as Id, n.Name_Last_First as Name, w.User_Name, Last_Login
// //from w_users w join name n on w.idName = n.idName
// //where w.idName = ? or w.idName = ?
// //and w.Status = 'a' order by w.Last_Login desc";
// //    $markup = $dbo->markupQueryResults($query, $arrParams);
// //    if ($markup != "") {
// //        $webTableMarkup = "<div id='webDiv' style='margin-top: 15px;'><input type='checkbox' id='cbWeb' name='cbWeb' title='Check to move to Original member'/><label for='cbWeb' title='Check to move to Original member'> Web user accounts.</label><table>"
// //           . $markup . "</table></div>";
// //    }
//     // Build company-specific controls
//     $emps = array();
//     $numEmplMU = "Employees";
//     if ($keepName->get_companyRcrd()) {
//         $emps = getEmployeeData($dbcon, $keepName->get_idName());

//         //$shoCareOfEmployee = getEmployees($emps, false, true);

//         $shoEmployee = getEmployees($emps, false, true);

//         if (count($emps) == 1) {
//             $numEmplMU = count($emps) . " Employee";
//         } else {
//             $numEmplMU = count($emps) . " Employees";
//         }
//     }
// }
// ?>
<!DOCTYPE html>
<!-- <html> -->
<!--     <head> -->
<!--         <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"> -->
        <title><?php echo $pageTitle; ?></title>
// <?php echo TOP_NAV_CSS; ?>
<!--         <link href="css/default.css" rel="stylesheet" type="text/css" /> -->
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
        <link href="<?php echo JQ_DT_CSS; ?>" rel="stylesheet" type="text/css" />
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
<!--         <script type="text/javascript"> -->
//             function changeMemberBasis(sc, id) {
//                 "use strict";
//                 if (sc) {
                    if (sc.value === '<?php echo MemType::Indivual; ?>') {
//                         $('.indiv').css("display", "table-cell");
//                         $('.comp').css("display", "none");
//                         $('.hhk-home').css('display', "table-cell");
//                     } else {
//                         $('.indiv').css("display", "none");
//                         $('.comp').css("display", "table-cell");
//                         $('.hhk-home').css('display', "none");
//                         if (id != 0) {
//                             var tbs = $("#divFuncTabs").tabs("length");
//                             $("#divFuncTabs").tabs("option", "disabled", [tbs - 2]);
//                         }
//                     }
//                 }
//             }
//             $(document).ready(function () {
//                 "use strict";
//                 var isOrg, dupId, keepId;

//                 dupId = $('input#dtxtId').val();
//                 keepId = $('input#txtId').val();

//                 // Main form submit button dialog form for disabling page during POST
//                 $("#submit").dialog({
//                     autoOpen: false,
//                     resizable: false,
//                     width: 300,
//                     modal: true
//                 });
//                 // Main form submit button.  Disable page during POST
//                 $('#btnSubmit').click(function () {
//                     $('#submit').dialog("option", "title", "<h1><img name='busyImage' src='images/busy.gif'/> Saving... </h1>");
//                     $('#submit').dialog('open');
//                 });
                if ($('#dselMbrType').val() == '<?php echo MemType::Indivual; ?>') {
//                     isOrg = false;
//                     $('.indiv').css("display", "table-cell");
//                     $('.comp').css("display", "none");
//                     $('.hhk-home').css('display', "table-cell");
//                 } else {
//                     isOrg = true;
//                     $('.indiv').css("display", "none");
//                     $('.comp').css("display", "table-cell");
//                     $('.hhk-home').css('display', "none");
//                 }
//                 // Select an member for a link target
//                 $('#selectLink').change(function () {
//                     // IE thinks removing children is a change...
//                     if (this.value && this.value != '') {
//                         $('#schForm').submit();
//                         //var getCBtn = document.getElementById('fetchMember');
//                         //getCBtn.click();
//                     }
//                 });
//                 $('#textLink').keyup(function () {
//                     var mm, linkCode;
//                     mm = $(this).val();

//                     if (isOrg) {
//                         linkCode = 'co';
//                     } else {
//                         linkCode = 'ind';
//                     }

//                     if (linkCode != '' && mm.length > 2) {
//                         getNames($(this), 'selectLink', linkCode, dupId);
//                     }
//                 });
//                 if (keepId > 0) {
//                     $('#keeperDiv').css("display", "block");
//                 } else {
//                     $('#keeperDiv').css("display", "none");
//                 }
//                 changeMemberBasis();
//             });
        </script>
<!--         <script type="text/javascript" src="../js/common.js"></script> -->
<!--     </head> -->
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
<!--         <div class="topNavigation"></div> -->
<!--         <div id="contentDiv"> -->
// <?php echo $menuMarkup; ?>
            <div id="duplicateDiv" style="background-color: #F7F90F; padding: 7px;" >
<!--                 <h3>Process Duplicate Member</h3> -->
                <div style="margin-bottom: 15px;">
<!--                     <table> -->
<!--                         <tr> -->
<!--                             <th>Id</th> -->
<!--                             <th class="indiv">Full Name</th> -->
<!--                             <th>Company</th> -->
<!--                             <th>Status</th> -->
<!--                             <th>Basis</th> -->
                            <th class="comp"><?php echo $numDupEmplMU; ?></th>
<!--                         </tr><tr> -->
                            <td style="vertical-align:middle; text-align: center;" ><input type="text" style="border:none; background-color:transparent;" id="dtxtId" name="dtxtId" readonly="readonly" size ="4" value="<?php echo $dupId . ' '; ?>" /></td>
                            <td class="indiv"><input type="text" class="ui-widget" readonly="readonly" size="25" value="<?php echo $dupName->get_fullName(); ?>" /></td>
                            <td><input id="dtxtCoName" type="text" size="26" class="ui-widget"  readonly="readonly" value="<?php echo $dupName->get_company(); ?>" /></td>
                            <td style="vertical-align:middle;"><select id="dselStatus" ><option value="u" selected="selected">Duplicate</option></select></td>
                            <td style="vertical-align:middle;" ><select id="dselMbrType" ><?php echo $dmbrTypeOpt ?></select></td>
                            <td class="comp" style="vertical-align:middle;"><select id="shoDupempl" name="shoDupempl[]" multiple="multiple" size="3" title="Click a name to go to that record" ><?php echo $shoDupEmployee; ?></select></td>                        </tr>
<!--                     </table> -->
<!--                 </div> -->
<!--                 <form id="schForm" action="procDuplicate.php" method="post"> -->
<!--                     <p>Select an existing member that is the original of the above duplicate.</p> -->
<!--                     Member Search: <input type="text" id="textLink" name="textLink" size="10" title="Enter at least 3 characters to invoke search" /> -->
                    <select id="selectLink" name="selectLink" style="width: 210px;"></select>
                    <input name="hdnDupId"  type="hidden" value="<?php echo $dupId; ?>" />
<!--                 </form> -->
<!--             </div> -->
            <div id="keeperDiv" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail" style="margin-top: 15px; font-size: 90%;">
                <?php echo $resultMessage ?>
<!--                 <form action="procDuplicate.php" method="post"  id="form1" name="form1" > -->
<!--                     <h3>Selected Original Member</h3> -->
<!--                     <table> -->
<!--                         <tr> -->
                            <th style="width:40px;"><span>Id</span></th>
<!--                             <th class="indiv">Prefix</th> -->
<!--                             <th class="indiv">First Name</th> -->
<!--                             <th class="indiv">Middle</th> -->
<!--                             <th class="indiv">Last Name</th> -->
<!--                             <th class="indiv">Suffix</th> -->
<!--                             <th class="indiv">Nickname</th> -->
<!--                             <th>Company</th> -->
<!--                             <th>Status</th> -->
<!--                             <th>Basis</th> -->
                            <th class="comp"><?php echo $numEmplMU; ?></th>
<!--                         </tr><tr> -->
                            <td style="vertical-align:middle; text-align: center;" ><input type="text" style="border:none; background-color:transparent;" name="txtId" id="txtId" readonly="readonly" size ="4" value="<?php echo $keepId; ?>" /></td>
                            <td class="indiv" style="vertical-align:middle;"><select id="selPrefix" name="selPrefix"><?php echo $prefixOpt ?></select></td>
                            <td class="indiv"><input name="txtFirstName" type="text" value="<?php echo $keepName->get_firstname(); ?>" onkeypress="return noenter(event)" /></td>
                            <td class="indiv"><input name="txtMiddleName" size="5" type="text" value="<?php echo $keepName->get_middleName(); ?>" onkeypress="return noenter(event)" /></td>
                            <td class="indiv"><input name="txtLastName" type="text" value="<?php echo $keepName->get_lastName(); ?>" onkeypress="return noenter(event)" /></td>
                            <td class="indiv" style="vertical-align:middle;"><select id="selSuffix" name="selSuffix" ><?php echo $suffixOpt ?></select></td>
                            <td class="indiv"><input name="txtNickname" size="10" type="text" value="<?php echo $keepName->get_nickName(); ?>" onkeypress="return noenter(event)" /></td>
                            <td class="tdBox"><input id="txtCoName" name="txtCoName" type="text" size="26" readonly="readonly" value="<?php echo $keepName->get_company(); ?>" /></td>
                            <td class="tdBox" style="vertical-align:middle;"><select id="selStatus" name="selStatus" disabled="disabled"><?php echo $statusOpt ?></select></td>
                            <td class="tdBox" style="vertical-align:middle;" ><select id="selMbrType" name="selMbrType"><?php echo $dmbrTypeOpt ?></select></td>
                            <td class="comp"><select id="shoempl" name="shoempl[]" multiple="multiple" size="3" title="Click a name to go to that record" ><?php echo $shoEmployee; ?></select></td>
<!--                         </tr> -->
<!--                     </table> -->
                    <?php echo $orgTableMarkup; ?>
                    <?php echo $empTableMarkup; ?>
                    <?php echo $partnerTableMarkup; ?>
                    <?php echo $childTableMarkup; ?>
                    <?php echo $sibTableMarkup; ?>
                    <?php echo $rltvTableMarkup; ?>
                    <?php echo $volTableMarkup; ?>
                    <?php echo $donTableMarkup; ?>
<!--                     <div id="divSubmitButtons"> -->
<!--                         <input type="reset" name="btnReset" value="Reset" />&nbsp;&nbsp; -->
<!--                         <input type="submit" value="Save" name="btnSubmit" id="btnSubmit" /> -->
<!--                     </div> -->
<!--                     <div id="submit"></div> -->
                    <input name="hdnDupId"  type="hidden" value="<?php echo $dupId; ?>" />
<!--                 </form> -->
<!--             </div> -->
            <div style="clear: both;"></div>
            <a href="NameEdit.php?id=<?php echo $dupId; ?>">Cancel</a>
<!--         </div> -->
<!--     </body> -->
<!-- </html> -->
