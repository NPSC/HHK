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
        return PaymentMethod::Charge;
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

    public function creditSale(\PDO $dbh, PaymentManagerPayment $pmp, Invoice $invoice, $postbackUrl) {

        $uS = Session::getInstance();

        // Check token id for pre-stored credentials.
        $tokenRS = CreditToken::getTokenRsFromId($dbh, $pmp->getIdToken());

        // Do we have a token?
        if (CreditToken::hasToken($tokenRS)) {
            $pmp->setChargeCard($tokenRS->CardType->getStoredVal());
            $pmp->setChargeAcct($tokenRS->MaskedAccount->getStoredVal());
            $pmp->setCardHolderName($tokenRS->CardHolderName->getStoredVal());
        }

        $gwResp = new LocalGwResp($invoice->getAmountToPay(), $invoice->getInvoiceNumber(), $pmp->getChargeCard(), $pmp->getChargeAcct(), $pmp->getCardHolderName(), MpTranType::Sale, $uS->username);

        $vr = new LocalResponse($gwResp, $invoice->getSoldToId(), $invoice->getIdGroup(), $pmp->getIdToken(), PaymentStatusCode::Paid);

        $vr->setPaymentDate($pmp->getPayDate());
        $vr->setPaymentNotes($pmp->getPayNotes());


        // New Token?
        if ($vr->response->getIdToken() != '') {

            $guestTokenRs = CreditToken::getTokenRsFromId($dbh, $vr->getIdToken());

            $vr->response->setMaskedAccount($guestTokenRs->MaskedAccount->getStoredVal());
            $vr->response->setCardHolderName($guestTokenRs->CardHolderName->getStoredVal());
            $vr->response->setOperatorId($uS->username);
            $vr->cardType = $guestTokenRs->CardType->getStoredVal();
            $vr->expDate = $guestTokenRs->ExpDate->getStoredVal();
        }

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $vr, $this->getGatewayName(), TransType::Sale, TransMethod::Token);
        $vr->setIdTrans($transRs->idTrans->getStoredVal());


        // Record Payment
        $vrr = SaleReply::processReply($dbh, $vr, $uS->username);


        //ChargeAsCashTX::sale($dbh, $cashResp, $uS->username, $paymentDate);

        // Update invoice
//        $invoice->updateInvoiceBalance($dbh, $invoice->getAmountToPay(), $uS->username);
//
//        $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
//        $payResult->feePaymentAccepted($dbh, $uS, $cashResp, $invoice);
//        $payResult->setDisplayMessage('External Credit Payment Recorded.  ');
    }

    protected function _voidSale(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid) {
        return array('warning' => '_voidSale is not implemented. ');
    }

    protected function _returnPayment(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $retAmount, $bid) {
        return array('warning' => '_returnPayment is not implemented. ');
    }

    public function voidReturn(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid) {
        return array('warning' => 'Not Available.  ');
    }

    public function returnAmount(\PDO $dbh, Invoice $invoice, $rtnToken, $paymentNotes = '') {
        return array('warning' => 'Return Amount is not implemented. ');
    }

    public function reverseSale(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid) {
        return $this->voidSale($dbh, $invoice, $payRs, $pAuthRs, $bid);
    }

    protected static function _saveEditMarkup(\PDO $dbh, $gatewayName, $post) {

    }

    protected static function _createEditMarkup(\PDO $dbh, $gatewayName) {
        return '';
    }

    public function processHostedReply(\PDO $dbh, $post, $ssoTtoken, $idInv, $payNotes, $payDate) {

    }

    public function getPaymentResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup, $invoiceNumber, $idToken = 0, $payNotes = '') {

        return new LocalResponse($vcr->getAuthorizedAmount(), $idPayor, $invoiceNumber, $vcr->getCardType(), $vcr->getMaskedAccount());

    }

    public function getCofResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup) {

    }

    public function selectPaymentMarkup(\PDO $dbh, &$payTbl, $index = '') {

        $tbl = new HTMLTable();
        $tbl->addBodyTr(
            HTMLTable::makeTd('New Card: ', array('class'=>'tdlabel'))
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups(readGenLookupsPDO($dbh, 'Charge_Cards')), '', TRUE), array('name'=>'selChargeType'.$index, 'style'=>'margin-right:.4em;', 'class'=>'hhk-feeskeys'.$index)))
            .HTMLTable::makeTd(' Acct. #: '.HTMLInput::generateMarkup('', array('name'=>'txtChargeAcct'.$index, 'size'=>'4', 'title'=>'Only the last 4 digits.', 'class'=>'hhk-feeskeys'.$index)))
             );
        $tbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type' => 'textbox', 'size'=>'40', 'placeholder'=>'Cardholder Name', 'name' => 'txtvdNewCardName', 'class'=>'hhk-feeskeys')), array('colspan'=>'4')));


        // Charge as Cash markup
        $payTbl->addBodyTr(
            HTMLTable::makeTd($tbl->generateMarkup(), array('colspan'=>'5'))
            , array('id'=>'trvdCHName'.$index));

    }

}