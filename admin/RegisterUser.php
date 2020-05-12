<?php
/**
 * RegisterUser.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


require ("AdminIncludes.php");

require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'WebSecRS.php');
require ('functions' . DS . 'RegUserManager.php');
require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . 'Addresses.php');

require (CLASSES . "fbUserClass.php");

require (CLASSES . "AuditLog.php");
//require (THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php');
require (THIRD_PARTY . 'PHPMailer/v6/src/PHPMailer.php');
require (THIRD_PARTY . 'PHPMailer/v6/src/SMTP.php');
require (THIRD_PARTY . 'PHPMailer/v6/src/Exception.php');

$wInit = new webInit();
$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();

$uname = $uS->username;

$resps = array();
addslashesextended($_POST);
$actionTakenTable = "";

/*
 * Check Post...
 */
if (isset($_POST["btnSave"])) {
    $n = 1;
    $actionTakenTable = "<table>";

    // each waiting person -
    while (isset($_POST["txtfb$n"])) {
    	
        if (isset($_POST["cb$n"])) {
        	
            $resps = manageRegistration($dbh, $n, $uname);

            if (isset($resps["success"]) && $resps["success"] != '') {
                // success
                $sAlert = "Success: " . $resps["success"];
            } else if (isset($resps["error"]) && $resps["error"] != '') {
                // error
            	$sAlert = "Error: " . $resps["error"];
            } else {
            	$sAlert = '<span class="ui-state-highlight">Uh-oh, a member must already be defined for this web user: ' . $_POST["txtfb$n"] . '</span>';
            }
            
            $actionTakenTable .= "<tr><td>" . $sAlert . "</td></tr>";
        }

        $n++;
    }
    $actionTakenTable .= "</table>";
}

//******************************************************************
//
// load the to be registered rows
//
$whereStr = " Status = 'w' order by Timestamp asc ";
$fbx = new fbUserClass("");
$res = $fbx->selectRows($dbh, $whereStr);

if (!is_null($res)) {

    $n = 1;

    $header = "<th style='width:55px;'>Select</th><th>Name</th><th>Phone</th><th>Email</th><th>User Name</th><th>Delete</th></tr>";
    $toBeRegisteredRows = array("fb" => "", "web" => "");
    foreach ($res as $r) {

        $toBeRegisteredRows[$r["Access_Code"]] .= "<tr class='trClass$n'>" . $header . "<tr class='ui-state-highlight trClass$n'>";
        $toBeRegisteredRows[$r["Access_Code"]] .= "<td><input type='checkbox' name='cb$n' id='cb$n' /> Save</td>";
        $toBeRegisteredRows[$r["Access_Code"]] .= "<td>" . $r["fb_First_Name"] . " " . $r["fb_Last_Name"] . "</td>";
        $toBeRegisteredRows[$r["Access_Code"]] .= "<td>" . $r["fb_Phone"] . "</td>
        <td>" . $r["fb_Email"] . "</td>
        <input type='hidden' name='txtfb$n' id='txtfb$n' value='" . strtolower($r["fb_id"]) . "' />
        <td>" . $r["PIFH_Username"] . "</td>
        <td colspan='2' style='text-align:center;'><input class='cbDel' type='checkbox' name='$n' id='cbDel_$n' /></td>
        </tr>";

            //Load possible matches
        $orem = "";
        if ($r["fb_Email"] != "") {
            $orem = " or LOWER(Preferred_Email)='" . strtolower($r["fb_Email"]) . "'";
        }

        $orphone = "";
        if ($r["fb_Phone"] != "") {
            $orphone = " or Preferred_Phone='" . $r["fb_Phone"] . "'";
        }

        $query = "SELECT v.Id, v.Fullname, v.Address_1, v.Address_2, v.City, v.StateProvince, v.PostalCode,v.Preferred_Phone, v.Preferred_Email,
        ifnull(u.User_Name,'') as User_Name, ifnull(u.Status,'') as Status, ifnull(a.Role_Id, '') as Role_Id
        FROM vmember_listing v left join w_users u on v.Id = u.idName left join w_auth a on v.Id = a.idName
        WHERE v.MemberStatus='a' and ((LOWER(Name_Last) = '" . strtolower($r["fb_Last_Name"]) . "'and (LOWER(Name_First)='" . strtolower($r["fb_First_Name"]) . "' or LOWER(Name_Nickname)='" . strtolower($r["fb_First_Name"]) . "'))
         " . $orem . $orphone . ");";

        $res2 = $dbh->query($query);
        $toBeRegisteredRows[$r["Access_Code"]] .= "<tr class='trClass$n'><td colspan='6'><div style='margin-left:35px;'><table id='tb$n'><tr><th style='width:55px;'>Select</th><th>Name</th><th>Address</th><th>City, State</th><th>Phone</th><th>Email</th><th>User Name</th></tr>";

        while ($r2 = $res2->fetch(PDO::FETCH_ASSOC)) {
            $toBeRegisteredRows[$r["Access_Code"]] .= "<tr>
            <td><input type='radio' class='rbChooser' name='b$n' id='r" . $r2["Id"] . "' value='" . $r2["Id"] . "' /><a href='NameEdit.php?id=" . $r2["Id"] . "'>" . $r2["Id"] . "</a></td>
            <td>" . $r2["Fullname"] . "&nbsp;</td>
            <td>" . $r2["Address_1"] . " " . $r2["Address_2"] . "&nbsp;</td>";
            if ($r2["City"] != "" || $r2["StateProvince"] != "") {
                $toBeRegisteredRows[$r["Access_Code"]] .= "<td>" . $r2["City"] . ", " . $r2["StateProvince"] . "&nbsp;</td>";
            } else {
                $toBeRegisteredRows[$r["Access_Code"]] .= "<td>&nbsp;</td>";
            }

            $toBeRegisteredRows[$r["Access_Code"]] .= "<td>" . $r2["Preferred_Phone"] . "&nbsp;</td>
            <td class='tdBox'>" . $r2["Preferred_Email"] . "</td>";
            if ($r["Status"] != "a") {
                $toBeRegisteredRows[$r["Access_Code"]] .= "<td>" . $r2["User_Name"] . "</td></tr>";
            } else {
                $alreadyAlert = new alertMessage("already" . $r2["Id"]);
                $alreadyAlert->set_Context(alertMessage::Alert);
                $toBeRegisteredRows[$r["Access_Code"]] .= "<td>" . $alreadyAlert->createMarkup("Registered") . "</td></tr>";
            }
        }

        $toBeRegisteredRows[$r["Access_Code"]] .= "</table></div></td></tr>";
        $toBeRegisteredRows[$r["Access_Code"]] .= "<tr class='trClass$n clsVolBottom'><td colspan='6'></td></tr>";

        $n++;
    }
}


if ($toBeRegisteredRows["fb"] == "") {
    $toBeRegisteredRows["fb"] = "<tr><td colspan=7>There are no people waiting for Facebook registration</td></tr>";
}
if ($toBeRegisteredRows["web"] == "") {
    $toBeRegisteredRows["web"] = "<tr><td colspan=7>There are no people waiting for Web registration</td></tr>";
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo DEFAULT_CSS; ?>
        <?php echo JQ_UI_CSS; ?>
        <?php echo NOTY_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        
        <script type="text/javascript">
            $(document).ready(function() {
                $('.rbChooser').change(function() {
                    $(this).each(function(index) {
                        if (this.checked) {
                            var cb = '#c'+this.name;
                            var jcb = $(cb);
                            if (jcb)
                                jcb.prop("checked", true);
                        }
                    });
                });
                $('input.cbDel').change( function() {

                    if ($(this).prop('checked')) {

                        if (!confirm("Delete this record?")) {
                            $(this).prop('checked', false);
                            return;
                        }

                        var fbid = 'txtfb' + $(this).attr('name');
                        var fb = $('#' + fbid);
                        deleteFBX(fb.val(), this);
                    }
                });
                function  deleteFBX(fbid, cbCtrl) {
                    var names;
                    $.get( "liveNameSearch.php",
                    {cmd: "del", fid: fbid },
                    function(data){
                        if (data != null && data != "") {
                            names = $.parseJSON(data);
                            if (names[0])
                                names = names[0];

                            if (names && names.success) {
                                // all ok
                                $('tr.trClass' +$(cbCtrl).attr('name')).css({ 'display':'none' });
                            } else if (names.error) {
                                alert("Web Server error: " + names.error);
                            } else {
                                alert("Empty data shell returned from the web server");
                            }
                        } else {
                            alert('Nothing was returned from the web server');

                        }
                    }
                    );
                }
            });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div id="tabs-1" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail">

                <form  action="RegisterUser.php" method="post"  id="form1" name="form1" >
                    <table>
                        <tr><th colspan="6" >Web</th></tr>
                        <?php echo $toBeRegisteredRows["web"]; ?>
                    </table>
                    <p style="text-align:right;">
                        <input type="submit" value="Save" name="btnSave" />
                    </p>
                </form>
                <?php echo $actionTakenTable ?>
            </div>
        </div>
    </body>
</html>
