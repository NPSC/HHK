<?php

namespace HHK\Payment\PaymentGateway\Local;

use HHK\Member\Role\Guest;
use HHK\Member\AbstractMember;
use HHK\Payment\{CreditToken, Receipt, Transaction};
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentGateway\CreditPayments\{ReturnReply, SaleReply, VoidReply};
use HHK\Payment\PaymentManager\PaymentManagerPayment;
use HHK\Payment\PaymentResult\{PaymentResult, ReturnResult};
use HHK\SysConst\{MemBasis, MpTranType, PaymentMethod, PaymentStatusCode, TransMethod, TransType};
use HHK\Tables\EditRS;
use HHK\Tables\Payment\{PaymentRS, Payment_AuthRS};
use HHK\sec\Session;
use HHK\Exception\MemberException;
use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector, HTMLTable};
use HHK\House\HouseServices;
use HHK\Exception\PaymentException;
use HHK\Payment\GatewayResponse\GatewayResponseInterface;

/**
 * Description of LocalGateway
 *
 * @author ecran
 */
class LocalGateway extends AbstractPaymentGateway {

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
	public function hasUndoReturnPmt() {
		return TRUE;
	}

	public function hasUndoReturnAmt() {
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

		} else {

			if (trim($pmp->getCardHolderName()) == '') {

				try {

					$guest = AbstractMember::GetDesignatedMember($dbh, $invoice->getSoldToId(), MemBasis::Indivual);  //new Guest($dbh, '', $invoice->getSoldToId());

				} catch (MemberException $ex) {

					$guest = AbstractMember::GetDesignatedMember($dbh, $invoice->getSoldToId(), MemBasis::Company);
				}

				$pmp->setCardHolderName($guest->get_fullName());
			}
		}

		$gwResp = new LocalGatewayResponse( $invoice->getAmountToPay (), $invoice->getInvoiceNumber (), $pmp->getChargeCard (), $pmp->getChargeAcct (), $pmp->getCardHolderName (), MpTranType::Sale, $uS->username );

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

		$gwResp = new LocalGatewayResponse( 0, '', $selChgType, $chgAcct, $cardHolderName, MpTranType::CardOnFile, $uS->username );

		$vr = new LocalResponse ( $gwResp, $idGuest, $idGroup, 0, PaymentStatusCode::Paid );

		try {
			$vr->idGuestToken = CreditToken::storeToken($dbh, $vr->idRegistration, $vr->idPayor, $vr->response);
		} catch(\Exception $ex) {
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
		$tknRs = CreditToken::getTokenRsFromId ( $dbh, $payRs->idToken->getStoredVal () );
		$cardHolderName = $tknRs->CardHolderName->getStoredVal ();

		// Get cardholder name
		if ($cardHolderName == '') {

			try {
				$guest = AbstractMember::GetDesignatedMember($dbh, $invoice->getSoldToId(), MemBasis::Indivual);  //new Guest($dbh, '', $invoice->getSoldToId());
			} catch (MemberException $ex) {
				$guest = AbstractMember::GetDesignatedMember($dbh, $invoice->getSoldToId(), MemBasis::Company);
			}

			$cardHolderName = $guest->get_fullName();
		}

		// create gw response
		$gwResp = new LocalGatewayResponse(
				$pAuthRs->Approved_Amount->getStoredVal (),
				$invoice->getInvoiceNumber (),
				$pAuthRs->Card_Type->getStoredVal (),
				$pAuthRs->Acct_Number->getStoredVal (),
				$cardHolderName,
				MpTranType::Void, $uS->username );

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

		$dataArray = array (
				'bid' => $bid
		);

		// find the token
		$tknRs = CreditToken::getTokenRsFromId ( $dbh, $payRs->idToken->getStoredVal () );
		$cardHolderName = $tknRs->CardHolderName->getStoredVal ();

		// Get cardholder name
		if ($cardHolderName == '') {

			try {
				$guest = AbstractMember::GetDesignatedMember($dbh, $invoice->getSoldToId(), MemBasis::Indivual);  //new Guest($dbh, '', $invoice->getSoldToId());
			} catch (MemberException $ex) {
				$guest = AbstractMember::GetDesignatedMember($dbh, $invoice->getSoldToId(), MemBasis::Company);
			}

			$cardHolderName = $guest->get_fullName();
		}

		$gwResp = new LocalGatewayResponse(
				$pAuthRs->Approved_Amount->getStoredVal (),
				$invoice->getInvoiceNumber (),
				$pAuthRs->Card_Type->getStoredVal (),
				$pAuthRs->Acct_Number->getStoredVal (),
				$cardHolderName,
				MpTranType::ReturnSale,
				$uS->username );

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
		$dataArray ['receipt'] = HTMLContainer::generateMarkup ( 'div', nl2br ( Receipt::createReturnMarkup ( $dbh, $vrr, $uS->siteName, $uS->sId ) ) );
		$dataArray ['success'] = 'Payment is Returned.  ';

		return $dataArray;
	}

	public function returnAmount(\PDO $dbh, Invoice $invoice, $rtnToken, $paymentNotes) {

		$uS = Session::getInstance ();

		$tokenRS = CreditToken::getTokenRsFromId ( $dbh, $rtnToken );
		$amount = abs ( $invoice->getAmount () );
		$cardHolderName = $tokenRS->CardHolderName->getStoredVal();

		if ($cardHolderName == '') {

			try {
				$guest = AbstractMember::GetDesignatedMember($dbh, $invoice->getSoldToId(), MemBasis::Indivual);  //new Guest($dbh, '', $invoice->getSoldToId());
			} catch (MemberException $ex) {
				$guest = AbstractMember::GetDesignatedMember($dbh, $invoice->getSoldToId(), MemBasis::Company);
			}

			$cardHolderName = $guest->get_fullName();
		}

		$gwResp = new LocalGatewayResponse(
				$amount,
				$invoice->getInvoiceNumber (),
				$tokenRS->CardType->getStoredVal (),
				$tokenRS->MaskedAccount->getStoredVal (),
				$cardHolderName,
				MpTranType::Sale,
				$uS->username );

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

	public function undoReturnPayment(\PDO $dbh, $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid) {

		$uS = Session::getInstance();
		$dataArray = array('bid' => $bid);

		// find the token
		$tknRs = CreditToken::getTokenRsFromId ( $dbh, $payRs->idToken->getStoredVal () );
		$cardHolderName = $tknRs->CardHolderName->getStoredVal();

		if ($cardHolderName == '') {

			try {
				$guest = AbstractMember::GetDesignatedMember($dbh, $invoice->getSoldToId(), MemBasis::Indivual);  //new Guest($dbh, '', $invoice->getSoldToId());
			} catch (MemberException $ex) {
				$guest = AbstractMember::GetDesignatedMember($dbh, $invoice->getSoldToId(), MemBasis::Company);
			}

			$cardHolderName = $guest->get_fullName();
		}

		$gwResp = new LocalGatewayResponse(
				$pAuthRs->Approved_Amount->getStoredVal (),
				$invoice->getInvoiceNumber (),
				$pAuthRs->Card_Type->getStoredVal (),
				$pAuthRs->Acct_Number->getStoredVal (),
				$cardHolderName,
				MpTranType::ReturnSale,
				$uS->username );

		$vr = new LocalResponse ( $gwResp, $invoice->getSoldToId (), $invoice->getIdGroup (), $tknRs->idGuest_token->getStoredVal (), PaymentStatusCode::VoidReturn );
		$vr->setPaymentDate ( date ( 'Y-m-d H:i:s' ) );

		// Record transaction
		$transRs = Transaction::recordTransaction ( $dbh, $vr, $this->getGatewayType(), TransType::undoRetrn, TransMethod::Token );
		$vr->setIdTrans ( $transRs->idTrans->getStoredVal () );

		// Payment record
		$payRs->Status_Code->setNewVal(PaymentStatusCode::Paid);
		$payRs->Updated_By->setNewVal($uS->username);
		$payRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

		EditRS::update($dbh, $payRs, array($payRs->idPayment));
		EditRS::updateStoredVals($payRs);

		// Payment Auth record
		EditRS::delete($dbh, $pAuthRs, array($pAuthRs->idPayment_auth));

		// Update invoice
		$invoice->updateInvoiceBalance ( $dbh, $vr->response->getAuthorizedAmount (), $uS->username );

		$vr->idVisit = $invoice->getOrderNumber ();
		$dataArray ['receipt'] = HTMLContainer::generateMarkup ( 'div', nl2br ( Receipt::createSaleMarkup( $dbh, $invoice, $uS->siteName, $uS->sId, $vr ) ) );
		$dataArray ['success'] = 'Return is Undone.  ';

		return $dataArray;
	}

	public function undoReturnAmount(\PDO $dbh, $invoice, PaymentRs $payRs, Payment_AuthRS $pAuthRs, $bid) {

		$uS = Session::getInstance();
		$dataArray = array('bid' => $bid);

		// find the token
		$tknRs = CreditToken::getTokenRsFromId ( $dbh, $payRs->idToken->getStoredVal () );
		$cardHolderName = $tknRs->CardHolderName->getStoredVal();

		if ($cardHolderName == '') {

			try {
				$guest = AbstractMember::GetDesignatedMember($dbh, $invoice->getSoldToId(), MemBasis::Indivual);  //new Guest($dbh, '', $invoice->getSoldToId());
			} catch (MemberException $ex) {
				$guest = AbstractMember::GetDesignatedMember($dbh, $invoice->getSoldToId(), MemBasis::Company);
			}

			$cardHolderName = $guest->get_fullName();
		}

		$gwResp = new LocalGatewayResponse(
				$pAuthRs->Approved_Amount->getStoredVal (),
				$invoice->getInvoiceNumber (),
				$pAuthRs->Card_Type->getStoredVal (),
				$pAuthRs->Acct_Number->getStoredVal (),
				$cardHolderName,
				MpTranType::ReturnAmt,
				$uS->username );

		$vr = new LocalResponse ( $gwResp, $invoice->getSoldToId (), $invoice->getIdGroup (), $tknRs->idGuest_token->getStoredVal (), PaymentStatusCode::VoidSale );
		$vr->setPaymentDate ( date ( 'Y-m-d H:i:s' ) );

		// Record transaction
		Transaction::recordTransaction($dbh, $vr, '', TransType::undoRetrn, TransMethod::Token);

		// Payment records.
		$dbh->exec("delete from payment_invoice where Payment_Id = " . $payRs->idPayment->getStoredVal ());
		$dbh->exec("delete from payment_auth where idPayment = " . $payRs->idPayment->getStoredVal ());
		$dbh->exec("delete from payment where idPayment = " . $payRs->idPayment->getStoredVal ());

		$invoice->updateInvoiceBalance($dbh, $pAuthRs->Approved_Amount->getStoredVal (), $uS->username);
		// delete invoice
		$invoice->deleteInvoice($dbh, $uS->username);

		$dataArray['success'] = 'Refund is undone.  ';
		return $dataArray;

	}

	protected static function _saveEditMarkup(\PDO $dbh, $gatewayName, $post) {
	}
	protected static function _createEditMarkup(\PDO $dbh, $gatewayName) {
		return 'The House is using a separate un-integrated credit card gateway.';
	}
	public function processHostedReply(\PDO $dbh, $post, $ssoTtoken, $idInv, $payNotes, $payDate) {
		throw new PaymentException( 'Local gateway does not process gateway replys.  ' );
	}
	public function getPaymentResponseObj(GatewayResponseInterface $vcr, $idPayor, $idGroup, $invoiceNumber, $idToken = 0, $payNotes = '') {
		return new LocalResponse ( $vcr, $idPayor, $idGroup, $idToken );
	}
	public function getCofResponseObj(GatewayResponseInterface $vcr, $idPayor, $idGroup) {
		return new LocalResponse ( $vcr, $idPayor, $idGroup, 0 );
	}

	public function selectPaymentMarkup(\PDO $dbh, &$payTbl, $index = '') {

		// Charge card list
		$ccs = readGenLookupsPDO ( $dbh, 'Charge_Cards' );

		foreach ( $ccs as $v ) {
		    $v[0] = $v[1];
			$cardNames [$v[1]] = $v;
		}

		$tbl = new HTMLTable();
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
				'type' => 'text',
				'size' => '40',
				'placeholder' => 'Cardholder Name',
		        'name' => 'txtvdNewCardName' . $index,
		        'class' => 'hhk-feeskeys' . $index,
		) ), array (
				'colspan' => '4'
		) ) );

		$tbl->addBodyTr ( HTMLTable::makeTd ('For security purposes, the Name field only allows letters', array('colspan' => '4'
		)), array (
				'style'=>'display:none;font-size:smaller;color:red;',
				'id'=>'lhnameerror'
		) );

		$payTbl->addBodyTr ( HTMLTable::makeTd ( $tbl->generateMarkup (), array (
				'colspan' => '5'
		) ), array (
				'id' => 'trvdCHName' . $index
		) );
	}
}
?>