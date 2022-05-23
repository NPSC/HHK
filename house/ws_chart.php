<?php
use HHK\sec\WebInit;
use HHK\SysConst\WebPageCode;
use HHK\sec\SecurityComponent;
use HHK\sec\Session;

/**
 * ws_chart.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2013-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
/**
 *  includes and requires
 */
require ("homeIncludes.php");


$wInit = new WebInit(WebPageCode::Service);

/* @var $dbh PDO */
$dbh = $wInit->dbh;
$guestAdmin = SecurityComponent::is_Authorized("guestadmin");
addslashesextended($_REQUEST);
$c = "";

// Get our command
if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_STRING);
}

$uS = Session::getInstance();


$events = array();

try {

    switch ($c) {
        case 'arrTOD':

//             $source = new ChartDataSource();
//             $query = Google\Visualization\DataSource\DataSourceHelper::parseQuery($_REQUEST["cmd"]);
//             $dataTable = $source->generateDataTable($query, $dbh);


            break;

        case 'rmrate':

            $dsn = "mysql:host=10.138.0.21;dbname=overview;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES   => TRUE,
            ];

            $obh = new PDO($dsn, 'overview', 'QfK=0}$<7[qffPIATTS%', $options);
            $obh->exec("SET SESSION wait_timeout = 3600;");



            $stmt = $obh->query("Select Rate, count(Rate) from overview.site group by Rate order by Start_Date;");

            $events = array();
            $events[] = array('Rate', 'Houses at Rate');

            while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {

                $events[] = array('$'.$r[0], intval($r[1]));
            }

            break;


        case 'roomsByHouse':

            $dsn = "mysql:host=10.138.0.21;dbname=overview;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES   => TRUE,
            ];

            $obh = new PDO($dsn, 'overview', 'QfK=0}$<7[qffPIATTS%', $options);
            $obh->exec("SET SESSION wait_timeout = 3600;");


            $stmt = $obh->query("Select Title, Contracted_Rooms from overview.site  order by Start_Date;");

            $events = array();
            $events[] = array('Rooms', 'House Rooms');

            while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {

                $events[] = array($r[0], intval($r[1]));
            }


            break;

        case 'guestsByHouse':

            $dsn = "mysql:host=10.138.0.21;dbname=overview;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES   => TRUE,
            ];

            $obh = new PDO($dsn, 'overview', 'QfK=0}$<7[qffPIATTS%', $options);
            $obh->exec("SET SESSION wait_timeout = 3600;");


            $events[] = array('Year', 'Visit Nights');

            $stmt = $obh->query("Select Title, Db_Schema, Start_Date from site order by Start_Date;");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Cycle years
            for ($y=2014; $y<2022; $y++) {

                $total = 0;
                $houses = 0;

                // Each House
                foreach ($rows as $r) {

                    $startDT = new \DateTime($r['Start_Date']);
                    if ($startDT->format('Y') > $y) {
                        //$events[] = array($r['Title'], 0);
                        continue;
                    }

                    $cnt = 0;
                    $houses++;

                    $schema = $r['Db_Schema'];

                    $stmt = $obh->query("CALL `$schema`.sum_visit_days($y);");

                    while ($c = $stmt->fetch(\PDO::FETCH_NUM)) {
                        $cnt = $c[0];
                    }

                    $total += $cnt;
                    //$events[] = array($r['Title'], intval($cnt));

                }

                reset($rows);
                $events[] = array("$y: $houses", intval($total));
            }
            break;


        case 'incomeByHouse':

            $dsn = "mysql:host=10.138.0.21;dbname=overview;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES   => TRUE,
            ];

            $obh = new \PDO($dsn, 'overview', 'QfK=0}$<7[qffPIATTS%', $options);
            $obh->exec("SET SESSION wait_timeout = 3600;");


            $events[] = array('Year', 'Income');

            $stmt = $obh->query("Select Title, Db_Schema, Start_Date from site order by Start_Date;");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);


            $total = array();
            $houses = 0;

            // Each House
            foreach ($rows as $r) {

                $cnt = 0;
                $houses++;

                $schema = $r['Db_Schema'];

                $stmt = $obh->query("select YEAR(Payment_Date), sum(amount) from $schema.payment where Status_Code = 's' and Is_Refund = 0 group by YEAR(Payment_Date)");

                $rw = $stmt->fetchAll(\PDO::FETCH_NUM);

                foreach ($rw as $m) {

                    if (isset($total[$m[0]])) {
                        $total[$m[0]] += $m[1];
                    } else {
                        $total[$m[0]] = $m[1];
                    }

                }
            }

            foreach ($total as $k => $t) {
                $events[] = array($k, $t);
            }



            break;

        default:

    }

} catch (PDOException $ex) {
    $events = array("error" => "Database Error: " . $ex->getMessage());

} catch (Exception $ex) {
    $events = array("error" => "Programming Error: " . $ex->getMessage());
}



if (is_array($events)) {
    echo (json_encode($events));
} else {
    echo $events;
}

exit();

function roomRates($rows) {
    $dataTable = new DataTable();

    // columns
    $colDescriptors[] = new ColumnDescription('', ValueType::TIMEOFDAY, 'Time of Day');
    $colDescriptors[] = new ColumnDescription('', ValueType::NUMBER, 'Arrivals');
    $dataTable->addColumns($colDescriptors);

    foreach ($rows as $r) {
        $tr = new TableRow();


        $tr->addCell(new TableCell(new TimeOfDayValue($r['value'])));
        $tr->addCell(new TableCell(new TimeOfDayValue($c['value'])));


        $dataTable->addRow($tr);

    }


//     $events = array(
//         "cols" => array(
//             array('id'=>'', 'label'=>'Time of Day', 'type'=>'timeofday'),
//             array('id'=>'', 'label'=>'Arrivals', 'type'=>'number')
//         ),
//         'rows'=> array(
//             array('c' => array(array('v'=>[9,30,0]), array('v' => 4))),
//             array('c' => array(array('v'=>[13,00,0]), array('v' => 7))),
//             array('c' => array(array('v'=>[13,30,0]), array('v' => 6))),
//             array('c' => array(array('v'=>[14,00,0]), array('v' => 3))),
//             array('c' => array(array('v'=>[14,30,0]), array('v' => 10))),
//             array('c' => array(array('v'=>[20,30,0]), array('v' => 13))),
//         )
//     );

    //             $events = '{"cols":[{"id":"", "label": "Time of Day", "type":"timeofday"},{"id":"", "label": "Arrivals", "type": "number"}],
    //                   "rows":[{"c":[{"v": [12,30,0]}, {"v": 4}]}, {"c":[{"v": [13,30,0]}, {"v": 6}]}, {"c":[{"v": [15,45,0]}, {"v": 10}]}, {"c":[{"v": [17,30,0]}, {"v": 7}]}, {"c":[{"v": [19,30,0]}, {"v": 6}]}]}';

}
