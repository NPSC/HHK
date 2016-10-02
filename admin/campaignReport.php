<?php
/**
 * campaignReport.php
 *
 * @category  Report
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

require_once ("AdminIncludes.php");

require_once(CLASSES . "chkBoxCtrlClass.php");
require_once(CLASSES . "selCtrl.php");

$wInit = new webInit();

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();

$config = new Config_Lite(ciCFG_FILE);
$fyMonths = $config->getString("financial", "FY_Diff_Months", '3');
$startYear = $config->getString('site', 'Start_Year', '2005');


$rb_fyChecked = "checked='checked'";
$rb_cyChecked = "";


if (isset($_POST["selYears"])) {
    $yearSelected = filter_var($_POST["selYears"], FILTER_SANITIZE_STRING);
} else {
    $yearSelected = "all";
}
$selYearOptions = getYearOptionsMarkup($yearSelected, $startYear, $fyMonths);

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="css/default.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
<?php echo TOP_NAV_CSS; ?>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript">
            // Init j-query
            $(document).ready(function() {
                $.ajaxSetup ({
                    beforeSend: function() {
                        $('body').css('cursor', "wait");
                    },
                    complete: function(){
                        $('body').css('cursor', "auto");
                    },
                    cache: false
                });
                $('#btnCamp').click( function() {
                    var rb;
                    if ($('#rb_Cal_fy').prop('checked') ) {
                        rb = 'fy';
                    }
                    else {
                        rb = 'cy';
                    }
                    var yr = $('#selYears').val();
                    var fym = $('input#fyMonth').val();
                    $.ajax(
                    { type: "POST",
                        url: "ws_Report.php",
                        data: ({
                            cmd: 'fullcamp',
                            calyear: rb,
                            rptyear: yr,
                            fymonths: fym
                        }),
                        success: handleResponse,
                        error: handleError,
                        datatype: "json"
                    });
                });
                $('#btnList').click( function() {
                    var yr = $('#selYears').val();

                    $.ajax(
                    { type: "POST",
                        url: "ws_Report.php",
                        data: ({
                            cmd: 'listcamp',
                            rptyear: yr
                        }),
                        success: handleResponse,
                        error: handleError,
                        datatype: "json"
                    });
                });
                $('#Print_Button').click(function() {
                    $("div.printArea").printArea();
                });

            });
            function handleResponse(data, statusTxt, xhrObject) {
                if (statusTxt != "success")
                    alert('Server had a problem.  ' + xhrObject.status + ", "+ xhrObject.responseText);

                if (data) {
                    data = $.parseJSON(data);
                    if (data.error) {
                        alert('Application Error - ' + data.error);
                    }
                    else if (data.success) {
                        $('#divCampaignTable').html('');
                        re = /\$@|\(|\)|\+|\[|\_|\]|\[|\}|\{|\||\\|\!|\$/g;
                        // remove special characters like "$" and "," etc...

                        $('#divCampaignTable').append(data.success.replace(re, ""));
                        $('#reportDiv').css("display", "block");
                    }
                    else {
                        alert('Junk returned from the server! - '+data);
                    }
                }
                else {
                    alert('Nothing returned from the server');
                }
            };
            function handleError(xhrObject, stat, thrwnError) {
                alert("Server error: " + stat + ", " + thrwnError);
            };

        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <div id="vcampaign" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail">
                <h2><?php echo $wInit->pageHeading; ?></h2>
                <table>
                    <tr>
                        <th>Year</th>
                        <th>Type</th>
                    </tr>
                    <tr>
                        <td>
                            <select name="selYears" id="selYears">
                                <?php echo $selYearOptions; ?>
                            </select><input type="hidden" id="hdnselCampOptions" value="" />
                        </td>
                        <td>Fiscal Year<input type="radio" id="rb_Cal_fy" name="rb_CalSelect" value ="fy" <?php echo $rb_fyChecked; ?> />&nbsp;Calender Year<input type="radio" id="rb_Cal_cy" value="cy" name="rb_CalSelect" <?php echo $rb_cyChecked; ?> /></td>
                        <td style="text-align:right;"><input type="button" id="btnCamp" value="Run Campaign Roll-ups" /></td>
                    </tr>
                    <tr>
                        <td colspan="2">(Fiscal Year Begins <input type="text" id="fyMonth" value="<?php echo $fyMonths; ?>" size="1" readonly="readonly"/> Months Early)</td>
                        <td style="text-align:right;"><input type="button" id="btnList" value="List Campaign Detail" /></td>
                    </tr>
                </table>
            </div><div style="clear:both;"></div>
            <div id="reportDiv" style="margin-top:10px; display:none;" class="ui-widget ui-widget-content" >
                <input id="Print_Button" type="button" value="Print" title="Press to print out this listing."/>
                <div id="divCampaignTable" class="printArea" >
                </div>
            </div>
            <div id="submit"></div>
        </div>
    </body>
</html>
