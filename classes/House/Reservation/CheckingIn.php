<?php

namespace HHK\House\Reservation;

use HHK\Config_Lite\Config_Lite;
use HHK\Exception\RuntimeException;
use HHK\HTMLControls\HTMLContainer;
use HHK\House\Family\{Family, FamilyAddGuest};
use HHK\House\HouseServices;
use HHK\House\Registration;
use HHK\House\ReserveData\ReserveData;
use HHK\House\Room\RoomChooser;
use HHK\House\Visit\Visit;
use HHK\sec\{SecurityComponent, Session};
use HHK\Payment\PaymentManager\PaymentManager;
use HHK\Payment\PaymentResult;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Purchase\{CheckinCharges, PaymentChooser, RateChooser};
use HHK\SysConst\{GLTableNames, ItemPriceCode, ReservationStatus, VisitStatus};
use HHK\Tables\EditRS;
use HHK\Tables\Reservation\ReservationRS;

/**
 * Description of CheckingIn
 *
 * @author Eric
 */

class CheckingIn extends ActiveReservation {
    
    protected $visit;
    protected $resc;
    protected $errors;
    
    public static function reservationFactoy(\PDO $dbh, $post) {
        
        $rData = new ReserveData($post, 'Check-in');
        
        if ($rData->getIdResv() > 0) {
            $rData->setSaveButtonLabel('Check-in');
            return CheckingIn::loadReservation($dbh, $rData);
        }
        
        throw new RuntimeException('Reservation Id not defined.');
        
    }
    
    public static function loadReservation(\PDO $dbh, ReserveData $rData) {
        
        $uS = Session::getInstance();
        
        // Load reservation and visit
        $stmt = $dbh->query("SELECT r.*, rg.idPsg, ifnull(v.`idVisit`, 0) as `idVisit`, ifnull(v.`Status`, '') as `SpanStatus`, ifnull(v.Span_Start, '') as `SpanStart`, ifnull(v.Span_End, datedefaultnow(v.Expected_Departure)) as `SpanEnd`
FROM reservation r
        LEFT JOIN
    registration rg ON r.idRegistration = rg.idRegistration
	LEFT JOIN
    visit v on v.idReservation = r.idReservation and v.Span = " . $rData->getSpan() .
            " WHERE r.idReservation = " . $rData->getIdResv());
        
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (count($rows) != 1) {
            throw new RuntimeException("Reservation Id not found.  ");
        }
        
        $rRs = new ReservationRS();
        EditRS::loadRow($rows[0], $rRs);
        
        $rData->setIdPsg($rows[0]['idPsg']);
        $rData->setIdVisit($rows[0]['idVisit'])
        ->setSpanStatus($rows[0]['SpanStatus'])
        ->setSpanStartDT($rows[0]['SpanStart'])
        ->setSpanEndDT($rows[0]['SpanEnd']);
        
        // Reservation status determines which class to use.
        
        // Uncommitted cannot check in.
        if ($rRs->Status->getStoredVal() == ReservationStatus::UnCommitted) {
            $rData->setSaveButtonLabel('Save');
            return new ActiveReservation($rData, $rRs, new Family($dbh, $rData, TRUE));
        }
        
        // check for additional visit span guest
        if ($rData->getSpanStatus() == VisitStatus::ChangeRate || $rData->getSpanStatus() == VisitStatus::NewSpan || $rData->getSpanStatus() == VisitStatus::CheckedOut) {
            return new CheckedoutReservation($rData, $rRs, new Family($dbh, $rData));
        }
        
        // Staying resv - add guests
        if ($rRs->Status->getStoredVal() == ReservationStatus::Staying) {
            $rData->setInsistCkinDemog($uS->InsistCkinDemog);
            return new StayingReservation($rData, $rRs, new FamilyAddGuest($dbh, $rData, TRUE));
        }
        
        // Otherwise we can check in.
        if (Reservation_1::isActiveStatus($rRs->Status->getStoredVal())) {
            $rData->setInsistCkinDemog($uS->InsistCkinDemog);
            return new CheckingIn($rData, $rRs, new Family($dbh, $rData, TRUE));
        }
        
        // Default
        return new StaticReservation($rData, $rRs, new Family($dbh, $rData));
        
    }
    
    public function createMarkup(\PDO $dbh) {
        
        // Add the family, hospital, etc sections.
        $this->createDatesMarkup();
        $this->createHospitalMarkup($dbh);
        $this->createFamilyMarkup($dbh);
        
        $this->reserveData->setResvSection($this->createCheckinMarkup($dbh));
        
        return $this->reserveData->toArray();
        
    }
    
    protected function createCheckinMarkup(\PDO $dbh) {
        
        $uS = Session::getInstance();
        $labels = new Config_Lite(LABEL_FILE);
        
        $resv = new Reservation_1($this->reservRs);
        
        // Registration
        $reg = new Registration($dbh, $this->reserveData->getIdPsg());
        
        // Room Chooser
        $roomChooser = new RoomChooser($dbh, $resv, 1, $resv->getExpectedArrival(), $resv->getExpectedDeparture());
        
        // Rate Chooser
        $rateChooser = new RateChooser($dbh);
        
        // Create rate chooser markup?
        if ($uS->RoomPriceModel != ItemPriceCode::None) {
            
            $resc = $roomChooser->getSelectedResource();
            
            if (is_null($resc)) {
                $roomKeyDeps = '';
            } else {
                $roomKeyDeps = $resc->getKeyDeposit($uS->guestLookups[GLTableNames::KeyDepositCode]);
            }
            
            // Rate Chooser
            $dataArray['rate'] = $rateChooser->createCheckinMarkup($dbh, $resv, $resv->getExpectedDays(), $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'));
            
            // Payment Chooser
            if ($uS->PayAtCkin) {
                
                $checkinCharges = new CheckinCharges(0, $resv->getVisitFee(), $roomKeyDeps);
                $checkinCharges->sumPayments($dbh);
                
                // select gateway
                if ($resv->getIdResource() > 0) {
                    // Get gateway merchant
                    $gwStmt = $dbh->query("SELECT ifnull(l.Merchant, '') as `Merchant`, ifnull(l.idLocation, 0) as idLocation FROM location l join room r on l.idLocation = r.idLocation
                    join resource_room rr on r.idRoom = rr.idRoom where l.Status = 'a' and rr.idResource = " . $resv->getIdResource());
                    
                } else {
                    $gwStmt = $dbh->query("SELECT DISTINCT ifnull(l.Merchant, '') as `Merchant`, ifnull(l.idLocation, 0) as idLocation FROM room rm LEFT JOIN location l  on l.idLocation = rm.idLocation
                    where l.`Status` = 'a' or l.`Status` is null;");
                }
                
                $rows = $gwStmt->fetchAll(\PDO::FETCH_ASSOC);
                $merchants = array();
                
                if (count($rows) > 0) {
                    
                    foreach ($rows as $r) {
                        $merchants[$r['idLocation']] = $r['Merchant'];
                    }
                }
                
                $paymentGateway = AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, $merchants);
                
                $dataArray['pay'] = HTMLContainer::generateMarkup('div',
                    PaymentChooser::createMarkup($dbh, $resv->getIdGuest(), $reg->getIdRegistration(), $checkinCharges, $paymentGateway, $resv->getExpectedPayType(), $uS->KeyDeposit, FALSE, $uS->DefaultVisitFee, $reg->getPreferredTokenId())
                    , array('style'=>'clear:left; float:left;'));
                
            }
            
        }
        
        // Room Chooser
        $dataArray['rChooser'] = $roomChooser->CreateCheckinMarkup($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN), FALSE, TRUE, 1);
        
        // Rates array with amount calculated for each rate.
        $dataArray['ratelist'] = $rateChooser->makeRateArray($dbh, $resv->getExpectedDays(), $resv->getIdRegistration(), $resv->getFixedRoomRate(), ($resv->getNumberGuests() * $resv->getExpectedDays()));
        
        // Rooms array with key deposit info
        $dataArray['rooms'] = $roomChooser->makeRoomsArray();
        
        if ($uS->VisitFee) {
            // Visit Fee Array
            $dataArray['vfee'] = $rateChooser->makeVisitFeeArray($dbh, $resv->getVisitFee());
        }
        
        // Vehicles
        if ($uS->TrackAuto) {
            $dataArray['vehicle'] = $this->vehicleMarkup($dbh);
        }
        
        // Reservation status title
        $dataArray['rStatTitle'] = '';
        
        // Reservation notes
        $dataArray['notes'] = HTMLContainer::generateMarkup('fieldset',
            HTMLContainer::generateMarkup('legend', 'Visit Notes', array('style'=>'font-weight:bold;'))
            , array('id'=>'hhk-noteViewer', 'style'=>'clear:left; float:left; width:90%; font-size: 0.9em;', 'class'=>'hhk-panel'));
        
        
        // Collapsing header
        $hdr = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('span', 'Checking In')
            , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));
        
        
        return array('hdr'=>$hdr, 'rdiv'=>$dataArray);
    }
    
    public function save(\PDO $dbh, $post) {
        
        // Save family, rate, hospital, room.
        parent::saveResv($dbh, $post);
        
        if ($this->reserveData->hasError() === FALSE) {
            $this->saveCheckIn($dbh, $post);
        }
        
        return $this;
        
    }
    
    protected function saveCheckIn(\PDO $dbh, $post) {
        
        $uS = Session::getInstance();
        
        $resv = new Reservation_1($this->reservRs);
        
        $stmt = $dbh->query("Select idVisit from visit where idReservation = " . $resv->getIdReservation() . " limit 1;");
        
        if ($stmt->rowCount() > 0) {
            throw new RuntimeException('Visit already exists for reservation Id ' . $resv->getIdReservation());
        }
        
        $this->checkVisitDates($uS->CheckInTime);
        
        if ($this->reserveData->hasError()) {
            return;
        }
        
        // Is resource specified?
        if ($resv->getIdResource() == 0) {
            $this->reserveData->addError('A room was not specified.  ');
            return;
        }
        
        $resources = $resv->findGradedResources($dbh, $this->reserveData->getArrivalDT()->format('Y-m-d'), $this->reserveData->getDepartureDT()->format('Y-m-d'), 1, array('room', 'rmtroom', 'part'), TRUE);
        
        // Does the resource still fit the requirements?
        if (isset($resources[$resv->getIdResource()]) === FALSE) {
            $this->reserveData->addError('The room is already in use.  ');
            return;
        }
        
        // Get our room.
        $resc = $resources[$resv->getIdResource()];
        unset($resources);
        
        // Only admins can pick an unsuitable room.
        if (SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN) === FALSE && $resc->optGroup != '') {
            $this->reserveData->addError('Room ' . $resc->getTitle() . ' is ' . $resc->optGroup . '.  ');
            return;
        }
        
        if (count($this->getStayingMembers()) > $resc->getMaxOccupants()) {
            $this->reserveData->addError("The maximum occupancy (" . $resc->getMaxOccupants() . ") for room " . $resc->getTitle() . " is exceded.  ");
            return;
        }
        
        // create visit ( -1 forces a new visit)
        $visit = new Visit($dbh, $resv->getIdRegistration(), -1, $this->reserveData->getArrivalDT(), $this->reserveData->getDepartureDT(), $resc, $uS->username);
        
        // Add guests
        foreach ($this->getStayingMembers() as $m) {
            
            if ($uS->PatientAsGuest === FALSE && $m->isPatient()) {
                $this->reserveData->addError('Patients cannot stay  .');
                return;
            }
            
            $visit->addGuestStay($m->getId(), $this->reserveData->getArrivalDT()->format('Y-m-d H:i:s'), $this->reserveData->getArrivalDT()->format('Y-m-d H:i:s'), $this->reserveData->getDepartureDT()->format('Y-m-d 10:00:00'));
            
        }
        
        $visit->setRateCategory($resv->getRoomRateCategory());
        $visit->setIdRoomRate($resv->getIdRoomRate());
        $visit->setRateAdjust($resv->getRateAdjust());
        $visit->setPledgedRate($resv->getFixedRoomRate());
        
        // Rate Glide
        $visit->setRateGlideCredit(RateChooser::setRateGlideDays($dbh, $resv->getIdRegistration(), $uS->RateGlideExtend));
        
        // Primary guest
        $visit->setPrimaryGuestId($resv->getIdGuest());
        
        // Reservation Id
        $visit->setReservationId($resv->getIdReservation());
        
        // hospital stay id
        $visit->setIdHospital_stay($resv->getIdHospitalStay());
        
        //
        // Checkin  Saves visit
        //
        $visit->checkin($dbh, $uS->username);
        
        
        // Save new reservation status
        $resv->setStatus(ReservationStatus::Staying);
        $resv->setActualArrival($visit->getArrivalDate());
        $resv->setExpectedDeparture($visit->getExpectedDeparture());
        $resv->setNumberGuests(count($this->getStayingMembers()));
        $resv->setIdResource($resc->getIdResource());
        $resv->saveReservation($dbh, $resv->getIdRegistration(), $uS->username);
        
        //
        // Payment
        //
        $pmp = PaymentChooser::readPostedPayment($dbh, $post);
        
        // Check for key deposit
        if ($uS->KeyDeposit && is_null($pmp) === FALSE) {
            
            $reg = new Registration($dbh, 0, $resv->getIdRegistration());
            
            $depCharge = $resc->getKeyDeposit($uS->guestLookups[GLTableNames::KeyDepositCode]);
            $depBalance = $reg->getDepositBalance($dbh);
            
            if ($depCharge > 0 && $pmp->getKeyDepositPayment() == 0 && $depBalance > 0) {
                
                // Pay deposit with registration balance
                if ($depCharge <= $depBalance) {
                    $pmp->setKeyDepositPayment($depCharge);
                } else {
                    $pmp->setKeyDepositPayment($depBalance);
                }
                
            } else if ($pmp->getKeyDepositPayment() > 0) {
                
                $visit->visitRS->DepositPayType->setNewVal($pmp->getPayType());
            }
            
            // Update Pay type.
            $visit->updateVisitRecord($dbh, $uS->username);
        }
        
        $paymentManager = new PaymentManager($pmp);
        $this->payResult = HouseServices::processPayments($dbh, $paymentManager, $visit, 'ShowRegForm.php?vid='.$visit->getIdVisit(), $visit->getPrimaryGuestId());
        
        $this->resc = $resc;
        $this->visit = $visit;
        
        return;
    }
    
    public function checkedinMarkup(\PDO $dbh) {
        
        $creditCheckOut = array();
        
        if ($this->reserveData->hasError()) {
            return $this->createMarkup($dbh);
        }
        
        // Checking In?
        if ($this->gotoCheckingIn === 'yes' && $this->reserveData->getIdResv() > 0) {
            return array('gotopage'=>'CheckingIn.php?rid=' . $this->reserveData->getIdResv());
        }
        
        $uS = Session::getInstance();
        $reply = '';
        $payId = 0;
        $invNumber = '';
        
        if ($this->payResult !== NULL) {
            // Payment processed
            $reply .= $this->payResult->getReplyMessage();
            
            if ($this->payResult->getStatus() == PaymentResult::FORWARDED) {
                $creditCheckOut = $this->payResult->getForwardHostedPayment();
            }
            
            // Payment Id
            $payId = $this->payResult->getIdPayment();
            Registration::updatePrefTokenId($dbh, $this->visit->getIdRegistration(), $this->payResult->getIdToken());
            
            // New Invoice
            if (is_null($this->payResult->getInvoiceNumber()) === FALSE && $this->payResult->getInvoiceNumber() != '') {
                $invNumber = $this->payResult->getInvoiceNumber();
            }
            
        }
        //        else if ($this->cofResult !== NULL) {
        //            // Process card on file
        //            if (count($this->cofResult) > 0) {
        //                $this->cofResult['resvTitle'] = $this->reserveData->getResvTitle();
        //                $creditCheckOut = $this->cofResult;
        //            }
        //        }
        
        // email the form
        if ($uS->Guest_Track_Address != '' && $uS->NoReplyAddr != '') {
            
            // Generate Reg form
            $reservArray = ReservationSvcs::generateCkinDoc($dbh, 0, $this->visit->getIdVisit(), $this->visit->getSpan(), $uS->resourceURL . 'images/receiptlogo.png');
            
            try {
                
                $mail = prepareEmail();
                
                $mail->From = $uS->NoReplyAddr;
                $mail->FromName = $uS->siteName;
                
                $tos = explode(',', $uS->Guest_Track_Address);
                foreach ($tos as $t) {
                    $to = filter_var($t, FILTER_SANITIZE_EMAIL);
                    if ($to !== FALSE && $to != '') {
                        $mail->addAddress($to);
                    }
                }
                
                $mail->addReplyTo($uS->NoReplyAddr, $uS->siteName);
                $mail->isHTML(true);
                
                $mail->Subject = "New Check-In to " . $this->resc->getTitle() . " by " . $uS->username;
                
                $mail->msgHTML($reservArray['docs'][0]['doc'] . $reservArray['docs'][0]['style']);
                $mail->send();
                
            } catch (\Exception $ex) {
                $dataArray['warning'] = "Email not sent.  Error: ".$ex->getMessage();
            }
        }
        
        
        // Credit payment?
        if (count($creditCheckOut) > 0) {
            return $creditCheckOut;
        }
        
        $dataArray['payId'] = $payId;
        $dataArray['invoiceNumber'] = $invNumber;
        $dataArray['vid'] = $this->visit->getIdVisit();
        $dataArray['success'] = "Checked-In.  " . $reply;
        $dataArray['regid'] = $this->visit->getIdRegistration();
        
        return $dataArray;
        
    }
    
}
?>