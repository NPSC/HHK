<?php
/**
 * ShowHsKpg.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

require (CLASSES . 'Notes.php');

require (CLASSES . 'CreateMarkupFromDB.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'ResourceView.php');

$wInit = new webInit(WebPageCode::Page);
$pageTitle = $wInit->pageTitle;

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();
$logoUrl = $uS->resourceURL . 'images/registrationLogo.png';
$guestAdmin = SecurityComponent::is_Authorized("guestadmin");


$stmtMarkup = CreateMarkupFromDB::generateHTML_Table(ResourceView::roomsClean($dbh, '', $guestAdmin, TRUE), 'tbl');

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
<?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <style type="text/css" media="print">
            body {margin:0; padding:0; line-height: 1.4em; word-spacing:1px; letter-spacing:0.2px; font: 13px Arial, Helvetica,"Lucida Grande", serif; color: #000;}
        </style>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
    </head>
    <body>
        <div id="contentDiv">
            <div id="divBody" style="max-width: 1000px; clear:left; font-size: .9em;" class='PrintArea ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox'>
                    <?php echo $stmtMarkup; ?>
            </div>
        </div>

    </body>
</html>
