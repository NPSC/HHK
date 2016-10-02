<?php
/**
 * ShowHsKpg.php
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
require ("homeIncludes.php");

require (CLASSES . 'Notes.php');

require (HOUSE . 'Resource.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'ResourceView.php');

$wInit = new webInit(WebPageCode::Page);
$pageTitle = $wInit->pageTitle;

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();
$logoUrl = $uS->resourceURL . 'images/registrationLogo.png';
$guestAdmin = ComponentAuthClass::is_Authorized("guestadmin");


$stmt = $dbh->query("select DISTINCT
    r.idRoom,
ifnull(v.idVisit, 0) as idVisit,
    r.Title,
    r.`Status`,
    r.`State`,
    r.`Availability`,
    ifnull(n.Name_Full, '') as `Name`,
    ifnull(v.Arrival_Date, '') as `Arrival`,
    ifnull(v.Expected_Departure, '') as `Expected_Departure`,
    r.Last_Cleaned,
    r.Notes
from
    room r
        left join
    resource_room rr ON r.idRoom = rr.idRoom
        left join
    visit v ON rr.idResource = v.idResource and v.`Status` = '" . VisitStatus::CheckedIn . "'
        left join
    name n ON v.idPrimaryGuest = n.idName
        left join resource re on rr.idResource = re.idResource
where re.`Type` != '". ResourceTypes::Partition ."' and re.`Type` != '" .ResourceTypes::Block. "' order by r.Title;");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtMarkup = ResourceView::roomsClean($dbh, $rows, 'tblFac', $uS->guestLookups[GL_TableNames::RoomStatus], '', $guestAdmin, TRUE);

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
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
        <script type='text/javascript'>
            $(document).ready(function () {
                "use strict";
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
