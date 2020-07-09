<?php
/**
 * recent.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require("AdminIncludes.php");


$wInit = new webInit();

$dbh = $wInit->dbh;

$page = $wInit->page;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();

$donationsFlag = SecurityComponent::is_Authorized("NameEdit_Donations");





?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo NOTY_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        
        <script type="text/javascript">
            function handleResponse(dataTxt, statusTxt, xhrObject) {

                if (statusTxt != "success") {
                    alert('Server had a problem.  ' + xhrObject.status + ", "+ xhrObject.responseText);
                }
                else {
                    data = $.parseJSON(dataTxt);
                    if (data.error) {
                        // error message
                        $('div#result').text(data.error);
                    } else if (data.success) {
                        $('div#result').html(data.success);
                    }
                }
            }
            function handleError(xhrObject, stat, thrwnError) {
                alert("Server error: " + stat + ", " + thrwnError);
            }


            // Init j-query
            $(document).ready(function() {
                $( ".dtpicker" ).datepicker({
                    changeMonth: true,
                    changeYear: true,
                    autoSize: true
                });
                $('#btngo').click( function () {
                    // Collect the parameters
                    var parms = new Object();
                    $('.parm').each(function (index) {
                        parms[$(this).attr("id")] = $(this).prop("checked");
                    });
                    $('.dtpicker').each(function (index) {
                        parms[$(this).attr("id")] = $(this).val();
                    });

                    $.ajax(
                    { type: "post",
                        url: "ws_gen.php",
                        data: ({
                            cmd: "recent",
                            parms: parms
                        }),
                        success: handleResponse,
                        error: handleError,
                        datatype: "json"
                    });

                });
            });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
                <h1 style="margin: 10px 5px;">View Recent Changes to Member Information</h1>
                <div id="vrecent" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail" >
                    <div style="margin-top: 15px;">
                        <table>
                            <tr><td class="tdlabel">Start Date:</td><td><input type="text" id ="sdate" class="dtpicker"  VALUE='' size="10" title="Starting date - Inclusive"/></td></tr>
                            <tr><td class="tdlabel">  End Date:</td><td><INPUT TYPE='text' id="edate" class="dtpicker"  VALUE='' size="10" title="Ending date - Inclusive" /></td></tr>
                            <tr><td class="tdlabel" colspan="2">(Leave End Date blank for today)</td></tr>
                        </table>
                    </div>
                    <div style="margin-top: 15px;">
                        <table>
                            <tr><td class="tdlabel">Include New Members</td><td><input type="checkbox" class="parm" id="incnew" checked="checked"/></td></tr>
                            <tr><td class="tdlabel">Include Updates to Existing Members</td><td><input type="checkbox" class="parm" id="incupd" checked="checked"/></td></tr>

                            <tr><td class="tdlabel">Name, Type & Statuses</td><td><input type="checkbox" id="cbname" class="parm" checked="checked"/></td></tr>
                            <tr><td class="tdlabel">Addresses</td><td><input type="checkbox" id="cbaddr" class="parm" checked="checked"/></td></tr>
                            <tr><td class="tdlabel">Phone</td><td><input type="checkbox" id="cbphone" class="parm" checked="checked"/></td></tr>
                            <tr><td class="tdlabel">Email</td><td><input type="checkbox" id="cbemail" class="parm" checked="checked"/></td></tr>
                            <tr><td class="tdlabel">Volunteer Categories</td><td><input type="checkbox" id="cbvol" class="parm" /></td></tr>
                            <tr><td class="tdlabel">Web User</td><td><input type="checkbox" id="cbweb" class="parm" /></td></tr>
                            <tr><td class="tdlabel">Calendar Events</td><td><input type="checkbox" class="parm" id="cbevents" /></td></tr>
                            <?php if ($donationsFlag) { ?>
                                <tr><td class="tdlabel">Donations</td><td><input type="checkbox" class="parm" id="cbdonations" /></td></tr>
                            <?php } ?>
                        </table>
                    </div>
                    <div >
                        <input type="button" id="btngo" value="Go"/>
                    </div>
                </div><div style="clear:both;"></div>
                <div id="result" class="ui-widget ui-widget-content" style="float:left; margin-top:10px;"></div>
            </div>
    </body>
</html>
