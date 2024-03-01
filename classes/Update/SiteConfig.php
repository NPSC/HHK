<?php
namespace HHK\Update;

use DateTime;
use HHK\Exception\ValidationException;
use HHK\House\OperatingHours;
use HHK\HTMLControls\{HTMLTable, HTMLInput, HTMLSelector, HTMLContainer};
use HHK\Exception\RuntimeException;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\TableLog\HouseLog;
use HHK\Tables\EditRS;
use HHK\Tables\House\Desig_HolidaysRS;
use HHK\sec\{Session, SysConfig};
use HHK\US_Holidays;
use HHK\sec\Labels;
use HHK\sec\SecurityComponent;
use HHK\sec\WebInit;

/**
 * SiteConfig.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @author    William Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017, 2018-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of SiteConfig
 * Contains logic and markup to view and edit the HHK site config file.
 *
 * @author Eric
 */
class SiteConfig {

    public static function createHolidaysMarkup(\PDO $dbh, $resultMessage) {

        $gArray = array( 1 => array(1, 'Jan'),
            2 => array(2, 'Feb'),
            3 => array(3, 'Mar'), 4 => array(4, 'Apr'), 5 => array(5, 'May'), 6 => array(6, 'Jun'),
            7 => array(7, 'Jul'), 8 => array(8, 'Aug'), 9 => array(9, 'Sep'), 10 => array(10, 'Oct'), 11 => array(11, 'Nov'), 12 => array(12, 'Dec'));

        $tbl = new HTMLTable();
        $trs = array();
        $opts = array(
            array('true', 'True'),
            array('false', 'False')
        );


        // Show sys config parms
        $stbl = new HTMLTable();

        $r = SysConfig::getKeyRecord($dbh, WebInit::SYS_CONFIG, 'UseCleaningBOdays');
        $inpt = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($opts, $r['Value'], FALSE), array('name' => 'sys_config' . '[' . $r['Key'] . ']'));
        $stbl->addBodyTr(HTMLTable::makeTd($r['Key'].':', array('class' => 'tdlabel')) . HTMLTable::makeTd($inpt . ' ' . $r['Description']));

        $r = SysConfig::getKeyRecord($dbh, WebInit::SYS_CONFIG, 'Show_Holidays');
        $inpt = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($opts, $r['Value'], FALSE), array('name' => 'sys_config' . '[' . $r['Key'] . ']'));
        $stbl->addBodyTr(HTMLTable::makeTd($r['Key'].':', array('class' => 'tdlabel')) . HTMLTable::makeTd($inpt . ' ' . $r['Description']));

        $r = SysConfig::getKeyRecord($dbh, WebInit::SYS_CONFIG, 'Show_Closed');
        $inpt = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($opts, $r['Value'], FALSE), array('name' => 'sys_config' . '[' . $r['Key'] . ']'));
        $stbl->addBodyTr(HTMLTable::makeTd($r['Key'].':', array('class' => 'tdlabel')) . HTMLTable::makeTd($inpt . ' ' . $r['Description']));

        $year = (int) date("Y");
        $year--;
        $start = TRUE;

        // Columns
        for ($y = $year; $y < $year+4; $y++) {

            $holidays = new US_Holidays($dbh, $y);

            foreach ($holidays->get_list() as $k => $holiday) {

                if ($start) {
                    $trs[$k] = HTMLTable::makeTd($holiday["name"], array('style'=>'text-align:right;'));
                    $attr = array('type'=>'checkbox', 'name'=>'cbhol[' .$k. ']');

                    if ($holiday['use'] == '1') {
                        $attr['checked'] = 'checked';
                    }
                    $trs[$k] .= HTMLTable::makeTd(HTMLInput::generateMarkup('', $attr), array('style'=>'text-align:center;'));
                }

                if ($holiday['use'] != '1') {
                    $trs[$k] .= HTMLTable::makeTd('');

                } else if ($holiday['type'] == US_Holidays::Designated) {

                    if ($holiday['timestamp'] == 0) {
                        $m = '';
                        $d = '';
                    } else {
                        $m = date('n', $holiday['timestamp']);
                        $d = date('j', $holiday['timestamp']);
                    }

                    $trs[$k] .= HTMLTable::makeTd(
                            HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($gArray, $m), array('name' => 'selMonth_' .$y . '[' . $k. ']'))
                            .HTMLInput::generateMarkup($d, array('name'=>'tbday_' .$y . '[' . $k. ']', 'size'=>'3')));
                } else {
                    $trs[$k] .= HTMLTable::makeTd($holiday['timestamp'] == 0 ? '' : date("D, M j", $holiday["timestamp"]));
                }
            }
            $start = FALSE;

        }

        foreach ($trs as $k => $tr) {
            $tbl->addBodyTr($tr);
        }

        if ($resultMessage != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($resultMessage, array('colspan'=>'4', 'style'=>'text-align:center; font-weight:bold;')));
        }

        $tbl->addHeader(HTMLTable::makeTh('Holiday') . HTMLTable::makeTh('Enable') .HTMLTable::makeTh($year++)
                . HTMLTable::makeTh($year++) . HTMLTable::makeTh($year++) . HTMLTable::makeTh($year++));

        $operatingHours = new OperatingHours($dbh);

        return HTMLContainer::generateMarkup('h3', 'Configuration Parameters') . $stbl->generateMarkup() . '<br/>'
        . HTMLContainer::generateMarkup('h3', 'Annual Holidays') . $tbl->generateMarkup()
        . $operatingHours->getEditMarkup();
    }

    public static function checkUploadFile($upFile) {


        // Undefined | Multiple Files | $_FILES Corruption Attack
        // If this request falls under any of them, treat it invalid.
        if (
                !isset($_FILES[$upFile]['error']) ||
                is_array($_FILES[$upFile]['error'])
        ) {
            throw new RuntimeException('Invalid upload file parameters.');
        }

        // Check $_FILES['upfile']['error'] value.
        switch ($_FILES[$upFile]['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException('Upload file was not received.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('Exceeded upload filesize limit.');
            default:
                throw new RuntimeException('Unknown file upload errors.');
        }

        // You should also check filesize here.
        if ($_FILES[$upFile]['size'] > 10000000) {
            throw new RuntimeException('Exceeded filesize limit.');
        }

    }

    /**
     * Summary of loadZipCodeFile
     * @param \PDO $dbh
     * @param mixed $file
     * @return string
     */
    public static function loadZipCodeFile(\PDO $dbh, $file) {

        $content = self::readZipFile($file);

        $lines = explode("\n", $content);

        // Remove the first line - headings
        array_shift($lines);

        // Delete old table contents
        if (count($lines) > 30000) {
            $dbh->exec("delete from postal_codes;");
        } else {
            return "File size is too small.";
        }


        $query = '';

        $indx = 0;
        $recordCounter = 0;
        $maxRecords = 10000;

        // 2020 download file structure.
        // zip, type, decommissioned, primary_city, acceptable_cities, unacceptable_cities, state, county, timezone, area_codes, world_region, country, approximate_latitude, approximate_longitude,
        //  0     1        2               3                 4                  5              6      7         8        8            10         11             12                    13
        // polygon_offset_latitude, polygon_offset_longitude, internal_point_latitude, internal_point_longitude, latitude_min, latitude_max, longitude_min, longitude_max,
        //          14                        15                        16                        17
        // area_land, area_water, housing_count, population_count, irs_estimated_population_2015, white, black_or_african_american, american_indian_or_alaskan_native, asian, native_hawaiian_and_other_pacific_islander, other_race, two_or_more_races,total_male_population,total_female_population,pop_under_10,pop_10_to_19,pop_20_to_29,pop_30_to_39,pop_40_to_49,pop_50_to_59,pop_60_to_69,pop_70_to_79,pop_80_plus


        foreach ($lines as $line) {

            $fields = str_getcsv($line);

            if (count($fields) > 20) {

                $county = filter_var(trim($fields[7]), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $city = filter_var(trim($fields[3]), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $altCitys = filter_var(trim($fields[4]), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                // Use precision coordinates if available
                $lat = filter_var(trim($fields[16]), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                $long = filter_var(trim($fields[17]), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

                if ($lat == '' || $long == '') {
                	// use approximate coordinates
                	$lat = filter_var(trim($fields[12]), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                	$long = filter_var(trim($fields[13]), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                }

                $query .= "('"
                        . filter_var(trim($fields[0]), FILTER_SANITIZE_NUMBER_INT) . "','"    	// Zip_Code
                        . $city . "','"        													// City
                        . $county . "','"        												// County
                        . filter_var(trim($fields[6]), FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "','"        	// State
                        . $lat . "','"   // Lat
                        . $long . "','"	// Long
                        . filter_var(trim(substr($fields[1], 0, 2)), FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "','"						//Type
                        . $altCitys
                        . "'),";
                $indx++;
                $recordCounter++;
            }

            if ($indx > $maxRecords) {

                $indx = 0;

                if ($query != "") {
                    $dbh->exec("insert into postal_codes values " . substr($query, 0, -1));
                }

                $query = '';
            }
        }

        // Insert the remaining records.
        if ($indx > 0 && $query != "") {
            $dbh->exec("insert into postal_codes values " . substr($query, 0, -1));
        }

        return "Success, " . $recordCounter . " zip codes loaded.";

    }

    /**
     * Summary of readZipFile
     * @param mixed $file
     * @throws \HHK\Exception\RuntimeException
     * @return bool|string
     */
    protected static function readZipFile($file) {

    //$zip = Zip_open($file);
    $contents = '';
    $zip = new \ZipArchive;

    if ($zip->open($file) === TRUE) {

        $content = $zip->getFromIndex(0);
        $zip->close();

    } else {
        throw new RuntimeException("Problem opening zip file.  Error code = " . $zip->getStatusString());
    }

    return $content;
}


    public static function saveHolidays(\PDO $dbh, $post, $uname) {
        $resultMsg = '';

        // Turn fed holidays on or off
        $stmt = $dbh->query("Select Code, Substitute from gen_lookups where Table_Name = 'Holiday'");
        $hols = $stmt->fetchall(\PDO::FETCH_ASSOC);
        $ctrl = array();

        if (isset($post['cbhol'])) {
            $ctrl = $post['cbhol'];
        }

        // Federal Holidays
        foreach ($hols as $h) {

            // build control name

            if (isset($ctrl[$h['Code']])) {
                // set this holidy
                $dbh->exec("update gen_lookups set Substitute = '1' where Table_Name = 'Holiday' and Code = '". $h['Code'] ."'");
            } else {
                // skip this holiday
                $dbh->exec("update gen_lookups set Substitute = '' where Table_Name = 'Holiday' and Code = '". $h['Code'] ."'");
            }

        }


        // Designated Holidays
        $year = (int) date("Y");
        $year--;

        for ($y = $year; $y < $year+4; $y++) {

            $exists = FALSE;

            $dhRs = new Desig_HolidaysRS();
            $dhRs->Year->setStoredVal($y);
            $rows = EditRS::select($dbh, $dhRs, array($dhRs->Year));

            if (count($rows) == 1) {
                EditRS::loadRow($rows[0], $dhRs);
                $exists = TRUE;
            }

            if (isset($post['selMonth_' .$y])) {

                $months = $post['selMonth_' .$y];
                $days = $post['tbday_' .$y];


                // each designated holiday
                foreach ($months as $x => $m) {

                    $dateStr = $y . '-' . $m .'-' . (strlen($days[$x]) < 2 ? '0'.$days[$x] : $days[$x]);

                    switch ($x) {
                        case '10':
                            $dhRs->dh1->setNewVal($dateStr);
                            break;

                        case '11':
                            $dhRs->dh2->setNewVal($dateStr);
                            break;

                        case '12':
                            $dhRs->dh3->setNewVal($dateStr);
                            break;

                        case '13':
                            $dhRs->dh4->setNewVal($dateStr);
                            break;

                        default:

                    }
                }
            }

            $dhRs->Updated_By->setNewVal($uname);
            $dhRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            if ($exists) {

                EditRS::update($dbh, $dhRs, array($dhRs->Year));

            } else {

                $dhRs->Year->setNewVal($y);
                EditRS::Insert($dbh, $dhRs);

            }

        }

        $operatingHours = new OperatingHours($dbh);
        $resultMsg.= $operatingHours->save($post);

        self::saveSysConfig($dbh, $post);

        return $resultMsg;
    }

    public static function createCliteMarkup() {

        $tbl = new HTMLTable();

        $tbl->addBodyTr(HTMLTable::makeTd(ucfirst("Environment Variables"), array('colspan' => '3', 'style'=>'font-weight:bold;border-top: solid 1px black;')));

        foreach ($_ENV as $key=>$val) {

            if ($key == 'Password' || $key == 'sitePepper' || $key == 'ReadonlyPassword' || $key == 'BackupPassword') {

                $inpt = '********';

            } else {

                $inpt = $val;
            }

            $desc = '';

            $tbl->addBodyTr(
                HTMLTable::makeTd($key.':', array('class' => 'tdlabel'))
                . HTMLTable::makeTd($inpt) . HTMLTable::makeTd($desc, array('style'=>'width: 100%'))
            );
        }

        //$tbl->addFooterTr(HTMLTable::makeTd('', array('colspan' => '3', 'style'=>'font-weight:bold;border-top: solid 1px black;')));
        return $tbl;
    }

    public static function createLabelsMarkup(\PDO $dbh, $onlySection = '') {

        $tbl = new HTMLTable();
        $inputSize = '40';

        $stmt = $dbh->query("select `Code`, `Description` from `gen_lookups` where Table_Name = 'labels_category' order by `Order`");
        $cats = [];
        while($r = $stmt->fetch(\PDO::FETCH_ASSOC)){
            $cats[strtolower($r['Description'])] = $r['Code'];
        }

        if(count($cats) > 0){
            $uS = Session::getInstance();
            if(!is_array($uS->labels)){
                $uS->labels = Labels::initLabels($dbh);
            }
            foreach ($uS->labels as $section => $name) {
                if (($onlySection == '' || $onlySection == $section)) {

                    $tbl->addBodyTr(HTMLTable::makeTd(ucfirst($section), array('colspan' => '3', 'style'=>'font-weight:bold;border-top: solid 1px black;')));


                    if (is_array($name)) {

                        foreach ($name as $key => $val) {

                            $attr = array(
                                'name' => 'labels[' . $cats[strtolower($section)] . '][' . $key . ']',
                                'id' => $section . $key
                            );


                            $attr['size'] = $inputSize;
                            //
                            $inpt = HTMLInput::generateMarkup($val, $attr);

                            $desc = '';

                            $tbl->addBodyTr(
                                HTMLTable::makeTd($key.':', array('class' => 'tdlabel'))
                                . HTMLTable::makeTd($inpt) . HTMLTable::makeTd($desc)
                            );

                            unset($attr);
                        }
                    }
                }
            }
        }else{
            $tbl->addBodyTr(HTMLTable::makeTd("No Label Categories found", array('colspan' => '3', 'style'=>'border-top: solid 1px black;')));
        }

        //$tbl->addFooterTr(HTMLTable::makeTd('', array('colspan' => '3', 'style'=>'font-weight:bold;border-top: solid 1px black;')));
        return $tbl;
    }

    public static function createMarkup(\PDO $dbh, $category = NULL, array $hideCats = array()) {

        // sys config table
        $sctbl = new HTMLTable();
        $cat = '';

        $categorySql = '';
        if($category !== NULL){
            $categorySql = "and `s`.`Category` = '" . $category . "' ";
        }

        if(count($hideCats) > 0){
            foreach($hideCats as $i=>$cat){
                $hideCats[$i] = "'" . $cat . "'";
            }
            $categorySql = "and `s`.`Category` NOT IN (" . implode(",", $hideCats) . ") ";
        }

        $stmt = $dbh->query("select s.*, g.`Description` as `Cat` from sys_config s left join gen_lookups g on s.Category = g.Code and g.Table_Name = 'Sys_Config_Category' where s.Show = 1 " . $categorySql . "order by g.`Order`, s.`Key`");

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // New Section?
            if ($cat != $r['Cat']) {
                $sctbl->addBodyTr(HTMLTable::makeTd($r['Cat'], array('colspan' => '3', 'style'=>'font-weight:bold;border-top: solid 1px black;')));
                $cat = $r['Cat'];
            }

            if($r['Key'] == "PaymentDisclaimer"){
                $r['Value'] = str_replace("<br/>", "\r\n", $r['Value']);
            }

            if ($r['Type'] == 'b') {
                // Boolean

                $opts = array(
                    array('true', 'True'),
                    array('false', 'False')
                );

                $inpt = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($opts, $r['Value'], FALSE), array('name' => 'sys_config' . '[' . $r['Key'] . ']'));

            } else if ($r['Type'] == 't') {
                // text area

                $inpt = HTMLContainer::generateMarkup('textarea', $r['Value'], array('name' => 'sys_config' . '[' . $r['Key'] . ']', 'rows'=>'2', 'cols'=>'38'));

            } else if ($r['Type'] == 'i') {
                // integer

                $inpt = HTMLInput::generateMarkup($r['Value'], array('name' => 'sys_config' . '[' . $r['Key'] . ']', 'size'=>'7'));

            } else if ($r['Type'] == 'lu' && $r['GenLookup'] != '') {
                // lookup

                $opts = readGenLookupsPDO($dbh, $r['GenLookup'], 'order');

                $inpt = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($opts), $r['Value'], FALSE), array('name' => 'sys_config' . '[' . $r['Key'] . ']'));

            } else {

                // text input
                $inpt = HTMLInput::generateMarkup($r['Value'], array('name' => 'sys_config' . '[' . $r['Key'] . ']', 'size'=>40));
            }

            $sctbl->addBodyTr(HTMLTable::makeTd($r['Key'].':', array('class' => 'tdlabel')) . HTMLTable::makeTd($inpt . ' ' . $r['Description']));

            if($r['Key'] == 'DKIMdomain' && strlen($r['Value']) > 0){
                $sctbl->addBodyTr(HTMLTable::makeTd('DKIM CNAME Record:', array('class' => 'tdlabel', 'style'=>'vertical-align:top;')) . HTMLTable::makeTd("<strong>key</strong>: hhk._domainkey." . $r['Value'] . " <br><strong>Value</strong>: hhk.dkim.nonprofitsoftwarecorp.org"));
                $sctbl->addBodyTr(HTMLTable::makeTd('DKIM CNAME Record:', array('class' => 'tdlabel', 'style'=>'vertical-align:top;')) . HTMLTable::makeTd("<strong>key</strong>: hhk2._domainkey." . $r['Value'] . " <br><strong>Value</strong>: hhk2.dkim.nonprofitsoftwarecorp.org"));
            }

        }

        //if(SecurityComponent::is_TheAdmin() && $category == NULL){
            // site.cfg entries
            //$tblMkup = self::createCliteMarkup()->generateMarkup();
        //}else{
            $tblMkup = '';
        //}

        return $sctbl->generateMarkup() . $tblMkup;
    }

//     public static function saveConfig($dbh, array $post, $userName = '') {

//         foreach ($post as $secName => $secArray) {

//             if ($config->hasSection($secName)) {

//                 foreach ($secArray as $itemName => $val) {

//                     $val = filter_var($val, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

//                     if ($config->has($secName, $itemName)) {

//                         // password cutout
//                         if ($val != '' && (strstr($itemName, 'Password') !== FALSE) && $config->getString($secName, $itemName, '') != $val) {
//                             $val = encryptMessage($val);
//                         }

//                         // log changes
//                         if ($config->getString($secName, $itemName, '') != $val && is_null($dbh) === FALSE) {
//                             HouseLog::logSiteConfig($dbh, $secName . ':' . $itemName, $val, $userName);
//                             $config->set($secName, $itemName, $val);
//                         }
//                     }
//                 }
//             }
//         }

//         $config->save();

//     }

    public static function saveSysConfig(\PDO $dbh, array $post) {

        $mess = ['type'=>'', 'text'=>''];

        // save sys config
        foreach ($post['sys_config'] as $itemName => $val) {

            $value = filter_var($val, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $key = filter_var($itemName, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if($itemName == "PaymentDisclaimer"){
                $value = str_replace("\r\n", "<br/>", $value);
            }

            SysConfig::saveKeyValue($dbh, 'sys_config', $key, $value);

        }

        $mess['type'] = 'success';
        $mess['text'] = "Site Configuration saved successfully";

        return $mess;

    }

    public static function saveLabels(\PDO $dbh, array $post) {

    	$uS = Session::getInstance();
    	$mess = ['type'=>'', 'text'=>''];
        // save labels
        try{
            if(!isset($post['labels'])){
                throw new RuntimeException("No Labels found.");
            }
            foreach ($post['labels'] as $category=> $vals) {
                foreach ($vals as $key=>$val){
                    $value = filter_var($val, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $key = filter_var($key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                    SysConfig::saveKeyValue($dbh, 'labels', $key, $value, $category);
                }

            }
            //reload labels
            $uS->labels = Labels::initLabels($dbh);

            $mess['type'] = 'success';
            $mess['text'] = "Labels saved successfully";
        }catch(\Exception $e){
            $mess['type'] = 'error';
            $mess['text'] = "Labels not saved: " . htmlspecialchars($e->getMessage(), ENT_QUOTES);
        }

        return $mess;

    }

    public static function createPaymentCredentialsMarkup(\PDO $dbh, $resultMessage) {

        $uS = Session::getInstance();

        $tbl = new HTMLTable();

        // Payment Gateway name

        $opts = readGenLookupsPDO($dbh, 'Pay_Gateway_Name');
        $inpt = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($opts, SysConfig::getKeyValue($dbh, 'sys_config', 'PaymentGateway'), TRUE), array('name' => 'payGtwyName'));

        $tbl->addBodyTr(
                HTMLTable::makeTh('Payment Gateway', array())
                .HTMLTable::makeTd($inpt)
        );

        // Batch Settlement hour
        $bopts = array(
            array('01:00', '1am'),
            array('02:00', '2am'),
            array('03:00', '3am'),
            array('04:00', '4am'),
            array('05:00', '5am'),
        );

        $binpt = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($bopts, SysConfig::getKeyValue($dbh, 'sys_config', 'BatchSettlementHour'), TRUE), array('name' => 'payGtwybtch'));

        $tbl->addBodyTr(
                HTMLTable::makeTh('Batch Settlement Hour', array())
                .HTMLTable::makeTd($binpt)
        );

        // Spacer
        $tbl->addBodyTr(HTMLTable::makeTd('&nbsp', array('colspan'=>'2')));

        return $tbl->generateMarkup() . AbstractPaymentGateway::createEditMarkup($dbh, $uS->PaymentGateway, $resultMessage);
    }

    public static function savePaymentCredentials(\PDO $dbh, $post) {

        $uS = Session::getInstance();
        $msg = '';

        // Batch settlement
        if (isset($post['payGtwybtch'])) {
            $bhour = filter_var($post['payGtwybtch'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if (SysConfig::getKeyValue($dbh, 'sys_config', 'BatchSettlementHour') != $bhour) {
                SysConfig::saveKeyValue($dbh, 'sys_config', 'BatchSettlementHour', $bhour);
                $uS->BatchSettlementHour = $bhour;
                $msg .= 'Batch Settlement Hour Changed.  ';
            }
        }

        $newGW = filter_var($post['payGtwyName'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (isset($post['payGtwyName']) && SysConfig::getKeyValue($dbh, 'sys_config', 'PaymentGateway') != $newGW) {

            // change gateway
            if ($newGW == '') {
                // use "local" gateway
                SysConfig::saveKeyValue($dbh, 'sys_config', 'PaymentGateway', $newGW);
                $uS->PaymentGateway = $newGW;

            } else {

                // Change payment gateway
                SysConfig::saveKeyValue($dbh, 'sys_config', 'PaymentGateway', $newGW);
                $uS->PaymentGateway = $newGW;
            }

            $msg .= "Payment Gateway Changed.";

        } else {
            // Update current GW.
            $msg = AbstractPaymentGateway::saveEditMarkup($dbh, $uS->PaymentGateway, $post);

        }

        return $msg;
    }

}
?>