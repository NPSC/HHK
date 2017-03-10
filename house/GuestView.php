<?php
/**
 * GuestView.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require_once ("homeIncludes.php");

require_once (CLASSES . 'CreateMarkupFromDB.php');
require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';



try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;
$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$wInit->sessionLoadGuestLkUps();


$resultMessage = "";


// Guest listing
$stmt = $dbh->query("select * from vguest_view");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$guests = array();
foreach ($rows as $r) {

    $g = array(
        'Last Name' => $r['Last Name'],
        'First Name' => $r['First Name'],
        'Room' => $r['Room'],
        'Phone' => $r['Phone'],
        'Arrival' => date('M j, Y', strtotime($r['Arrival'])),
        'Expected Departure' =>  date('M j, Y', strtotime($r['Expected Departure']))
    );

    if ($uS->EmptyExtendLimit > 0) {
        if ($r['On_Leave'] > 0) {
            $g['On Leave'] = 'On Leave';
        } else {
            $g['On Leave'] = '';
        }
    }

    if ($uS->TrackAuto) {
        $g['Make'] = $r['Make'];
        $g['Model'] = $r['Model'];
        $g['Color'] = $r['Color'];
        $g['State Reg.'] = $r['State Reg.'];
        $g['License Plate'] = $r['License Plate'];
    }

    $guests[] = $g;

}

$guestTable = CreateMarkupFromDB::generateHTML_Table($guests, 'tblList');

$vehicleTable = '';

if ($uS->TrackAuto) {
    // Vehicle listing
    $vstmt = $dbh->query("SELECT
    ifnull((case when n.Name_Suffix = '' then n.Name_Last else concat(n.Name_Last, ' ', g.`Description`) end), '') as `Last Name`,
    ifnull(n.Name_First, '') as `First Name`,
    ifnull(rm.Title, '')as `Room`,
    ifnull(np.Phone_Num, '') as `Phone`,
    ifnull(r.Actual_Arrival, r.Expected_Arrival) as `Arrival`,
    case when r.Expected_Departure < now() then now() else r.Expected_Departure end as `Expected Departure`,
	l.Title as `Status`,
    ifnull(v.Make, '') as `Make`,
    ifnull(v.Model, '') as `Model`,
    ifnull(v.Color, '') as `Color`,
    ifnull(v.State_Reg, '') as `State Reg.`,
    ifnull(v.License_Number, '') as `License Plate`
from
	vehicle v join reservation r on v.idRegistration = r.idRegistration
        left join
    `name` n ON n.idName = r.idGuest
        left join
    name_phone np ON n.idName = np.idName
        and n.Preferred_Phone = np.Phone_Code
        left join
    resource rm ON r.idResource = rm.idResource
        left join
    gen_lookups g on g.`Table_Name` = 'Name_Suffix' and g.`Code` = n.Name_Suffix
		left join
	lookups l on l.Category = 'ReservStatus' and l.`Code` = r.`Status`
where r.`Status` in ('a', 's', 'uc')
order by l.Title, `Arrival`");

    $vrows = $vstmt->fetchAll(PDO::FETCH_ASSOC);

    for ($i = 0; $i < count($vrows); $i++) {

        $vrows[$i]['Arrival'] = date('M j, Y', strtotime($vrows[$i]['Arrival']));
        $vrows[$i]['Expected Departure'] =  date('M j, Y', strtotime($vrows[$i]['Expected Departure']));
    }


    $vehicleTable = CreateMarkupFromDB::generateHTML_Table($vrows, 'tblListv');

}

$guestMessage = '';
$vehicleMessage = '';
$emtableMarkupv = '';

if (isset($_POST['btnEmail']) || isset($_POST['btnEmailv'])) {

    $emAddr = '';
    $subject = '';

    if (isset($_POST['txtEmail'])) {
        $emAddr = filter_var($_POST['txtEmail'], FILTER_SANITIZE_STRING);
    }

    if (isset($_POST['txtSubject'])) {
        $subject = filter_var($_POST['txtSubject'], FILTER_SANITIZE_STRING);
    }

    if ($emAddr != '' && $subject != '') {

        $config = new Config_Lite(ciCFG_FILE);

        $mail = prepareEmail($config);

        $mail->From = $config->getString('house', 'NoReply', '');
        $mail->FromName = $uS->siteName;

        $tos = explode(',', $emAddr);
        foreach ($tos as $t) {
            $bcc = filter_var($t, FILTER_SANITIZE_EMAIL);
            if ($bcc !== FALSE && $bcc != '') {
                $mail->addAddress($bcc);
            }
        }

        $mail->isHTML(true);

        $mail->Subject = $subject;

        if (isset($_POST['btnEmail'])) {
            $body = $guestTable;
        } else {
            $body = $vehicleTable;
        }

        $mail->msgHTML($guestTable);
        if($mail->send()) {
            $resultMessage .= "Email sent.  ";
        } else {
            $resultMessage .= "Email failed!  " . $mail->ErrorInfo;
        }

    } else {
        $resultMessage = 'Fill in both email address and subject.';
    }

    if (isset($_POST['btnEmail'])) {
        $guestMessage = $resultMessage;
    } else {
        $vehicleMessage = $resultMessage;
    }

}

// create send guest email table
$emTbl = new HTMLTable();
$emTbl->addBodyTr(HTMLTable::makeTd('Subject: ' . HTMLInput::generateMarkup('Current Guests Report', array('name' => 'txtSubject', 'size' => '70'))));
$emTbl->addBodyTr(HTMLTable::makeTd(
        'Email: '
        . HTMLInput::generateMarkup('', array('name' => 'txtEmail', 'size' => '70'))));

$emTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('Send Email', array('name' => 'btnEmail', 'type' => 'submit')) . HTMLContainer::generateMarkup('span', $guestMessage, array('style'=>'color:red;margin-left:.5em;'))));

$emtableMarkup = $emTbl->generateMarkup(array(), 'Email the Current Guest Report');

if ($uS->TrackAuto) {
    // create send guest email table
    $emTblv = new HTMLTable();
    $emTblv->addBodyTr(HTMLTable::makeTd('Subject: ' . HTMLInput::generateMarkup('Vehicle Report', array('name' => 'txtSubjectv', 'size' => '70'))));
    $emTblv->addBodyTr(HTMLTable::makeTd(
            'Email: '
            . HTMLInput::generateMarkup('', array('name' => 'txtEmailv', 'size' => '70'))));

    $emTblv->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('Send Email', array('name' => 'btnEmailv', 'type' => 'submit')) . HTMLContainer::generateMarkup('span', $vehicleMessage, array('style'=>'color:red;margin-left:.5em;'))));

    $emtableMarkupv = $emTblv->generateMarkup(array(), 'Email the Vehicle Report');
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <link href="css/house.css" rel="stylesheet" type="text/css" />
        <?php echo JQ_DT_CSS ?>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PAG_JS; ?>"></script>
        <script type="text/javascript">
    $(document).ready(function () {
        "use strict";
    
        $('#btnEmail, #btnPrint, #btnEmailv, #btnPrintv').button();
        $('#tblList').dataTable({"iDisplayLength": 50, "dom": '<"top"if>rt<"bottom"lp><"clear">', "order": [[0, 'asc']]});
        $('#tblListv').dataTable({"iDisplayLength": 50, "ordering": false, "dom": '<"top"if>rt<"bottom"lp><"clear">'});
        $('#btnPrint').click(function() {
            $("div.PrintArea").printArea();
        });
        $('#btnPrintv').click(function() {
            $("div.PrintAreav").printArea();
        });
        $('#mainTabs').tabs();
        $('#mainTabs').show();
    });
        </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <div style="float:left; margin-right: 100px; margin-top:10px;">
                <h2><?php echo $wInit->pageHeading; ?></h2>
            </div>

            <div style="clear:both;"></div>
            <div id="mainTabs" style="display:none;font-size: .9em;">
                <ul>
                    <li><a href="#tabGuest">Resident Guests</a></li>
                    <?php if ($uS->TrackAuto) { ?>
                    <li><a href="#tabVeh">Vehicles</a></li>
                    <?php } ?>
                </ul>
                <div id="tabGuest" class="hhk-tdbox" style=" padding-bottom: 1.5em; display:none;">
                    <form name="formEm" method="Post" action="GuestView.php">
                        <?php echo $emtableMarkup; ?>
                    </form>
                    <input type="button" value="Print" id='btnPrint' name='btnPrint' style="margin-right:.3em;"/>
                    <div class="PrintArea"><?php echo $guestTable; ?></div>
                </div>
                <div id="tabVeh" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none;">
                    <form name="formEmv" method="Post" action="GuestView.php">
                        <?php echo $emtableMarkupv; ?>
                    </form>
                    <input type="button" value="Print" id='btnPrintv' name='btnPrintv' style="margin-right:.3em;"/>
                    <div class="PrintAreav"><?php echo $vehicleTable; ?></div>
                </div>
            </div>

        </div>  <!-- div id="contentDiv"-->
    </body>
</html>
