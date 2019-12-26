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
 * Description of LocalResponse
 *
 * @author Eric
 */
class LocalResponse extends PaymentResponse {

    protected $cardNum;
    protected $cardType;



    function __construct($amount, $idPayor, $invoiceNumber, $cardType, $cardAcct, $idToken, $payNote = '') {

        $this->paymentType = PayType::ChargeAsCash;
        $this->idPayor = $idPayor;
        $this->amount = $amount;
        $this->invoiceNumber = $invoiceNumber;
        $this->cardNum = $cardAcct;
        $this->cardType = $cardType;
        $this->setIdToken($idToken);
        $this->payNotes = $payNote;

    }


    public function getChargeType() {
        return $this->cardType;
    }

    public function getCardNum() {
        return $this->cardNum;
    }

    public function getStatus() {

        return CreditPayments::STATUS_APPROVED;
    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {

        $chgTypes = readGenLookupsPDO($dbh, 'Charge_Cards');
        $cgType = $this->getChargeType();

        if (isset($chgTypes[$this->getChargeType()])) {
            $cgType = $chgTypes[$this->getChargeType()][1];
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Credit Card:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd($cgType . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd($this->getCardNum()));

    }
}
