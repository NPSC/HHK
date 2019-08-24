<?php
/**
 * Registration.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class Registration {

    protected $regRS;
    public $isNew;
    protected $depositBalance;
    protected $lodgingMOA;
    protected $rawRow;

    public function __construct(PDO $dbh, $idPsg, $idRegistration = 0) {

        $this->regRS = new RegistrationRs();
        $rows = array();
        $this->rawRow = array();
        $this->isNew = TRUE;
        $this->depositBalance = NULL;
        $this->lodgingMOA = NULL;

        if ($idPsg > 0) {
            $this->regRS->idPsg->setStoredVal($idPsg);
            $rows = EditRS::select($dbh, $this->regRS, array($this->regRS->idPsg));

        } else if ($idRegistration > 0) {
            $this->regRS->idRegistration->setStoredVal($idRegistration);
            $rows = EditRS::select($dbh, $this->regRS, array($this->regRS->idRegistration));
        }

        if (count($rows) > 0) {

            EditRS::loadRow($rows[0], $this->regRS);
            $this->rawRow = $rows[0];
            $this->isNew = FALSE;
        }
    }

    public static function loadDepositBalance(PDO $dbh, $idGroup) {

        $depositBalance = 0.0;
        $idg = intval($idGroup, 10);

        if ($idg < 1) {
            return $depositBalance;
        }

        $query = "select
    sum(il.Amount)
from
    invoice_line il
        join
    invoice i ON il.Invoice_Id = i.idInvoice
where
    il.Item_Id in (" . ItemId::DepositRefund . "  , " . ItemId::KeyDeposit . ")
        and i.Deleted = 0
        and il.Deleted = 0
        and i.Status = '" . InvoiceStatus::Paid . "'
        and i.idGroup = " . $idg;
        $stmt = $dbh->query($query);

        $rows = $stmt->fetchAll(PDO::FETCH_NUM);

        if (count($rows) == 1) {
            $depositBalance = floatval($rows[0][0]);
        }

        return $depositBalance;
    }

    public static function loadLodgingBalance(PDO $dbh, $idGroup) {

        $lodgingBalance = 0.0;
        $idg = intval($idGroup, 10);

        if ($idg < 1) {
            return $lodgingBalance;
        }

        $query = "select
    sum(il.Amount)
from
    invoice_line il
        join
    invoice i ON il.Invoice_Id = i.idInvoice
        LEFT JOIN
    name_volunteer2 nv ON i.Sold_To_Id = nv.idName
        AND nv.Vol_Category = 'Vol_Type'
        AND nv.Vol_Code = 'ba'

where
    il.Item_Id = ". ItemId::LodgingMOA . "
        and i.Deleted = 0
        and il.Deleted = 0
        and (i.Status = '" . InvoiceStatus::Paid . "' or nv.idName IS NOT NULL)
        and i.idGroup = " . $idg;
        $stmt = $dbh->query($query);

        $rows = $stmt->fetchAll(PDO::FETCH_NUM);

        if (count($rows) == 1) {
            $lodgingBalance = floatval($rows[0][0]);
        }

        return $lodgingBalance;
    }

    public static function updatePrefTokenId(\PDO $dbh, $idRegistration, $idToken) {

        $tokenId = intval($idToken);
        $regId = intval($idRegistration);

        if ($tokenId < 1 || $regId < 1) {
            return;
        }

        return $dbh->exec("update registration set Pref_Token_Id = $tokenId where idregistration = $regId and Pref_Token_Id != $tokenId");
    }

    /**
     *
     * @param \PDO $dbh
     * @return mixed
     */
    public function getDepositBalance(\PDO $dbh) {

        if (is_null($this->depositBalance)) {
            $this->depositBalance = $this->loadDepositBalance($dbh, $this->getIdRegistration());
        }

        return $this->depositBalance;

    }

    public function getLodgingMOA(\PDO $dbh) {

        if (is_null($this->lodgingMOA)) {
            $this->lodgingMOA = $this->loadLodgingBalance($dbh, $this->getIdRegistration());
        }

        return $this->lodgingMOA;

    }

    public function getIdRegistration() {
        return $this->regRS->idRegistration->getStoredVal();
    }

    public function getIdPsg() {
        return $this->regRS->idPsg->getStoredVal();
    }

    public function getEmailReceipt() {
        if ($this->regRS->Email_Receipt->getStoredVal() == 1) {
            return TRUE;
        }
        return FALSE;
    }

    public function getPreferredTokenId() {
        return $this->regRS->Pref_Token_Id->getStoredVal();
    }


    public function getNoVehicle() {
        return $this->regRS->Vehicle->getStoredVal();
    }

    public function setNoVehicle($b) {
        if ($b) {
            $this->regRS->Vehicle->setNewVal('1');
        } else {
            $this->regRS->Vehicle->setNewVal('0');
        }
    }

    public function isNew() {
        if ($this->regRS->idRegistration->getStoredVal() == 0) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function getRegRS() {
        return $this->regRS;
    }

    public function extractVehicleFlag($pData) {

        if (isset($pData["cbNoVehicle"])) {
            if (strtolower($pData["cbNoVehicle"]) == 'on' || $pData["cbNoVehicle"] == '1') {
                $this->regRS->Vehicle->setNewVal('1');
            } else {
                $this->regRS->Vehicle->setNewVal('0');
            }
        } else {
            $this->regRS->Vehicle->setNewVal('0');
        }

    }

    public function extractRegistration(\PDO $dbh, $pData) {

        if (isset($pData['regGuest_Ident'])) {
            $this->regRS->Guest_Ident->setNewVal('1');
            $this->rawRow['Guest_Ident'] = '1';
        } else {
            $this->regRS->Guest_Ident->setNewVal('0');
            $this->rawRow['Guest_Ident'] = '0';
        }

        if (isset($pData['regPamphlet'])) {
            $this->regRS->Pamphlet->setNewVal('1');
            $this->rawRow['Pamphlet'] = '1';
        } else {
            $this->regRS->Pamphlet->setNewVal('0');
            $this->rawRow['Pamphlet'] = '0';
        }

        if (isset($pData['regReferral'])) {
            $this->regRS->Referral->setNewVal('1');
            $this->rawRow['Referral'] = '1';
        } else {
            $this->regRS->Referral->setNewVal('0');
            $this->rawRow['Referral'] = '0';
        }

        if (isset($pData['regSig_Card'])) {
            $this->regRS->Sig_Card->setNewVal('1');
            $this->rawRow['Sig_Card'] = '1';
        } else {
            $this->regRS->Sig_Card->setNewVal('0');
            $this->rawRow['Sig_Card'] = '0';
        }

        if (isset($pData["cbEml"])) {
            $this->regRS->Email_Receipt->setNewVal('1');
        } else {
            $this->regRS->Email_Receipt->setNewVal('');
        }
    }

    public function saveRegistrationRs(PDO $dbh, $idPsg, $uname) {

        $msg = "";

        $this->regRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $this->regRS->Updated_By->setNewVal($uname);
        $this->regRS->Status->setNewVal(VisitStatus::Active);

        if (is_null($this->depositBalance) === FALSE) {
            $this->regRS->Key_Deposit_Bal->setNewVal($this->depositBalance);
        }

        if (is_null($this->lodgingMOA) === FALSE) {
            $this->regRS->Lodging_Balance->setNewVal($this->lodgingMOA);
        }


        if ($this->regRS->idRegistration->getStoredVal() === 0 && $idPsg != 0) {
           // Insert
            $this->regRS->idPsg->setNewVal($idPsg);
            $this->regRS->Date_Registered->setNewVal(date("Y-m-d H:i:s"));
            $idReg = EditRS::insert($dbh, $this->regRS);

            $logText = VisitLog::getInsertText($this->regRS);
            VisitLog::logRegistration($dbh, $idPsg, $idReg, $logText, "insert", $uname);

            $this->regRS->idRegistration->setNewVal($idReg);
            $msg .= 'New Registration.  ';

        } else if ($this->regRS->idRegistration->getStoredVal() != 0) {
            // Update
            $cnt = EditRS::update($dbh, $this->regRS, array($this->regRS->idRegistration));

            $logText = VisitLog::getUpdateText($this->regRS);
            VisitLog::logRegistration($dbh, $idPsg, $this->regRS->idRegistration->getStoredVal(), $logText, "update", $uname);

            if ($cnt > 0) {
                $msg .= 'Registration Updated.  ';
            }
        } else {
            throw new Hk_Exception_Runtime('Registration missing a PSG Id.');
        }

        EditRS::updateStoredVals($this->regRS);
        return $msg;
    }

    public function createRegMarkup(PDO $dbh, $adminKey) {

        // get session instance
        $uS = Session::getInstance();
        $labels = new Config_Lite(LABEL_FILE);
        $tbl = new HTMLTable();

        // Date Registered
        $tbl->addBodyTr(HTMLTable::makeTh('Date')
                . HTMLTable::makeTd(($this->regRS->Date_Registered->getStoredVal() == '' ? '' : date('M j, Y', strtotime($this->regRS->Date_Registered->getStoredVal())))));

        $regs = readGenLookupsPDO($dbh, 'registration', 'Order');

        // Selected Items.
        foreach ($regs as $r) {

            if (strtolower($r['Substitute']) == 'y' && isset($this->rawRow[$r['Code']])) {

                $attrs = array(
                    'type' => 'checkbox',
                    'class' => 'hhk-regvalue',
                    'name' => 'reg' . $r['Code'],
                    'id' => 'reg' . $r['Code'],
                );

                // checked?
                if ($this->rawRow[$r['Code']] == '1') {
                    $attrs['checked'] = 'checked';
                }

                $tbl->addBodyTr(
                        HTMLTable::makeTh(HTMLContainer::generateMarkup('label', $r['Description'], array('for'=>'reg' . $r['Code'])))
                        . HTMLTable::makeTd(HTMLInput::generateMarkup('', $attrs)));
            }
        }

        $emAttrs = array(
            'type' => 'checkbox',
            'class' => 'hhk-regvalue',
            'name' => 'cbEml',
            'id' => 'cbEml',
        );

        if ($this->regRS->Email_Receipt->getStoredVal() == "1") {
            $emAttrs['checked'] = 'checked';
        }

        // Email receipt
        $tbl->addBodyTr(
            HTMLTable::makeTh(HTMLContainer::generateMarkup('label', 'Email Receipt', array('for'=>'cbEml')))
            . HTMLTable::makeTd(HTMLInput::generateMarkup('', $emAttrs))
            );


        // Key Deposit
        if ($uS->KeyDeposit) {
            $kdBal = $this->getDepositBalance($dbh);

            $tbl->addBodyTr(
                HTMLTable::makeTh($labels->getString('resourceBuilder', 'keyDepositLabel', 'Deposit'))
                .HTMLTable::makeTd('$' . number_format($kdBal, 2)));
            //$keyMkup = "</tr><tr><th class='tdlabel'>" . $labels->getString('resourceBuilder', 'keyDepositLabel', 'Deposit') . "</td><td>$" . number_format($kdBal, 2) . "</td>";
        }

        // Lodging MOA
        $tbl->addBodyTr(
            HTMLTable::makeTh($labels->getString('statement', 'lodgingMOA', 'MOA'))
            .HTMLTable::makeTd('$' . number_format($this->getLodgingMOA($dbh), 2)));

        return $tbl->generateMarkup();

    }

}


