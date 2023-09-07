<?php

namespace HHK\House;

use HHK\SysConst\{InvoiceStatus, ItemId, VisitStatus};
use HHK\TableLog\VisitLog;
use HHK\Tables\EditRS;
use HHK\Tables\Registration\RegistrationRS;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\Exception\RuntimeException;
use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLTable};
use HHK\House\Reservation\Reservation_1;

/**
 * Registration.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class Registration {

    /**
     * Summary of regRS
     * @var
     */
    protected $regRS;

    /**
     * Summary of isNew
     * @var
     */
    public $isNew;
    /**
     * Summary of depositBalance
     * @var
     */
    protected $depositBalance;
    /**
     * Summary of lodgingMOA
     * @var
     */
    protected $lodgingMOA;
    /**
     * Summary of prepaymentMOA
     * @var
     */
    protected $prepaymentMOA;
    /**
     * Summary of donations
     * @var
     */
    protected $donations;
    /**
     * Summary of rawRow
     * @var
     */
    protected $rawRow;

    /**
     * Summary of __construct
     * @param \PDO $dbh
     * @param mixed $idPsg
     * @param mixed $idRegistration
     */
    public function __construct(\PDO $dbh, $idPsg, $idRegistration = 0) {

        $this->regRS = new RegistrationRs();
        $rows = array();
        $this->rawRow = array();
        $this->isNew = TRUE;
        $this->depositBalance = NULL;
        $this->lodgingMOA = NULL;
        $this->prepaymentMOA = NULL;

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

    /**
     * Summary of loadDepositBalance
     * @param \PDO $dbh
     * @param mixed $idRegistration
     * @param int $idVisit
     * @return float
     */
    public static function loadDepositBalance(\PDO $dbh, $idRegistration, $idVisit = 0) {

        $depositBalance = 0.0;
        $where = '';

        if ($idVisit == 0) {
            $idg = intval($idRegistration, 10);
            $where = "and i.idGroup = " . $idg;
            if ($idg < 1) {
                return $depositBalance;
            }
        } else {
            $where = " and i.Order_Number = " . $idVisit;
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
        and i.Status = '" . InvoiceStatus::Paid ."' " . $where;
        $stmt = $dbh->query($query);

        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        if (count($rows) == 1) {
            $depositBalance = floatval($rows[0][0]);
        }

        return $depositBalance;
    }

    /**
     * Summary of loadLodgingBalance
     * @param \PDO $dbh
     * @param mixed $idRegistration
     * @param int $idVisit
     * @return float
     */
    public static function loadLodgingBalance(\PDO $dbh, $idRegistration, $idVisit = 0)
    {
        $lodgingBalance = 0.0;
        $where = '';

        if ($idVisit == 0) {
            $idg = intval($idRegistration, 10);
            if ($idg < 1) {
                return $lodgingBalance;
            }
            $where = " and i.idGroup = " . $idg;
        } else {
            $where = " and i.Order_Number = " . $idVisit;
        }


        $query = "select
    sum(il.Amount)
from
    invoice_line il
        join
    invoice i ON il.Invoice_Id = i.idInvoice
where
    il.Item_Id = ". ItemId::LodgingMOA . "
        and i.Deleted = 0
        and il.Deleted = 0
        and i.Status = '" . InvoiceStatus::Paid . "' " . $where;
        $stmt = $dbh->query($query);

        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        if (count($rows) == 1) {
            $lodgingBalance = floatval($rows[0][0]);
        }

        return $lodgingBalance;
    }

    /**
     * Summary of loadDonationBalance
     * @param \PDO $dbh
     * @param mixed $idGroup
     * @return float
     */
    public static function loadDonationBalance(\PDO $dbh, $idGroup) {

        $DonBalance = 0.0;
        $idg = intval($idGroup, 10);

        if ($idg < 1) {
            return $DonBalance;
        }

        $query = "select
    sum(il.Amount)
from
    invoice_line il
        join
    invoice i ON il.Invoice_Id = i.idInvoice
where
    il.Item_Id = ". ItemId::LodgingDonate . "
        and i.Deleted = 0
        and il.Deleted = 0
        and i.Status = '" . InvoiceStatus::Paid . "'
        and i.idGroup = " . $idg;
        $stmt = $dbh->query($query);

        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        if (count($rows) == 1) {
            $DonBalance = floatval($rows[0][0]);
        }

        return $DonBalance;
    }

    /**
     * Summary of loadPrepayments
     * @param \PDO $dbh
     * @param mixed $idGroup
     * @return float|int
     */
    public static function loadPrepayments(\PDO $dbh, $idGroup) {

        $prePayment = 0;
        $idg = intval($idGroup, 10);

        if ($idg < 1) {
            return $prePayment;
        }

        $query = "select
        sum(il.Amount)
    from
        invoice_line il
            join
        invoice i ON il.Invoice_Id = i.idInvoice and i.idGroup = $idg AND il.Item_Id = ". ItemId::LodgingMOA . " AND il.Deleted = 0
            join
    	reservation_invoice ri ON i.idInvoice = ri.Invoice_Id
    where
        i.Deleted = 0
        AND i.Order_Number = 0
        AND i.`Status` = '" . InvoiceStatus::Paid . "'";

        $stmt = $dbh->query($query);

        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        if (count($rows) == 1) {
            $prePayment = floatval($rows[0][0]);
        }

        return $prePayment;
    }

    /**
     * Summary of updatePrefTokenId
     * @param \PDO $dbh
     * @param mixed $idRegistration
     * @param mixed $idToken
     * @return bool|int
     */
    public static function updatePrefTokenId(\PDO $dbh, $idRegistration, $idToken) {

        $tokenId = intval($idToken);
        $regId = intval($idRegistration);

        if ($tokenId < 1 || $regId < 1) {
            return;
        }

        return $dbh->exec("update registration set Pref_Token_Id = $tokenId where idregistration = $regId and Pref_Token_Id != $tokenId");
    }

    /**
     * Summary of readPrefTokenId
     * @param \PDO $dbh
     * @param mixed $idRegistration
     * @return mixed
     */
    public static function readPrefTokenId(\PDO $dbh, $idRegistration) {

        $tokenId = 0;

        if ($idRegistration > 0) {
            $stmt = $dbh->query("select Pref_Token_Id from registration where idRegistration = $idRegistration");
            $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
            if (count($rows) == 1) {
                $tokenId = $rows[0][0];
            }
        }

        return $tokenId;
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

    /**
     * Summary of getLodgingMOA
     * @param \PDO $dbh
     * @return float
     */
    public function getLodgingMOA(\PDO $dbh) {

        if (is_null($this->lodgingMOA)) {
            $this->lodgingMOA = $this->loadLodgingBalance($dbh, $this->getIdRegistration());
        }

        return $this->lodgingMOA;
    }

    /**
     * Summary of getPrePayments
     * @param \PDO $dbh
     * @return float|int
     */
    public function getPrePayments(\PDO $dbh) {

        if (is_null($this->prepaymentMOA)) {
            $this->prepaymentMOA = $this->loadPrepayments($dbh, $this->getIdRegistration());
        }

        return $this->prepaymentMOA;
    }

    /**
     * Summary of getDonations
     * @param \PDO $dbh
     * @return float
     */
    public function getDonations(\PDO $dbh) {

        if (is_null($this->donations)) {
            $this->donations = $this->loadDonationBalance($dbh, $this->getIdRegistration());
        }

        return $this->donations;
    }

    /**
     * Summary of getIdRegistration
     * @return mixed
     */
    public function getIdRegistration() {
        return $this->regRS->idRegistration->getStoredVal();
    }

    /**
     * Summary of getIdPsg
     * @return mixed
     */
    public function getIdPsg() {
        return $this->regRS->idPsg->getStoredVal();
    }

    /**
     * Summary of getEmailReceipt
     * @return bool
     */
    public function getEmailReceipt() {
        if ($this->regRS->Email_Receipt->getStoredVal() == 1) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Summary of getPreferredTokenId
     * @return mixed
     */
    public function getPreferredTokenId() {
        return $this->regRS->Pref_Token_Id->getStoredVal();
    }


    /**
     * Summary of getNoVehicle
     * @return mixed
     */
    public function getNoVehicle() {
        return $this->regRS->Vehicle->getStoredVal();
    }

    /**
     * Summary of setNoVehicle
     * @param mixed $b
     * @return void
     */
    public function setNoVehicle($b) {
        if ($b) {
            $this->regRS->Vehicle->setNewVal('1');
        } else {
            $this->regRS->Vehicle->setNewVal('0');
        }
    }

    /**
     * Summary of isNew
     * @return bool
     */
    public function isNew() {
        if ($this->regRS->idRegistration->getStoredVal() == 0) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Summary of getRegRS
     * @return RegistrationRS
     */
    public function getRegRS() {
        return $this->regRS;
    }

    /**
     * Summary of extractVehicleFlag
     * @return void
     */
    public function extractVehicleFlag() {

        if (isset($_POST["cbNoVehicle"])) {
            $this->regRS->Vehicle->setNewVal('1');
        } else {
            $this->regRS->Vehicle->setNewVal('0');
        }

    }

    /**
     * Summary of extractRegistration

     * @return void
     */
    public function extractRegistration() {

        if (filter_has_var(INPUT_POST, 'regGuest_Ident')) {
            $this->regRS->Guest_Ident->setNewVal('1');
            $this->rawRow['Guest_Ident'] = '1';
        } else {
            $this->regRS->Guest_Ident->setNewVal('0');
            $this->rawRow['Guest_Ident'] = '0';
        }

        if (filter_has_var(INPUT_POST, 'regPamphlet')) {
            $this->regRS->Pamphlet->setNewVal('1');
            $this->rawRow['Pamphlet'] = '1';
        } else {
            $this->regRS->Pamphlet->setNewVal('0');
            $this->rawRow['Pamphlet'] = '0';
        }

        if (filter_has_var(INPUT_POST, 'regReferral')) {
            $this->regRS->Referral->setNewVal('1');
            $this->rawRow['Referral'] = '1';
        } else {
            $this->regRS->Referral->setNewVal('0');
            $this->rawRow['Referral'] = '0';
        }

        if (filter_has_var(INPUT_POST, 'regSig_Card')) {
            $this->regRS->Sig_Card->setNewVal('1');
            $this->rawRow['Sig_Card'] = '1';
        } else {
            $this->regRS->Sig_Card->setNewVal('0');
            $this->rawRow['Sig_Card'] = '0';
        }

        if (filter_has_var(INPUT_POST, "cbEml")) {
            $this->regRS->Email_Receipt->setNewVal('1');
        } else {
            $this->regRS->Email_Receipt->setNewVal('');
        }
    }

    /**
     * Summary of saveRegistrationRs
     * @param \PDO $dbh
     * @param int $idPsg
     * @param string $uname
     * @throws \HHK\Exception\RuntimeException
     * @return string
     */
    public function saveRegistrationRs(\PDO $dbh, $idPsg, $uname) {

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
            throw new RuntimeException('Registration missing a PSG Id.');
        }

        EditRS::updateStoredVals($this->regRS);
        return $msg;
    }

    /**
     * Summary of createRegMarkup
     * @param \PDO $dbh
     * @param mixed $adminKey
     * @return string
     */
    public function createRegMarkup(\PDO $dbh, $adminKey) {

        // get session instance
        $uS = Session::getInstance();
        $labels = Labels::getLabels();
        $tbl = new HTMLTable();

        // Date Registered
        $tbl->addBodyTr(HTMLTable::makeTh('Date', array('style'=>'text-align:right;'))
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
                    HTMLTable::makeTh(HTMLContainer::generateMarkup('label', $r['Description'], array('for'=>'reg' . $r['Code'])), array('style'=>'text-align:right;'))
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
            HTMLTable::makeTh(HTMLContainer::generateMarkup('label', 'Email Receipt', array('for'=>'cbEml')), array('style'=>'text-align:right;'))
            . HTMLTable::makeTd(HTMLInput::generateMarkup('', $emAttrs))
            );


        // Key Deposit
        if ($uS->KeyDeposit) {
            $kdBal = $this->getDepositBalance($dbh);

            $tbl->addBodyTr(
                HTMLTable::makeTh($labels->getString('resourceBuilder', 'keyDepositLabel', 'Deposit'), array('style'=>'text-align:right;'))
                .HTMLTable::makeTd('$' . number_format($kdBal, 2), array('style'=>'text-align:left;')));
        }

        // Lodging MOA
        $tbl->addBodyTr(
            HTMLTable::makeTh($labels->getString('statement', 'lodgingMOA', 'MOA'), array('style'=>'text-align:right;'))
            .HTMLTable::makeTd('$' . number_format($this->getLodgingMOA($dbh), 2), array('style'=>'text-align:left;')));

//         // Pre-Payments
//         if ($uS->AcceptResvPaymt) {
//             $tbl->addBodyTr(
//                 HTMLTable::makeTh($labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' Pre-Payments', array('style'=>'text-align:right;'))
//                 .HTMLTable::makeTd('$' . number_format($this->getPrePayments($dbh), 2), array('style'=>'text-align:left;')));
//         }

        // Donations
        $tbl->addBodyTr(
            HTMLTable::makeTh('Donations', array('style'=>'text-align:right;'))
            .HTMLTable::makeTd('$' . number_format($this->getDonations($dbh), 2), array('style'=>'text-align:left;')));

        return $tbl->generateMarkup();

    }

}
