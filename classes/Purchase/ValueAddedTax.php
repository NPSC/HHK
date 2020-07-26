<?php

namespace HHK\Purchase;

use HHK\Exception\RuntimeException;

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
     * @throws RuntimeException
     */
    public function __construct(\PDO $dbh) {

        $this->allTaxedItems = array();

        foreach ($this->loadTaxedItemList($dbh) as $i) {
                $this->allTaxedItems[] = new TaxedItem($i['idItem'], $i['taxIdItem'], $i['Max_Days'], $i['Percentage'], $i['Description'], $i['Gl_Code'], $i['First_Order_Id'], $i['Last_Order_Id']);
        }
    }

    /**
     * Get current taxes for a particular visit id.
     *
     * @param \PDO $dbh
     * @param int $idVisit
     * @return []
     */
    protected function loadTaxedItemList(\PDO $dbh) {

        // Taxed items
        $tistmt = $dbh->query("select ii.idItem, ti.Percentage, ti.Description, ti.Timeout_Days as `Max_Days`, ti.idItem as `taxIdItem`, ti.Gl_Code, ti.First_Order_Id, ti.Last_Order_Id "
                . " from item_item ii join item i on ii.idItem = i.idItem join item ti on ii.Item_Id = ti.idItem"
                . " where ti.Deleted = 0 ");

        return $tistmt->fetchAll(\PDO::FETCH_ASSOC);
    }


    /** Get sum of tax item percentage values connected to each taxable item.
     *
     * @param int $numDays
     * @return array of each taxed item containing the sum (float) of all connected taxes filtered by days.
     */
    public function getTaxedItemSums($idVisit, $numDays) {

        $taxedItems = array();

        foreach ($this->getCurrentTaxedItems($idVisit, $numDays) as $t) {

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
    public function getTimedoutTaxItems($taxedItemId, $idVisit, $numDays) {

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
     * @param int $idVisit
     * @param int $numDays
     * @return array
     */
    public function getCurrentTaxedItems($idVisit, $numDays = 0) {

        $current = array();

        foreach ($this->getAllTaxedItems($idVisit) as $t) {

            if ($t->getMaxDays() == 0 || $numDays <= $t->getMaxDays()) {
                $current[] = $t;
            }
        }

        return $current;
    }

    /**
     * Get the taxing Items for a given taxed item
     *
     * @param int $idVisit
     * @param int $numDays
     * @param int $idTaxedItem
     * @return array
     */
    public function getCurrentTaxingItems($idVisit, $numDays, $idTaxedItem) {

        $taxingItems = array();
        foreach ($this->getCurrentTaxedItems($idVisit, $numDays) as $t) {
            if ($t->getIdTaxedItem() == $idTaxedItem) {
                $taxingItems[] = $t;
            }
        }

        return $taxingItems;
    }

    /**
     *
     * @param int $idVisit
     * @return array of all the TaxedItems for a particular visit Id
     */
    public function getAllTaxedItems($idVisit) {

        if (is_null($idVisit) || $idVisit < 0) {
            // throw an error
            throw new RuntimeException('Invalid visit Id: ' . $idVisit);
        }

        $taxSubset = [];

        foreach ($this->allTaxedItems as $t) {

            if ($idVisit == 0 && $t->getLastOrderId() == 0) {
                $taxSubset[] = $t;
            } else {

                if ($t->getLastOrderId() == 0) {
                    $lastOrderId = $idVisit;
                } else {
                    $lastOrderId = $t->getLastOrderId();
                }

                if ($idVisit >= $t->getFirstOrderId() && $idVisit <= $lastOrderId) {
                    $taxSubset[] = $t;
                }
            }
        }

        return $taxSubset;

    }

}
?>