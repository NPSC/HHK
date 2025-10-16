<?php

namespace HHK\House;


use HHK\Common;
use HHK\Exception\PaymentException;
use HHK\Exception\RuntimeException;
use HHK\House\Reservation\ReservationSvcs;
use HHK\Exception\UnexpectedValueException;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLTable;
use HHK\House\Registration;
use HHK\House\PSG;
use HHK\House\Reservation\Reservation_1;
use HHK\House\Resource\AbstractResource;
use HHK\House\Room\RoomChooser;
use HHK\House\Visit\Visit;
use HHK\House\Visit\VisitViewer;
use HHK\Member\Role\Guest;
use HHK\Note\LinkNote;
use HHK\Note\Note;
use HHK\Payment\CreditToken;
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\Invoice\InvoiceLine\OneTimeInvoiceLine;
use HHK\Payment\Invoice\InvoiceLine\TaxInvoiceLine;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentManager\PaymentManager;
use HHK\Payment\PaymentManager\PaymentManagerPayment;
use HHK\Payment\PaymentResult\PaymentResult;
use HHK\Purchase\Item;
use HHK\Purchase\PaymentChooser;
use HHK\Purchase\RateChooser;
use HHK\Purchase\ValueAddedTax;
use HHK\Purchase\VisitCharges;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\SysConst\AddressPurpose;
use HHK\SysConst\ExcessPay;
use HHK\SysConst\GLTableNames;
use HHK\SysConst\InvoiceStatus;
use HHK\SysConst\ItemId;
use HHK\SysConst\PayType;
use HHK\SysConst\ReservationStatus;
use HHK\SysConst\ResourceStatus;
use HHK\SysConst\RoomState;
use HHK\SysConst\VisitStatus;
use HHK\TableLog\VisitLog;
use HHK\Tables\EditRS;
use HHK\Tables\Visit\StaysRS;
use HHK\Tables\Visit\VisitRS;
use HHK\Tables\Visit\Visit_LogRS;
use HHK\sec\Labels;
use HHK\sec\SecurityComponent;
use HHK\sec\Session;
use PDOException;



/**
 * HouseServices.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class HouseServices {

    /**
     * Show visit details
     *
     * @param \PDO $dbh
     * @param int $idGuest Supply either this or the next
     * @param int $idVisit
     * @param int $span span = 'max' means load last visit span, otherwise load int value
     * @param boolean $isAdmin Administrator flag
     * @param string $action Processing code with various settings.
     * @param array $coStayDates Dates for an early checkout. Adjusts the final payments
     * @return array
     */
    public static function getVisitFees(\PDO $dbh, $idGuest, $idV, $idSpan, $action = '', $coStayDates = []) {

        $uS = Session::getInstance();

        // Get labels
        $labels = Labels::getLabels();
        $isFutureSpan = FALSE;
        $idVisit = intval($idV, 10);
        $span = intval($idSpan, 10);

        if ($idVisit < 1 || $span < 0) {
            return ["error" => "A Visit is not selected: $idV-$idSpan"];
        }

        // Get the visit span indicated
        $query = "select * from vspan_listing where idVisit = $idVisit and Span = $span;";
        $stmt1 = $dbh->query($query);
        $rows = $stmt1->fetchAll(\PDO::FETCH_ASSOC);

        $dataArray = [];


        if (count($rows) == 0) {
            return ["error" => "<span>No Visit Data.  idVisit=$idVisit and Span = $span.</span>"];
        }

        $vSpanListing = $rows[0];

        if ($vSpanListing['Status'] == VisitStatus::Reserved) {
            //  we are looking at a future span.
            $isFutureSpan = TRUE;
        }

        // Hospital and association lists
        $vSpanListing['Hospital'] = '';
        if (isset($uS->guestLookups[GLTableNames::Hospital][$vSpanListing['idHospital']])) {
            $vSpanListing['Hospital'] = $uS->guestLookups[GLTableNames::Hospital][$vSpanListing['idHospital']][1];
        }

        $vSpanListing['Association'] = '';
        if (isset($uS->guestLookups[GLTableNames::Hospital][$vSpanListing['idAssociation']])) {
            $vSpanListing['Association'] = $uS->guestLookups[GLTableNames::Hospital][$vSpanListing['idAssociation']][1];
            if (trim($vSpanListing['Association']) == '(None)') {
                $vSpanListing['Association'] = '';
            }
        }

        if ($vSpanListing['Span_End'] != '') {
            $vspanEndDT = new \DateTime($vSpanListing['Span_End']);
            $vspanEndDT->sub(new \DateInterval('P1D'));
        } else {
            $vspanEndDT = new \DateTime();
        }

        $vspanStartDT = new \DateTime($vSpanListing['Span_Start']);

        $priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

        $visitCharge = new VisitCharges($vSpanListing['idVisit']);
        $visitCharge->sumPayments($dbh);

        $coDate = '';

        if ($action == 'ref' && count($coStayDates) > 0) {
            // Visit is checking out to a different date than "today"

        	$coDateDT = new \DateTime('1900-01-01');
        	// find latest co date
        	foreach ($coStayDates as $c) {

        		$cDT= new \DateTime($c);

        		if ($cDT > $coDateDT) {
        			$coDateDT = $cDT;
        		}
        	}

        	$coDate = $coDateDT->format('Y-m-d');

            $visitCharge->sumDatedRoomCharge($dbh, $priceModel, $coDate, 0, TRUE);

            // if a previous stay checked out later than the checked in stay.
            $coDate = $visitCharge->getFinalVisitCoDate()->format('Y-m-d H:i:s');

        } else {
            $visitCharge->sumCurrentRoomCharge($dbh, $priceModel, 0, TRUE);
        }

        // Show adjust button?
        $showAdjust = FALSE;
        $hdArry = Common::readGenLookupsPDO($dbh, "House_Discount");
        $adnlArray = Common::readGenLookupsPDO($dbh, 'Addnl_Charge');

        if ($action != 'cf' && !$isFutureSpan && (count($hdArry) > 0 || count($adnlArray) > 0)) {
            $showAdjust = TRUE;
        }


        // Get main visit markup section
        $mkup = HTMLContainer::generateMarkup('div',
            VisitViewer::createActiveMarkup(
                $dbh,
                $vSpanListing,
                $visitCharge,
                $uS->KeyDeposit,
                $uS->VisitFee,
                $uS->EmptyExtendLimit,
                $action,
                $coDate,
                $showAdjust)
            ,
            ['style' => 'margin-top:10px;']);

        $mkup = HTMLContainer::generateMarkup('div',
        		VisitViewer::createStaysMarkup(
                    $dbh,
                    $vSpanListing['idReservation'],
                    $idVisit,
                    $span,
                    $isFutureSpan,
                    $vSpanListing['idPrimaryGuest'],
                    $idGuest,
                    $labels,
                    $action,
                    $coStayDates)
                . $mkup,
            ['id' => 'divksStays']);

        // Show fees if not hf = hide fees.
        if ($action != 'hf' && !$isFutureSpan) {
        	$mkup .= HTMLContainer::generateMarkup('div',
                VisitViewer::createPaymentMarkup($dbh, $vSpanListing, $visitCharge, $idGuest, $action),
                ['class' => 'hhk-flex']);

        }


        $dataArray['success'] = $mkup;

        // Start and end dates for rate changer
        $dataArray['start'] = $vspanStartDT->format('c');
        $dataArray['end'] = $vspanEndDT->format('c');
        $dataArray['visitStatus'] = $vSpanListing['Status'];

        return $dataArray;
    }

    /**
     *
     * @param \PDO $dbh
     * @param int $idVisit
     * @param int $span
     * @param array $post
     * @param string $postbackPage

     * @return array
     */
    public static function saveFees(\PDO $dbh, $idVisit, $span, array $post, $postbackPage) {

        $uS = Session::getInstance();
        $dataArray = [];
        $creditCheckOut = [];
        $reply = '';
        $warning = '';
        $returnReserv = FALSE;
        $returnCkdIn = FALSE;

        if ($idVisit == 0) {
            return ["error" => "Neither Guest or Visit was selected."];
        }

        // Remove any indicated visit stays
        if (isset($post['removeCb'])) {

            foreach ($post['removeCb'] as $r => $v) {
                $idStay = intval(filter_var($r, FILTER_SANITIZE_NUMBER_INT), 10);
                $reply .= VisitViewer::removeStay($dbh, $idVisit, $span, $idStay, $uS->username);
            }
        }

        // instantiate current visit
        $visit = new Visit($dbh, 0, $idVisit, $span);


        // Notes
        if (isset($post["taNewVNote"])) {

            $notes = filter_var($post["taNewVNote"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if ($notes != '' && $idVisit > 0) {
                $notes = filter_var(base64_decode($notes), FILTER_SANITIZE_FULL_SPECIAL_CHARS); //sanitize decoded notes
                LinkNote::save($dbh, $notes, $idVisit, Note::VisitLink, '', $uS->username, $uS->ConcatVisitNotes);
            }
        }

        // Ribbon Note
        if (isset($post["txtRibbonNote"])){
            $ribbonNote = filter_var($post["txtRibbonNote"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $oldNote = $visit->getNotes();
            $visit->setNotes($ribbonNote);
            $visit->updateVisitRecord($dbh, $uS->username);

            if($oldNote != $visit->getNotes()){
                $reply .= " Ribbon Note updated.";
            }
        }

        // Notice to Check out
        if (isset($post["noticeToCheckout"])){
            $noticeToCheckout = filter_var($post["noticeToCheckout"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            try{
                $vacateDT = new \DateTime($noticeToCheckout);
                $vacateDT->setTime(0, 0, 0);
            }catch(\Exception $e){
                throw new \ErrorException("The " . Labels::getString("Visit", "noticeToCheckout", "Notice to Checkout") . " field must be a valid date");
            }
            $oldNotice = $visit->getNoticeToCheckout();
            $visit->setNoticeToCheckout($vacateDT->format("Y-m-d 00:00:00"));
            $visit->updateVisitRecord($dbh, $uS->username);

            if($oldNotice != $visit->getNoticeToCheckout()){
                $reply .= " " . Labels::getString("Visit", "noticeToCheckout", "Notice to Checkout") . " updated.";
            }
        }else{
            $oldNotice = $visit->getNoticeToCheckout();
            $visit->setNoticeToCheckout("");
            $visit->updateVisitRecord($dbh, $uS->username);

            if($oldNotice != $visit->getNoticeToCheckout()){
                $reply .= " " . Labels::getString("Visit", "noticeToCheckout", "Notice to Checkout") . " updated.";
            }
        }

        // Change room rate
        if (isset($post['rateChgCB']) && isset($post['extendCb']) === FALSE) {
            $rateChooser = new RateChooser($dbh);
            $reply .= $rateChooser->changeRoomRate($dbh, $visit, $post);
            $returnCkdIn = TRUE;
        }

        // Change Visit Fee
        if (isset($post['selVisitFee'])) {

            $visitFeeOption = filter_var($post['selVisitFee'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $reply .= VisitViewer::changeVisitFee($dbh, $visitFeeOption, $visit);
        }

        // Change STAY Checkin date
        if (isset($post['stayCkInDate'])) {
            $reply .= $visit->checkStayStartDates($dbh, $post['stayCkInDate']);
        }


        // Undo checkout?
        if (isset($post['undoCkout']) && $visit->getVisitStatus() == VisitStatus::CheckedOut) {

            // Get the new expected co date.
            if (isset($post['txtUndoDate'])) {
                $newExpectedDT = new \DateTime(filter_var($post['txtUndoDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            } else {
                $newExpectedDT = new \DateTime($visit->getActualDeparture());
            }

            // Undo the checkout.
            $reply .= self::undoCheckout($dbh, $visit, $newExpectedDT, $uS->username);
            $returnCkdIn = TRUE;


        // Not undoing checkout...
        } else {

            // Instantiate a payment manager payment container.
            $paymentManger = new PaymentManager(PaymentChooser::readPostedPayment($dbh, $post));


            // Process a checked in guest
            if ($visit->getVisitStatus() == VisitStatus::CheckedIn) {

                // Change any expected checkout dates
                if (isset($post['stayExpCkOut'])) {

                    $replyArray = $visit->changeExpectedCheckoutDates($dbh, $post['stayExpCkOut']);

                    if (isset($replyArray['isChanged']) && $replyArray['isChanged']) {
                        $returnCkdIn = TRUE;
                        $returnReserv = TRUE;
                    }

                    $reply .= $replyArray['message'];
                }


                // Leave enabled
                if ($uS->EmptyExtendLimit > 0) {

                    // Begin visit leave?
                    if (isset($post['extendCb'])) {

                        $extendStartDate = '';
                        if (isset($post['txtWStart']) && $post['txtWStart'] != '') {
                            $extendStartDate = filter_var($post['txtWStart'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                        }

                        $extDays = 0;
                        if (isset($post['extendDays'])) {
                            $extDays = intval(filter_var($post['extendDays'], FILTER_SANITIZE_NUMBER_INT), 10);
                        }

                        $noCharge = FALSE;
                        if (isset($post['noChargeCb'])) {
                            $noCharge = TRUE;
                        }

                        $reply .= $visit->beginLeave($dbh, $extendStartDate, $extDays, $noCharge);
                        $returnCkdIn = TRUE;
                    }


                    // Return/Extend leave?
                    if (isset($post['leaveRetCb'])) {

                        $extContrl = '';

                        if (isset($post['rbOlpicker']) && $post['rbOlpicker'] == 'ext') {
                            $extContrl = 'extend';
                        } else if (isset($post['rbOlpicker']) && $post['rbOlpicker'] == 'rtDate') {
                            $extContrl = 'return';
                        }

                        if ($extContrl == 'extend') {
                            // Extend current leave

                            if (isset($post['extendDate']) && $post['extendDate'] != '') {

                                $extendDate = filter_var($post['extendDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                                $reply .= $visit->extendLeave($dbh, $extendDate);
                                $returnCkdIn = TRUE;
                            }

                        } else if ($extContrl == 'return') {
                            // Return from Leave

                            if (isset($post['txtWRetDate']) && $post['txtWRetDate'] != '') {

                                $returnDate = filter_var($post['txtWRetDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                                $reply .= $visit->endLeave($dbh, $returnDate);
                                $returnCkdIn = TRUE;
                            }
                        }
                    }
                }


                // Change primary guest?
                if (isset($post['rbPriGuest'])) {
                    $newPg = intval(filter_var($post['rbPriGuest'], FILTER_SANITIZE_NUMBER_INT), 10);

                    if ($newPg > 0 && $newPg != $visit->getPrimaryGuestId()) {

                        $visit->setPrimaryGuestId($newPg);
                        $visit->updateVisitRecord($dbh, $uS->username);

                        $resv = Reservation_1::instantiateFromIdReserv($dbh, $visit->getReservationId());
                        $resv->setIdGuest($newPg);
                        $resv->saveReservation($dbh, $resv->getIdRegistration(), $uS->username);

                        $reply .= Labels::getString('MemberType', 'primaryGuest', 'Primary Guest') . ' Id updated.  ';
                    }
                }

                // Check-out any guests.
                if (isset($post['stayActionCb'])) {

                    // See whose checking out
                    foreach ($post['stayActionCb'] as $idr => $v) {

                        $id = intval(filter_var($idr, FILTER_SANITIZE_NUMBER_INT));
                        if ($id < 1) {
                            continue;
                        }

                        // Check out Date
                        $coDate = date('Y-m-d');
                        if (isset($post['stayCkOutDate'][$id]) && $post['stayCkOutDate'][$id] != '') {
                            $coDate = filter_var($post['stayCkOutDate'][$id], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                        }

                        $coHour = intval(date('H'), 10);
                        $coMin = intval(date('i'), 10);

                        if (isset($post['stayCkOutHour'][$id]) && $post['stayCkOutHour'][$id] != '') {
                            $coHour = intval(filter_var($post['stayCkOutHour'][$id], FILTER_SANITIZE_NUMBER_INT), 10);

                            if ($coHour < 0) {
                                $coHour = 0;
                            } else if ($coHour > 23) {
                                $coHour = 23;
                            }
                        }

                        $coDT = new \DateTime($coDate);
                        $coDT->setTimezone(new \DateTimeZone($uS->tz));

                        $coDT->setTime($coHour, $coMin, 0);

                        $visit->checkOutGuest($dbh, $id, $coDT->format('Y-m-d H:i:s'), '', TRUE);

                        $returnCkdIn = TRUE;

                    }

                    $reply .= $visit->getInfoMessage();
                    $warning .= $visit->getErrorMessage();
                }
            }


            // Make guest payment
            $payResult = self::processPayments($dbh, $paymentManger, $visit, $postbackPage, $visit->getPrimaryGuestId());

            // Handle the payment result
            if (is_null($payResult) === FALSE) {

                if($payResult->wasError()){
                    $dataArray["error"] = $payResult->getReplyMessage();
                }else{
                    $reply .= $payResult->getReplyMessage();
                }

                if ($payResult->getStatus() == PaymentResult::FORWARDED) {
                    $creditCheckOut = $payResult->getForwardHostedPayment();
                }

                // Receipt
                if (is_null($payResult->getReceiptMarkup()) === FALSE && $payResult->getReceiptMarkup() != '') {
                    $dataArray['receipt'] = HTMLContainer::generateMarkup('div', $payResult->getReceiptMarkup());

                    Registration::updatePrefTokenId($dbh, $visit->getIdRegistration(), $payResult->getIdToken());
                }

                // New Invoice
                if (is_null($payResult->getInvoiceNumber()) === FALSE && $payResult->getInvoiceNumber() != '') {
                    $dataArray['invoiceNumber'] = $payResult->getInvoiceNumber();
                }

                //add paymentId
                if (is_null($payResult->getIdPayment()) === FALSE && $payResult->getIdPayment() != 0) {
                    $dataArray['idPayment'] = $payResult->getIdPayment();
                }

                //add billToEmail
                $billToEmail = $payResult->getInvoiceBillToEmail($dbh);
                if ($billToEmail !== '') {
                    $dataArray['billToEmail'] = $billToEmail;
                }
            }
        }

        // Undo Room Change?
        if (isset($post['undoRmChg']) && $visit->getVisitStatus() == VisitStatus::NewSpan) {

            $reply .= self::undoRoomChange($dbh, $visit, $uS->username);
            $returnCkdIn = TRUE;
        }

        // Delete future room change?
        if (isset($post['delFutRmChg']) && $visit->getVisitStatus() == VisitStatus::CheckedIn) {

            $reply .= self::deleteNextFutureVisit($dbh, $visit);
            $returnCkdIn = TRUE;
        }


        // divert to credit payment site?
        if (count($creditCheckOut) > 0) {
            if(isset($creditCheckOut['hpfToken'])){ // if deluxe, don't forward
                $dataArray['deluxehpf'] = $creditCheckOut;
            }else{

                // return early to credit payment site, other than Deluxe
                return $creditCheckOut;
            }
        }

        // Return checked in guests markup?
        if ($returnCkdIn) {
            $dataArray['curres'] = 'y';
        }

        // Return reservations and waitlist?
        if ($returnReserv) {
            $dataArray['reservs'] = 'y';
            $dataArray['waitlist'] = 'y';

            if ($uS->ShowUncfrmdStatusTab) {
                $dataArray['unreserv'] = 'y';
            }
        }


        $dataArray['success'] = $reply;
        $dataArray['warning'] = $warning;

        return $dataArray;
    }

    /**
     * Summary of deleteNextFutureVisit - delete a future visit and update the Next_IdResource.
     * @param \PDO $dbh
     * @param Visit $Visit
     * @return string
     */
    public static function deleteNextFutureVisit(\PDO $dbh, Visit $visit) {

        $msg = '';
        $uS = Session::getInstance();
        $idVisit = $visit->getIdVisit();
        $span = $visit->getSpan();

        // Look for any reserved future visit
        $vstmt = $dbh->query("Select * from visit where Status = '" . VisitStatus::Reserved . "' and idVisit = $idVisit and Span > $span Order by Span;");
        $ro = $vstmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($ro) > 0) {
            // Load the first reserved visit
            $vrs = new VisitRS();
            EditRS::loadRow($ro[0], $vrs);
            $resVisit = new Visit($dbh, 0, $vrs->idVisit->getStoredVal(), $vrs->Span->getStoredVal());

            // Gather pertinent data
            $visit->setExpectedDeparture($resVisit->getExpectedDeparture());
            $visit->setNextIdResource(0);

            // Delete the visit span
            $resVisit->deleteThisVisitSpan($dbh);


            // Any more reserved visits?
            if (isset($ro[1])) {
                $visit->setNextIdResource($ro[1]['idResource']);
                $visit->setExpectedDeparture($ro[1]['Span_Start']);
            }

            // Update Next_IdResource from given visit.  Use the next reserved visit's next resource if it exists.

            $visit->updateVisitRecord($dbh, $uS->username);

            $msg .= 'The Next Future Visit Span was deleted.  ';
        }

        return $msg;
    }

    /**
     * Summary of showPayInvoice
     * @param \PDO $dbh
     * @param int $id
     * @param int $iid
     * @return array<string>
     */
    public static function showPayInvoice(\PDO $dbh, $id, $iid) {

        $mkup = HTMLContainer::generateMarkup(
            'div',
            PaymentChooser::createPayInvMarkup($dbh, $id, $iid),
            ['class' => 'hhk-payInvoice', 'style' => 'min-width:600px;clear:left;']
        );

        return ['mkup' => $mkup];
    }

    /**
     * Summary of payInvoice
     * @param \PDO $dbh
     * @param int $idPayor
     * @param mixed $post
     * @return array
     */
    public static function payInvoice(\PDO $dbh, $idPayor, array $post) {

        $reply = 'Uh-oh, Payment NOT made.';
        $postbackPage = '';
        $dataArray = [];
        $creditCheckOut = [];

        if (isset($post['pbp'])) {
            $postbackPage = filter_var($post['pbp'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        // Instantiate a payment manager payment container.
        $paymentManager = new PaymentManager(PaymentChooser::readPostedPayment($dbh, $post));

        // Is it a return payment?
        if ($paymentManager->pmp !== null && $paymentManager->pmp->getTotalPayment() < 0) {

            // Here is where we look for the reimbursment pay type.
            $paymentManager->pmp->setRtnPayType($paymentManager->pmp->getPayType());
            $paymentManager->pmp->setRtnCheckNumber($paymentManager->pmp->getCheckNumber());
            $paymentManager->pmp->setRtnTransferAcct($paymentManager->pmp->getTransferAcct());
            $paymentManager->pmp->setBalWith(ExcessPay::Refund);
        }

        // Make payment
        $payResult = self::processPayments($dbh, $paymentManager, NULL, $postbackPage, $idPayor);

        if ($payResult !== null) {

            $reply = $payResult->getReplyMessage();

            if ($payResult->getStatus() == PaymentResult::FORWARDED) {
                $creditCheckOut = $payResult->getForwardHostedPayment();
            }

            // Receipt
            if ($payResult->getReceiptMarkup() != '') {
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', $payResult->getReceiptMarkup());
            }

            // New Invoice
            if ($payResult->getInvoiceNumber() != '') {
                $dataArray['invoiceNumber'] = $payResult->getInvoiceNumber();
            }

            //add paymentId
            if (is_null($payResult->getIdPayment()) === FALSE && $payResult->getIdPayment() != 0) {
                $dataArray['idPayment'] = $payResult->getIdPayment();
            }

            //add billToEmail
            $billToEmail = $payResult->getInvoiceBillToEmail($dbh);
            if ($billToEmail !== '') {
                $dataArray['billToEmail'] = $billToEmail;
            }
        }

        // divert to credit payment site.
        if (count($creditCheckOut) > 0) {
            if(isset($creditCheckOut['hpfToken'])){ //if deluxe, don't forward
                $dataArray['deluxehpf'] = $creditCheckOut;
            }else{
                return $creditCheckOut;
            }
        }

        $dataArray['success'] = $reply;
        $dataArray['error'] = $payResult->getErrorMessage();

        return $dataArray;
    }

    /**
     * Summary of saveHousePayment
     * @param \PDO $dbh
     * @param int $idItem
     * @param mixed $ord
     * @param mixed $amt
     * @param string $discount
     * @param mixed $addnlCharge
     * @param mixed $adjDate
     * @param mixed $notes
     * @return array<string>
     */
    public static function saveHousePayment(\PDO $dbh, $idItem, $ord, $amt, $discount, $addnlCharge, $adjDate, $notes) {

        $uS = Session::getInstance();
        $dataArray = [];

        if ($ord == 0 || ($discount == '' && $addnlCharge == '')) {
            return $dataArray;
        }

        $visit = new Visit($dbh, 0, $ord);
        $amount = floatval($amt);
        $invoice = NULL;

        if ($adjDate != '') {
            $invDate = date('Y-m-d H:i:s', strtotime($adjDate));
        } else {
            $invDate = date('Y-m-d H:i:s');
        }


        if ($idItem == ItemId::Discount) {

            $codes = Common::readGenLookupsPDO($dbh, 'House_Discount');

            if (isset($codes[$discount])) {

                $amount = 0 - $amount;

                $invLine = new OneTimeInvoiceLine();
                $invLine->createNewLine(new Item($dbh, ItemId::Discount, $amount), 1, $codes[$discount][1]);
                $invoice = new Invoice($dbh);

                $invoice->newInvoice(
                    $dbh,
                    0,
                    $uS->subsidyId,
                    $visit->getIdRegistration(),
                    $visit->getIdVisit(),
                    $visit->getSpan(),
                    $notes,
                    $invDate,
                    $uS->username);

                $invoice->addLine($dbh, $invLine, $uS->username);

                // Pay the invoice
                $invoice->updateInvoiceBalance($dbh, $amount, $uS->username);

                $dataArray['reply'] = $codes[$discount][1] . ' Discount Applied.  ';

            } else {
                $dataArray['reply'] = 'Discount code not found: ' . $discount;
            }

        }

        if ($idItem == ItemId::AddnlCharge) {

            $codes = Common::readGenLookupsPDO($dbh, 'Addnl_Charge');

            if (isset($codes[$addnlCharge])) {

                $invLine = new OneTimeInvoiceLine();
                $invLine->createNewLine(new Item($dbh, ItemId::AddnlCharge, $amount), 1, $codes[$addnlCharge][1]);

                if (is_null($invoice)) {
                    $invoice = new Invoice($dbh);

                    $invoice->newInvoice(
                        $dbh,
                        0,
                        $visit->getPrimaryGuestId(),
                        $visit->getIdRegistration(),
                        $visit->getIdVisit(),
                        $visit->getSpan(),
                        $notes,
                        $invDate,
                        $uS->username);

                }

                $invoice->addLine($dbh, $invLine, $uS->username);
                $invoice->updateInvoiceStatus($dbh, $uS->username);

                // Taxed items
                $vat = new ValueAddedTax($dbh);

                foreach ($vat->getCurrentTaxedItems($visit->getIdVisit()) as $t) {

                    if ($t->getIdTaxedItem() == ItemId::AddnlCharge) {
                        $taxInvoiceLine = new TaxInvoiceLine();
                        $taxInvoiceLine->createNewLine(new Item($dbh, $t->getIdTaxingItem(), $amount), $t->getDecimalTax(),  ' ('. $t->getTextPercentTax().')');
                        $taxInvoiceLine->setSourceItemId(ItemId::AddnlCharge);
                        $invoice->addLine($dbh, $taxInvoiceLine, $uS->username);
                    }
                }

                if ($invoice->getAmount() == 0) {

                    // We can pay it now and return a receipt.
                    $paymentManager = new PaymentManager(new PaymentManagerPayment(PayType::Cash));
                    $paymentManager->setInvoice($invoice);
                    $paymentManager->pmp->setPayDate($invDate);
                    $payResult = $paymentManager->makeHousePayment($dbh, '');

                    if (is_null($payResult->getReceiptMarkup()) === FALSE && $payResult->getReceiptMarkup() != '') {
                        $dataArray['receipt'] = HTMLContainer::generateMarkup('div', $payResult->getReceiptMarkup());
                        $dataArray['reply'] = $codes[$addnlCharge][1] . ': item recorded. ';
                        
                        //add paymentId
                        if (is_null($payResult->getIdPayment()) === FALSE && $payResult->getIdPayment() != 0) {
                            $dataArray['idPayment'] = $payResult->getIdPayment();
                        }

                        //add billToEmail
                        $billToEmail = $payResult->getInvoiceBillToEmail($dbh);
                        if ($billToEmail !== '') {
                            $dataArray['billToEmail'] = $billToEmail;
                        }
                    }

                } else {
                    $dataArray['reply'] = $codes[$addnlCharge][1] . ' additional charge is invoiced. ';
                }

            } else {
                $dataArray['reply'] = 'Additional Charge code not found: ' . $addnlCharge;
            }
        }

        return $dataArray;
    }

    /**
     * Summary of processPayments
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentManager\PaymentManager $paymentManager
     * @param mixed $visit
     * @param mixed $postbackPage
     * @param mixed $idPayor
     * @return PaymentResult|\HHK\Payment\PaymentResult\ReturnResult|null
     */
    public static function processPayments(\PDO $dbh, PaymentManager $paymentManager, $visit, $postbackPage, $idPayor) {

        $uS = Session::getInstance();
        $payResult = NULL;
        $invoice = NULL;
        $invoice = NULL;

        if (is_null($paymentManager->pmp)) {
            return $payResult;
        }

        $paymentManager->pmp->setPriceModel(AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel));

        // Payments - setup
        if (is_null($visit) === FALSE && is_a($visit, 'HHK\House\Visit\Visit')) {
            // It's a visit
            $paymentManager->pmp->priceModel->setCreditDays($visit->getRateGlideCredit());
            $paymentManager->pmp->setVisitCharges(new VisitCharges($visit->getIdVisit()));
        } else {
            // Not a visit, maybe a reservation
            $paymentManager->pmp->priceModel->setCreditDays(0);
            $paymentManager->pmp->setVisitCharges(new VisitCharges(0));
        }

        if ($paymentManager->pmp->getPayType() == PayType::Invoice) {
            $idPayor = $paymentManager->pmp->getIdInvoicePayor();
        }

        if($paymentManager->pmp->getBalWith() != ExcessPay::MoveToResv){
            // Create Invoice.
            $invoice = $paymentManager->createInvoice($dbh, $visit, $idPayor, $paymentManager->pmp->getInvoiceNotes());
        }

        //get resvId
        $resvId = ($visit instanceof Reservation_1 ? $visit->getIdReservation() : 0);

        if (is_null($invoice) === FALSE && $invoice->getStatus() == InvoiceStatus::Unpaid) {

            if ($invoice->getAmountToPay() >= 0) {
                // Make guest payment
                $payResult = $paymentManager->makeHousePayment($dbh, $postbackPage);

            } else if ($invoice->getAmountToPay() < 0) {
                // Make guest return
                $payResult = $paymentManager->makeHouseReturn($dbh, $paymentManager->pmp->getPayDate(), $resvId);
            }
        }

        return $payResult;

    }

    /** Fill in the VISIT change room dialog box in order to show it to the user
     *
     * @param \PDO $dbh
     * @param int $idGuest
     * @param int $idV
     * @param int $idSpan
     * @param boolean $isAdmin
     * @return array
     */
    public static function showChangeVisitRoom(\PDO $dbh, $idGuest, $idV, $idSpan, $isAdmin) {

        $uS = Session::getInstance();
        $dataArray = [];

        $idVisit = intval($idV, 10);
        $span = intval($idSpan, 10);
        $hasReservedSpan = FALSE;
        $showChangeNow = false;
        $resvIdResc = 0;
        $resvTitle = 'Reserved Room';
        $resvSpanExpectedEnd = null;
        $mySpan = null;
        $reservedSpans = [];

        // Check if the visit and span are valid.
        if ($idVisit < 1 || $span < 0) {
            return ["error" => "A Visit is not selected: $idV-$idSpan"];
        }

        // Look at all the spans for this visit.
        $query = "SELECT
    v.Span,
    v.Span_Start,
    v.Expected_Departure,
    v.`Status`,
    v.idReservation,
    v.idResource,
    v.Pledged_Rate,
    ifnull(r.Title, 'R') as `Resource_Title`
FROM
    visit v join `resource` r on v.idResource = r.idResource
WHERE
    idVisit = $idVisit
ORDER BY Span;";

        $stmt1 = $dbh->query($query);
        while ($rw = $stmt1->fetch(\PDO::FETCH_ASSOC)) {

            // Selected visit span?
            if ($span == $rw['Span']) {
                $mySpan = $rw;
            }

            // Reserve span present?
            if ($rw['Status'] == VisitStatus::Reserved) {
                $reservedSpans[] = $rw;

                $hasReservedSpan = TRUE;

                // Is this the future room change date?
                if (isset($mySpan['Expected_Departure'])) {  // $mySpan may  not be set yet.

                    $expDep = new \DateTime($mySpan['Expected_Departure']);
                    $resvSpanStart = new \DateTime($rw['Span_Start']);

                    if ($expDep->format('Y-m-d') == $resvSpanStart->format('Y-m-d')) {
                        //$showChangeNow = true;
                        $resvIdResc = $rw['idResource'];
                        $resvTitle = $rw['Resource_Title'];
                        $resvSpanExpectedEnd = $rw['Expected_Departure'];
                    }
                }
            }
        }

        // span found?
        if ($mySpan === null) {
            return ["error" => "<span>No Data for the indicated visit Id, span: ($idVisit, $idSpan).</span>"];
        }


        // Change rooms control
        if ($mySpan['Status'] == VisitStatus::CheckedIn) {

            $vspanStartDT = new \DateTime($mySpan['Span_Start']);

            $expDepDT = new \DateTime($mySpan['Expected_Departure']);
            $expDepDT->setTime(0, 0, 0);

            $now = new \DateTime();
            $now->setTime(0, 0, 0);

            // only update the expected departure if a reserved span isn't next
            if ($expDepDT <= $now && $hasReservedSpan == false) {
                $expDepDT = $now->add(new \DateInterval('P1D'));
            }

            $reserv = Reservation_1::instantiateFromIdReserv($dbh, $mySpan['idReservation']);

            $roomChooser = new RoomChooser($dbh, $reserv, 0, $vspanStartDT, $expDepDT->setTime($uS->CheckOutTime, 0));
            $curResc = $roomChooser->getSelectedResource();

            // Make the room chooser
            $dataArray['success'] = $roomChooser->createFutureSpanEditMkup($dbh, $reservedSpans) . $roomChooser->createChangeRoomsMarkup($dbh, $isAdmin, $reservedSpans);

            $dataArray['rooms'] = $roomChooser->makeRoomsArray();
            $dataArray['curResc'] = [
                'idResc' => $curResc->getIdResource(),
                'maxOcc' => $curResc->getMaxOccupants(),
                'rate' => $mySpan['Pledged_Rate'],
                'defaultRateCat' => $curResc->getDefaultRoomCategory(),
                'title' => $curResc->getTitle(),
                'key' => $curResc->getKeyDeposit($uS->guestLookups[GLTableNames::KeyDepositCode]),
                'status' => ResourceStatus::Available,
                'merchant' => $curResc->getMerchant(),
            ];

            $dataArray['curVisitSpan'] = [
                'start' => $vspanStartDT->format('c'),
                'end' => $expDepDT->format('c'),
                'idVisit' => $idVisit,
                'span' => $span,
                'isAuthorized' => $isAdmin,
                'idGuest' => $idGuest,
                'hasReservedSpan' => $hasReservedSpan,
                'showChangeNow' => $showChangeNow,
                'reservedIdResource' => $resvIdResc,
                'reservedTitle' => $resvTitle,
                'reservedSpanExpEnd' => $resvSpanExpectedEnd,
            ];

            $dataArray["reservedSpans"] = $reservedSpans;

        } else {
            $dataArray['error'] = "The Change Rooms command is only available for checked-in visits.";
        }


        return $dataArray;
    }

    /**
     * Summary of changeRoomList
     * @param \PDO $dbh
     * @param mixed $idVisit
     * @param mixed $span
     * @param mixed $changeDate
     * @param mixed $rescId
     * @return array
     */
    public static function visitRoomList(\PDO $dbh, $idVisit, $span, $changeDate, $rescId) {

        $dataArray = [];
        $vid = intval($idVisit, 10);
        $spanId = intval($span, 10);

        $vstmt = $dbh->query("Select idReservation, Span_Start, Expected_Departure from visit where idVisit = $vid and Span = $spanId");

        $vRows = $vstmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($vRows) == 1) {

            $now = new \DateTime();
            $now->setTime(10, 0, 0);

            // Expected Departure
            $expDepDT = new \DateTime($vRows[0]['Expected_Departure']);
            $expDepDT->setTime(10, 0, 0);

            if ($expDepDT < $now) {
                $expDepDT = new \DateTime($now->format('Y-m-d H:i:s'));
            }

            // Original Span Start Date
            $spanStartDT = new \DateTime($vRows[0]['Span_Start']);
            $spanStartDT->setTime(10, 0, 0);

            // CHange rooms start date
            $changeDT = new \DateTime($changeDate);
            $changeDT->setTime(10, 0, 0);

            // Cannot change rooms before the span start date.
            if ($changeDT < $spanStartDT) {
                $changeDT = $spanStartDT;
            }

            // Cannot change rooms after today
            if ($changeDT > $now) {
                $changeDT = $now;
            }

            $reserv = Reservation_1::instantiateFromIdReserv($dbh, $vRows[0]['idReservation']);

            $roomChooser = new RoomChooser($dbh, $reserv, 0, $changeDT, $expDepDT);

            $dataArray['sel'] = $roomChooser->createChangeRoomsSelector($dbh, TRUE);
            $dataArray['rooms'] = $roomChooser->makeRoomsArray();
            $dataArray['idResc'] = $rescId;

        } else {
            // error
            $dataArray['error'] = 'Visit id is missing.  ';
        }

        return $dataArray;
    }


    /**
     * Summary of changeVisitRooms, called by visit dialog change rooms control
     * @param \PDO $dbh
     * @param int $idVisit
     * @param int $span
     * @param int $newRescId
     * @param string $replaceRoom
     * @param string $changeDate
     * @param string $changeEndDate
     * @param bool $useDefaultRate
     * @return array<float|int|string>
     */
    public static function changeVisitRoom(\PDO $dbh, $idVisit, $span, $newRescId, $replaceRoom, $changeDate, $changeEndDate, $useDefaultRate) {

        $uS = Session::getInstance();
        $dataArray = [];
        $returntoVisit = FALSE;
        $returnCkdIn = FALSE;
        $returnReserv = FALSE;
        $reply = '';

        // Change Rooms?
        if ($newRescId != 0) {

            // instantiate current visit
            $visit = new Visit($dbh, 0, $idVisit, $span);


            if ($newRescId != $visit->getidResource()) {

                $resc = AbstractResource::getResourceObj($dbh, $newRescId);

                $now = new \DateTIme();
                $today = new \DateTime();
                $today->setTime(0, 0);


                if ($replaceRoom == 'rpl') {
                    // Replace room in existing span
                    $chRoomDT = new \DateTime($visit->getSpanStart());
                    $chgEndDT = $today;

                } else {
                    // Change room - new span
                    if ($changeDate != '') {

                        $chDT = new \DateTime($changeDate);
                        $chRoomDT = new \DateTime($chDT->format('Y-m-d 00:00:00'));

                        $chDT = new \DateTime($changeEndDate);
                        $chgEndDT = new \DateTime($chDT->format('Y-m-d 00:00:00'));

                    } else {
                        // No change date, use today
                        $chRoomDT = $now;
                        $chgEndDT = $today;
                    }
                }


                $departDT = new \DateTime($visit->getExpectedDeparture());
                $departDT->setTime($uS->CheckOutTime, 0, 0);

                if ($departDT < $today) {
                    $departDT = $today;
                }

                $arriveDT = new \DateTime($visit->getSpanStart());

                if ($chRoomDT < $arriveDT || $chRoomDT > $chgEndDT) {

                    $reply .= "The change room date must be within the visit timeframe, between " . $arriveDT->format('M j, Y') . ' and ' . $chgEndDT->format('M j, Y');

                } else {

                    //if deposit needs to be paid
                    $curRescId = $visit->getidResource();
                    $curResc = AbstractResource::getResourceObj($dbh, $curRescId);
                    if($curResc->getKeyDeposit($uS->guestLookups[GLTableNames::KeyDepositCode]) < $resc->getKeyDeposit($uS->guestLookups[GLTableNames::KeyDepositCode])){
                        $returntoVisit = TRUE;
                    }

                    // Default room rate
                    $newRateCategory = '';
                    if ($useDefaultRate) {
                        $newRateCategory = $resc->getDefaultRoomCategory();
                    }

                    $reply .= $visit->changeRooms($dbh, $resc, $chRoomDT, $chgEndDT, $newRateCategory);

                    $returnCkdIn = TRUE;
                    $returnReserv = TRUE;
                }
            }
        }

        // Return checked in guests markup?
        if ($returnCkdIn) {
            $dataArray['curres'] = 'y';
        }

        if ($returnReserv) {
            $dataArray['reservs'] = 'y';
            $dataArray['waitlist'] = 'y';

            if ($uS->ShowUncfrmdStatusTab) {
                $dataArray['unreserv'] = 'y';
            }
        }

        if($returntoVisit){
            $dataArray['openvisitviewer'] = ($span + 1);
        }

        $dataArray['msg'] = $reply;

        return $dataArray;

    }


    public static function undoRoomChange(\PDO $dbh, Visit $visit, $uname) {

        // Reservation
        $resv = Reservation_1::instantiateFromIdReserv($dbh, $visit->getReservationId());

        if ($resv->isNew() === TRUE) {
            return '';
        }

        // Get next visit
        $nextVisitRs = new VisitRS();
        $nextVisitRs->idVisit->setStoredVal($visit->getIdVisit());
        $nextVisitRs->Span->setStoredVal($visit->getSpan() + 1);
        $vRows = EditRS::select($dbh, $nextVisitRs, array($nextVisitRs->idVisit, $nextVisitRs->Span));

        if (count($vRows) != 1) {
            throw new UnexpectedValueException('Next Visit Span not found.');
        }

        EditRS::loadRow($vRows[0], $nextVisitRs);

        // Next visit must be the last.
        if ($nextVisitRs->Status->getStoredVal() != VisitStatus::CheckedIn) {
            throw new UnexpectedValueException('Cannot Undo this room change. The next Visit Span must be Checked-in.');
        }

        // Dates
        if ($nextVisitRs->Span_End->getStoredVal() == '') {

            $expDepDT = new \DateTime($nextVisitRs->Expected_Departure->getStoredVal());
            $now = new \DateTime();

            if ($now > $expDepDT) {
                $expDepDT = $now;
            }

        } else {
            $expDepDT = new \DateTime($nextVisitRs->Span_End->getStoredVal());
        }

        $startDt = date('Y-m-d 23:59:59', strtotime($visit->getSpanStart()));


        // Number of occupants

        // Load ALL of next visit's stays
        $nextStays = Visit::loadStaysStatic($dbh, $nextVisitRs->idVisit->getStoredVal(), $nextVisitRs->Span->getStoredVal(), VisitStatus::CheckedIn);

        // Load this visit's stays that were still checked in.
        $stays = Visit::loadStaysStatic($dbh, $visit->getIdVisit(), $visit->getSpan(), VisitStatus::NewSpan);

        $numOccupants = max([count($nextStays), count($visit->stays)] );

        // Check room availability
        if ($resv->isResourceOpen($dbh, $visit->getidResource(), $startDt, $expDepDT->format('Y-m-d 01:00:00'), $numOccupants, ['room', 'rmtroom', 'part'], TRUE, TRUE) === FALSE) {
            throw new UnexpectedValueException('The room is unavailable. ');
        }

        // Get new room cleaning status to copy to original room
        $resc = AbstractResource::getResourceObj($dbh, $nextVisitRs->idResource->getStoredVal());
        $rooms = $resc->getRooms();
        $room = array_shift($rooms);
        $roomStatus = $room->getStatus();

        try{
            if(!$dbh->inTransaction()){
                $dbh->beginTransaction();
            }
        
            // Undo visit checkout
            $visit->visitRS->Actual_Departure->setNewVal($nextVisitRs->Actual_Departure->getStoredVal());
            $visit->visitRS->Span_End->setNewVal($nextVisitRs->Span_End->getStoredVal());
            $visit->visitRS->Expected_Departure->setNewVal($expDepDT->format('Y-m-d 10:00:00'));
            $visit->visitRS->Status->setNewVal($nextVisitRs->Status->getStoredVal());

            $updateCounter = $visit->updateVisitRecord($dbh, $uname);

            if ($updateCounter != 1) {
                throw new RuntimeException('Visit table update failed. Checkout is not undone.');
            }

            // update Reservation
            $resv->setIdResource($visit->getidResource());
            $resv->saveReservation($dbh, $resv->getIdRegistration(), $uname);

            // remove the next visit
            EditRS::delete($dbh, $nextVisitRs, [$nextVisitRs->idVisit, $nextVisitRs->Span]);
            $logDelText = VisitLog::getDeleteText($nextVisitRs, $nextVisitRs->idVisit->getStoredVal());
            VisitLog::logVisit($dbh, $nextVisitRs->idVisit->getStoredVal(), $nextVisitRs->Span->getStoredVal(), $nextVisitRs->idResource->getStoredVal(), $nextVisitRs->idRegistration->getStoredVal(), $logDelText, "delete", $uname);


            // original stays with status = changeRoom.
            $visit->loadStays($dbh, VisitStatus::NewSpan);
            foreach ($visit->stays as $s) {

                // Check status in next stay
                foreach ($nextStays as $ns) {

                    if ($s->idName->getStoredVal() == $ns->idName->getStoredVal()
                            && date('Y-m-d', strtotime($s->Span_End_Date->getStoredVal())) == date('Y-m-d', strtotime($ns->Span_Start_Date->getStoredVal()))) {

                        $s->Checkout_Date->setNewVal($ns->Checkout_Date->getStoredVal());
                        $s->Span_End_Date->setNewVal($ns->Span_End_Date->getStoredVal());
                        $s->Status->setNewVal($ns->Status->getStoredVal());
                        $s->Expected_Co_Date->setNewVal($ns->Expected_Co_Date->getStoredVal());
                        $s->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                        $s->Updated_By->setNewVal($uname);

                        $cnt = EditRS::update($dbh, $s, array($s->idStays));
                        if ($cnt > 0) {
                            $logText = VisitLog::getUpdateText($s);
                            EditRS::updateStoredVals($s);
                            VisitLog::logStay($dbh, $s->idVisit->getStoredVal(), $s->Visit_Span->getStoredVal(), $s->idRoom->getStoredVal(), $s->idStays->getStoredVal(), $s->idName->getStoredVal(), $visit->getIdRegistration(), $logText, "update", $uname);
                        }

                        break;
                    }
                }
            }


            // Next stays that are not in original stays
            foreach ($nextStays as $ns) {

                // remove the known stays
                foreach ($stays as $s) {

                    if ($s->idName->getStoredVal() == $ns->idName->getStoredVal()
                            && date('Y-m-d', strtotime($s->Span_End_Date->getStoredVal())) == date('Y-m-d', strtotime($ns->Span_Start_Date->getStoredVal()))) {

                        EditRS::delete($dbh, $ns, [$ns->idStays]);
                        continue 2;
                    }
                }

                // just change the span.
                $ns->Visit_Span->setNewVal($visit->getSpan());

                $ns->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                $ns->Updated_By->setNewVal($uname);

                $cnt = EditRS::update($dbh, $ns, array($ns->idStays));
                if ($cnt > 0) {
                    $logText = VisitLog::getUpdateText($ns);
                    EditRS::updateStoredVals($ns);
                    VisitLog::logStay($dbh, $ns->idVisit->getStoredVal(), $ns->Visit_Span->getStoredVal(), $ns->idRoom->getStoredVal(), $ns->idStays->getStoredVal(), $ns->idName->getStoredVal(), $visit->getIdRegistration(), $logText, "update", $uname);
                }

            }

            // Update room cleaning status of this room
            $resc2 = AbstractResource::getResourceObj($dbh, $visit->getidResource());
            $rooms2 = $resc2->getRooms();
            $room2 = array_shift($rooms2);
            $room2->setStatus($roomStatus);
            $room2->saveRoom($dbh, $uname, TRUE, $roomStatus);

            // Update invoices
            $dbh->exec("Update invoice set Suborder_Number = " . $visit->getSpan() . " where Order_Number = " . $visit->getIdVisit() . " and Suborder_Number != ". $visit->getSpan());

            if($dbh->inTransaction()){
                $dbh->commit();
            }

            return "Change Rooms is undone.  ";
        }catch (PDOException $e){
            if($dbh->inTransaction()){
                $dbh->rollBack();
            }
            throw new UnexpectedValueException("Undo Room Change Failed");
        }

    }

    /**
     * Summary of undoCheckout
     * @param \PDO $dbh
     * @param \HHK\House\Visit\Visit $visit
     * @param \DateTime $newExpectedDT
     * @param mixed $uname
     * @throws \HHK\Exception\RuntimeException
     * @return string
     */
    public static function undoCheckout(\PDO $dbh, Visit $visit, \DateTime $newExpectedDT, $uname) {

        $reply = '';
        $uS = Session::getInstance();

        if ($visit->getVisitStatus() != VisitStatus::CheckedOut) {
            return 'Cannot undo checkout, visit continues in another room or at another rate.  ';
        }

        $actDeptDT = new \DateTime($visit->getActualDeparture());

        $resv = Reservation_1::instantiateFromIdReserv($dbh, $visit->getReservationId());

        $startDT = new \DateTime($visit->getSpanStart());
        $startDT->setTime(23, 59, 59);

        // Undo reservation termination
        $resv->setActualDeparture('');
        $resv->setExpectedDeparture($newExpectedDT->format('Y-m-d '. $uS->CheckOutTime . ':00:00'));
        $resv->setStatus(ReservationStatus::Staying);

        $resv->saveReservation($dbh, $resv->getIdRegistration(), $uname);


        // Undo visit checkout
        $visit->visitRS->Actual_Departure->setNewVal('');
        $visit->visitRS->Span_End->setNewVal('');
        $visit->visitRS->Expected_Departure->setNewVal($newExpectedDT->format('Y-m-d '. $uS->CheckOutTime . ':00:00'));
        $visit->visitRS->Status->setNewVal(VisitStatus::CheckedIn);
        $visit->visitRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
        $visit->visitRS->Updated_By->setNewVal($uname);

        $updateCounter = EditRS::update($dbh, $visit->visitRS, array($visit->visitRS->idVisit, $visit->visitRS->Span));

        if ($updateCounter != 1) {
            throw new RuntimeException('Visit table update failed. Checkout is not undone.');
        }

        $logText = VisitLog::getUpdateText($visit->visitRS);
        EditRS::updateStoredVals($visit->visitRS);
        VisitLog::logVisit($dbh, $visit->getIdVisit(), $visit->visitRS->Span->getStoredVal(), $visit->visitRS->idResource->getStoredVal(), $visit->visitRS->idRegistration->getStoredVal(), $logText, "update", $uname);
        $reply .= "Checkout is undone.  ";

        // Update stays
        $visit->loadStays($dbh, VisitStatus::CheckedOut);
        foreach ($visit->stays as $s) {

            if (date('Y-m-d', strtotime($s->Span_End_Date->getStoredVal())) == $actDeptDT->format('Y-m-d')) {

                // Undo stay checkout
                $s->Checkout_Date->setNewVal('');
                $s->Span_End_Date->setNewVal('');
                $s->Status->setNewVal(VisitStatus::CheckedIn);
                $s->Expected_Co_Date->setNewVal($newExpectedDT->format('Y-m-d '. $uS->CheckOutTime . ':00:00'));

                // cancel on-leave
                $s->On_Leave->setNewVal(0);

                $s->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                $s->Updated_By->setNewVal($uname);

                $cnt = EditRS::update($dbh, $s, array($s->idStays, $s->Visit_Span));
                if ($cnt > 0) {
                    $logText = VisitLog::getUpdateText($s);
                    EditRS::updateStoredVals($s);
                    VisitLog::logStay($dbh, $s->idVisit->getStoredVal(), $s->Visit_Span->getStoredVal(), $s->idRoom->getStoredVal(), $s->idStays->getStoredVal(), $s->idName->getStoredVal(), $visit->getIdRegistration(), $logText, "update", $uname);
                }
            }
        }


        // Update room cleaning status of this room
        $resc2 = AbstractResource::getResourceObj($dbh, $visit->getidResource());
        $rooms2 = $resc2->getRooms();
        $room2 = array_shift($rooms2);
        $room2->setStatus(RoomState::Clean);
        $room2->saveRoom($dbh, $uname, TRUE, RoomState::Clean);

        return $reply;
    }

    /**
     * Summary of createAddrObj
     * @param \PDO $dbh
     * @param mixed $idName
     * @return array
     */
    public static function createAddrObj(\PDO $dbh, $idName) {

        $guest = new Guest($dbh, '', $idName);
        $addrObj = $guest->getAddrObj();
        $addr = $addrObj->get_Data(AddressPurpose::Home);

        $adrArray = array(
            'adraddress1' => $addr['Address_1'],
            'adraddress2' => $addr['Address_2'],
            'adrcity' => $addr['City'],
            'adrcounty' => $addr['County'],
            'adrstate' => $addr['State_Province'],
            'adrcountry' => $addr['Country_Code'],
            'adrzip' => $addr['Postal_Code']
        );

        return $adrArray;
    }

    // Just credit cards with delete checkboxes.
    /**
     * Summary of guestEditCreditTable
     * @param \PDO $dbh
     * @param mixed $idRegistration
     * @param mixed $idGuest
     * @param mixed $index
     * @param mixed $defaultMerchant
     * @return string
     */
    public static function guestEditCreditTable(\PDO $dbh, $idRegistration, $idGuest, $index, $defaultMerchant = '') {

        $uS = Session::getInstance();

        $gateway = AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, AbstractPaymentGateway::getCreditGatewayNames($dbh, 0, 0, 0));
        $merchants = $gateway->getMerchants($dbh);

        $tbl = new HTMLTable();

        $tkRsArray = CreditToken::getRegTokenRSs($dbh, $idRegistration, '', $idGuest);

        $tbl->addBodyTr(HTMLTable::makeTh("X", array('title'=>'Delete card from file')) . HTMLTable::makeTh("Card on File") . HTMLTable::makeTh("Name"));

        // List any valid stored cards on file
        foreach ($tkRsArray as $tkRs) {

            $merchant = ' (' . ucfirst($tkRs->Merchant->getStoredVal()) . ')';
            if (count($merchants) == 1 || $tkRs->Merchant->getStoredVal() == "") {
                $merchant = '';
            }

            $tbl->addBodyTr(
                    HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'cbDelCard'.$index. '_'.$tkRs->idGuest_token->getStoredVal())))
                    . HTMLTable::makeTd($tkRs->CardType->getStoredVal() . ' - ' . $tkRs->MaskedAccount->getStoredVal() . $merchant)
                    . HTMLTable::makeTd($tkRs->CardHolderName->getStoredVal())
                );

        }

        // New card.
        if ($gateway->hasCofService()) {

        	$attr = array('type'=>'checkbox', 'name'=>'rbUseCard'.$index);
        	/*if (count($tkRsArray) == 0) {
	        	$attr['checked'] = 'checked';
	        } else {
	        	unset($attr['checked']);
	        }*/

	        $tbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'New', array('for'=>'rbUseCard'.$index, 'style'=>'margin-right: .5em;'))
	        		.  HTMLInput::generateMarkup('', $attr), array('style'=>'text-align:right;', 'colspan'=> '3'))
	        );

	        $tbl->addBodyTr( HTMLTable::makeTd('', array('id'=>'tdChargeMsg' . $index, 'colspan'=>'4', 'style'=>'color:red; display:none;')));

	        $gateway->setCheckManualEntryCheckbox(TRUE);

	        $gwTbl = new HTMLTable();
	        $gateway->selectPaymentMarkup($dbh, $gwTbl, $index);
	        $tbl->addBodyTr(HTMLTable::makeTd($gwTbl->generateMarkup(array('style'=>'width:100%;')), array('colspan'=>'4', 'style'=>'padding:0;')));
        }

        $mkup = $tbl->generateMarkup(array('id' => 'tblupCredit'.$index, 'class'=>'igrs'));

        return $mkup;

    }

    /**
     * Return from View Credit Table; delete any indicated cards and send out COF transaction to Gateway
     *
     * @param \PDO $dbh
     * @param integer $idGuest
     * @param integer $idGroup
     * @param array $post
     * @param string $postBackPage
     * @return array
     */
    public static function cardOnFile(\PDO $dbh, $idGuest, $idGroup, $post, $postBackPage, $idx) {
        // Credit card processing
        $uS = Session::getInstance();

        $dataArray = array();

        $keys = array_keys($post);
        $msg = '';


        // Delete any credit tokens
        foreach ($keys as $k) {

            $parts = explode('_', $k);

            if (count($parts) > 1 && $parts[0] == 'cbDelCard'.$idx) {

                $idGt = intval(filter_var($parts[1], FILTER_SANITIZE_NUMBER_INT), 10);

                if ($idGt > 0) {

                    $cnt = $dbh->exec("update guest_token set Token = '' where idGuest_token = " . $idGt);

                    if ($cnt > 0) {
                        $gtRs = CreditToken::getTokenRsFromId($dbh, $idGt);
                        $msg .= 'Card ' . $gtRs->MaskedAccount->getStoredVal() . ', Name ' . $gtRs->CardHolderName->getStoredVal() . ' deleted.  ';
                    }
                }
            }
        }

        $dataArray['success'] = $msg;

        // Add a new card
        if (isset($post['rbUseCard'.$idx])) {

            $manualKey = FALSE;
            $selGw = '';
            $cardType = '';
            $chargeAcct = '';

            $guest = new Guest($dbh, '', $idGuest);
            $newCardHolderName = $guest->getRoleMember()->get_fullName();


            if (isset($post['btnvrKeyNumber'.$idx])) {
            	$manualKey = TRUE;
            }

            if (isset($post['txtvdNewCardName'.$idx]) && $post['txtvdNewCardName'.$idx] != '') {
            	$newCardHolderName = strtoupper(filter_var($post['txtvdNewCardName'.$idx], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            // For mulitple merchants
            if (isset($post['selccgw'.$idx])) {
            	$selGw = strtolower(filter_var($post['selccgw'.$idx], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            if (isset($post['selChargeType'.$idx])) {
            	$cardType = filter_var($post['selChargeType'.$idx], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            if (isset($post['txtChargeAcct'.$idx])) {

            	$chargeAcct = filter_var($post['txtChargeAcct'.$idx], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            	if (strlen($chargeAcct) > 4) {
            		$chargeAcct = substr($chargeAcct, -4, 4);
            	}

            }

            try {

                $gateway = AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, $selGw);

                if ($gateway->hasCofService()) {
                	$dataArray = $gateway->initCardOnFile($dbh, $uS->siteName, $idGuest, $idGroup, $manualKey, $newCardHolderName, $postBackPage, $cardType, $chargeAcct, $idx);
                }

            } catch (PaymentException $ex) {

                $dataArray['error'] = $ex->getMessage();
            }
        } else {
            // return new form
            $dataArray['COFmkup'] = self::guestEditCreditTable($dbh, $idGroup, $idGuest, $idx);
        }

        return $dataArray;
    }



    /**
     * Summary of changeExpectedDepartureDate - Change a stay departure date, then check if it affects the visit span.
     * @param \PDO $dbh
     * @param mixed $idGuest
     * @param mixed $idVisit
     * @param mixed $newDate
     * @return array
     */
    public static function changeExpectedDepartureDate(\PDO $dbh, $idGuest, $idVisit, $newDate) {

        if ($newDate == '' || $idGuest < 1 || $idVisit < 1) {
            return ['error' => 'Parameters not specified.   '];
        }

        $visit = new Visit($dbh, 0, $idVisit);
        $guestDates[$idGuest] = $newDate;
        $uS = Session::getInstance();

        $result = $visit->changeExpectedCheckoutDates($dbh, $guestDates);

        return ['success' => $result['message'], 'isChanged' => $result['isChanged']];
    }


    /**
     * Summary of visitChangeLogMarkup
     * @param \PDO $dbh
     * @param mixed $idReg
     * @return array
     */
    public static function visitChangeLogMarkup(\PDO $dbh, $idReg) {

        $lTable = new HTMLTable();
        if ($idReg > 0) {
            $visLogRS = new Visit_LogRS();
            $visLogRS->idRegistration->setStoredVal($idReg);
            $rows = EditRS::select($dbh, $visLogRS, array($visLogRS->idRegistration), 'and', array($visLogRS->Timestamp), FALSE);
            $cnt = 0;
            foreach ($rows as $r) {
                $vlRS = new Visit_LogRS();
                EditRS::loadRow($r, $vlRS);
                // only show my id or table visit.
                if ($vlRS->Log_Type->getStoredVal() == 'visit') {

                    $lTable->addBodyTr(
                            HTMLTable::makeTd(date('m/d/Y H:i:s', strtotime($vlRS->Timestamp->getStoredVal())))
                            . HTMLTable::makeTd($vlRS->Sub_Type->getStoredVal())
                            . HTMLTable::makeTd($vlRS->User_Name->getStoredVal())
                            . HTMLTable::makeTd($vlRS->idVisit->getStoredVal())
                            . HTMLTable::makeTd($vlRS->Span->getStoredVal())
                            . HTMLTable::makeTd($vlRS->idRr->getStoredVal())
                            . HTMLTable::makeTd(VisitLog::parseLogText($vlRS->Log_Text->getStoredVal()))
                    );
                    if ($cnt++ == 47) {
                        break;
                    }
                }
            }
        }

        $lTable->addHeaderTr(
                HTMLTable::makeTh('Date')
                . HTMLTable::makeTh('Operation')
                . HTMLTable::makeTh('User')
                . HTMLTable::makeTh('Visit Id')
                . HTMLTable::makeTh('Span')
                . HTMLTable::makeTh('Resource Id')
                . HTMLTable::makeTh('Message'));

        return array('vlog' => $lTable->generateMarkup());
    }

}