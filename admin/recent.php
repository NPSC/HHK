<?php

use HHK\sec\{SecurityComponent, WebInit, Session};

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

$uS = Session::getInstance();





?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

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
                        flagAlertMessage(data.error, true);
                    } else if (data.success) {
                        $('div#result').html(data.success).show();
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

                $('#btngo').button();

                $('form#vrecent').on('submit', function (e) {
                    e.preventDefault();
                    var data = $(this).serializeArray();
                    data.push({name:'cmd',value:'recent'});

                    $.ajax({
                        type: "post",
                        url: "ws_gen.php",
                        data: data,
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
                <form id="vrecent" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail" >
                    <div style="margin-top: 15px;">
                        <table>
                            <tr><td class="tdlabel">Start Date:</td><td><input type="text" name="sdate" class="dtpicker"  VALUE='' size="10" title="Starting date - Inclusive"/></td></tr>
                            <tr><td class="tdlabel">  End Date:</td><td><INPUT TYPE='text' name="edate" class="dtpicker"  VALUE='' size="10" title="Ending date - Inclusive" /></td></tr>
                            <tr><td class="tdlabel" colspan="2">(Leave End Date blank for today)</td></tr>
                            <tr>
                                <td class="tdlabel">Include:</td>
                                <td>
                                    <select name="includeTbl[]" multiple="multiple" size="8">
                                        <option value="name" selected>Name, Type & Statuses</option>
                                        <option value="addr" selected>Addresses</option>
                                        <option value="phone" selected>Phone</option>
                                        <option value="email" selected>Email</option>
                                        <?php echo ($uS->volunteer ? '<option value="vol">Volunteer Categories</option>':''); ?>
                                        <option value="web">Web User</option>
                                        <?php echo ($uS->volunteer ? '<option value="events">Calendar Events</option>':''); ?>
                                        <?php if($donationsFlag){
                                            echo '<option value="donations">Donations</option>';
                                        } ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div style="margin-top: 15px;">
                        <table>
                            <tr><td class="tdlabel">Include New Members</td><td><input type="checkbox" class="parm" name="incnew" checked="checked"/></td></tr>
                            <tr><td class="tdlabel">Include Updates to Existing Members</td><td><input type="checkbox" class="parm" name="incupd" checked="checked"/></td></tr>
                        </table>
                    </div>
                    <div >
                        <input type="submit" id="btngo" value="Go"/>
                    </div>
                </form>
                <div id="result" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail" style="margin:10px 0; display: none;"></div>
            </div>
    </body>
</html>
