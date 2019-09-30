<?php

/*
 * The MIT License
 *
 * Copyright 2019 Eric.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Value Added Tax (VAT).
 *
 * @author Eric
 */
class ValueAddedTax {

    protected $allTaxedItems;

    /**
     *
     * @param \PDO $dbh
     * @param int $idVisit  must be NULL to use idRegistration.
     * @param int $idRegistration
     * @throws Hk_Exception_Runtime
     */
    public function __construct(\PDO $dbh, $idVisit) {

        $this->allTaxedItems = array();

        if (is_null($idVisit) === FALSE) {

            foreach ($this->loadTaxedItemList($dbh, $idVisit) as $i) {
                $this->allTaxedItems[] = new TaxedItem($i['idItem'], $i['taxIdItem'], $i['Max_Days'], $i['Percentage'], $i['Description'], $i['Gl_Code'], $i['First_Order_Id'], $i['Last_Order_Id']);
            }

        } else {
            throw new Hk_Exception_Runtime('Value Added tax is missing a valid visit id or registration id');
        }
    }

    /**
     * Get current taxes for a particular visit id.
     *
     * @param \PDO $dbh
     * @param int $idVisit
     * @return []
     */
    protected function loadTaxedItemList(\PDO $dbh, $idVisit) {

        // Taxed items
        $tistmt = $dbh->query("select ii.idItem, ti.Percentage, ti.Description, ti.Timeout_Days as `Max_Days`, ti.idItem as `taxIdItem`, ti.Gl_Code, ti.First_Order_Id, ti.Last_Order_Id "
                . " from item_item ii join item i on ii.idItem = i.idItem join item ti on ii.Item_Id = ti.idItem"
                . " where ti.Deleted = 0 and (($idVisit = 0 and ti.Last_Order_Id = 0) or $idVisit <= ti.Last_Order_Id or ti.Last_Order_Id= 0) and $idVisit >= ti.First_Order_Id");

        return $tistmt->fetchAll(\PDO::FETCH_ASSOC);
    }


    /** Get sum of tax item percentage values connected to each taxable item.
     *
     * @param int $numDays
     * @return array of each taxed item containing the sum (float) of all connected taxes filtered by days.
     */
    public function getTaxedItemSums($numDays, $idVisit = NULL) {

        // Any taxes
        $taxedItems = array();

        foreach ($this->getCurrentTaxedItems($numDays, $idVisit) as $t) {

            if (isset($taxedItems[$t->getIdTaxedItem()])) {
                $taxedItems[$t->getIdTaxedItem()] += $t->getDecimalTax();
            } else {
                $taxedItems[$t->getIdTaxedItem()] = $t->getDecimalTax();
            }
        }

        return $taxedItems;
    }

    /** return any 'timed-out' tax items connected to the given item
     *  compared against $numDays.
     *
     * @param int $taxedItemId  the taxable item
     * @param int $numDays  the number of days under the tax
     * @return array tax item id's that have timed out.
     */
    public function getTimedoutTaxItems($taxedItemId, $numDays, $idVisit = NULL) {

        $timedout = array();

        if ($numDays <= 0) {
            return $timedout;
        }

        foreach ($this->getAllTaxedItems($idVisit) as $t) {

            if ($t->getIdTaxedItem() == $taxedItemId && $t->getMaxDays() > 0 && $numDays > $t->getMaxDays()) {
                $timedout[] = $t;
            }
        }

        return $timedout;
    }

    /**
     * The current taxed items include the vat objects
     *
     * @param type $numDays
     * @param type $idVisit
     * @return type
     */
    public function getCurrentTaxedItems($numDays = 0, $idVisit = NULL) {

        $current = array();

        foreach ($this->getAllTaxedItems($idVisit) as $t) {

            if ($t->getMaxDays() == 0 || $numDays <= $t->getMaxDays()) {
                $current[] = $t;
            }
        }

        return $current;
    }

    /**
     *
     * @return array of all the TaxedItems
     */
    public function getAllTaxedItems($idVisit = NULL) {
        return $this->allTaxedItems;
    }

}


class ValueAddedTaxReg extends ValueAddedTax {


    /**
     * Load taxes for all visits associated with a registration Id.
     *
     * @param \PDO $dbh
     * @param int $idReg
     * @return []
     */
    protected function loadTaxedItemList(\PDO $dbh, $idReg) {

        // Taxed items
        $tistmt = $dbh->query("select ii.idItem, ti.Percentage, ti.Description, ti.Timeout_Days as `Max_Days`, ti.idItem as `taxIdItem`, ti.Gl_Code, ti.First_Order_Id, ti.Last_Order_Id "
                . " from item_item ii join item i on ii.idItem = i.idItem join item ti on ii.Item_Id = ti.idItem"
                . " Join visit v on v.idRegistration = $idReg"
                . " where ti.Deleted = 0 and ((v.idVisit = 0 and ti.Last_Order_Id = 0) or ((v.idVisit <= ti.Last_Order_Id or ti.Last_Order_Id= 0) and v.idVisit >= ti.First_Order_Id))");
// v.idVisit >= ti.First_Order_Id and v.idVisit <= ti.Last_Order_Id");

        return $tistmt->fetchAll(\PDO::FETCH_ASSOC);
    }

        /**
     *
     * @return array of all the TaxedItems
     */
    public function getAllTaxedItems($idVisit = NULL) {

        if (is_null($idVisit) || $idVisit < 1) {
            // throw an error
            throw new Hk_Exception_Runtime('Invalid visit Id: ' . $idVisit);
        }

        $taxSubset = [];

        foreach ($this->allTaxedItems as $t) {

            if ($idVisit >= $t->getFirstOrderId() && $idVisit <= $t->getLastOrderId()) {
                $taxSubset[] = $t;
            }
        }

        return $taxSubset;

    }

}


class TaxedItem {

    protected $idTaxedItem;
    protected $idTaxingItem;
    protected $maxDays;
    protected $percentTax;
    protected $decimalTax;
    protected $taxingItemDesc;
    protected $taxingItemGlCode;
    protected $firstOrderId;
    protected $lastOrderId;

    public function __construct($idTaxedItem, $idTaxingItem, $maxDays, $percentTax, $taxingItemDesc, $taxingItemGlCode, $firstOrderId, $lastOrderId) {
        $this->idTaxedItem = $idTaxedItem;
        $this->idTaxingItem = $idTaxingItem;
        $this->maxDays = intval($maxDays, 10);
        $this->percentTax = $percentTax;
        $this->taxingItemDesc = $taxingItemDesc;
        $this->taxingItemGlCode = $taxingItemGlCode;
        $this->firstOrderId = $firstOrderId;
        $this->lastOrderId = $lastOrderId;
    }

    public function getIdTaxedItem() {
        return $this->idTaxedItem;
    }

    public function getIdTaxingItem() {
        return $this->idTaxingItem;
    }

    public function getMaxDays() {
        return $this->maxDays;
    }

    public function getPercentTax() {
        return $this->percentTax;
    }

    public function getTextPercentTax() {
        $strTax = (string)$this->getPercentTax();

        return $this->suppressTrailingZeros($strTax);
    }

    public function getDecimalTax() {
        return $this->getPercentTax() / 100;
    }

    public function getTaxingItemDesc() {
        return $this->taxingItemDesc;
    }

    public function getTaxingItemGlCode() {
        return $this->taxingItemGlCode;
    }

    public function getFirstOrderId() {
        return $this->firstOrderId;
    }

    public function getLastOrderId() {
        return $this->lastOrderId;
    }


    public static function suppressTrailingZeros($strTax) {

        $taxPrettyStr = '';

        $taxArray = str_split($strTax, 1);
        $cntr = count($taxArray);

        if ($cntr <= 1) {
            $taxPrettyStr = $strTax;
        } else {

            for ($n = ($cntr - 1); $n>=1; $n--) {
                if ($taxArray[$n] == '0' || $taxArray[$n] == '.') {
                    unset($taxArray[$n]);
                } else {
                    break;
                }
            }

            $taxPrettyStr = implode('', $taxArray);
        }

        return $taxPrettyStr . '%';
    }


}
