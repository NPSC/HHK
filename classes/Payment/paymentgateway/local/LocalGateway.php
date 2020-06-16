<?php

/*
 * The MIT License
 *
 * Copyright 2019 Non-Profit Software Corporation.
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
		return array ();
	}
	public function getGatewayName() {
		return 'local';
	}
	public function getGatewayType() {
		return '';
	}
	public function hasVoidReturn() {
		return FALSE;
	}
	
	public function hasCofService() {
		return TRUE;
	}
	protected function setCredentials($credentials) {
		$this->credentials = $credentials;
	}
	public function creditSale(\PDO $dbh, PaymentManagerPayment $pmp, Invoice $invoice, $postbackUrl) {
		$uS = Session::getInstance ();

		// Lookup the charge card
		$chgTypes = readGenLookupsPDO ( $dbh, 'Charge_Cards' );
		if (isset ( $chgTypes [$pmp->getChargeCard ()] )) {
			$pmp->setChargeCard ( $chgTypes [$pmp->getChargeCard ()] [1] );
		}

		// Check token id for pre-stored credentials.
		$tokenRS = CreditToken::getTokenRsFromId ( $dbh, $pmp->getIdToken () );

		// Do we have a token?
		if (CreditToken::hasToken ( $tokenRS )) {
			$pmp->setChargeCard ( $tokenRS->CardType->getStoredVal () );
			$pmp->setChargeAcct ( $tokenRS->MaskedAccount->getStoredVal () );
			$pmp->setCardHolderName ( $tokenRS->CardHolderName->getStoredVal () );
		}

		$gwResp = new LocalGwResp ( $invoice->getAmountToPay (), $invoice->getInvoiceNumber (), $pmp->getChargeCard (), $pmp->getChargeAcct (), $pmp->getCardHolderName (), MpTranType::Sale, $uS->username );

		$vr = new LocalResponse ( $gwResp, $invoice->getSoldToId (), $invoice->getIdGroup (), $pmp->getIdToken (), PaymentStatusCode::Paid );

		$vr->setPaymentDate ( $pmp->getPayDate () );
		$vr->setPaymentNotes ( $pmp->getPayNotes () );

		// New Token?
		if ($vr->getIdToken () != '') {

			$guestTokenRs = CreditToken::getTokenRsFromId ( $dbh, $vr->getIdToken () );

			$vr->response->setMaskedAccount ( $guestTokenRs->MaskedAccount->getStoredVal () );
			$vr->response->setCardHolderName ( $guestTokenRs->CardHolderName->getStoredVal () );
			$vr->response->setOperatorId ( $uS->username );
			$vr->cardType = $guestTokenRs->CardType->getStoredVal ();
			$vr->expDate = $guestTokenRs->ExpDate->getStoredVal ();
		}

		// Record transaction
		$transRs = Transaction::recordTransaction ( $dbh, $vr, $this->getGatewayName (), TransType::Sale, TransMethod::Token );
		$vr->setIdTrans ( $transRs->idTrans->getStoredVal () );

		// Record Payment
		$vrr = SaleReply::processReply ( $dbh, $vr, $uS->username );

		$payResult = new PaymentResult ( $invoice->getIdInvoice (), $invoice->getIdGroup (), $invoice->getSoldToId (), $vr->getIdToken () );

		// Update invoice
		$invoice->updateInvoiceBalance ( $dbh, $vrr->response->getAuthorizedAmount (), $uS->username );

		$payResult->feePaymentAccepted ( $dbh, $uS, $vrr, $invoice );
		$payResult->setDisplayMessage ( 'Paid by Credit Card.  ' );

		return $payResult;
	}
	public function initCardOnFile(\PDO $dbh, $pageTitle, $idGuest, $idGroup, $manualKey, $cardHolderName, $postbackUrl, $selChgType = '', $chgAcct = '', $idx = '') {
		$uS = Session::getInstance ();
		
		if ($selChgType == '' || $chgAcct == '') {
			return array('COFmsg'=>'Missing charge type and/or account number');
		}
		
		if ($cardHolderName == '') {
			$guest = new Guest($dbh, '', $idGuest);
			$cardHolderName = $guest->getRoleMember()->getMemberFullName();
		}

		$gwResp = new LocalGwResp ( 0, '', $selChgType, $chgAcct, $cardHolderName, MpTranType::CardOnFile, $uS->username );
		
		$vr = new LocalResponse ( $gwResp, $idGuest, $idGroup, 0, PaymentStatusCode::Paid );
		
		try {
			$vr->idToken = CreditToken::storeToken($dbh, $vr->idRegistration, $vr->idPayor, $vr->response);
		} catch(Exception $ex) {
			return array('error'=> $ex->getMessage());
		}
		
		$dataArray['COFmsg'] = 'Card Added.';
		$dataArray['COFmkup'] = HouseServices::guestEditCreditTable($dbh, $idGroup, $idGuest, $idx);
		return $dataArray;
	}
	
	protected function _voidSale(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid) {
		$uS = Session::getInstance ();
		$dataArray = array (
				'bid' => $bid
		);

		// find the token record
		if ($payRs->idToken->getStoredVal () > 0) {
			$tknRs = CreditToken::getTokenRsFromId ( $dbh, $payRs->idToken->getStoredVal () );
		}

		$gwResp = new LocalGwResp ( $pAuthRs->Approved_Amount->getStoredVal (), $invoice->getInvoiceNumber (), $pAuthRs->Card_Type->getStoredVal (), $pAuthRs->Acct_Number->getStoredVal (), $tknRs->CardHolderName->getStoredVal (), MpTranType::Void, $uS->username );

		$vr = new LocalResponse ( $gwResp, $invoice->getSoldToId (), $invoice->getIdGroup (), $tknRs->idGuest_token->getStoredVal (), PaymentStatusCode::VoidSale );
		$vr->setPaymentDate ( date ( 'Y-m-d H:i:s' ) );

		// Record transaction
		$transRs = Transaction::recordTransaction ( $dbh, $vr, $this->getGatewayType (), TransType::Void, TransMethod::Token );
		$vr->setIdTrans ( $transRs->idTrans->getStoredVal () );

		// Record payment
		$vrr = VoidReply::processReply ( $dbh, $vr, $uS->username, $payRs );

		// Update invoice
		$invoice->updateInvoiceBalance ( $dbh, 0 - $vrr->response->getAuthorizedAmount (), $uS->username );

		$vrr->idVisit = $invoice->getOrderNumber ();
		$dataArray ['receipt'] = HTMLContainer::generateMarkup ( 'div', nl2br ( Receipt::createVoidMarkup ( $dbh, $vrr, $uS->siteName, $uS->sId ) ) );
		$dataArray ['success'] = 'Payment is void.  ';

		return $dataArray;
	}

	protected function _returnPayment(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $retAmount, $bid) {
		$uS = Session::getInstance ();

		// find the token
		if ($payRs->idToken->getStoredVal () > 0) {
			$tknRs = CreditToken::getTokenRsFromId ( $dbh, $payRs->idToken->getStoredVal () );
		}

		$dataArray = array (
				'bid' => $bid
		);

		$gwResp = new LocalGwResp ( $pAuthRs->Approved_Amount->getStoredVal (), $invoice->getInvoiceNumber (), $pAuthRs->Card_Type->getStoredVal (), $pAuthRs->Acct_Number->getStoredVal (), $tknRs->CardHolderName->getStoredVal (), MpTranType::ReturnSale, $uS->username );

		$vr = new LocalResponse ( $gwResp, $invoice->getSoldToId (), $invoice->getIdGroup (), $tknRs->idGuest_token->getStoredVal (), PaymentStatusCode::Retrn );
		$vr->setPaymentDate ( date ( 'Y-m-d H:i:s' ) );

		// Record transaction
		$transRs = Transaction::recordTransaction ( $dbh, $vr, $this->getGatewayType (), TransType::Retrn, TransMethod::Token );
		$vr->setIdTrans ( $transRs->idTrans->getStoredVal () );

		// Record payment
		$vrr = ReturnReply::processReply ( $dbh, $vr, $uS->username, $payRs );

		// Update invoice
		$invoice->updateInvoiceBalance ( $dbh, 0 - $vrr->response->getAuthorizedAmount (), $uS->username );

		$vrr->idVisit = $invoice->getOrderNumber ();
		$dataArray ['receipt'] = HTMLContainer::generateMarkup ( 'div', nl2br ( Receipt::createVoidMarkup ( $dbh, $vrr, $uS->siteName, $uS->sId ) ) );
		$dataArray ['success'] = 'Payment is Returned.  ';

		return $dataArray;
	}

	public function voidReturn(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid) {
		return array (
				'warning' => 'Not Available.  '
		);
	}
	public function returnAmount(\PDO $dbh, Invoice $invoice, $rtnToken, $paymentNotes) {
		
		$uS = Session::getInstance ();

		$tokenRS = CreditToken::getTokenRsFromId ( $dbh, $rtnToken );
		$amount = abs ( $invoice->getAmount () );

		$gwResp = new LocalGwResp ( $amount, $invoice->getInvoiceNumber (), $tokenRS->CardType->getStoredVal (), $tokenRS->MaskedAccount->getStoredVal (), $tokenRS->CardHolderName->getStoredVal (), MpTranType::Sale, $uS->username );

		$vr = new LocalResponse ( $gwResp, $invoice->getSoldToId (), $invoice->getIdGroup (), $rtnToken, PaymentStatusCode::Paid );
		$vr->setPaymentDate ( date ( 'Y-m-d H:i:s' ) );
		$vr->setPaymentNotes ( $paymentNotes );
		$vr->setRefund(TRUE);

		$vrr = ReturnReply::processReply ( $dbh, $vr, $uS->username, NULL );

		$rtnResult = new ReturnResult ( $invoice->getIdInvoice (), $invoice->getIdGroup (), $invoice->getSoldToId (), $tokenRS->idGuest_token->getStoredVal () );

		// Update invoice
		$invoice->updateInvoiceBalance ( $dbh, 0 - $vrr->response->getAuthorizedAmount (), $uS->username );

		$rtnResult->feePaymentAccepted ( $dbh, $uS, $vrr, $invoice );
		$rtnResult->setDisplayMessage ( 'Refund by Credit Card.  ' );

		return $rtnResult;
	}
	public function reverseSale(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid) {
		return $this->_voidSale ( $dbh, $invoice, $payRs, $pAuthRs, $bid );
	}
	protected static function _saveEditMarkup(\PDO $dbh, $gatewayName, $post) {
	}
	protected static function _createEditMarkup(\PDO $dbh, $gatewayName) {
		return '';
	}
	public function processHostedReply(\PDO $dbh, $post, $ssoTtoken, $idInv, $payNotes, $payDate) {
		throw new Hk_Exception_Payment ( 'Local gateway does not process gateway replys.  ' );
	}
	public function getPaymentResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup, $invoiceNumber, $idToken = 0, $payNotes = '') {
		return new LocalResponse ( $vcr, $idPayor, $idGroup, $idToken );
	}
	public function getCofResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup) {
		throw new Hk_Exception_Payment ( 'Card on file services are not implemented.  ' );
	}
	
	public function selectPaymentMarkup(\PDO $dbh, &$payTbl, $index = '') {

		// Charge card list
		$ccs = readGenLookupsPDO ( $dbh, 'Charge_Cards' );

		foreach ( $ccs as $v ) {
		    $v[0] = $v[1];
			$cardNames [$v[1]] = $v;
		}

		$tbl = new HTMLTable ();
		$tbl->addBodyTr ( HTMLTable::makeTd ( 'New Card: ', array (
				'class' => 'tdlabel'
		) ) . HTMLTable::makeTd ( HTMLSelector::generateMarkup ( HTMLSelector::doOptionsMkup ( removeOptionGroups ( $cardNames ), '', TRUE ), array (
				'name' => 'selChargeType' . $index,
				'class' => 'hhk-feeskeys' . $index
		) ) ) . HTMLTable::makeTd ( HTMLInput::generateMarkup ( '', array (
				'name' => 'txtChargeAcct' . $index,
				'placeholder' => 'Acct.',
				'size' => '6',
				'title' => 'Only the last 4 digits.',
				'class' => 'hhk-feeskeys' . $index
		) ) ) );
		$tbl->addBodyTr ( HTMLTable::makeTd ( HTMLInput::generateMarkup ( '', array (
				'type' => 'textbox',
				'size' => '40',
				'placeholder' => 'Cardholder Name',
		        'name' => 'txtvdNewCardName' . $index,
		        'class' => 'hhk-feeskeys' . $index
		) ), array (
				'colspan' => '4'
		) ) );
		$payTbl->addBodyTr ( HTMLTable::makeTd ( $tbl->generateMarkup (), array (
				'colspan' => '5'
		) ), array (
				'id' => 'trvdCHName' . $index
		) );
	}
}