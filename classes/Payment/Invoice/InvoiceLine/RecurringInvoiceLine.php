<?php

namespace HHK\Payment\Invoice\InvoiceLine;

use HHK\SysConst\{InvoiceLineType};
use HHK\Tables\EditRS;
use HHK\Tables\Payment\InvoiceLineRS;
use HHK\Purchase\Item;
/**
 * RecurringInvoiceLine.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */



/**
 * Description of RecurringInvoiceLine
 *
 * @author Eric
 */


class RecurringInvoiceLine extends AbstractInvoiceLine {

    protected $periodStart;
    protected $periodEnd;
    protected $units;

    public function __construct($useDetail = TRUE) {
        parent::__construct($useDetail);
        $this->setTypeId(InvoiceLineType::Recurring);
    }

    public function loadRecord(InvoiceLineRS $invoiceLine) {
        parent::loadRecord($invoiceLine);

        $this->setPeriodStart($invoiceLine->Period_Start->getStoredVal());
        $this->setPeriodEnd($invoiceLine->Period_End->getStoredVal());

    }

    public function createNewLine(Item $item, $quantity, $startDate = '', $endDate = '', $units = 0) {

        $this->setPeriodEnd($endDate);
        $this->setPeriodStart($startDate);
        $this->setUnits($units);

        parent::createNewLine($item, $quantity, $this->var);
    }

    public function save(\PDO $dbh, $deleted = FALSE) {

        //
        $ilRs1 = new InvoiceLineRS();

        $ilRs1->Invoice_Id->setNewVal($this->getInvoiceId());
        $ilRs1->Item_Id->setNewVal($this->getItemId());
        $ilRs1->Type_Id->setNewVal($this->getTypeId());
        $ilRs1->Description->setNewVal($this->getDescription());
        $ilRs1->Quantity->setNewVal($this->getQuantity());
        $ilRs1->Price->setNewVal($this->getPrice());

        $ilRs1->Amount->setNewVal($this->getQuantity() * $this->getPrice());

        $ilRs1->Period_Start->setNewVal($this->getPeriodStart());
        $ilRs1->Period_End->setNewVal($this->getPeriodEnd());

        if ($deleted) {
            $ilRs1->Deleted->setNewVal(1);
        }

        $recId = EditRS::insert($dbh, $ilRs1);

        return $recId;

    }

    public function updateLine(\PDO $dbh) {

        $this->invLineRs->Period_Start->setNewVal($this->getPeriodStart());
        $this->invLineRs->Period_End->setNewVal($this->getPeriodEnd());
        $this->invLineRs->Description->setNewVal($this->getDescription());

        return parent::updateLine($dbh);
    }

    public function setDescription($description) {

        if ($this->useDetail && $this->getPeriodStart() != '' && $this->getPeriodEnd() != '') {

            if ($this->units < 1) {
                $this->description = $description .  ':  ' . date('M j, Y', strtotime($this->getPeriodStart())) . ' - ' . date('M j, Y', strtotime($this->getPeriodEnd()));
            } else {
                $this->description = $description .  ':  ' . date('M j, Y', strtotime($this->getPeriodStart())) . ' - ' . date('M j, Y', strtotime($this->getPeriodEnd()));
            }
        } else {
            $this->description = $description; // . ($this->var == '' ? '' : '; ' . $this->var);
        }

        return $this;
    }

    public function getPeriodStart() {
        return $this->periodStart;
    }

    public function getPeriodEnd() {
        return $this->periodEnd;
    }

    public function setPeriodStart($strDate) {
        $this->periodStart = $strDate;
        return $this;
    }

    public function setPeriodEnd($strDate) {
        $this->periodEnd = $strDate;
        return $this;
    }

    public function getUnits() {
        return $this->units;
    }

    public function setUnits($units) {
        $this->units = $units;
        return $this;
    }
}
?>