<?php

namespace HHK\Payment\Invoice\InvoiceLine;

use HHK\Tables\EditRS;
use HHK\Tables\Payment\InvoiceLineRS;
use HHK\Purchase\Item;
use HHK\SysConst\{InvoiceLineType};
use HHK\SysConst\ItemId;

/**
 * AbstractInvoiceLine.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */



/**
 * Description of AbstractInvoiceLine
 *
 * @author Eric
 */
abstract class AbstractInvoiceLine {

    /**
     * Summary of lineId
     * @var int
     */
    protected $lineId;
    /**
     * Summary of amount
     * @var
     */
    protected $amount;
    /**
     * Summary of quantity
     * @var int
     */
    protected $quantity;
    /**
     * Summary of price
     * @var
     */
    protected $price;
    /**
     * Summary of itemId
     * @var int
     */
    protected $itemId;
    /**
     * Summary of description
     * @var string
     */
    protected $description;
    /**
     * Summary of typeId
     * @var int
     */
    protected $typeId;
    /**
     * Summary of sourceItemId
     * @var int
     */
    protected $sourceItemId;
    /**
     * Summary of invoiceId
     * @var int
     */
    protected $invoiceId;
    /**
     * Summary of invLineRs
     * @var InvoiceLineRS
     */
    protected $invLineRs;
    /**
     * Summary of var
     * @var
     */
    protected $var;
    /**
     * Summary of carriedFrom
     * @var
     */
    protected $carriedFrom;
    /**
     * Summary of useDetail
     * @var
     */
    protected $useDetail;
    /**
     * Summary of isPercentage
     * @var
     */
    protected $isPercentage;

    /**
     * Summary of __construct
     * @param mixed $useDetail
     */
    public function __construct($useDetail = TRUE) {
        $this->useDetail = $useDetail;
        $this->invLineRs = new InvoiceLineRS();
    }

    /**
     * Summary of loadRecord
     * @param \HHK\Tables\Payment\InvoiceLineRS $invoiceLine
     * @return void
     */
    public function loadRecord(InvoiceLineRS $invoiceLine) {

        $this->lineId = $invoiceLine->idInvoice_Line->getStoredVal();
        $this->setItemId($invoiceLine->Item_Id->getStoredVal());
        $this->setSourceItemId($invoiceLine->Source_Item_Id->getStoredVal());
        $this->description = $invoiceLine->Description->getStoredVal();
        $this->setPrice($invoiceLine->Price->getStoredVal());
        $this->setAmount($invoiceLine->Amount->getStoredVal());
        $this->setQuantity($invoiceLine->Quantity->getStoredVal());

        $this->invLineRs = $invoiceLine;

        $this->carriedFrom = '';
        $this->useDetail = TRUE;
    }

    /**
     * Summary of invoiceLineFactory
     * @param mixed $typeId
     * @return HoldInvoiceLine|InvoiceInvoiceLine|OneTimeInvoiceLine|RecurringInvoiceLine|ReimburseInvoiceLine|TaxInvoiceLine|WaiveInvoiceLine|null
     */
    public static function invoiceLineFactory($typeId) {

        switch ($typeId) {
            case InvoiceLineType::Recurring:
                return new RecurringInvoiceLine();

            case InvoiceLineType::Invoice:
                return new InvoiceInvoiceLine();

            case InvoiceLineType::OneTime;
                return new OneTimeInvoiceLine();

            case InvoiceLineType::Hold:
                return new HoldInvoiceLine();

            case InvoiceLineType::Reimburse:
                return new ReimburseInvoiceLine();

            case InvoiceLineType::Tax:
                return new TaxInvoiceLine();

            case InvoiceLineType::Waive:
                return new WaiveInvoiceLine();

            default:
                return NULL;
        }

    }

    /**
     * Summary of createNewLine
     * @param \HHK\Purchase\Item $item
     * @param mixed $quantity
     * @param mixed $str1
     * @param mixed $str2
     * @return void
     */
    public function createNewLine(Item $item, $quantity, $str1 = '', $str2 = '') {
        $description = "";
        switch($item->getIdItem()){
            case ItemId::AddnlCharge:
            case ItemId::Discount:
                $description = $str1;
                break;
            case ItemId::LodgingMOA:
                $description = $item->getDescription() . " " . $str1;
                break;
            default:
                $description = $item->getDescription();
        }
        
        $this->var = $str1;
        $this->setItemId($item->getIdItem());
        $this->setPrice($item->getUnitPrice());
        $this->setQuantity($quantity);
        $this->setDescription($description);

    }

    /**
     * Summary of save
     * @param \PDO $dbh
     * @param mixed $deleted
     * @return int
     */
    public function save(\PDO $dbh, $deleted = FALSE) {

        //
        $ilRs1 = new InvoiceLineRS();

        $ilRs1->Invoice_Id->setNewVal($this->getInvoiceId());
        $ilRs1->Item_Id->setNewVal($this->getItemId());
        $ilRs1->Type_Id->setNewVal($this->getTypeId());
        $ilRs1->Source_Item_Id->setNewVal($this->getSourceItemId());
        $ilRs1->Description->setNewVal($this->getDescription());
        $ilRs1->Quantity->setNewVal($this->getQuantity());
        $ilRs1->Price->setNewVal($this->getPrice());

        $ilRs1->Amount->setNewVal(round($this->getQuantity() * $this->getPrice(), 2));

        if ($deleted) {
            $ilRs1->Deleted->setNewVal(1);
        }

        $recId = EditRS::insert($dbh, $ilRs1);

        return $recId;

    }

    /**
     * Summary of updateLine
     * @param \PDO $dbh
     * @return int
     */
    public function updateLine(\PDO $dbh) {

        $affected = 0;
        $this->invLineRs->Description->setNewVal($this->getDescription());

        if ($this->getLineId() > 0) {
            $affected = EditRS::update($dbh, $this->invLineRs, array($this->invLineRs->idInvoice_Line));
        }

        return $affected;
    }

    /**
     * Summary of setDeleted
     * @return void
     */
    public function setDeleted() {
        $this->invLineRs->Deleted->setNewVal(1);
    }

    /**
     * Summary of getAmount
     * @return mixed
     */
    public function getAmount() {
        return $this->amount;
    }

    /**
     * Summary of getQuantity
     * @return mixed
     */
    public function getQuantity() {
        return $this->quantity;
    }

    /**
     * Summary of getPrice
     * @return mixed
     */
    public function getPrice() {
        return $this->price;
    }

    /**
     * Summary of getItemId
     * @return mixed
     */
    public function getItemId() {
        return $this->itemId;
    }

    /**
     * Summary of getDescription
     * @return mixed|string
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * Summary of getTypeId
     * @return mixed
     */
    public function getTypeId() {
        return $this->typeId;
    }

    /**
     * Summary of getLineId
     * @return mixed
     */
    public function getLineId() {
        return $this->lineId;
    }

    /**
     * Summary of getInvoiceId
     * @return mixed
     */
    public function getInvoiceId() {
        return $this->invoiceId;
    }

    /**
     * Summary of getCarriedFrom
     * @return mixed|string
     */
    public function getCarriedFrom() {
        return $this->carriedFrom;
    }

    /**
     * Summary of getSourceItemId
     * @return mixed
     */
    public function getSourceItemId() {
        return $this->sourceItemId;
    }

    /**
     * Summary of setSourceItemId
     * @param mixed $sourceItemId
     * @return static
     */
    public function setSourceItemId($sourceItemId) {
        $this->sourceItemId = $sourceItemId;
        return $this;
    }


    /**
     * Summary of setCarriedFrom
     * @param mixed $invoiceNumber
     * @return static
     */
    public function setCarriedFrom($invoiceNumber) {
        $this->carriedFrom = $invoiceNumber;
        return $this;
    }

    /**
     * Summary of setInvoiceId
     * @param mixed $id
     * @return static
     */
    public function setInvoiceId($id) {
        $this->invoiceId = $id;
        return $this;
    }

    /**
     * Summary of setAmount
     * @param mixed $amount
     * @return static
     */
    public function setAmount($amount) {
        $this->amount = $amount;
        return $this;
    }

    /**
     * Summary of setQuantity
     * @param mixed $quantity
     * @return static
     */
    public function setQuantity($quantity) {
        $this->quantity = $quantity;
        return $this;
    }

    /**
     * Summary of setPrice
     * @param mixed $price
     * @return static
     */
    public function setPrice($price) {
        $this->price = $price;
        return $this;
    }

    /**
     * Summary of setItemId
     * @param mixed $itemId
     * @return static
     */
    public function setItemId($itemId) {
        $this->itemId = $itemId;
        return $this;
    }

    /**
     * Summary of setUseDetail
     * @param mixed $bool
     * @return static
     */
    public function setUseDetail($bool) {

        if ($bool === TRUE) {
            $this->useDetail = TRUE;
        } else {
            $this->useDetail = FALSE;
        }
        return $this;
    }

    /**
     * Summary of getUseDetail
     * @return bool|mixed
     */
    public function getUseDetail() {
        return $this->useDetail;
    }

    /**
     * Summary of appendDescription
     * @param mixed $str
     * @return void
     */
    public function appendDescription($str) {
        $this->var = $str;
    }

    /**
     * Summary of setDescription
     * @param mixed $description
     * @return static
     */
    public function setDescription($description) {

        $this->description = $description;

        // var will have invoice notes when useDetail is off.
        /*
        if ($this->useDetail) {
            $this->description .= ($this->description != '' ? '; ' . $this->var : $this->var);
        } else {
            $this->description .= ($this->var != '' ? '; ' . $this->var : '');
        }
*/
        return $this;
    }

    /**
     * Summary of setTypeId
     * @param mixed $typeId
     * @return static
     */
    protected function setTypeId($typeId) {
        $this->typeId = $typeId;
        return $this;
    }

}
?>