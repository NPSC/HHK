<?php
/**
 * AccessLog.php
 *
-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2018 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");
require (CLASSES . 'CreateMarkupFromDB.php');

$wInit = new webInit();
$dbh = $wInit->dbh;

$menuMarkup = $wInit->generatePageMenu();

//disable inactive users
$stmt = $dbh->query("select * from w_users");
if ($stmt->rowCount() > 0) {
    $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach($users as $user){
        $user = UserClass::disableInactiveUser($dbh, $user);
    }
}

// View user log
$log = '';
$users = array();
$actions = array();
$userNameDate = '';

if (isset($_POST['btnAccess'])) {

    $whereStr = '';


    $dte = filter_var($_POST['aclogdate'], FILTER_SANITIZE_STRING);

    if ($dte != '') {
        $userNameDate = date('M j, Y', strtotime($dte));
        $whereStr = " and DATE(Access_Date) = DATE('" . date('Y-m-d', strtotime($dte)) . "') ";
    }

    if (isset($_POST['selUsers'])) {
        $userStr = '';
        $postUsers = filter_var_array($_POST['selUsers']);

        foreach ($postUsers as $u) {
            if ($u != '') {
                $userStr .= ($userStr == '' ? "'" : ",'") . $u . "'";
                $users[$u] = $u;
            }
        }

        if ($userStr != '') {
            $whereStr .= " and w.idName in (" . $userStr . ")";
        }
    }

    if (isset($_POST['selActions'])) {
        $userStr = '';
        $postActions = filter_var_array($_POST['selActions']);

        foreach ($postActions as $u) {
            if ($u != '') {
                $userStr .= ($userStr == '' ? "'" : ",'") . $u . "'";
                $actions[$u] = $u;
            }
        }

        if ($userStr != '') {
            $whereStr .= " and l.Action in (" . $userStr . ")";
        }
    }

    $stmt = $dbh->query("Select w.idName as Id, l.Username, l.IP, l.Access_Date, l.`Action` from w_user_log l left join w_users w on l.Username = w.User_Name WHERE 1=1 $whereStr order by Access_Date DESC Limit 100;");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $edRows = array();
    $actNames = array(
        'L'=>"Login",
        'PS' => "Set Password",
        'PC' => "Changed Password",
        'PL' => "Locked Out"
    );
    
    foreach ($rows as $r) {

        $r['Date'] = date('M j, Y H:i:s', strtotime($r['Access_Date']));

        if ($r['Id'] > 0) {
            $r['Id'] = HTMLContainer::generateMarkup('a', $r['Id'], array('href'=>'NameEdit.php?id='.$r['Id']));
        }

        unset($r['Access_Date']);
        
        if(isset($actNames[$r["Action"]])){
            $r['Action'] = $actNames[$r["Action"]];
        }

        $edRows[] = $r;
    }

    $log = CreateMarkupFromDB::generateHTML_Table($edRows, 'userlog');

}

$usernames = HTMLSelector::generateMarkup(HTMLSelector::getLookups($dbh, "select idName, User_Name from w_users", $users, TRUE), array('name'=>'selUsers[]', 'multiple'=>'multiple', 'size'=>'5'));

$actOpts = array(
    0=>array(0=>'L', 1=>'Logins'),
    1=>array(0=>'PS', 1=>'Set Password'),
    2=>array(0=>'PC', 1=>'Change Password'),
    3=>array(0=>'PL', 1=>'Lock out'),

);

$actionsel = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($actOpts, $actions, TRUE), array('name'=>'selActions[]', 'multiple'=>'multiple', 'size'=>'4'));
?>
<!DOCTYPE html >
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo FAVICON; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        

        <script type="text/javascript">
            $(document).ready(function() {

				function getActionName(data) {
					switch(data) {
                    	case 'L':
                        	return "Login";
                        	break;
                    	case 'PS':
                        	return "Set Password";
                    		break;
                    	case 'PC':
                        	return "Password Change";
                        	break;
                    	case "PL":
                        	return "Locked Out";
                        	break;
                        default:
                            return data;
                            break;
                    }
				}
            	
            	var dtCols = [
                    {
                        "targets": [0],
                        title: "Username",
                        data: "Username",
                        sortable: true,
                        searchable: true
                    },
                    {
                        "targets": [1],
                        title: "IP",
                        data: 'IP',
                        sortable: true,
                        searchable: true
                    },
                    {
                        "targets": [2],
                        title: "Action",
                        searchable: true,
                        sortable: true,
                        data: "Action",
                        render: function (data, type) {
                            return getActionName(data);
                        }
                    },
                    {
                        "targets": [3],
                        title: "Date",
                        searchable: true,
                        sortable: true,
                        data: "Date",
                        render: function (data, type) {
                            return dateRender(data, type, 'MMM D, YYYY h:mm a');
                        }
                    }
                ];
            	
            	var tableAttrs = {
                    class: 'display compact',
                    width: '100%'
                }

                var dtTable = $('#dtLog')
                        .DataTable({
                            "columnDefs": dtCols,
                            "serverSide": true,
                            "processing": true,
                            "deferRender": true,
                            "language": {"sSearch": "Search Access Log :"},
                            "sorting": [[3, 'desc']],
                            "paging": true,
                            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                            //"dom": '<"dtTop"if>rt<"dtBottom"lp><"clear">',
                            "Dom": '<"top"ilf>rt<"bottom"ip>',
                            ajax: {
                                url: "ws_gen.php",
                                data: {
                                    'cmd': 'accesslog'
                                },
                            },
                            initComplete: function () {
                                this.api().columns().every( function () {
                                    var column = this;
                                    var select = $('<select style="max-width: 100%"><option value="" selected>No Filter</option></select>')
                                        .appendTo( $(column.header()) )
                                        .on( 'change', function () {
                                            var val = $.fn.dataTable.util.escapeRegex(
                                                $(this).val()
                                            );
                     
                                            column
                                                .search( val ? val : '')
                                                .draw();
                                        } );
                                    select.click( function(e) {
                                        e.stopPropagation();
                                  	});
                                    if(column.index() == 2){
                                    	column.data().unique().sort().each( function ( d, j ) {
                                        	console.log(d);
                                        	select.append( '<option value="'+d+'">'+getActionName(d)+'</option>' )
                                    	} );
                                    }else{
                                    	column.data().unique().sort().each( function ( d, j ) {
                                        	select.append( '<option value="'+d+'">'+d+'</option>' )
                                    	} );
                                    }
                                } );
                            }
                        });
    				
                
                $( "input.autoCal" ).datepicker({
                    changeMonth: true,
                    changeYear: true,
                    autoSize: true,
                    dateFormat: 'M d, yy'
                });
            });
        </script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
<?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <form method="POST">
            <div class="ui-widget ui-widget-content hhk-tdbox" style="float:left;">
                <table>
                    <tr>
                        <td>Choose a date (leave empty for most recent entries):</td>
                        <td><input type="text" id ="aclogdate" class="autoCal" name="aclogdate" VALUE='<?php echo $userNameDate; ?>' /></td>
                    </tr>
                    <tr>
                        <td >Choose one or more usernames:</td>
                        <td><?php echo $usernames; ?></td>

                    </tr>
                    <tr>
                        <td >Choose one or more Actions</td>
                        <td><?php echo $actionsel; ?></td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align: center;"><input name="btnAccess" id="btnAccess" type="submit" value="View Access Log" style="margin:3px;"/></td>
                    </tr>
                </table>

				<div style="margin: 10px;">
					<table style="width:100%;" class="display ignrSave" id="dtLog"></table>
				</div>
                <div style="margin-top:10px;">
                    <?php echo $log; ?>
                </div>

            </div>
            </form>
        </div>

    </body>
</html>
