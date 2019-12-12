<?php

/*
 * The MIT License
 *
 * Copyright 2019 ecran.
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
 * Description of LocalGateway
 *
 * @author ecran
 */
class LocalGateway extends PaymentGateway {

    public static function getPaymentMethod() {
        return PaymentMethod::ChgAsCash;
    }

    protected function loadGateway(\PDO $dbh) {
        return array();
    }

    public function getGatewayName() {
        return 'local';
    }

    protected function setCredentials($credentials) {
        $this->credentials = $credentials;
    }

    protected static function _saveEditMarkup(\PDO $dbh, $gatewayName, $post) {

    }

    protected static function _createEditMarkup(\PDO $dbh, $gatewayName) {
        return '';
    }

    public function processHostedReply(\PDO $dbh, $post, $ssoTtoken, $idInv, $payNotes) {

    }

    public function getPaymentResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup, $invoiceNumber, $idToken = 0, $payNotes = '') {

        return new ManualChargeResponse($vcr->getAuthorizedAmount(), $idPayor, $invoiceNumber, $vcr->getCardType(), $vcr->getMaskedAccount());

    }

    public function getCofResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup) {

    }

    public function selectPaymentMarkup(\PDO $dbh, &$payTbl) {

        // Charge as Cash markup
        $payTbl->addBodyTr(
            HTMLTable::makeTd('New Card: ', array('class'=>'tdlabel'))
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups(readGenLookupsPDO($dbh, 'Charge_Cards')), '', TRUE), array('name'=>'selChargeType', 'style'=>'margin-right:.4em;', 'class'=>'hhk-feeskeys')))
            .HTMLTable::makeTd(' Acct. #: '.HTMLInput::generateMarkup('', array('name'=>'txtChargeAcct', 'size'=>'4', 'title'=>'Only the last 4 digits.', 'class'=>'hhk-feeskeys')))
            , array('id'=>'trvdCHName'));

    }

}
