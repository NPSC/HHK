<?php
namespace HHK\Neon;

use HHK\Update\SiteConfig;
use HHK\Config_Lite\Config_Lite;
use HHK\HTMLControls\{HTMLTable, HTMLSelector};
use HHK\sec\Session;
use HHK\HTMLControls\HTMLContainer;
/**
 *
 * @author Eric
 *
 */
class ConfigureNeon
{

    protected $wsConfig;
    protected $serviceName;

    /**
     */
    public function __construct($serviceFile) {

        if (file_exists($serviceFile)) {
            try {

                $this->wsConfig = new Config_Lite($serviceFile);

            } catch (\HHK\Config_Lite\Exception\Exception $ex) {
                $this->wsConfig = NULL;
            }
        }

        $this->serviceName = 'NeonCRM';

    }

    public function showConfig(\PDO $dbh) {

        $externals = '';

        if (is_null($this->wsConfig) === FALSE) {

            $externals = SiteConfig::createCliteMarkup($this->wsConfig, new Config_Lite(REL_BASE_DIR . 'conf' . DS . 'neonTitles.cfg'))->generateMarkup();

            if ($this->wsConfig->getString('credentials', 'User') != '' && $this->wsConfig->getString('credentials', 'Password') != '') {

                try {

                    $transfer = new TransferMembers($this->wsConfig->getString('credentials', 'User'), decryptMessage($this->wsConfig->getString('credentials', 'Password')));
                    $stmt = $dbh->query("Select * from neon_lists;");

                    while ($list = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                        if (isset($list['HHK_Lookup']) === FALSE) {
                            continue;
                        }

                        $neonItems = $transfer->listNeonType($list['Method'], $list['List_Name'], $list['List_Item']);

                        if ($list['HHK_Lookup'] == 'Fund') {

                            // Use Items for the Fund
                            $stFund = $dbh->query("select idItem as Code, Description, '' as `Substitute` from item where Deleted = 0;");
                            $hhkLookup = array();

                            while ($row = $stFund->fetch(\PDO::FETCH_BOTH)) {
                                $hhkLookup[$row["Code"]] = $row;
                            }

                            $hhkLookup['p'] = array('Code'=>'p', 0=>'p', 'Description' => 'Payment', 1=>'Payment', 'Substitute'=>'', 2=>'');

                        } else if ($list['HHK_Lookup'] == 'Pay_Type') {

                            // Use Items for the Fund
                            $stFund = $dbh->query("select `idPayment_method` as `Code`, `Method_Name` as `Description`, '' as `Substitute` from payment_method;");
                            $hhkLookup = array();

                            while ($row = $stFund->fetch(\PDO::FETCH_BOTH)) {
                                $hhkLookup[$row['Code']] = $row;
                            }

                        } else {
                            $hhkLookup = removeOptionGroups(readGenLookupsPDO($dbh, $list['HHK_Lookup']));
                        }

                        $stmtList = $dbh->query("Select * from neon_type_map where List_Name = '" . $list['List_Name'] . "'");
                        $items = $stmtList->fetchAll(\PDO::FETCH_ASSOC);

                        $mappedItems = array();
                        foreach ($items as $i) {
                            $mappedItems[$i['Neon_Type_Code']] = $i;
                        }

                        $nTbl = new HTMLTable();
                        $nTbl->addHeaderTr(HTMLTable::makeTh('HHK Lookup') . HTMLTable::makeTh($this->serviceName . ' Name') . HTMLTable::makeTh($this->serviceName . ' Id'));

                        foreach ($neonItems as $n => $k) {

                            $hhkTypeCode = '';
                            if (isset($mappedItems[$n])) {
                                $hhkTypeCode = $mappedItems[$n]['HHK_Type_Code'];
                            }

                            $nTbl->addBodyTr(
                                HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($hhkLookup, $hhkTypeCode), array('name' => 'sel' . $list['List_Name'] . '[' . $n . ']')))
                                . HTMLTable::makeTd($k)
                                . HTMLTable::makeTd($n, array('style'=>'text-align:center;'))
                                );
                        }

                        $externals .= $nTbl->generateMarkup(array('style'=>'margin-top:15px;'), $list['List_Name']);
                    }

                    // Custom fields
                    $results = $transfer->listCustomFields();
                    $cfTbl = new HTMLTable();

                    $cfTbl->addHeaderTr(HTMLTable::makeTh('Field') . HTMLTable::makeTh($this->serviceName . ' id'));

                    foreach ($results as $v) {
                        if ($this->wsConfig->has('custom_fields', $v['fieldName'])) {
                            $cfTbl->addBodyTr(HTMLTable::makeTd($v['fieldName']) . HTMLTable::makeTd($v['fieldId']));
                        }
                    }

                    $externals .= $cfTbl->generateMarkup(array('style'=>'margin-top:15px;'), 'Custom Fields');

                    // Sources
                    $results = $transfer->listSources();
                    $sTbl = new HTMLTable();
                    $sTbl->addHeaderTr(HTMLTable::makeTh('Source') . HTMLTable::makeTh($this->serviceName . ' id'));

                    foreach ($results as $v) {

                        $sTbl->addBodyTr(HTMLTable::makeTd($v['name']) . HTMLTable::makeTd($v['id']));

                    }

                    $externals .= $sTbl->generateMarkup(array('style'=>'margin-top:15px;'), 'Sources');

                } catch (\Exception $pe) {
                    $externals = HTMLContainer::generateMarkup('span', "Transfer Error: " .$pe->getMessage(), array('style'=>'margin-left:200px;color:red;'));
                }

            }
        }

        return $externals;

    }

    public function saveConfig(\PDO $dbh) {

        $uS = Session::getInstance();
        $count = 0;
        $idTypeMap = 0;

        $transfer = new TransferMembers($this->wsConfig->getString('credentials', 'User'), decryptMessage($this->wsConfig->getString('credentials', 'Password')));

        // Custom fields
        $results = $transfer->listCustomFields();
        $custom_fields = array();

        foreach ($results as $v) {
            if ($this->wsConfig->has('custom_fields', $v['fieldName'])) {
                $custom_fields[$v['fieldName']] = $v['fieldId'];
            }
        }

        // Write Custom Field Ids to the config file.
        $confData = array('custom_fields' => $custom_fields);
        SiteConfig::saveConfig($dbh, $this->wsConfig, $confData, $uS->username);


        // Properties
        $stmt = $dbh->query("Select * from neon_lists;");

        while ($list = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $neonItems = $transfer->listNeonType($list['Method'], $list['List_Name'], $list['List_Item']);

            if ($list['HHK_Lookup'] == 'Fund') {

                // Use Items for the Fund
                $stFund = $dbh->query("select `idItem` as `Code`, `Description`, '' as `Substitute` from item where Deleted = 0;");
                $hhkLookup = array();

                while ($row = $stFund->fetch(\PDO::FETCH_BOTH)) {
                    $hhkLookup[$row['Code']] = $row;
                }

                $hhkLookup['p'] = array('Code'=>'p', 0=>'p', 'Description' => 'Payment', 1=>'Payment', 'Substitute'=>'', 2=>'');

            } else if ($list['HHK_Lookup'] == 'Pay_Type') {

                // Use Items for the Fund
                $stFund = $dbh->query("select `idPayment_method` as `Code`, `Method_Name` as `Description`, '' as `Substitute` from payment_method;");
                $hhkLookup = array();

                while ($row = $stFund->fetch(\PDO::FETCH_BOTH)) {
                    $hhkLookup[$row['Code']] = $row;
                }

            } else {
                $hhkLookup = removeOptionGroups(readGenLookupsPDO($dbh, $list['HHK_Lookup']));
            }

            $stmtList = $dbh->query("Select * from neon_type_map where List_Name = '" . $list['List_Name'] . "'");
            $items = $stmtList->fetchAll(\PDO::FETCH_ASSOC);
            $mappedItems = array();
            foreach ($items as $i) {
                $mappedItems[$i['HHK_Type_Code']] = $i;
            }

            $nTbl = new HTMLTable();
            $nTbl->addHeaderTr(HTMLTable::makeTh('HHK Lookup') . HTMLTable::makeTh('NeonCRM Name') . HTMLTable::makeTh('NeonCRM Id'));

            foreach ($neonItems as $n => $k) {

                if (isset($_POST['sel' . $list['List_Name']][$n])) {

                    $hhkTypeCode = filter_var($_POST['sel' . $list['List_Name']][$n], FILTER_SANITIZE_STRING);

                    if ($hhkTypeCode == '') {
                        // delete if previously set
                        foreach ($mappedItems as $i) {
                            if ($i['Neon_Type_Code'] == $n && $i['HHK_Type_Code'] != '') {
                                $dbh->exec("delete from neon_type_map  where idNeon_type_map = " .$i['idNeon_type_map']);
                                break;
                            }
                        }

                        continue;

                    } else if (isset($hhkLookup[$hhkTypeCode]) === FALSE) {
                        continue;
                    }

                    if (isset($mappedItems[$hhkTypeCode])) {
                        // Update
                        $count = $dbh->exec("update neon_type_map set Neon_Type_Code = '$n', Neon_Type_name = '$k' where HHK_Type_Code = '$hhkTypeCode' and List_Name = '" . $list['List_Name'] . "'");
                    } else {
                        // Insert
                        $idTypeMap = $dbh->exec("Insert into neon_type_map (List_Name, Neon_Name, Neon_Type_Code, Neon_Type_Name, HHK_Type_Code, Updated_By, Last_Updated) "
                            . "values ('" . $list['List_Name'] . "', '" . $list['List_Item'] . "', '" . $n . "', '" . $k . "', '" . $hhkTypeCode . "', '" . $uS->username . "', now() );");
                    }
                }
            }
        }

        return $count + $idTypeMap;
    }
}

