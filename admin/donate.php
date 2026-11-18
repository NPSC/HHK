<?php
use HHK\Crypto;
use HHK\Donation\{Donation, DonateMarkup};
use HHK\House\Report\ReportFilter;
use HHK\sec\{Session,WebInit};
use HHK\SysConst\{AddressPurpose, CampaignType, GLTableNames, SalutationCodes, WebPageCode};
use HHK\Tables\Donate\DonationsRS;
use HHK\Tables\EditRS;


/**
 * donate.php
 *
 * @author Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license MIT
 * @link https://github.com/NPSC/HHK
 */

require ("AdminIncludes.php");

$wInit = new WebInit(WebPageCode::Service);
$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();

// Array for responses
$resp = array();

// check security codes; exit if not secure
if (filter_has_var(INPUT_POST, "sq")) {
    $sq = filter_input(INPUT_POST, "sq", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $pw = Crypto::decryptMessage($sq);
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
if (filter_has_var(INPUT_POST, 'cmd')) {
    $cmd = filter_input(INPUT_POST, 'cmd', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

$id = 0;
if (filter_has_var(INPUT_POST, "id")) {
    $id = intval(filter_input(INPUT_POST, "id", FILTER_SANITIZE_NUMBER_INT), 10);
}

$donId = 0;
if (filter_has_var(INPUT_POST, 'did')) {
    $donId = intval(filter_input(INPUT_POST, 'did', FILTER_SANITIZE_NUMBER_INT), 10);
}

try {

    switch ($cmd) {
        case "insert":
        case "update":

            $data = (isset($_POST["qd"]) && is_array($_POST["qd"])) ? $_POST["qd"] : array();
            $resp = recordDonation($dbh, floatval($uS->MaxDonate), $id, $data, $uS->nameLookups[GLTableNames::PayType], ($cmd == "update" ? $donId : 0));

            break;

        case "get":

            $resp = getDonation($dbh, $donId, $id);

            break;

        case "delete":

            $resp = deleteDonation($dbh, $donId, $uname);

            break;

        case "list":

            // The Donations tab filter posts donFilter=1 with its controls; otherwise list everything.
            $filter = null;
            if (filter_has_var(INPUT_POST, 'donFilter')) {
                $filter = DonateMarkup::createListFilter($dbh);
                $filter->loadSelectedTimePeriod()->loadSelectedCampaigns();
            }

            $resp = listDonations($dbh, $id, $filter);

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


/**
 * Record a new donation, or update an existing one, from the NameEdit donation dialog.
 *
 * @param PDO $dbh
 * @param float $maxDonationAmt
 * @param int $id donor id
 * @param array $data the dialog's field values, keyed by input id
 * @param array $payCodes
 * @param int $donId existing donation to update; 0 to insert a new one
 * @return array{success?: string, error?: string}
 */
function recordDonation(PDO $dbh, float $maxDonationAmt, int $id, array $data, array $payCodes, int $donId = 0) {

    if ($id <= 0) {
        return array("error" => "Bad Id: " . $id);
    }

    if (count($data) == 0) {
        return array("error" => "Missing data.  ");
    }

    $amt = 0;
    if (isset($data["damount"])) {
        $amt = floatval(filter_var($data["damount"], FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND));
    }

    $campaignCode = isset($data["dselCamp"]) ? filter_var($data["dselCamp"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';
    if ($campaignCode == '') {
        return array("error" => "The Campaign is not specified.  ");
    }

    $payType = isset($data["dselPaytype"]) ? filter_var($data["dselPaytype"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';
    $dte = isset($data["ddate"]) ? filter_var($data["ddate"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';
    $salut = isset($data["dselSalutation"]) ? filter_var($data["dselSalutation"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : SalutationCodes::FirstOnly;
    $envel = isset($data["dselEnvelope"]) ? filter_var($data["dselEnvelope"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : SalutationCodes::Formal;
    $addr = isset($data["dselAddress"]) ? filter_var($data["dselAddress"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : AddressPurpose::Home;
    $includedId = isset($data["selAssoc"]) ? intval(filter_var($data["selAssoc"], FILTER_SANITIZE_NUMBER_INT), 10) : 0;
    $notes = isset($data['dnote']) ? filter_var($data["dnote"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';
    $fundCode = isset($data["dselStudent"]) ? filter_var($data["dselStudent"], FILTER_SANITIZE_NUMBER_INT) : '';

    try {
        $donation = new Donation($dbh, $payCodes, $maxDonationAmt, $campaignCode);
        $donation->newDonation($dbh, $amt, $id, $includedId, $payType, $dte, $addr, $salut, $envel, $notes, $fundCode);

        if ($donId > 0) {
            $donation->updateDonation($dbh, $donId);
            return array("success" => "Donation updated");
        }

        $donation->saveDonation($dbh);
    } catch (RuntimeException $ex) {
        // Validation failures; show the message as-is in the dialog.
        return array("error" => $ex->getMessage());
    }

    return array("success" => "Donation recorded");
}

/**
 * One donation's values for the edit dialog, keyed by the dialog's input ids.
 *
 * @param PDO $dbh
 * @param int $donId
 * @param int $id the member whose page is asking; must be the donor
 * @return array{donation?: array, error?: string}
 */
function getDonation(PDO $dbh, int $donId, int $id) {

    try {
        $donRS = Donation::loadDonation($dbh, $donId);
    } catch (RuntimeException $ex) {
        return array("error" => $ex->getMessage());
    }

    if ($donRS->Donor_Id->getStoredVal() != $id) {
        return array("error" => "Edit this donation from the donor's page.  ");
    }

    // A donation has either a joint donor (individuals) or a care-of employee (organizations).
    $includedId = intval($donRS->Assoc_Id->getStoredVal()) > 0 ? $donRS->Assoc_Id->getStoredVal() : $donRS->Care_Of_Id->getStoredVal();

    return array(
        "donation" => array(
            "dselCamp" => $donRS->Campaign_Code->getStoredVal(),
            "ddate" => date("M j, Y", strtotime($donRS->Date_Entered->getStoredVal())),
            "dselPaytype" => $donRS->Pay_Type->getStoredVal(),
            "damount" => number_format(floatval($donRS->Amount->getStoredVal()), 2, ".", ""),
            "dselAddress" => $donRS->Address_Purpose->getStoredVal(),
            "selAssoc" => (intval($includedId) > 0 ? (string)$includedId : ""),
            "dselSalutation" => $donRS->Salutation_Code->getStoredVal(),
            "dselEnvelope" => $donRS->Envelope_Code->getStoredVal(),
            // Notes are stored HTML-encoded; the dialog's textarea needs plain text.
            "dnote" => html_entity_decode($donRS->Note->getStoredVal() ?? '', ENT_QUOTES | ENT_HTML5),
            "dselStudent" => $donRS->Fund_Code->getStoredVal(),
        )
    );
}

/**
 * Mark a donation as deleted.
 *
 * @param PDO $dbh
 * @param int $donId
 * @param string $uname
 * @return array{success?: string, error?: string}
 */
function deleteDonation(PDO $dbh, int $donId, $uname) {

    if ($donId <= 0) {
        return array("error" => "Bad donation record Id");
    }

    $count = EditRS::updateFields($dbh, new DonationsRS(), ['iddonations' => $donId], ['Status' => 'd', 'Last_Updated' => date('Y-m-d H:i:s'), 'Updated_By' => $uname]);

    if ($count == 1) {
        return array("success" => "Donation Deleted.  ");
    }

    return array("error" => "Donation not found.  ");
}

/**
 * Donation list for the NameEdit donations DataTable.  Includes donations where the member
 * is the donor or the associate (joint gifts show on both partners).
 *
 * @param PDO $dbh
 * @param int $id
 * @param ReportFilter|null $filter time period and campaigns to limit the list to; null lists everything
 * @return array{data?: array, donorName?: string, monetaryTotal?: string, inKindTotal?: string, total?: string, filterItems?: list<array{label: string, value: string}>, error?: string}
 */
function listDonations(PDO $dbh, $id, ?ReportFilter $filter = null) {

    if ($id == 0) {
        return array('error' => "Bad Member Id");
    }

    $where = "(Donor_Id = :id or Assoc_Id = :id2)";
    $params = array(
        ":id" => $id,
        ":id2" => $id
    );
    // Applied filter, one summary line each
    $filterItems = array();

    if ($filter !== null) {

        // Time period: report start through the day before the query end.
        $where .= " and Date_Entered >= :start and Date_Entered < :end";
        $params[":start"] = $filter->getReportStart();
        $params[":end"] = $filter->getQueryEnd();

        $filterItems[] = array('label' => 'Showing', 'value' => date('M j, Y', strtotime($filter->getReportStart())) . ' - ' . date('M j, Y', strtotime($filter->getReportEnd())));

        $campaigns = $filter->getSelectedCampaigns();
        if (count($campaigns) > 0) {
            $holders = array();
            foreach (array_values($campaigns) as $i => $code) {
                $holders[] = ":camp$i";
                $params[":camp$i"] = $code;
            }
            // vdonation_view's Campaign_Code is the campaign title, so match the codes on the donations table
            // (same case-insensitive comparison the view's campaign join uses).
            $where .= " and iddonations in (select `iddonations` from `donations` where lcase(trim(`Campaign_Code`)) in (" . implode(',', array_map(fn($h) => "lcase(trim($h))", $holders)) . "))";
            $filterItems[] = array('label' => 'Campaigns', 'value' => $filter->getSelectedCampaignsString());
        }
    }

    // This member's kind and name.  Use Record_Company: billing agent organizations also have Record_Member set (NameEdit kludge).
    $nStmt = $dbh->prepare("select Record_Company, case when Record_Company = 1 then Company else Name_Full end from name where idName = :id;");
    $nStmt->execute(array(
        ":id" => $id
    ));
    $member = $nStmt->fetch(PDO::FETCH_NUM);
    $isIndividual = ($member === false || $member[0] != 1);
    $donorName = ($member === false ? '' : $member[1]);

    $stmt = $dbh->prepare("SELECT iddonations, Donor_Id, Amount, Campaign_Code, Date_Entered, Care_Of_Id, Assoc_Id, Name_Last, Name_First, Campaign_Type, Note
        FROM vdonation_view WHERE $where order by Date_Entered desc;");
    $stmt->execute($params);

    $rows = array();
    $monetaryTotal = 0;
    $inKindTotal = 0;

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {

        // $isIndividual describes this member, which is the donor whenever Donor_Id == $id
        $careOfId = 0;
        $careOfName = '';

        if ($r["Donor_Id"] == $id) {
            // This member is the donor
            if ($r["Assoc_Id"] != 0 && $isIndividual) {
                $src = "Joint";
                $srcTitle = "Joint gift with this member's spouse/partner";
            } else if ($r["Care_Of_Id"] != 0 && ! $isIndividual) {
                // Organization with a sponsor employee
                $careOfId = intval($r["Care_Of_Id"]);
                $careOfName = $r["Name_Last"] . ", " . $r["Name_First"];
                $src = $careOfName;
                $srcTitle = "Sponsor employee";
            } else if (! $isIndividual) {
                $src = "Organization";
                $srcTitle = "Organization gift with no sponsor employee assigned";
            } else {
                $src = "Individual";
                $srcTitle = "Individual donation";
            }
        } else {
            // This member is the associate (partner/spouse of the donor)
            $src = "Joint (spouse gave)";
            $srcTitle = "Joint gift made by this member's spouse/partner";
        }

        $inKind = ($r['Campaign_Type'] == CampaignType::InKind);
        if ($inKind) {
            $inKindTotal += $r['Amount'];
        } else {
            $monetaryTotal += $r['Amount'];
        }

        $rows[] = array(
            'id' => intval($r['iddonations']),
            'src' => $src,
            'srcTitle' => $srcTitle,
            // Joint gifts are edited from the page of the partner who gave.
            'editable' => ($r["Donor_Id"] == $id),
            'careOfId' => $careOfId,
            'careOfName' => $careOfName,
            'campaign' => $r['Campaign_Code'],
            'amount' => floatval($r['Amount']),
            'inKind' => $inKind,
            'date' => $r['Date_Entered'],
            'dateDisplay' => date("M j, Y", strtotime($r["Date_Entered"])),
            // Notes are stored HTML-encoded; the client escapes on display.
            'note' => html_entity_decode($r['Note'] ?? '', ENT_QUOTES | ENT_HTML5),
        );
    }

    return array(
        'data' => $rows,
        'donorName' => $donorName,
        'monetaryTotal' => number_format($monetaryTotal, 2, '.', ','),
        'inKindTotal' => number_format($inKindTotal, 2, '.', ','),
        'total' => number_format($monetaryTotal + $inKindTotal, 2, '.', ','),
        'filterItems' => $filterItems
    );
}
