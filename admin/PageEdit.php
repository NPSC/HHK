<?php
/**
 * PageEdit.php
 *
 * @category  Configuration
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

require_once ("AdminIncludes.php");


require_once(REL_BASE_DIR . "classes" . DS . "selCtrl.php");

$wInit = new webInit();

$dbh = $wInit->dbh;
//$dbh = new PDO($dsn, $username, $passwd, $options);
$page = $wInit->page;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();


$siteMarkup = "";
// create web site table
$stmt = $dbh->query("Select * from web_sites");


if ($stmt->rowCount() > 0) {
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $siteMarkup .= "<tr><td><input type='button' id='loadpages" . $r["Site_Code"] . "' name='" . $r["Site_Code"] . "' value='View Pages' class='loadPages'/></td>
            <td>" . $r["Description"] . "</td>
            <td><input type='button' id='editsite" . $r["Site_Code"] . "' name='" . $r["Site_Code"] . "' value='Edit' class='editSite'/></td>
            <td>" . $r["Site_Code"] . "</td>
            <td>" . $r["HTTP_Host"] . "</td>
            <td>" . $r["Relative_Address"] . "</td>
            <td>" . $r["Required_Group_Code"] . "</td>
            <td>" . $r["Path_To_CSS"] . "</td>
            <td>" . $r["Path_To_JS"] . "</td>
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
    <th>Code</th><th>Host Address</th><th>Rel. Address</th><th>Sec. Code</th><th>CSS</th>
    <th>JS</th><th>Default Page</th><th>Index Page</th><th>Updated By</th><th>Last Updated</th></tr>" . $siteMarkup . "</table>";

$pageTypes = DoLookups($dbh, "Page_Type", '', false);

$stmt = $dbh->query("select Group_Code as Code, Title as Description from w_groups");
$grps = $stmt->fetchAll();
$securityCodes = doOptionsMkup($grps, 'xz');



$webAlert = new alertMessage("webContainer");
$webAlert->set_DisplayAttr("none");
$webAlert->set_Context(alertMessage::Success);
$webAlert->set_iconId("webIcon");
$webAlert->set_styleId("webResponse");
$webAlert->set_txtSpanId("webMessage");
$webAlert->set_Text("oh-oh");

$getWebReplyMessage = $webAlert->createMarkup();

$webAlert = new alertMessage("siteContainer");
$webAlert->set_DisplayAttr("none");
$webAlert->set_Context(alertMessage::Success);
$webAlert->set_iconId("siteIcon");
$webAlert->set_styleId("siteResponse");
$webAlert->set_txtSpanId("siteMessage");
$webAlert->set_Text("oh-oh");

$getSiteReplyMessage = $webAlert->createMarkup();
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
        <link href="css/default.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo JQ_DT_CSS ?>" rel="stylesheet" type="text/css" />
<?php echo TOP_NAV_CSS; ?>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript">
            var pageId, webSite, listTable;
            // Init j-query and the page blocker.
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
                webSite = "";
                $('#pageDialog').dialog({
                    autoOpen: false,
                    width: 550,
                    resizable: true,
                    modal: true,
                    buttons: {
                        "Save Page": function() {
                            var parms = new Object();
                            $('.pada').each(function(index) {
                                parms[$(this).attr("id")] = $(this).val();
                            });
                            parms["pid"] = pageId;
                            parms["website"] = webSite;
                            $.ajax(
                            { type: "POST",
                                url: "ws_gen.php",
                                data: ({
                                    cmd: 'edpage',
                                    parms: parms
                                }),
                                success: handlePageEdit,
                                error: handleError,
                                datatype: "json"
                            });
                        },
                        "Delete Page": function() {
                            if (confirm("Confirm deleting Page " + pageId + "?" )) {
                                $.ajax(
                                { type: "POST",
                                    url: "ws_gen.php",
                                    data: ({
                                        cmd: 'delpage',
                                        pid: pageId
                                    }),
                                    success: handlePageEdit,
                                    error: handleError,
                                    datatype: "json"
                                });
                                $( this ).dialog( "close" );
                            }
                        },
                        "Exit": function() {
                            $('body').css('cursor', "auto");
                            $( this ).dialog( "close" );
                        }
                    },
                    close: function() {}
                });
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

                            $.ajax(
                            { type: "POST",
                                url: "ws_gen.php",
                                data: ({
                                    cmd: 'edsite',
                                    parms: parms
                                }),
                                success: handleSiteEdit,
                                error: handleError,
                                datatype: "json"
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
                    webSite = $(this).attr("name");
                    getPages(webSite);
                    $('.loadPages').parent().css("background-color", "white");
                    $(this).parent().css("background-color", "purple");
                });
                $('#btnNewPage').click( function() {
                    if (webSite == "")
                        return;
                    pageId = 'new';
                    $('#inFileName').val('');
                    $('#inTitle').val('');
                    $('#inLogin').val('0');
                    $('#inPmenu').val('');
                    $('#inPosMenu').val('');
                    $('#pageDialog').dialog( "option", "title", "<h1>Edit New Page</h1>");
                    $('#pageDialog').dialog( 'open' );
                });

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
                    $('#inLastUp').val(tds[12].innerHTML);
                    // Security codes
                    $('#siteSecCode option').each( function() {
                        if ($(this).val() == tds[6].innerHTML)
                            $(this).attr('selected', 'selected');
                    });

                    $('#siteDialog').dialog( "option", "title", "<h1>Edit Web Site: " +tds[1].innerHTML+ "</h1>");
                    $('#siteDialog').dialog( 'open' );
                });

            }); // end of doc load
            function getPages(pag) {
                "use strict";
                $.ajax({
                    type: "POST",
                    url: "ws_gen.php",
                    data: ({
                        page: pag,
                        cmd: "gpage"
                    }),
                    success: handle_gpage,
                    error: handleError,
                    datatype: "json"
                });

            }
            // onclick for page ID buttons.
            function pageEditButton( pId ) {
                "use strict";
                // load page with data
                var trow = $('#b_' + pId).parent().parent();
                var rowTitle = populatePageEdit(trow);
                pageId = pId;

                $('#pageDialog').dialog( "option", "title", "<h1>Edit Page: " + rowTitle + "</h1>");
                $('#pageDialog').dialog( 'open' );
            }
            function populatePageEdit(trow) {
                "use strict";
                var $kids = trow.children('td');
                $('#inFileName').val($kids[1].innerHTML);
                $('#inLogin').val($kids[2].innerHTML);
                $('#inTitle').val($kids[3].innerHTML);
                //$('#inAddress').val($kids[3].innerHTML);

                // parent menu page
                $('#inPmenu').val($kids[4].innerHTML);
                $('#inPosMenu').val($kids[5].innerHTML);

                // page type
                $('#selType option').each( function() {
                    if ($(this).text() == $kids[6].innerHTML)
                            $(this).attr('selected', 'selected');
                });
                // Security codes
                $('#selSecCode option').each( function() {
                    var codes = $kids[7].innerHTML.split(',');

                    for (var i = 0; i < codes.length; i++) {
                        if ($(this).text() == codes[i]) {
                            $(this).attr('selected', 'selected');
                        }
                    }
//                    if ($(this).text() == $kids[7].innerHTML)
//                        $(this).attr('selected', 'selected');
                });
                return $kids[1].innerHTML;
            }
            function handleError(xhrObject, stat, thrwnError) {
                "use strict";
                alert("Server error: " + stat + ", " + thrwnError);
            }

            function handleSiteEdit(dataTxt, statusTxt, xhrObject) {
                "use strict";
                if (statusTxt != "success") {
                    alert('Server had a problem.  ' + xhrObject.status + ", "+ xhrObject.responseText);
                }
                else {
                    if (dataTxt) {
                        var data = $.parseJSON(dataTxt);
                        var wasError = false, r = '';

                        if (!data) {
                            wasError = true;
                            r = "null response";
                        } else if (data.error) {
                            wasError = true;
                            r = data.error;
                        } else if (data.success) {
                            wasError = false;
                            r = data.success;
                        }


                        var spn = document.getElementById('siteMessage');

                        if (!wasError) {
                            // renew the page

                            // define the error message markup
                            $('#siteResponse').removeClass("ui-state-error").addClass("ui-state-highlight")
                            //$('#webContainer').attr("style", "display:block;");
                            $('#siteIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");
                            spn.innerHTML = "Okay: "+r;
                            $( "#siteContainer" ).show( "slide", {}, 200, alertCallback('siteContainer'));
                        }
                        else {
                            // define the success message markup
                            $('siteResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
                            //$('#webContainer').attr("style", "display:block;");
                            $('#siteIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
                            spn.innerHTML = "<strong>Error: </strong>"+r;
                            $( "#siteContainer" ).show( "slide", {}, 200, alertCallback('siteContainer'));
                        }
                    }
                }
            }

            function handlePageEdit(dataTxt, statusTxt, xhrObject) {
                "use strict";
                if (statusTxt != "success") {
                    alert('Server had a problem.  ' + xhrObject.status + ", "+ xhrObject.responseText);
                }
                else {
                    var data = $.parseJSON(dataTxt);
                    var wasError = false, r = '';

                    if (data.error) {
                        wasError = true;
                        r = data.error;
                    }

                    if (data.success) {
                        wasError = false;
                        r = data.success;
                    }


                    var spn = document.getElementById('webMessage');

                    if (!wasError) {
                        // renew the page
                        getPages(webSite);
                        // define the error message markup
                        $('#webResponse').removeClass("ui-state-error").addClass("ui-state-highlight")
                        //$('#webContainer').attr("style", "display:block;");
                        $('#webIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");
                        spn.innerHTML = "Okay: "+r;
                        $( "#webContainer" ).show( "slide", {}, 200, alertCallback('webContainer'));
                    }
                    else {
                        // define the success message markup
                        $('webResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
                        //$('#webContainer').attr("style", "display:block;");
                        $('#webIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
                        spn.innerHTML = "<strong>Error: </strong>"+r;
                        $( "#webContainer" ).show( "slide", {}, 200, alertCallback('webContainer'));
                    }
                }
            }

            function alertCallback(containerId) {
            "use strict";
                setTimeout(function() {
                    $( "#"+containerId+":visible" ).removeAttr( "style" ).fadeOut(500);
                }, 3500
            );
            };

            // cmd = gpages - get pages
            function handle_gpage(data, statusTxt, xhrObject) {
                "use strict";
                if (statusTxt != "success") {
                    alert('Server had a problem.  ' + xhrObject.status + ", "+ xhrObject.responseText);
                }
                else {
                    if (data) {
                        var dataObj = $.parseJSON(data);
                        var ecomsg;
                        var wasError = true;

                        if (dataObj.error) {
                            ecomsg = dataObj.error;
                        }
                        else if (dataObj.warning) {
                            ecomsg = dataObj.warning
                        }
                        else if (dataObj.success) {
                            ecomsg = dataObj.success
                            wasError = false;
                        }

                        if (wasError) {
                            // error message
                            $('#sitepages').html(ecomsg);
                        }
                        else {
                            // dump data onto page.
                            $('#sitepages').html(ecomsg);
                            $('#btnNewPage').css("display", "block");
                            try {
                                listTable = $('#tblPages').dataTable({
                                    "iDisplayLength": 50,
                                    "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]]
                                    , "Dom": '<"top"ilf>rt<"bottom"ip>'
                                });
                            } catch (err) {}

                        }
                    }
                }
            }
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-member-detail" style="margin-bottom: 10px; margin-right: 10px;">
                <?php echo $siteMarkup; ?>
                <input type="button" id="btnNewPage" value="New Page" style="display:none;"/>
            </div>
            <div id="sitepages" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail" style="margin-bottom: 10px;">
                <table id="tblPages" class="display"><thead></thead><tbody></tbody></table>
            </div>
        </div>  <!-- /content -->
        <div id="pageDialog">
            <table>
                <tr>
                    <th class="tdlabel">File Name</th>
                    <td><input type="text" id="inFileName" class="pada" value="" /></td>
                </tr>
                <tr>
                    <th class="tdlabel">Log-in Page</th>
                    <td><input type="text" id="inLogin" class="pada" value="" /></td>
                </tr>
                <tr>
                    <th class="tdlabel">Title</th>
                    <td><input type="text" id="inTitle" class="pada" value="" /></td>
                </tr>
                <tr>
                    <th class="tdlabel">Parent Menu Page</th>
                    <td><input type="text" id="inPmenu" class="pada" value="" /></td>
                </tr>
                <tr>
                    <th class="tdlabel">Menu Position</th>
                    <td><input type="text" id="inPosMenu" class="pada" value="" /></td>
                </tr>
                <tr>
                    <th class="tdlabel">Page Type</th>
                    <td><select id="selType" class="pada" ><?php echo $pageTypes; ?></select></td>
                </tr>
                <tr>
                    <th class="tdlabel">Security Code</th>
                    <td><select id="selSecCode" class="pada" multiple="multiple" size="5"><?php echo $securityCodes; ?></select></td>
                </tr>
                <tr><td>
                        <?php echo $getWebReplyMessage; ?>
                    </td></tr>
            </table>
        </div>
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
                    <th>JS Path</th>
                    <td><input type="text" id="inJs" class="spd" value=""/></td>
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
