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

    public static function loadZipCodeFile(\PDO $dbh, $content) {

        $resultMsg = '';
        $lines = explode("\n", $content);

        $content = '';

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
        foreach ($lines as $line) {

            $fields = str_getcsv($line);

            if (count($fields) > 11) {

                $query .= "('"
                        . filter_var(trim($fields[0]), FILTER_SANITIZE_NUMBER_INT) . "','"    // Zip_Code
                        . filter_var(trim($fields[2]), FILTER_SANITIZE_STRING) . "','"        // City
                        . filter_var(trim($fields[6]), FILTER_SANITIZE_STRING) . "','"        // County
                        . filter_var(trim($fields[5]), FILTER_SANITIZE_STRING) . "','"        // State
                        . filter_var(trim($fields[11]), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) . "','"
                        . filter_var(trim($fields[12]), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) . "','"
                        . filter_var(trim(substr($fields[1], 0, 2)), FILTER_SANITIZE_STRING) . "','"
                        . filter_var(trim($fields[3]), FILTER_SANITIZE_STRING)
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

    public static function createCliteMarkup(Config_Lite $config, Config_Lite $titles = NULL) {

        $tbl = new HTMLTable();
        $inputSize = '40';

        foreach ($config as $section => $name) {

            $tbl->addBodyTr(HTMLTable::makeTd(ucfirst($section), array('colspan' => '3', 'style'=>'font-weight:bold;border-top: solid 1px black;')));

            if (is_array($name)) {

                foreach ($name as $key => $val) {

                    $attr = array(
                        'name' => $section . '[' . $key . ']',
                        'id' => $section . $key
                    );

                    if ($section == 'code') {
                        $attr['readonly'] = 'Readonly';
                    }

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

        $tbl->addFooterTr(HTMLTable::makeTd('', array('colspan' => '3', 'style'=>'font-weight:bold;border-top: solid 1px black;')));
        return $tbl;
    }

    public static function createMarkup(\PDO $dbh, Config_Lite $config, Config_Lite $titles = NULL) {

        $tbl = self::createCliteMarkup($config, $titles);

        // add sys config table
        $stmt = $dbh->query("select * from sys_config order by `Category`, `Key`");

        $sctbl = new HTMLTable();

        $sctbl->addBodyTr(HTMLTable::makeTh('Sys Config', array('colspan' => '3', 'style'=>'text-align:left;')));

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $attr = array(
                'name' => 'sys_config' . '[' . $r['Key'] . ']'
            );


            if ($r['Type'] == 'b') {

                $opts = array(
                    array('true', 'True'),
                    array('false', 'False')
                );

                $inpt = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($opts, $r['Value'], FALSE), $attr);

            } else {

                $attr['size'] = 30;
                $inpt = HTMLInput::generateMarkup($r['Value'], $attr);
            }

            $sctbl->addBodyTr(HTMLTable::makeTd($r['Key'].':', array('class' => 'tdlabel')) . HTMLTable::makeTd($inpt) . HTMLTable::makeTd($r['Description']));

        }

        return $tbl->generateMarkup() . $sctbl->generateMarkup();
    }

    public static function saveConfig(\PDO $dbh, Config_Lite $config, array $post, $userName = '') {

        foreach ($post as $secName => $secArray) {

            if ($config->hasSection($secName)) {

                foreach ($secArray as $itemName => $val) {

                    $val = filter_var($val, FILTER_SANITIZE_STRING);

                    if ($config->has($secName, $itemName)) {

                        // password cutout
                        if (($itemName == 'Password' || $itemName == 'BackupPassword' || $itemName == 'ReadonlyPassword') && $config->getString($secName, $itemName, '') != $val) {
                            if ($val != '') {
                                $val = encryptMessage($val);
                            }
                        }

                        // log changes
                        if ($config->getString($secName, $itemName, '') != $val) {
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

        $stmt = $dbh->query("Select * from cc_hosted_gateway");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $tbl = new HTMLTable();

        foreach ($rows as $r) {

            $indx = $r['idcc_gateway'];
            $tbl->addBodyTr(HTMLTable::makeTd($r['cc_name'], array('class'=>'tdlabel'))
            .HTMLTable::makeTd(HTMLInput::generateMarkup($r['Merchant_Id'], array('name'=>$indx . '_txtMid')))
            .HTMLTable::makeTd(HTMLInput::generateMarkup(($r['Password'] != '' ? "********" : ''), array('name'=>$indx .'_txtpw')))
            .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>$indx .'_txtpw2')))
             .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>$indx .'cbDel'))));
        }

        if ($resultMessage != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($resultMessage, array('colspan'=>'4', 'style'=>'font-weight:bold;')));
        }

        $tbl->addHeader(HTMLTable::makeTh('Name') . HTMLTable::makeTh('Merchant Id')
                . HTMLTable::makeTh('Password') . HTMLTable::makeTh('Password Again') . HTMLTable::makeTh('Delete'));


        $ptbl = new HTMLTable();
//        $ccAttr = array('type'=>'radio', 'name'=>'useGw', 'id'=>'cbuseGw', 'disabled'=>'disabled', 'value'=>  PayType::Charge);
//        $cxAttr = array('type'=>'radio', 'name'=>'useGw', 'id'=>'cbuseEx', 'disabled'=>'disabled', 'value'=>  PayType::ChargeAsCash);

        if ($uS->ccgw == '')  {
            $using = 'External';
        } else {
            $using = 'Gateway';
        }

        $ptbl->addBodyTr(HTMLTable::makeTh('Using:')
                . HTMLTable::makeTd($using));


        return $tbl->generateMarkup() . $ptbl->generateMarkup(array('class'=>'hhk-tdbox'));
    }

    public static function savePaymentCredentials(\PDO $dbh, $post) {

        $msg = '';
        $ccRs = new Cc_Hosted_GatewayRS();
        $rows = EditRS::select($dbh, $ccRs, array());

        foreach ($rows as $r) {

            EditRS::loadRow($r, $ccRs);

            $indx = $ccRs->idcc_gateway->getStoredVal();

            // Clear the entries??
            if (isset($post[$indx . 'cbDel'])) {

                $ccRs->Merchant_Id->setNewVal('');
                $ccRs->Password->setNewVal('');
                $num = EditRS::update($dbh, $ccRs, array($ccRs->idcc_gateway));
                 $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - Payment Credentials Deleted.  ");

            } else {

                if (isset($post[$indx . '_txtMid'])) {
                    $mid = filter_var($post[$indx . '_txtMid'], FILTER_SANITIZE_STRING);
                        $ccRs->Merchant_Id->setNewVal($mid);
                }

                if (isset($post[$indx . '_txtpw']) && isset($post[$indx . '_txtpw2']) && $post[$indx . '_txtpw2'] != '') {

                    $pw = filter_var($post[$indx . '_txtpw'], FILTER_SANITIZE_STRING);
                    $pw2 = filter_var($post[$indx . '_txtpw2'], FILTER_SANITIZE_STRING);

                    // Don't save the pw blank characters
                    if ($pw != '********' && $pw == $pw2) {

                        $ccRs->Password->setNewVal(encryptMessage($pw));
                    } else {
                        // passwords don't match
                        $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - Passwords do not match.  ");
                    }
                }

                // Save record.
                $num = EditRS::update($dbh, $ccRs, array($ccRs->idcc_gateway));

                if ($num > 0) {
                    $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - Payment Credentials Updated.  ");
                } else {
                    $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - No changes detected.  ");
                }

            }
        }

        $cnt = self::updatePayTypes($dbh);

        if ($cnt > 0) {
            $msg .= "Pay Types updated.  ";
        }

        return $msg;
    }

    public static function updatePayTypes(\PDO $dbh) {

        $uS = Session::getInstance();
        $msg = '';

        $glRs = new GenLookupsRS();
        $glRs->Table_Name->setStoredVal('Pay_Type');
        $glRs->Code->setStoredVal(PayType::Charge);
        $rows = EditRS::select($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

        if (count($rows) > 0) {
            $glRs = new GenLookupsRS();
            EditRS::loadRow($rows[0], $glRs);


            if ($uS->ccgw != '') {
                $glRs->Substitute->setNewVal(PaymentMethod::Charge);
            } else {
                $glRs->Substitute->setNewVal(PaymentMethod::ChgAsCash);
            }


            $ctr = EditRS::update($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

            if ($ctr > 0) {
                $logText = HouseLog::getUpdateText($glRs);
                HouseLog::logGenLookups($dbh, 'Pay_Type', PayType::Charge, $logText, "update", $uS->username);
                $msg = "Pay_Type Charge is updated.  ";
            }
        }

        return $msg;
    }

}

