<?php
/**
 * PageEdit.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("AdminIncludes.php");
require (DB_TABLES . 'WebSecRS.php');

require(SEC . 'Pages.php');
require(REL_BASE_DIR . "classes" . DS . "selCtrl.php");

$wInit = new webInit();

$dbh = $wInit->dbh;

$page = $wInit->page;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();


$siteMarkup = "";
$webSite = '';

// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(alertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("uh-oh");

// Edit pages
if (isset($_POST["btnSubmit"])) {

    if (SecurityComponent::is_TheAdmin()) {

        try {

            $webSite = Pages::editPages($dbh, $_POST);

        } catch (Exception $ex) {

            $alertMsg->set_Text("Error: " . $ex->getMessage());
            $alertMsg->set_Context(alertMessage::Alert);
            $alertMsg->set_DisplayAttr("block");

        }
    } else {

        $alertMsg->set_Text("Unauthorized for Edit");
        $alertMsg->set_Context(alertMessage::Notice);
        $alertMsg->set_DisplayAttr("block");
    }
}

// create web site table
$stmt = $dbh->query("Select * from web_sites");


if ($stmt->rowCount() > 0) {
    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
        $siteMarkup .= "<tr><td><input type='button' id='loadpages" . $r["Site_Code"] . "' name='" . $r["Site_Code"] . "' value='View Pages' class='loadPages'/></td>
            <td>" . $r["Description"] . "</td>
            <td><input type='button' id='editsite" . $r["Site_Code"] . "' name='" . $r["Site_Code"] . "' value='Edit' class='editSite'/></td>
            <td>" . $r["Site_Code"] . "</td>
            <td>" . $r["HTTP_Host"] . "</td>
            <td>" . $r["Relative_Address"] . "</td>
            <td>" . $r["Required_Group_Code"] . "</td>
            <td>" . $r["Path_To_CSS"] . "</td>

            <td>" . $r["Default_Page"] . "</td>
            <td>" . $r["Index_Page"] . "</td>
            <td>" . $r["Updated_By"] . "</td>
            <td>" . date("m/d/Y", strtotime($r["Last_Updated"])) . "</td>
            </tr>";
    }

 } else {
     throw new Hk_Exception_Runtime("web_sites records not found.");
 }


$siteMarkup = "<table><tr><th>View Pages</th><th>Site</th><th>Edit</th>
    <th>Code</th><th>Host Address</th><th>Rel. Address</th><th>Authorization</th><th>CSS</th>
    <th>Default Page</th><th>Index Page</th><th>Updated By</th><th>Last Updated</th></tr>" . $siteMarkup . "</table>";

$stmtp = $dbh->query("select Group_Code as Code, Title as Description from w_groups");
$grps = $stmtp->fetchAll(\PDO::FETCH_NUM);
$securityCodes = doOptionsMkup($grps, 'xz');

$resultMessage = $alertMsg->createMarkup();
$getSiteReplyMessage = '';
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
        <?php echo DEFAULT_CSS; ?>
        <link href="<?php echo JQ_DT_CSS ?>" rel="stylesheet" type="text/css" />
<?php echo MULTISELECT_CSS; ?>
        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="../js/jquery.multiselect.min.js"></script>

        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript">
    function getPages(site) {
        "use strict";

        $.getJSON("ws_gen.php", {page: site, cmd: 'gpage'})
            .done(function(data, status, xhr) {

                if (data && data.error) {

                    if (data.gotopage) {
                        window.open(data.gotopage);
                    }

                    flagAlertMessage(data.error, true);
                    return;
                }

                $('#sitepages').children().remove().end().append($(data.success));
                $('#frmPages').show();

                $('#tblPages').dataTable({
                    "displayLength": 50,
                    "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]]
                    , "Dom": '<"top"ilf>rt<"bottom"ip>'
                });

                $('select.hhk-multisel').each( function () {
                    $(this).multiselect({
                        selectedList: 3
                    });
                });
            })
            .fail();

    }
    // Init j-query and the page blocker.
    $(document).ready(function() {

        var website = '<?php echo $webSite; ?>';

        $('.editSite, .loadPages, #btnReset, #btnSubmit').button();

        $('#siteDialog').dialog({
            autoOpen: false,
            width: 550,
            resizable: true,
            modal: true,
            buttons: {
                "Save Site": function() {
                    var parms = new Object();
                    $('.spd').each(function(index) {
                        parms[$(this).attr("id")] = $(this).val();
                    });

                    $.post("ws_gen.php", {cmd: 'edsite',parms: parms},
                        function (data) {

                    });
                },
                "Exit": function() {
                    $('body').css('cursor', "auto");
                    $( "#siteContainer" ).hide();
                    $( this ).dialog( "close" );
                }
            },
            close: function() {}
        });

        $('input.loadPages').click(function() {
            $("#divAlert1").hide();
            getPages($(this).attr("name"));
        });

        if (website != '') {
            getPages(website);
        }

        $('input.editSite').click(function() {
            // input control is in a td in a tr.
                    var tds = $(this).parent().parent().children('td');

                    $('#inDescription').val(tds[1].innerHTML);
                    $('#inSiteCode').val(tds[3].innerHTML);
                    $('#inHostAddr').val(tds[4].innerHTML);
                    $('#inRelAddr').val(tds[5].innerHTML);
                    $('#inCss').val(tds[7].innerHTML);
                    $('#inJs').val(tds[8].innerHTML);
                    $('#inDefault').val(tds[9].innerHTML);
                    $('#inIndex').val(tds[10].innerHTML);
                    $('#inUpBy').val(tds[11].innerHTML);
                    //$('#inLastUp').val(tds[12].innerHTML);
                    // Security codes
                    $('#siteSecCode option').each( function() {
                        if ($(this).val() == tds[6].innerHTML)
                            $(this).attr('selected', 'selected');
                    });

                    $('#siteDialog').dialog( "option", "title", "Edit Web Site: " +tds[1].innerHTML);
                    $('#siteDialog').dialog( 'open' );
        });

    }); // end of doc load
        </script>
    </head>
    <body <?php if ($testVersion) {echo "class='testbody'";} ?>>
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-member-detail" style="margin-bottom: 10px; margin-right: 10px;">
                <?php echo $siteMarkup; ?>
            </div>
            <div id="divAlertMsg" style="clear:both;"><?php echo $resultMessage; ?></div>
            <form id="frmPages" action="#" method="post" style="display:none;">
                <div id="sitepages" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail" style="margin-bottom: 10px;"></div>
                <div id="divSubmitButtons" class="ui-corner-all">
                    <input type="reset" name="btnReset" value="Reset" id="btnReset" />
                    <input type="submit" name="btnSubmit" value="Save" title="Save all changes." id="btnSubmit" />
                </div>
            </form>
        </div>  <!-- /content -->
        <div id="siteDialog" style="display:none;">
            <table>
                <tr>
                    <th>Description</th>
                    <td><input type="text" id="inDescription" class="spd" value=""/></td>
                </tr>
                <tr>
                    <th>Site Code</th>
                    <td><input type="text" id="inSiteCode" class="spd" value="" readonly="readonly"/></td>
                </tr>
                <tr>
                    <th>Host Address</th>
                    <td><input type="text" id="inHostAddr" class="spd" value=""/></td>
                </tr>
                <tr>
                    <th>Relative Address</th>
                    <td><input type="text" id="inRelAddr" class="spd" value=""/></td>
                </tr>
                <tr>
                    <th>CSS Path</th>
                    <td><input type="text" id="inCss" class="spd" value=""/></td>
                </tr>
                <tr>
                    <th>Default Page</th>
                    <td><input type="text" id="inDefault" class="spd" value=""/></td>
                </tr>
                <tr>
                    <th>Index Page</th>
                    <td><input type="text" id="inIndex" class="spd" value=""/></td>
                </tr>
                <tr>
                    <th>Updated By</th>
                    <td><input type="text" id="inUpBy" value="" readonly="readonly"/></td>
                </tr>
                <tr>
                    <th>Last Updated</th>
                    <td><input type="text" id="inLastUp" value="" readonly="readonly"/></td>
                </tr>
                <tr>
                    <th>Group Code</th>
                    <td><select id="siteSecCode" class="spd" multiple="multiple" size="5"><?php echo $securityCodes; ?></select></td>
                </tr>
                <tr><td>
                        <?php echo $getSiteReplyMessage; ?>
                    </td></tr>
            </table>
        </div>
    </body>
</html>
