<?php
/**
 * EventShells.php
 *
 * @category  Reports
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

require_once ("AdminIncludes.php");

$wInit = new webInit();
$dbcon = initDB();
$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();


function getEvtShellMarkup($res) {
    if (mysqli_num_rows($res) > 0) {
        // Table header row
        $markup = "<thead><tr>";
        for ($i = 0; $i < mysqli_num_fields($res); $i++) {
            $finfo = mysqli_fetch_field_direct($res, $i);
            $markup .= "<th>" . $finfo->name . "</th>";
        }
        $markup .= "</tr></thead><tbody>";

        // peruse the rows
        while ($rw = mysqli_fetch_array($res)) {

            $markup .= "<tr>";
            // peruse the fields in each row
            for ($i = 0; $i < mysqli_num_fields($res); $i++) {

                $finfo = mysqli_fetch_field_direct($res, $i);

                if ($finfo->type == 16) {
                    $w = ord($rw[$i]);
                    if ($rw[$i] == '1' || $rw[$i] === true || $w == 1) {
                        $fld = TRUE;
                    } else {
                        $fld = FALSE;
                    }
                } else {
                    $fld = $rw[$i];
                }

                $markup .= "<td>" . $fld . "</td>";
            }
            $markup .= "</tr>";
        }
        $markup .= "</tbody>";
    }
    else
        $markup = "<tbody><tr><td>No Records</td></tr></tbody>";

    return $markup;
}


// create event shells
$query = "Select * from vshells";
$res = queryDB($dbcon, $query);
$evtShellMarkup = getEvtShellMarkup($res);

closeDB($dbcon);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
    "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="css/default.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo JQ_DT_CSS; ?>" rel="stylesheet" type="text/css" />
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
<?php echo TOP_NAV_CSS; ?>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript">
    $(document).ready(function() {
        try {
        $('#dataTbl').dataTable({"iDisplayLength": 10,
                "aLengthMenu": [[10, 20, -1], [10, 20, "All"]]
                , "Dom": '<"top"ilf>rt<"bottom"ip>'
            });
        } catch (err) {

        }
    });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">

            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div id="tabs-3" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail">
                <table id="dataTbl" cellpadding="0" cellspacing="0" border="0" class="display">
                    <?php echo $evtShellMarkup ?>
                </table>
            </div>
        </div>
    </body>
</html>
