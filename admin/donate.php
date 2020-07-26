<?php
use HHK\sec\{Session,WebInit};
use HHK\SysConst\{ActivityTypes, AddressPurpose, CampaignType, GLTableNames, MemBasis, MemDesignation, MemStatus, SalutationCodes, WebPageCode};
use HHK\Tables\EditRS;
use HHK\Tables\ActivityRS;
use HHK\Tables\Donate\DonationsRS;
use HHK\Tables\Name\{NameAddressRS,NamePhoneRS,NameEmailRS};
use HHK\Exception\RuntimeException;
use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLTable};
use HHK\Member\AbstractMember;
use HHK\Donation\Campaign;

/**
 * donate.php
 *
 * @author Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license MIT
 * @link https://github.com/NPSC/HHK
 */

require ("AdminIncludes.php");

/*
 * require (DB_TABLES . 'nameRS.php');
 * require (DB_TABLES . 'ActivityRS.php');
 * require (DB_TABLES . 'DonateRS.php');
 *
 * require (CLASSES . 'Campaign.php');
 */
require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . 'Addresses.php');

$wInit = new webInit(WebPageCode::Service);
$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();

// Array for responses
$resp = array();

// check security codes; exit if not secure
if (isset($_POST["sq"])) {
    $sq = filter_var($_POST["sq"], FILTER_SANITIZE_STRING);
    $pw = decryptMessage($sq);
    $ts = strtotime($pw);
    $tnow = time();

    if ($ts < $tnow) {
        $resp["error"] = "Timed out: timestamp=$pw";
        echo (json_encode($resp));
        exit();
    }
} else {
    exit();
}

$uname = $uS->username;

// use cmd to determine actions
$cmd = "";
if (isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
}

try {

    switch ($cmd) {
        case "insert":
            $maxDonationAmt = floatval($uS->MaxDonate);

            $id = 0;
            if (isset($_POST["id"])) {
                $id = intval(filter_var($_POST["id"], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $resp = recordDonation($dbh, $maxDonationAmt, $id, $_POST);

            break;

        case "delete":
            $donId = 0;
            if (isset($_POST['did'])) {
                $donId = intval(filter_var($_POST['did'], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            $resp = deleteDonation($dbh, $donId, $uname);

            break;

        case "markup":

            $id = 0;
            if (isset($_POST["id"])) {
                $id = intval(filter_var($_POST["id"], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $resp = genDonationMarkup($dbh, $id);

            break;

        default:
            $resp["error"] = "Unknown command: $cmd";
            break;
    }
} catch (PDOException $ex) {
    $resp = array(
        "error" => "Database Error: " . $ex->getMessage()
    );
} catch (Exception $ex) {
    $resp = array(
        "error" => "HouseKeeper Error: " . $ex->getMessage()
    );
}

// output the response
echo (json_encode($resp));
exit();

function recordDonation(PDO $dbh, $maxDonationAmt, $id, $parms)
{
    $reply = array();
    // get session instance
    $uS = Session::getInstance();

    if ($id <= 0) {
        return array(
            "error" => "Bad Id: " . $id
        );
    }

    // other values packed here
    $data = array();
    if (isset($parms["qd"])) {
        $data = $parms["qd"];
    } else {
        return array(
            "error" => "Missing data.  "
        );
    }

    // Amount
    $amt = 0;
    if (isset($data["damount"])) {
        $amt = floatval(filter_var($data["damount"], FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND));
    }

    // Filter donation amouont
    if ($amt <= 0 || $amt > $maxDonationAmt) {
        return array(
            "error" => "Bad Amount: " . number_format($amt, 2, ".", ",") . ".  Amount must be greater than 0 and less than $" . number_format($maxDonationAmt, 2, ".", ",")
        );
    }

    // Campaign code must me defined.
    $campaignCode = '';
    if (isset($data["dselCamp"])) {
        $campaignCode = filter_var($data["dselCamp"], FILTER_SANITIZE_STRING);
    }
    if ($campaignCode == '') {
        return array(
            "error" => "The Campaign is not specified.  "
        );
    }

    // Pay type
    $payType = '';
    if (isset($data["dselPaytype"])) {
        $payType = filter_var($data["dselPaytype"], FILTER_SANITIZE_STRING);
    }
    if (isset($uS->nameLookups[GLTableNames::PayType][$payType]) === FALSE) {
        return array(
            "error" => "Bad Pay type: " . $payType
        );
    }

    // Date
    $dte = date('Y-m-d H:i:s');
    if (isset($data["ddate"])) {
        $dte = filter_var($data["ddate"], FILTER_SANITIZE_STRING);
    } else {
        $dte = date('Y-m-d H:i:s');
    }

    // These three have valid defaults
    $salut = SalutationCodes::FirstOnly;
    if (isset($data["dselSalutation"])) {
        $salut = filter_var($data["dselSalutation"], FILTER_SANITIZE_STRING);
    }
    $envel = SalutationCodes::Formal;
    if (isset($data["dselEnvelope"])) {
        $envel = filter_var($data["dselEnvelope"], FILTER_SANITIZE_STRING);
    }
    $addr = AddressPurpose::Home;
    if (isset($data["dselAddress"])) {
        $addr = intval(filter_var($data["dselAddress"]), FILTER_SANITIZE_STRING);
    }
    $includedId = 0;
    if (isset($data["selAssoc"])) {
        $includedId = intval(filter_var($data["selAssoc"], FILTER_SANITIZE_NUMBER_INT), 10);
    }

    $notes = '';
    if (isset($data['dnote'])) {
        $notes = filter_var($data["dnote"], FILTER_SANITIZE_STRING);
    }

    $campaign = new Campaign($dbh, $campaignCode);
    if ($campaign->get_idcampaign() == 0) {
        return array(
            "error" => "Bad Campaign Code: " . $campaignCode
        );
    }

    if ($campaign->isAmountValid($amt) === FALSE) {
        return array(
            "error" => "Amount outside campaign range limits: $" . number_format($amt, 2, ".", ",")
        );
    }

    $fundCode = '';
    if (isset($data["dselStudent"]) && $campaign->get_type() == CampaignType::Scholarship) {
        $fundCode = filter_var($data["dselStudent"], FILTER_SANITIZE_NUMBER_INT);
    }

    $name = AbstractMember::GetDesignatedMember($dbh, $id, MemBasis::Indivual);
    if ($name->isNew()) {
        return array(
            "error" => "Bad Member Id: " . $id
        );
    }

    if ($name->getMemberDesignation() == MemDesignation::Individual) {
        $assocId = $includedId;
        $careOfId = 0;
    } else {
        $assocId = 0;
        $careOfId = $includedId;
    }

    if ($name->getMemberDesignation() == MemDesignation::Organization and $careOfId < 1) {
        // All organizations must have an employee assigned as care of
        return array(
            "error" => "Please select an employee.  If no employees are listed, then create one and try the donation again.  (Someone signed that check.)"
        );
    }

    if ($name->get_status() != MemStatus::Active && $name->get_status() != MemStatus::Deceased) {
        return array(
            "error" => "The donor's Member Status must be set to Active in order to donate."
        );
    }

    // do the drop

    $activRS = new ActivityRS();
    $activRS->idName->setNewVal($id);
    $activRS->Trans_Date->setNewVal(date('Y-m-d H:i:s'));
    $activRS->Type->setNewVal(ActivityTypes::Donation);
    $activRS->Effective_Date->setNewVal($dte);
    $activRS->Product_Code->setNewVal('gift');
    $activRS->Source_System->setNewVal('manual');
    $activRS->Quantity->setNewVal(1);
    $activRS->Amount->setNewVal($amt);
    $activRS->Pay_Method->setNewVal($payType);
    $activRS->Campaign_Code->setNewVal($campaign->get_campaigncode());
    $activRS->Member_Type->setNewVal($name->get_type());
    $activRS->Status_Code->setNewVal('a');
    $activRS->Note->setNewVal($notes);

    $acId = EditRS::insert($dbh, $activRS);

    if ($acId > 0) {
        // Insert donation record
        $donRS = new DonationsRS();
        $donRS->Activity_Id->setNewVal($acId);
        $donRS->Amount->setNewVal($amt);
        $donRS->Donor_Id->setNewVal($id);
        $donRS->Care_Of_Id->setNewVal($careOfId);
        $donRS->Assoc_Id->setNewVal($assocId);
        $donRS->Type->setNewVal('local');
        $donRS->Date_Entered->setNewVal($dte);
        $donRS->Pay_Type->setNewVal($payType);
        $donRS->Member_type->setNewVal($name->get_type());
        $donRS->Donation_Type->setNewVal('gift');
        $donRS->Salutation_Code->setNewVal($salut);
        $donRS->Envelope_Code->setNewVal($envel);
        $donRS->Note->setNewVal($notes);

        $donRS->Org_Code->setNewVal('PIFH');
        $donRS->Fund_Code->setNewVal($fundCode);
        $donRS->Campaign_Code->setNewVal($campaign->get_campaigncode());
        $donRS->Status->setNewVal('a');
        $donRS->Updated_By->setNewVal($uS->username);
        $donRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        $addrRS = new NameAddressRS();
        $addrRS->idName->setStoredVal($id);
        $addrRS->Purpose->setStoredVal($addr);
        $rows = EditRS::select($dbh, $addrRS, array(
            $addrRS->idName,
            $addrRS->Purpose
        ));
        if (count($rows) > 0) {
            EditRS::loadRow($rows[0], $addrRS);
        }

        $donRS->Address_1->setNewVal($addrRS->Address_1->getStoredVal());
        $donRS->Address_2->setNewVal($addrRS->Address_2->getStoredVal());
        $donRS->City->setNewVal($addrRS->City->getStoredVal());
        $donRS->State->setNewVal($addrRS->State_Province->getStoredVal());
        $donRS->Postal_Code->setNewVal($addrRS->Postal_Code->getStoredVal());
        $donRS->Country->setNewVal($addrRS->Country->getStoredVal());
        $donRS->Address_Purpose->setNewVal($addr);

        $phoneRS = new NamePhoneRS();
        $phoneRS->idName->setStoredVal($id);
        $phoneRS->Phone_Code->setStoredVal($name->get_preferredPhone());
        $rows = EditRS::select($dbh, $phoneRS, array(
            $phoneRS->idName,
            $phoneRS->Phone_Code
        ));
        if (count($rows) > 0) {
            EditRS::loadRow($rows[0], $phoneRS);
        }

        $emailRS = new NameEmailRS();
        $emailRS->idName->setStoredVal($id);
        $emailRS->Purpose->setStoredVal($addr);
        $rows = EditRS::select($dbh, $emailRS, array(
            $emailRS->idName,
            $emailRS->Purpose
        ));
        if (count($rows) > 0) {
            EditRS::loadRow($rows[0], $emailRS);
        }

        $donRS->Phone->setNewVal($phoneRS->Phone_Num->getStoredVal());
        $donRS->Email->setNewVal($emailRS->Email->getStoredVal());

        $donId = EditRS::insert($dbh, $donRS);

        if ($donId > 0) {

            // insert vol_type = d if not there already...
            $query = "call InsertDonor(" . $id . ");";
            $dbh->exec($query);

            if ($assocId > 0) {
                // insert vol_type = d if not there already...
                $query = "call InsertDonor(" . $assocId . ");";
                $dbh->exec($query);
            }

            $reply["success"] = "ok";
        } else {
            throw new RuntimeException("DB Error, table=donations - insert failure.");
        }
    } else {
        throw new RuntimeException("DB Error, table=activiy, insert failure");
    }
    return $reply;
}

function deleteDonation(PDO $dbh, $donId, $uname)
{
    $reply = array();

    if ($donId > 0) {
        // mark donation record as deleted
        $query = "update donations set Status='d', Last_Updated=now(), Updated_By=:ub where iddonations = :did;";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(
            ':ub' => $uname,
            ':did' => $donId
        ));

        if ($stmt->rowCount() == 1) {
            $reply["success"] = "Donation Deleted.  ";
        } else {
            $reply["error"] = "Donation not found.  ";
        }
    } else {
        $reply["error"] = "Bad donation record Id";
    }
    return $reply;
}

function genDonationMarkup(PDO $dbh, $id)
{
    if ($id == 0) {
        return array(
            'error' => "<p>Bad Member Id</p>"
        );
    }

    $tbl = new HTMLTable();
    // Table header row
    $tbl->addHeaderTr(HTMLTable::makeTh('', array(
        'style' => 'width:25px;'
    )) . HTMLTable::makeTh('Source', array(
        'style' => 'width:70px;'
    )) . HTMLTable::makeTh('Campaign') . HTMLTable::makeTh('Amount') . HTMLTable::makeTh('Date') . HTMLTable::makeTh('X'));

    $query = "SELECT iddonations, Donor_Id, Amount, Campaign_Code, Date_Entered, Record_Member, Care_Of_Id, Assoc_Id, Name_Last, Name_First, Donor_Name, Campaign_Type, Fund_Code, Note
        FROM vdonation_view   WHERE Donor_Id = :id or Assoc_id = :id2 order by Date_Entered desc;";
    $stmt = $dbh->prepare($query, array(
        PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY
    ));
    $stmt->execute(array(
        ":id" => $id,
        ":id2" => $id
    ));

    $totInd = 0;
    $donorName = "";

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row2) {

        $donId = $row2["Donor_Id"];
        $assocId = $row2["Assoc_Id"];
        $careId = $row2["Care_Of_Id"];
        $recordMember = $row2["Record_Member"];
        $donationsId = $row2['iddonations'];

        if ($recordMember == 0) {
            $isIndividual = FALSE;
        } else {
            $isIndividual = TRUE;
        }

        // What if this is the donor
        if ($donId == $id) {
            if ($assocId != 0 && $isIndividual === TRUE) {
                $src = "jt";
                $srcTitle = "Joint with my partner/spouse";
            } else if ($careId != 0 && $isIndividual === FALSE) {
                $src = "<a href='NameEdit.php?id=" . $row2["Care_Of_Id"] . "' title='Sponser Employee'>" . $row2["Name_Last"] . ", " . $row2["Name_First"] . "</a>"; // I'm a company (or individual) with an employee ID listed in the donation
                $srcTitle = "Sponser employee";
            } else if ($isIndividual === FALSE) {
                $src = "org";
                $srcTitle = "Organization without a sponser assigned";
            } else {
                $src = "ind"; // I'm an individual donor
                $srcTitle = "Individual Donation";
            }

            $donorName = $row2["Donor_Name"];
            if ($row2['Campaign_Type'] != CampaignType::InKind) {
                $totInd = $totInd + $row2['Amount'];
            }
        } else if ($assocId == $id) {
            // what if this is the associate?
            if ($row2['Campaign_Type'] != CampaignType::InKind) {
                $totInd = $totInd + $row2['Amount'];
            }

            // if ($donId == $partnerId) { // If i am the assoc_ID and the donor is my spouse
            $src = "jt";
            $srcTitle = "Partner/spouse of the donor";
            // } else { // Here we can check other relatives as we define them
            // $src = "asoc"; // I'm an associate.
            // $srcTitle = "Associated with the donor";
            // }
        } else {
            // Dont know what this is.
            $src = "?";
            $srcTitle = "Unknown";
        }

        $styl = '';
        if ($row2['Campaign_Type'] == CampaignType::InKind) {
            $styl = 'font-style: italic;';
        }

        // Did we catch the donor's name?
        if ($donorName == "") {
            $query = "select case when Record_Member = 1 then concat( Name_First, ' ', Name_Last) else Company end as `name` from name where idName = :id;";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(
                ":id" => $id
            ));
            $r = $stmt->fetchall();
            $donorName = $r[0][0];
        }

        $editIcon = HTMLContainer::generateMarkup('button', HTMLContainer::generateMarkup('span', '', array(
            'class' => 'ui-button-icon ui-icon ui-icon-pencil'
        )) . 'Close', array(
            'type' => 'button',
            'title' => 'Edit Donation',
            'data-idDonation' => $donationsId,
            'style' => 'padding: 0 0;width: 1.5em;',
            'class' => 'ui-button ui-corner-all ui-widget ui-button-icon-only hhk-edit-donation'
        ));

        $tbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $editIcon, array(
            'class' => 'donlisting'
        ))) . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $src, array(
            'class' => 'donlisting',
            'title' => $srcTitle
        ))) . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $row2['Campaign_Code'], array(
            'class' => 'donlisting'
        ))) . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $row2['Amount'], array(
            'class' => 'donlisting',
            'style' => $styl
        ))) . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', date("M j, Y", strtotime($row2["Date_Entered"])), array(
            'class' => 'donlisting'
        ))) . HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
            'type' => 'checkbox',
            'id' => 'undonate_' . $row2["iddonations"],
            'class' => 'donlisting hhk-undonate'
        ))));

        if ($row2['Note'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd('Note:', array(
                'class' => 'tdlabel',
                'colspan' => '2',
                'style' => 'font-size:small;'
            )) . HTMLTable::makeTd($row2['Note'], array(
                'colspan' => '4',
                'style' => 'font-size:small;'
            )));
        }
    }

    $totalMarkup = number_format($totInd, 2, '.', ',');
    $tbl->prependBodyTr(HTMLTable::makeTd('Donor: ' . $donorName . ";  Total: $" . $totalMarkup, array(
        'colspan' => '6',
        'style' => 'font-size:smaller;'
    )));

    return array(
        'success' => $tbl->generateMarkup(array(
            'style' => 'width:100%'
        ))
    );
}
