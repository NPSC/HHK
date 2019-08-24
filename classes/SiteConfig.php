<?php

/**
 * SiteConfig.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
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

//        $stmt = $dbh->query("Select dh1, dh2, dh3, dh4, dh5 from desig_holidays where Year = ".$this->year);
//        $dhs = $stmt->fetchall(PDO::FETCH_ASSOC);
//
//        if (count($dhs) == 0) {
//            throw new Hk_Exception_Runtime('Designated holidays are not set up for year '.$this->year);
//        }
//
//        $stmt = $dbh->query("Select Code, Substitute from gen_lookups where Table_Name = 'Holiday'");
//        $hols = $stmt->fetchall(PDO::FETCH_ASSOC);
//
//        if (count($hols) == 0) {
//            throw new Hk_Exception_Runtime('Holidays are not defined.  ');
//        }

        $gArray = array( 1 => array(1, 'Jan'),
            2 => array(2, 'Feb'),
            3 => array(3, 'Mar'), 4 => array(4, 'Apr'), 5 => array(5, 'May'), 6 => array(6, 'Jun'),
            7 => array(7, 'Jul'), 8 => array(8, 'Aug'), 9 => array(9, 'Sep'), 10 => array(10, 'Oct'), 11 => array(11, 'Nov'), 12 => array(12, 'Dec'));

        $wdNames = array('Sun','Mon','Tue','Wed','Thr','Fri','Sat');

        $tbl = new HTMLTable();
        $trs = array();

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

        $tbl->addHeader(HTMLTable::makeTh('Holiday') . HTMLTable::makeTh('Non-Cleaning') .HTMLTable::makeTh($year++)
                . HTMLTable::makeTh($year++) . HTMLTable::makeTh($year++) . HTMLTable::makeTh($year++));

        // Week days
        $stmt = $dbh->query("Select Code, Substitute from gen_lookups where Table_Name = 'Non_Cleaning_Day'");
        $wds = $stmt->fetchall(\PDO::FETCH_ASSOC);

        $wdTbl = new HTMLTable();
        $wdTbl->addHeaderTr(HTMLTable::makeTh('Weekday').HTMLTable::makeTh('Non-Cleaning'));


        foreach ($wdNames as $k => $d) {

            $attr = array('name'=>'wd' . $k, 'type'=>'checkbox');
            if (isset($wds)) {
                foreach ($wds as $r) {
                    if ($r['Code'] == $k) {
                        $attr['checked'] = 'checked';
                    }
                }
            }

            $wdTbl->addBodyTr(HTMLTable::makeTd($d, array('style'=>'text-align:right;')) . HTMLTable::makeTd(HTMLInput::generateMarkup('', $attr), array('style'=>'text-align:center;')));
        }


        return HTMLContainer::generateMarkup('h3', 'Annual Non-Cleaning Days') . $tbl->generateMarkup() . HTMLContainer::generateMarkup('h3', 'Weekly Non-Cleaning Days', array('style'=>'margin-top:12px;')) . $wdTbl->generateMarkup();
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

    public static function loadZipCodeFile(\PDO $dbh, $file) {

        $resultMsg = '';
        $lines = explode("\n", self::readZipFile($file));

        // Remove the first line - headings
        array_shift($lines);

        // Delete old table contents
        if (count($lines) > 30000) {
            $dbh->exec("delete from postal_codes;");
            $resultMsg .= 'Old zip codes deleted.  ';
        }


        $query = '';

        $indx = 0;
        $recordCounter = 0;
        $maxRecords = 10000;

//"zip","type","primary_city","acceptable_cities","unacceptable_cities","state","county","timezone","area_codes","latitude","longitude","precise_lat, precise_long
//   0    1         2               3                   4                   5       6       7           8           9           10          11              12          13

//"world_region","country","decommissioned","estimated_population","notes"

        // New File as of 2/2018
// zip	type	decommissioned	primary_city	acceptable_cities	unacceptable_cities	state	county	timezone	area_codes	world_region	country	approximate_latitude
//  0     1            2             3                  4                       5                 6        7        8               9                10           11          12
//
// approximate_longitude	polygon_offset_latitude	polygon_offset_longitude	internal_point_latitude	internal_point_longitude	latitude_min	latitude_max	longitude_min	longitude_max	area_land	area_water	housing_count	population_count	irs_estimated_population_2015	white	black_or_african_american	american_indian_or_alaskan_native	asian	native_hawaiian_and_other_pacific_islander	other_race	two_or_more_races	total_male_population	total_female_population	pop_under_10	pop_10_to_19	pop_20_to_29	pop_30_to_39	pop_40_to_49	pop_50_to_59	pop_60_to_69	pop_70_to_79	pop_80_plus
//        13                             14                      15                               16                        17                         18            19                20              21
        foreach ($lines as $line) {

            $fields = str_getcsv($line);

            if (count($fields) > 20) {

                $county = filter_var(trim($fields[7]), FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH);
                $city = filter_var(trim($fields[3]), FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH);
                $altCitys = filter_var(trim($fields[4]), FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH);

                $query .= "('"
                        . filter_var(trim($fields[0]), FILTER_SANITIZE_NUMBER_INT) . "','"    // Zip_Code
                        . $city . "','"        // City
                        . $county . "','"        // County
                        . filter_var(trim($fields[6]), FILTER_SANITIZE_STRING) . "','"        // State
                        . filter_var(trim($fields[18]), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) . "','"
                        . filter_var(trim($fields[20]), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) . "','"
                        . filter_var(trim(substr($fields[1], 0, 2)), FILTER_SANITIZE_STRING) . "','"
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

    protected static function readZipFile($file) {

    $zip = Zip_open($file);

    if (is_resource($zip)) {

        $entry = zip_read($zip);
        $na = zip_entry_name($entry);

        $content = zip_entry_read($entry, zip_entry_filesize($entry));

        zip_entry_close($entry);
        zip_close($zip);

        if ($content === FALSE) {
            throw new Hk_Exception_Runtime("Problem reading zip file entry: $na.  ");
        }
    } else {
        throw new Hk_Exception_Runtime("Problem opening zip file.  Error code = $zip.  ");
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

        // Weekdays
        for ($d = 0; $d < 7; $d++) {

            if (isset($post['wd'.$d])) {
                $dbh->exec("replace into gen_lookups (`Table_Name`, `Code`) values ('Non_Cleaning_Day', '$d')");
            } else {
                $dbh->exec("delete from gen_lookups where `Table_Name`='Non_Cleaning_Day' and `Code`='$d'");
            }
        }

        return $resultMsg;
    }

    public static function createCliteMarkup(Config_Lite $config, Config_Lite $titles = NULL, $onlySection = '') {

        $tbl = new HTMLTable();
        $inputSize = '40';

        foreach ($config as $section => $name) {

            if ($onlySection == '' || $onlySection == $section) {

                if ($section == 'webServices') {

                    $tbl->addBodyTr(HTMLTable::makeTd(ucfirst($section)
                            . '<span style="margin-left:10px;"><a href="../house/SetupNeonCRM.htm" target="_blank">(Instructions)</a></span>'
                            , array('colspan' => '3', 'style'=>'font-weight:bold;border-top: solid 1px black;')));

                } else if ($section == 'code') {
                    continue;
                } else {
                    $tbl->addBodyTr(HTMLTable::makeTd(ucfirst($section), array('colspan' => '3', 'style'=>'font-weight:bold;border-top: solid 1px black;')));
                }

                if (is_array($name)) {

                    foreach ($name as $key => $val) {

                        $attr = array(
                            'name' => $section . '[' . $key . ']',
                            'id' => $section . $key
                        );

                        if ($key == 'Disclaimer' || $key == 'PaymentDisclaimer') {

                            $attr["rows"] = "3";
                            $attr["cols"] = $inputSize;
                            $inpt = HTMLCONTAINER::generateMarkup('textarea', $val, $attr);

                        } else {

                            $attr['size'] = $inputSize;
                            $inpt = HTMLInput::generateMarkup($val, $attr);
                        }


                        if (is_null($titles)) {
                            $desc = '';
                        } else {
                            $desc = $titles->getString($section, $key, '');
                        }

                        $tbl->addBodyTr(
                                HTMLTable::makeTd($key.':', array('class' => 'tdlabel'))
                                . HTMLTable::makeTd($inpt) . HTMLTable::makeTd($desc)
                        );

                        unset($attr);
                    }
                }
            }
        }

        //$tbl->addFooterTr(HTMLTable::makeTd('', array('colspan' => '3', 'style'=>'font-weight:bold;border-top: solid 1px black;')));
        return $tbl;
    }

    public static function createMarkup(\PDO $dbh, Config_Lite $config, Config_Lite $titles = NULL) {

        $tbl = self::createCliteMarkup($config, $titles);

        // add sys config table
        $sctbl = new HTMLTable();
        $cat = '';

        $stmt = $dbh->query("select s.*, g.Description as `Cat` from sys_config s left join gen_lookups g on s.Category = g.Code and g.Table_Name = 'Sys_Config_Category' order by `Category`, `Key`");

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // New Section?
            if ($cat != $r['Cat']) {
                $sctbl->addBodyTr(HTMLTable::makeTd($r['Cat'], array('colspan' => '3', 'style'=>'font-weight:bold;border-top: solid 1px black;')));
                $cat = $r['Cat'];
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
                // text area

                $inpt = HTMLInput::generateMarkup($r['Value'], array('name' => 'sys_config' . '[' . $r['Key'] . ']', 'size'=>'7'));

            } else if ($r['Type'] == 'lu' && $r['GenLookup'] != '') {
                // Boolean

                $opts = readGenLookupsPDO($dbh, $r['GenLookup']);

                $inpt = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($opts, $r['Value'], TRUE), array('name' => 'sys_config' . '[' . $r['Key'] . ']'));

            } else {

                // text input
                $inpt = HTMLInput::generateMarkup($r['Value'], array('name' => 'sys_config' . '[' . $r['Key'] . ']', 'size'=>40));
            }

            $sctbl->addBodyTr(HTMLTable::makeTd($r['Key'].':', array('class' => 'tdlabel')) . HTMLTable::makeTd($inpt . ' ' . $r['Description']));

        }

        return $tbl->generateMarkup() . $sctbl->generateMarkup();
    }

    public static function saveConfig($dbh, Config_Lite $config, array $post, $userName = '') {

        foreach ($post as $secName => $secArray) {

            if ($config->hasSection($secName)) {

                foreach ($secArray as $itemName => $val) {

                    $val = filter_var($val, FILTER_SANITIZE_STRING);

                    if ($config->has($secName, $itemName)) {

                        // password cutout
                        if ($val != '' && (strstr($itemName, 'Password') !== FALSE) && $config->getString($secName, $itemName, '') != $val) {
                            $val = encryptMessage($val);
                        }

                        // log changes
                        if ($config->getString($secName, $itemName, '') != $val && is_null($dbh) === FALSE) {
                            HouseLog::logSiteConfig($dbh, $secName . ':' . $itemName, $val, $userName);
                        }

                        $config->set($secName, $itemName, $val);

                    }
                }
            }
        }

        $config->save();

    }

    public static function saveSysConfig(\PDO $dbh, array $post) {

        // save sys config
        foreach ($post['sys_config'] as $itemName => $val) {

            $value = filter_var($val, FILTER_SANITIZE_STRING);
            $key = filter_var($itemName, FILTER_SANITIZE_STRING);

            SysConfig::saveKeyValue($dbh, 'sys_config', $key, $value);

        }

    }

    public static function createPaymentCredentialsMarkup(\PDO $dbh, $resultMessage) {

        $uS = Session::getInstance();

        $gateway = PaymentGateway::factory($dbh, $uS->PaymentGateway, $uS->ccgw);

        $ptbl = new HTMLTable();

        if ($uS->ccgw == '')  {
            $using = 'External';
        } else {
            $using = 'Gateway';
        }

        $ptbl->addBodyTr(HTMLTable::makeTh('Using:')
                . HTMLTable::makeTd($using));

        return $gateway->createEditMarkup($dbh, $resultMessage) . $ptbl->generateMarkup(array('class'=>'hhk-tdbox', 'style'=>'margin-top:10px;'));
    }

    public static function savePaymentCredentials(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        $gateway = PaymentGateway::factory($dbh, $uS->PaymentGateway, $uS->ccgw);

        $msg = $gateway->SaveEditMarkup($dbh, $post);

        $msg .= $gateway->updatePayTypes($dbh, $uS->username);

        return $msg;
    }

}

