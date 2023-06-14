<?php

use HHK\sec\{SecurityComponent, Session, WebInit};
use HHK\SysConst\WebPageCode;
use HHK\CreateMarkupFromDB;
use HHK\House\ResourceView;
use HHK\SysConst\RoomState;
use HHK\HTMLControls\HTMLContainer;

/**
 * ShowHsKpg.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

$wInit = new webInit(WebPageCode::Page);
$pageTitle = $wInit->pageTitle;

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();
$logoUrl = $uS->resourceURL . 'images/registrationLogo.png';
$guestAdmin = SecurityComponent::is_Authorized("guestadmin");

if(isset($_GET['tbl'])){
    switch ($_GET['tbl']){
        case 'all':
            $stmtMarkup = HTMLContainer::generateMarkup('h2', 'Housekeeping - All Rooms - ' . date('M d, Y'), ['style'=>'margin-bottom: 1em;']) . CreateMarkupFromDB::generateHTML_Table(ResourceView::roomsClean($dbh, '', $guestAdmin, TRUE), 'tbl');
            break;
        case 'notReady':
            $stmtMarkup = HTMLContainer::generateMarkup('h2', 'Housekeeping - Rooms Not Ready - ' . date('M d, Y'), ['style'=>'margin-bottom: 1em;']) . CreateMarkupFromDB::generateHTML_Table(ResourceView::roomsClean($dbh, RoomState::Dirty, $guestAdmin, TRUE), 'tbl');
            break;
        default:
            $stmtMarkup = '<h2>No table defined</h2>';
            break;
    }
}else{
    $stmtMarkup = '<h2>No table defined</h2>';
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo CSSVARS; ?>

        <style type="text/css" media="print">
            body {margin:0; padding:0; line-height: 1.4em; word-spacing:1px; letter-spacing:0.2px; font: 13px Arial, Helvetica,"Lucida Grande", serif; color: #000;}
            div#divBody table {width: 100%};
        </style>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript">
        	$(document).ready(function(){
        		window.print();
        	});
        </script>
    </head>
    <body>
        <div id="contentDiv">
            <div id="divBody" style="max-width: 1000px; clear:left; font-size: .9em;" class='PrintArea ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox'>
                    <?php echo $stmtMarkup; ?>
            </div>
        </div>

    </body>
</html>
