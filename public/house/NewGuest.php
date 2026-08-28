<?php

use HHK\sec\{Session, WebInit, Labels};
use HHK\House\Report\NewGuestReport;
use HHK\Vite\Vite;

/**
 * NewGuest.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");



try {
    $wInit = new WebInit();
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();
$labels = Labels::getLabels();
$menuMarkup = $wInit->generatePageMenu();

$dataTableWrapper = '';

$newGuestReport = new NewGuestReport($dbh, $_REQUEST);

if (isset($_POST['btnHere-' . $newGuestReport->getInputSetReportName()])) {
    $dataTableWrapper = $newGuestReport->generateMarkup();
}

if (isset($_POST['btnExcel-' . $newGuestReport->getInputSetReportName()])) {
    $newGuestReport->downloadExcel("NewGuests");
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>

        <?php echo Vite::asset('resources/js/house.js'); ?>

        <?php echo FAVICON; ?>
        <?php echo CSSVARS; ?>

        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", () => {
                var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';

                initReport(Object.assign(<?php echo json_encode($newGuestReport->getReportScriptConfig()); ?>, { dateFormat: dateFormat }));
            });
        </script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div id="<?php echo $newGuestReport->getInputSetReportName(); ?>Report">
                <?php echo $newGuestReport->generateFilterMarkup(); ?>
                <div class="rptResults"><?php echo $dataTableWrapper; ?></div>
            </div>
        </div>
    </body>
</html>
