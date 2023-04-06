<?php

use HHK\sec\WebInit;
use HHK\Donation\Campaign;
use HHK\SysConst\WebPageCode;

/**
 * liveGetCamp.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("AdminIncludes.php");

// Set page type for AdminPageCommon
$wInit = new webInit(WebPageCode::Service);

$dbh = $wInit->dbh;


//get the q parameter from URL
if (isset($_POST["qc"]) === FALSE) {
    exit();
}
$q = filter_var($_POST["qc"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

$resp = array();

$campaign = new Campaign($dbh, $q);

if ($campaign->get_idcampaign() > 0) {
    // got a campaign code
    $resp["camp"] = array(
        'mindonation' => $campaign->get_mindonation(),
        'maxdonation' => $campaign->get_maxdonation(),
        'type' => $campaign->get_type()
    );

} else {
    $resp["error"] = "Campaign Not Found.";
}



//output the response
echo( json_encode($resp) );
exit();

