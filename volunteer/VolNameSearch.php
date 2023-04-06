<?php
use HHK\sec\WebInit;
use HHK\SysConst\WebPageCode;
use HHK\Member\MemberSearch;

/**
 * VolNameSearch.php
 *
 * @category  Volunteer
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

require ("VolIncludes.php");

$wInit = new WebInit(WebPageCode::Service);

$dbh = $wInit->dbh;



addslashesextended($_GET);

if (isset($_GET['cmd'])) {
    $c = filter_var($_GET['cmd'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
} else {
    exit();
}

$events = array();

switch ($c) {

    case 'filter':

        //get the q parameter from URL
        $letters = '';
        if (isset($_GET['letters'])) {
            $letters = filter_var(urldecode($_GET['letters']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        // get basis
        $basis = '';
        if (isset($_GET['basis'])) {
            $basis = filter_var(urldecode($_GET['basis']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $fltr = '';
        if (isset($_GET['filter'])) {
            $fltr = filter_var(urldecode($_GET['filter']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $memberSearch = new MemberSearch($letters);
        $events = $memberSearch->volunteerCmteFilter($dbh, $basis, $fltr);

        break;

    default:
        $events = array("error"=>"Bad Command:  $c");


}



echo( json_encode($events) );

