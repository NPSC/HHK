<?php

namespace HHK\Payment\Invoice;

use HHK\Common;
use HHK\HTMLControls\HTMLInput;
use HHK\Payment\Receipt;
use HHK\Payment\Invoice\InvoiceLine\{AbstractInvoiceLine, InvoiceInvoiceLine};
use HHK\Purchase\Item;
use HHK\SysConst\{InvoiceStatus, ItemId, GLTableNames, BillStatus, PaymentMethod, VolMemberType, VolStatus};
use HHK\Tables\EditRS;
use HHK\Tables\Payment\{InvoiceRS, InvoiceLineRS};
use HHK\HTMLControls\{HTMLTable, HTMLContainer};
use HHK\House\Registration;
use HHK\Exception\{RuntimeException, PaymentException};
use HHK\sec\Session;
use HHK\sec\Labels;
use Mpdf\Mpdf;

/**
 * Invoice.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Invoice
 *
 * @author Eric
 */
class Invoice {

	/**
	 * Summary of invRs
	 * @var
	 */
	protected $invRs;

	/**
	 * Summary of invoiceNum
	 * @var
	 */
	protected $invoiceNum;
	/**
	 * Summary of idInvoice
	 * @var
	 */
	protected $idInvoice;
	/**
	 * Summary of amountToPay
	 * @var
	 */
	protected $amountToPay;
	/**
	 * Summary of delegatedInvoiceNumber
	 * @var
	 */
	protected $delegatedInvoiceNumber;
	/**
	 * Summary of delegatedStatus
	 * @var
	 */
	protected $delegatedStatus;
	/**
	 * Summary of tax_exempt
	 * @var
	 */
	protected $tax_exempt;
	/**
	 * Summary of __construct
	 * @param \PDO $dbh
	 * @param mixed $invoiceNumber
	 * @throws \RuntimeException
	 */


	function __construct(\PDO $dbh, $invoiceNumber = '') {
		$this->invRs = new InvoiceRS ();
		$this->idInvoice = 0;
		$this->amountToPay = 0;
		$this->invoiceNum = '';
		$this->delegatedStatus = '';
		$this->delegatedInvoiceNumber = '';
		$this->tax_exempt = 0;

		if ($invoiceNumber != '') {

			$this->invoiceNum = $invoiceNumber;

			$stmt = $dbh->query ( "select i.*, ifnull(di.Invoice_Number, '') as Delegated_Invoice_Number, ifnull(di.Status, '') as Delegated_Invoice_Status
                from invoice i left join invoice di on i.Delegated_Invoice_Id = di.idInvoice
                where i.Invoice_Number = '$invoiceNumber'" );
			$rows = $stmt->fetchAll ( \PDO::FETCH_ASSOC );

			if (count ( $rows ) == 1) {
				$this->loadFromRow ( $rows [0] );
			} else {
				throw new \RuntimeException( 'Invoice number not found: ' . $invoiceNumber );
			}
		}
	}
	/**
	 * Summary of load1stPartyUnpaidInvoices
	 * @param \PDO $dbh
	 * @param mixed $orderNumber
	 * @param mixed $returnId
	 * @return array
	 */
	public static function load1stPartyUnpaidInvoices(\PDO $dbh, $orderNumber, $returnId = 0) {

		$orderNum = str_replace ( "'", '', $orderNumber );

		if ($orderNum == '' || $orderNum == '0') {
			return array ();
		}

		$stmt = $dbh->query ( "SELECT
    i.idInvoice, i.`Invoice_Number`, i.`Balance`, i.`Amount`, IFNULL(n.Name_Full, '') as `Payor`
FROM
    `invoice` i
        LEFT JOIN
    name_volunteer2 nv ON i.Sold_To_Id = nv.idName
        AND nv.Vol_Category = 'Vol_Type'
        AND nv.Vol_Code = 'ba'
		LEFT JOIN
	`name` n ON i.Sold_To_Id = n.idName
WHERE
    i.Order_Number = '$orderNum' AND i.Status = '" . InvoiceStatus::Unpaid . "'
        AND i.Deleted = 0
        AND nv.idName IS NULL;" );

		return $stmt->fetchAll ( \PDO::FETCH_ASSOC );
	}

 /**
  * Summary of loadPrePayUnpaidInvoices
  * @param \PDO $dbh
  * @param mixed $idReservation
  * @param mixed $returnId
  * @return array
  */
	public static function loadPrePayUnpaidInvoices(\PDO $dbh, $idReservation, $returnId = 0) {

	    if ($idReservation < 1) {
	        return array ();
	    }

	    $stmt = $dbh->query ( "SELECT
    i.idInvoice, i.`Invoice_Number`, i.`Balance`, i.`Amount`
FROM
    `invoice` i
		LEFT JOIN
	`invoice_line` il on i.idInvoice = il.Invoice_Id
        LEFT JOIN
    `reservation_invoice_line` ri on il.idInvoice_Line = ri.Invoice_Line_Id

WHERE
    ri.Reservation_Id = $idReservation AND i.Status = '" . InvoiceStatus::Unpaid . "'
        AND i.Deleted = 0 group by i.idInvoice" );

	    return $stmt->fetchAll ( \PDO::FETCH_ASSOC );
	}

	/**
	 * Summary of loadUnpaidInvoices
	 * @param \PDO $dbh
	 * @param mixed $orderNumber
	 * @return array
	 */
	public static function loadUnpaidInvoices(\PDO $dbh, $orderNumber) {
		$invRs = new InvoiceRs ();
		$invRs->Order_Number->setStoredVal ( $orderNumber );
		$invRs->Status->setStoredVal ( InvoiceStatus::Unpaid );
		$invRs->Deleted->setStoredVal ( 0 );

		$rows = EditRS::select ( $dbh, $invRs, array (
				$invRs->Order_Number,
				$invRs->Status,
				$invRs->Deleted
		), 'and', array (
				$invRs->Invoice_Date
		) );

		return $rows;
	}
	/**
	 * Summary of getIdFromInvNum
	 * @param \PDO $dbh
	 * @param mixed $invNum
	 * @return mixed
	 */
	public static function getIdFromInvNum(\PDO $dbh, $invNum) {
		$idInvoice = 0;

		if ($invNum < 1) {
			return $idInvoice;
		}

		$invRs = new InvoiceRs ();
		$invRs->Invoice_Number->setStoredVal ( $invNum );
		$rows = EditRS::select ( $dbh, $invRs, array (
				$invRs->Invoice_Number
		) );

		if (count ( $rows ) == 1) {
			EditRS::loadRow ( $rows [0], $invRs );
			$idInvoice = $invRs->idInvoice->getStoredVal ();
		}

		return $idInvoice;
	}
	/**
	 * Summary of loadInvoice
	 * @param \PDO $dbh
	 * @param mixed $idInvoice
	 * @param mixed $idPayment
	 * @throws \HHK\Exception\RuntimeException
	 * @return void
	 */
	public function loadInvoice(\PDO $dbh, $idInvoice = 0, $idPayment = 0) {
		$this->invoiceNum = '';
		$rows = array ();

		if ($idInvoice > 0) {

			$stmt = $dbh->query ( "select i.*, ifnull(di.Invoice_Number, '') as Delegated_Invoice_Number, ifnull(di.Status, '') as Delegated_Invoice_Status
 from invoice i left join invoice di on i.Delegated_Invoice_Id = di.idInvoice
 where i.idInvoice = '$idInvoice'" );

			$rows = $stmt->fetchAll ( \PDO::FETCH_ASSOC );
		} else if ($idPayment > 0) {

			$stmt = $dbh->query ( "Select i.*, ifnull(di.Invoice_Number, '') as Delegated_Invoice_Number, ifnull(di.Status, '') as Delegated_Invoice_Status " . "from payment_invoice pi join invoice i on pi.Invoice_Id = i.idInvoice " . "left join invoice di on i.Delegated_Invoice_Id = di.idInvoice " . "where pi.Payment_Id = $idPayment" );
			$rows = $stmt->fetchAll ( \PDO::FETCH_ASSOC );
		}

		if (count ( $rows ) == 1) {
			$this->loadFromRow ( $rows [0] );
		} else {
			throw new RuntimeException( 'Invoice Id not found: ' . $idInvoice );
		}
	}
	/**
	 * Summary of loadFromRow
	 * @param mixed $row
	 * @return void
	 */
	protected function loadFromRow($row) {
		$this->invRs = new InvoiceRs ();

		EditRS::loadRow ( $row, $this->invRs );
		$this->invoiceNum = $this->invRs->Invoice_Number->getStoredVal ();
		$this->idInvoice = $this->invRs->idInvoice->getStoredVal ();
		$this->delegatedStatus = $row ['Delegated_Invoice_Status'];
		$this->delegatedInvoiceNumber = $row ['Delegated_Invoice_Number'];
		$this->tax_exempt = $this->invRs->tax_exempt->getStoredVal();
	}

	/**
	 * Summary of getLines
	 * @param \PDO $dbh
	 * @param bool $includeDeletedLines
	 * @return array
	 */
	public function getLines(\PDO $dbh, $includeDeletedLines = false) {
		$lines = array ();

		$stmt = $dbh->query ( "select il.*
from invoice_line il left join invoice_line_type ilt on il.Type_Id = ilt.id
where il.Invoice_Id = " . $this->idInvoice . " order by ilt.Order_Position" );

		while ( $r = $stmt->fetch ( \PDO::FETCH_ASSOC ) ) {

			$ilRs = new InvoiceLineRS ();
			EditRS::loadRow ( $r, $ilRs );

			// Falls through if line is marked as deleted.
			if ($ilRs->Deleted->getStoredVal () != 1 || $includeDeletedLines === false) {
				$iLine = AbstractInvoiceLine::invoiceLineFactory ( $ilRs->Type_Id->getStoredVal () );
				$iLine->loadRecord ( $ilRs );
				$lines [] = $iLine;
			}
		}

		return $lines;
	}
	/**
	 * Summary of addLine
	 * @param \PDO $dbh
	 * @param \HHK\Payment\Invoice\InvoiceLine\AbstractInvoiceLine $invLine
	 * @param mixed $user
	 * @throws \HHK\Exception\RuntimeException
	 * @return void
	 */
	public function addLine(\PDO $dbh, AbstractInvoiceLine $invLine, $user) {
		if ($this->isDeleted ()) {
			throw new RuntimeException( 'Cannot add a line to a deleted Invoice.  ' );
		}

		$invLine->setInvoiceId ( $this->getIdInvoice () );
		$invLine->save ( $dbh );
		$this->updateInvoiceAmount ( $dbh, $user );

	}
	/**
	 * Summary of deleteLine
	 * @param \PDO $dbh
	 * @param mixed $idInvoiceLine
	 * @param mixed $username
	 * @return bool
	 */
	public function deleteLine(\PDO $dbh, $idInvoiceLine, $username) {
		$lines = $this->getLines ( $dbh );
		$result = FALSE;

		foreach ( $lines as $line ) {

			if ($line->getLineId () == $idInvoiceLine) {

				$line->setDeleted ();
				$ct = $line->updateLine ( $dbh );

				if ($ct > 0) {
					$this->updateInvoiceAmount ( $dbh, $username );
					$this->updateInvoiceStatus ( $dbh, $username );

					// Delete any zero amount payments for this Invoice.
					$stmt = $dbh->query ( "select p.idPayment, pi.idPayment_Invoice from payment_invoice pi join payment p on pi.Payment_Id = p.idPayment and p.Amount = 0
where pi.Invoice_Id = " . $this->getIdInvoice () );

					$rows = $stmt->fetchAll ( \PDO::FETCH_NUM );
					if (count ( $rows ) > 0) {
						$idPayment = intval ( $rows [0] [0] );
						$idPayInv = intval ( $rows [0] [1] );

						if ($idPayment > 0) {
							$dbh->exec ( "delete from payment where idPayment = $idPayment" );
							$dbh->exec ( "delete from payment_invoice where idPayment_Invoice = $idPayInv" );
						}
					}

					$result = TRUE;
				}

				break;
			}
		}

		return $result;
	}
	/**
	 * Summary of updateInvoiceLineDates
	 * @param \PDO $dbh
	 * @param mixed $idVisit
	 * @param mixed $startDeltaDays
	 * @return void
	 */
	public static function updateInvoiceLineDates(\PDO $dbh, $idVisit, $startDeltaDays) {
		$uS = Session::getInstance ();

		if ($uS->ShowLodgDates == FALSE || $startDeltaDays == 0) {
			return;
		}

		$lines = array ();
		$itemDesc = '';
		$stmt = $dbh->query ( "Select il.*, it.Description as `Item_Description` from
            invoice i left join invoice_line il on i.idInvoice = il.Invoice_Id
            left join item it on il.Item_Id = it.idItem
    where i.Deleted = 0 and il.Deleted = 0 and il.Item_Id = " . ItemId::Lodging . " and i.Order_Number = '$idVisit'" );

		while ( $r = $stmt->fetch ( \PDO::FETCH_ASSOC ) ) {

			$ilRs = new InvoiceLineRS ();
			EditRS::loadRow ( $r, $ilRs );

			$iLine = AbstractInvoiceLine::invoiceLineFactory ( $ilRs->Type_Id->getStoredVal () );
			$iLine->loadRecord ( $ilRs );
			$lines [] = $iLine;
			$itemDesc = $r ['Item_Description'];
		}

		if (count ( $lines ) > 0) {

			$startIntervalDays = new \DateInterval ( 'P' . abs ( $startDeltaDays ) . 'D' );

			foreach ( $lines as $il ) {

				$stDT = new \DateTime ( $il->getPeriodStart () );
				$edDT = new \DateTime ( $il->getPeriodEnd () );

				if ($startDeltaDays > 0) {
					$stDT->add ( $startIntervalDays );
					$edDT->add ( $startIntervalDays );
				} else {
					$stDT->sub ( $startIntervalDays );
					$edDT->sub ( $startIntervalDays );
				}

				$il->setPeriodStart ( $stDT->format ( 'Y-m-d' ) );
				$il->setPeriodEnd ( $edDT->format ( 'Y-m-d' ) );
				$il->setDescription ( $itemDesc );

				$il->updateLine ( $dbh );
			}
		}
	}

	/**
	 * Summary of createMarkup
	 * @param \PDO $dbh
	 * @return string
	 */
	public function createMarkup(\PDO $dbh) {
		$uS = Session::getInstance ();

		$hospital = '';
		$roomTitle = '';
		$idGuest = 0;
		$idPatient = 0;
		$idAssoc = 0;
		$idHosp = 0;
		$patientName = '';

		// Find Hospital and Room
		$pstmt = $dbh->query ( "select
    hs.idHospital, hs.idAssociation, re.Title, v.idPrimaryGuest, hs.idPatient, n.Name_Full
from
    visit v
	left join
    resource re on v.idResource = re.idResource
        left join
    hospital_stay hs on v.idHospital_stay = hs.idHospital_stay
        left join
    name n on n.idName = hs.idPatient
where
    v.idVisit = " . $this->getOrderNumber () . " and v.Span = " . $this->getSuborderNumber());

		$rows = $pstmt->fetchAll ( \PDO::FETCH_ASSOC );

		if (count ( $rows ) > 0) {
			$idAssoc = $rows [0] ['idAssociation'];
			$idGuest = $rows [0] ['idPrimaryGuest'];
			$idHosp = $rows [0] ['idHospital'];
			$idPatient = $rows [0] ['idPatient'];
			$roomTitle = $rows [0] ['Title'];
			$patientName = $rows [0] ['Name_Full'];
		}

		// Hospital
		if ($idAssoc > 0 && isset ( $uS->guestLookups [GLTableNames::Hospital] [$idAssoc] ) && $uS->guestLookups [GLTableNames::Hospital] [$idAssoc] [1] != '(None)') {
			$hospital .= $uS->guestLookups [GLTableNames::Hospital] [$idAssoc] [1] . ' / ';
		}
		if ($idHosp > 0 && isset ( $uS->guestLookups [GLTableNames::Hospital] [$idHosp] )) {
			$hospital .= $uS->guestLookups [GLTableNames::Hospital] [$idHosp] [1];
		}

		// Items
		$tbl = new HTMLTable ();

		$tbl->addHeaderTr ( HTMLTable::makeTh ( 'Room' ) . HTMLTable::makeTh ( 'Item' ) . HTMLTable::makeTh ( 'Amount' ) );

		foreach ( $this->getLines ( $dbh ) as $line ) {
			$tbl->addBodyTr ( HTMLTable::makeTd ( $roomTitle ) . HTMLTable::makeTd ( $line->getDescription () ) . HTMLTable::makeTd ( number_format ( $line->getAmount (), 2 ), array (
					'class' => 'tdlabel'
			) ) );
		}

		// totals
		$tbl->addBodyTr ( HTMLTable::makeTd ( "Total:", array (
				'class' => 'tdlabel hhk-tdTotals',
				'colspan' => '2'
		) ) . HTMLTable::makeTd ( '$' . number_format ( $this->getAmount (), 2 ), array (
				'class' => 'hhk-tdTotals tdlabel'
		) ) );
		$tbl->addBodyTr ( HTMLTable::makeTd ( "Previous Payments:", array (
				'class' => 'tdlabel',
				'colspan' => '2'
		) ) . HTMLTable::makeTd ( number_format ( ($this->getAmount () - $this->getBalance ()), 2 ), array (
				'class' => 'hhk-tdTotals tdlabel'
		) ) );

		if ($this->getDelegatedStatus () == InvoiceStatus::Paid) {
			$tbl->addBodyTr ( HTMLTable::makeTd ( "Balance Due:", array (
					'class' => 'tdlabel',
					'colspan' => '2'
			) ) . HTMLTable::makeTd ( '$0.00', array (
					'class' => 'hhk-tdTotals tdlabel'
			) ) );
		} else {
			$tbl->addBodyTr ( HTMLTable::makeTd ( "Balance Due:", array (
					'class' => 'tdlabel',
					'colspan' => '2'
			) ) . HTMLTable::makeTd ( '$' . number_format ( $this->getBalance (), 2 ), array (
					'class' => 'hhk-tdTotals tdlabel'
			) ) );
		}

		// House Icon and address
		$rec = Receipt::getHouseIconMarkup ();
		$rec .= HTMLContainer::generateMarkup ( 'div', Receipt::getAddressTable ( $dbh, $uS->sId ), array (
				'style' => 'float:left;margin-bottom:10px;margin-left:20px;'
		) );

		// Invoice dates
		$invDate = new \DateTime ( $this->getDate () );
		$invDateString = $invDate->format ( 'M jS, Y' );
		$invDate->add ( new \DateInterval ( 'P' . $uS->InvoiceTerm . 'D' ) );

		$invTbl = new HTMLTable ();

		if ($this->isDeleted ()) {
			$rec .= HTMLContainer::generateMarkup ( 'h2', 'INVOICE - DELETED', array (
					'style' => 'clear:both;margin-bottom:10px;color:darkred;'
			) );
		} else if ($this->getStatus () == InvoiceStatus::Carried) {
			$rec .= HTMLContainer::generateMarkup ( 'h2', 'INVOICE - (Delegated To Invoice #' . $this->getDelegatedInvoiceNumber () . ')', array (
					'style' => 'clear:both;margin-bottom:10px;color:blue;'
			) );
		} else {
			$rec .= HTMLContainer::generateMarkup ( 'h2', 'INVOICE', array (
					'style' => 'clear:both;margin-bottom:10px;'
			) );
		}

		$invTbl->addBodyTr ( HTMLTable::makeTd ( 'INVOICE #:', array (
				'class' => 'tdlabel'
		) ) . HTMLTable::makeTd ( $this->getInvoiceNumber () ) );

		$invTbl->addBodyTr ( HTMLTable::makeTd ( 'DATE:', array (
				'class' => 'tdlabel'
		) ) . HTMLTable::makeTd ( $invDateString ) );

		if ($uS->InvoiceTerm > 0) {
			$invTbl->addBodyTr ( HTMLTable::makeTd ( 'TERMS NET:', array (
					'class' => 'tdlabel'
			) ) . HTMLTable::makeTd ( $uS->InvoiceTerm ) );
			$invTbl->addBodyTr ( HTMLTable::makeTd ( 'DUE DATE:', array (
					'class' => 'tdlabel'
			) ) . HTMLTable::makeTd ( $invDate->format ( 'M jS, Y' ) ) );
		}

		if ($this->getDelegatedStatus () == InvoiceStatus::Paid) {
			$invTbl->addBodyTr ( HTMLTable::makeTd ( 'BALANCE DUE:', array (
					'class' => 'tdlabel'
			) ) . HTMLTable::makeTd ( '$0.00' ) );
		} else {
			$invTbl->addBodyTr ( HTMLTable::makeTd ( 'BALANCE DUE:', array (
					'class' => 'tdlabel'
			) ) . HTMLTable::makeTd ( '$' . number_format ( $this->getBalance (), 2 ) ) );
		}

		$rec .= $invTbl->generateMarkup ( array (
				'class' => 'hhk-tdbox-noborder',
				'style' => 'float:left;'
		) );

		$billTbl = new HTMLTable ();
		$billTbl->addBodyTr ( HTMLTable::makeTd ( HTMLContainer::generateMarkup('h4', 'Bill To')));
		$billTbl->addBodyTr ( HTMLTable::makeTd ( $this->getBillToAddress ( $dbh, $this->getSoldToId () )->generateMarkup () ) );
		$rec .= $billTbl->generateMarkup ( array (
				'style' => 'float:right; margin-right:40px;'
		) );

		$rec .= HTMLContainer::generateMarkup ( 'div', '', array (
				'style' => 'clear:both;'
		) );

		// Patient and guest
		if ($idPatient != $idGuest && $patientName != '') {
			$rec .= HTMLContainer::generateMarkup ( 'h4', Labels::getString('memberType', 'patient', 'Patient')  . ':  ' . $patientName, array (
					'style' => 'margin-top:10px;'
			) );
		}

		$rec .= HTMLContainer::generateMarkup ( 'h4', Labels::getString('memberType', 'primaryGuest', 'Primary Guest'), array (
				'style' => 'margin-top:10px;'
		) );
		$rec .= $this->getGuestAddress ( $dbh, $idGuest );

		$rec .= HTMLContainer::generateMarkup ( 'h4', 'Hospital:  ' . $hospital, array (
				'style' => 'margin-bottom:10px;margin-top:10px;'
		) );

		$rec .= HTMLContainer::generateMarkup ( 'div', $tbl->generateMarkup (), array (
				'class' => 'hhk-tdbox'
		) );

		if ($this->getInvoiceNotes () != '') {
			$rec .= HTMLContainer::generateMarkup ( 'p', $this->getInvoiceNotes (), array (
					'style' => 'margin-top:1em;'
			) );
		}

		if ($this->isDeleted ()) {
			$rec = HTMLContainer::generateMarkup ( 'div', $rec, array (
					'style' => 'background-color:pink;'
			) );
		}

		return $rec;
	}

	public function createPDFMarkup(\PDO $dbh) {
		$uS = Session::getInstance ();

		$hospital = '';
		$roomTitle = '';
		$idGuest = 0;
		$idPatient = 0;
		$idAssoc = 0;
		$idHosp = 0;
		$patientName = '';

		// Find Hospital and Room
		$pstmt = $dbh->query ( "select
    hs.idHospital, hs.idAssociation, re.Title, v.idPrimaryGuest, hs.idPatient, n.Name_Full
from
    visit v
	left join
    resource re on v.idResource = re.idResource
        left join
    hospital_stay hs on v.idHospital_stay = hs.idHospital_stay
        left join
    name n on n.idName = hs.idPatient
where
    v.idVisit = " . $this->getOrderNumber () . " and v.Span = " . $this->getSuborderNumber());

		$rows = $pstmt->fetchAll ( \PDO::FETCH_ASSOC );

		if (count ( $rows ) > 0) {
			$idAssoc = $rows [0] ['idAssociation'];
			$idGuest = $rows [0] ['idPrimaryGuest'];
			$idHosp = $rows [0] ['idHospital'];
			$idPatient = $rows [0] ['idPatient'];
			$roomTitle = $rows [0] ['Title'];
			$patientName = $rows [0] ['Name_Full'];
		}

		// Hospital
		if ($idAssoc > 0 && isset ( $uS->guestLookups [GLTableNames::Hospital] [$idAssoc] ) && $uS->guestLookups [GLTableNames::Hospital] [$idAssoc] [1] != '(None)') {
			$hospital .= $uS->guestLookups [GLTableNames::Hospital] [$idAssoc] [1] . ' / ';
		}
		if ($idHosp > 0 && isset ( $uS->guestLookups [GLTableNames::Hospital] [$idHosp] )) {
			$hospital .= $uS->guestLookups [GLTableNames::Hospital] [$idHosp] [1];
		}

		// Items
		$tbl = new HTMLTable ();

		$tbl->addHeaderTr ( HTMLTable::makeTh ( 'Room' ) . HTMLTable::makeTh ( 'Item' ) . HTMLTable::makeTh ( 'Amount' ) );

		foreach ( $this->getLines ( $dbh ) as $line ) {
			$tbl->addBodyTr ( HTMLTable::makeTd ( $roomTitle , ['class'=>'invLineRoom']) . HTMLTable::makeTd ( $line->getDescription () , ['class'=>'invLineDesc']) . HTMLTable::makeTd ( number_format ( $line->getAmount (), 2 ), array (
					'class' => 'tdlabel invLineAmt'
			) ) );
		}

		// totals
		$tbl->addFooterTr ( HTMLTable::makeTd ( "Total:", array (
				'class' => 'tdlabel hhk-tdTotals',
				'colspan' => '2'
		) ) . HTMLTable::makeTd ( '$' . number_format ( $this->getAmount (), 2 ), array (
				'class' => 'hhk-tdTotals tdlabel'
		) ) );
		$tbl->addFooterTr ( HTMLTable::makeTd ( "Previous Payments:", array (
				'class' => 'tdlabel',
				'colspan' => '2'
		) ) . HTMLTable::makeTd ( number_format ( ($this->getAmount () - $this->getBalance ()), 2 ), array (
				'class' => 'hhk-tdTotals tdlabel'
		) ) );

		if ($this->getDelegatedStatus () == InvoiceStatus::Paid) {
			$tbl->addFooterTr ( HTMLTable::makeTd ( "Balance Due:", array (
					'class' => 'tdlabel',
					'colspan' => '2'
			) ) . HTMLTable::makeTd ( '$0.00', array (
					'class' => 'hhk-tdTotals tdlabel'
			) ) );
		} else {
			$tbl->addFooterTr ( HTMLTable::makeTd ( "Balance Due:", array (
					'class' => 'tdlabel',
					'colspan' => '2'
			) ) . HTMLTable::makeTd ( '$' . number_format ( $this->getBalance (), 2 ), array (
					'class' => 'hhk-tdTotals tdlabel'
			) ) );
		}

		// House Icon and address
		$headerTbl = new HTMLTable();
		$headerTbl->addBodyTr(
			HTMLTable::makeTd(Receipt::getHouseIconMarkup (), ['style'=>'width: 30%;']) .
			HTMLTable::makeTd(Receipt::getAddressTable ( $dbh, $uS->sId ))
		);
		$rec = $headerTbl->generateMarkup(['class'=>'mb-3 fullWidth']);

		// Invoice dates
		$invDate = new \DateTime ( $this->getDate () );
		$invDateString = $invDate->format ( 'M jS, Y' );
		$invDate->add ( new \DateInterval ( 'P' . $uS->InvoiceTerm . 'D' ) );

		$invTbl = new HTMLTable ();

		if ($this->isDeleted ()) {
			$rec .= HTMLContainer::generateMarkup ( 'h2', 'INVOICE - DELETED', array (
					'class'=>'mb-3', 'style' => 'color:darkred;'
			) );
		} else if ($this->getStatus () == InvoiceStatus::Carried) {
			$rec .= HTMLContainer::generateMarkup ( 'h2', 'INVOICE - (Delegated To Invoice #' . $this->getDelegatedInvoiceNumber () . ')', array (
					'class'=>'mb-3', 'style' => 'color:blue;'
			) );
		} else {
			$rec .= HTMLContainer::generateMarkup ( 'h2', 'INVOICE', array (
					'class' => 'mb-3'
			) );
		}

		$invTbl->addBodyTr ( HTMLTable::makeTd ( 'INVOICE #:', array (
				'class' => 'tdlabel'
		) ) . HTMLTable::makeTd ( $this->getInvoiceNumber () ) );

		$invTbl->addBodyTr ( HTMLTable::makeTd ( 'DATE:', array (
				'class' => 'tdlabel'
		) ) . HTMLTable::makeTd ( $invDateString ) );

		if ($uS->InvoiceTerm > 0) {
			$invTbl->addBodyTr ( HTMLTable::makeTd ( 'TERMS NET:', array (
					'class' => 'tdlabel'
			) ) . HTMLTable::makeTd ( $uS->InvoiceTerm ) );
			$invTbl->addBodyTr ( HTMLTable::makeTd ( 'DUE DATE:', array (
					'class' => 'tdlabel'
			) ) . HTMLTable::makeTd ( $invDate->format ( 'M jS, Y' ) ) );
		}

		if ($this->getDelegatedStatus () == InvoiceStatus::Paid) {
			$invTbl->addBodyTr ( HTMLTable::makeTd ( 'BALANCE DUE:', array (
					'class' => 'tdlabel'
			) ) . HTMLTable::makeTd ( '$0.00' ) );
		} else {
			$invTbl->addBodyTr ( HTMLTable::makeTd ( 'BALANCE DUE:', array (
					'class' => 'tdlabel'
			) ) . HTMLTable::makeTd ( '$' . number_format ( $this->getBalance (), 2 ) ) );
		}

		$billTbl = new HTMLTable ();
		$billTbl->addBodyTr ( HTMLTable::makeTd ( HTMLContainer::generateMarkup('h4', 'Bill To')));
		$billTbl->addBodyTr ( HTMLTable::makeTd ( $this->getBillToAddress ( $dbh, $this->getSoldToId () )->generateMarkup () ) );


		$rec .= (new HTMLTable())->addBodyTr(
			HTMLTable::makeTd($invTbl->generateMarkup(['class'=>'hhk-tdbox-noborder'])) .
			HTMLTable::makeTd($billTbl->generateMarkup())
		)->generateMarkup(['class'=>'mb-3 fullWidth']);

		// Patient and guest
		if ($idPatient != $idGuest && $patientName != '') {
			$rec .= HTMLContainer::generateMarkup ( 'h4', Labels::getString('memberType', 'patient', 'Patient')  . ':  ' . $patientName, array (
					'class' => 'mt-3'
			) );
		}

		$rec .= HTMLContainer::generateMarkup ( 'h4', Labels::getString('memberType', 'primaryGuest', 'Primary Guest'), array (
				'class' => 'mt-3'
		) );
		$rec .= $this->getGuestAddress ( $dbh, $idGuest );

		$rec .= HTMLContainer::generateMarkup ( 'h4', 'Hospital:  ' . $hospital, array (
				'class' => 'my-3'
		) );

		//invoice table
		$rec .= HTMLContainer::generateMarkup ( 'div', $tbl->generateMarkup(['width'=>'100%']), array (
				'class' => 'hhk-tdbox invoiceItemTbl'
		) );

		if ($this->getInvoiceNotes () != '') {
			$rec .= HTMLContainer::generateMarkup("div", 
				HTMLContainer::generateMarkup("h4", "Notes")
				. HTMLContainer::generateMarkup ( 'p', $this->getInvoiceNotes ())
			, ['class' => 'my-3']);
		}

		if ($this->isDeleted ()) {
			$rec = HTMLContainer::generateMarkup ( 'div', $rec, array (
					'style' => 'background-color:pink;'
			) );
		}

		return $rec;
	}

	/**
	 * Generate PDF of Invoice
	 * @param \PDO $dbh
	 * @param bool $download When true: initialize download; false: return string of pdf content
	 * @return string|void
	 */
	public function makePDF(\PDO $dbh, bool $download = false)
	{
		$stmtMarkup = $this->createPDFMarkup($dbh);

		$mpdf = new Mpdf(['tempDir' => sys_get_temp_dir() . "/mpdf", 'shrink-tables-to-fit'=>0]);
		$mpdf->showImageErrors = true;
		$mpdf->WriteHTML(
			'<html><head>' . HOUSE_CSS . INVOICE_CSS . '</head><body><div class="PrintArea">' . $stmtMarkup . '</div></body></html>'
		);

		if($download == true){
			$mpdf->OutputHttpDownload("Invoice.pdf");
		} else {
			return $mpdf->Output('', 'S');
		}
	}

	/**
	 * Summary of getGuestAddress
	 * @param \PDO $dbh
	 * @param mixed $idName
	 * @return string
	 */
	public static function getGuestAddress(\PDO $dbh, $idName) {
		$mkup = '';

		if ($idName > 0) {

			$stmt = $dbh->query ( "select
    ifnull(a.Address_1,'') as Address_1,
    ifnull(a.Address_2,'') as Address_2,
    ifnull(a.City,'') as City,
    ifnull(a.State_Province,'') as State,
    ifnull(a.Postal_Code,'') as Zip,
    ifnull(p.Phone_Num,'') as Phone_Num,
    ifnull(e.Email,'') as Email,
	n.Name_Full
from
	name n left join
    name_address a ON n.idName = a.idName  and a.Purpose = n.Preferred_Mail_Address
        left join
    name_phone p ON n.idName = p.idName and n.Preferred_Phone = p.Phone_Code
        left join
    name_email e ON n.idName = e.idName and n.Preferred_Email = e.Purpose
where
    n.idName = $idName" );

			$rows = $stmt->fetchAll ( \PDO::FETCH_ASSOC );

			if (count ( $rows ) == 1) {

				$street = $rows [0] ['Address_1'];
				$city = '';

				if ($street != '') {

					if ($rows [0] ['Address_2'] != '') {
						$street .= ', ' . $rows [0] ['Address_2'];
					}

					$city = $rows [0] ['City'] . ', ' . $rows [0] ['State'] . '  ' . $rows [0] ['Zip'];
				}

				$adrTbl = new HTMLTable ();

				$adrTbl->addBodyTr ( HTMLTable::makeTd ( $rows [0] ['Name_Full'] ) );
				$adrTbl->addBodyTr ( HTMLTable::makeTd ( $street ) );
				$adrTbl->addBodyTr ( HTMLTable::makeTd ( $city ) );

				if ($rows [0] ['Phone_Num'] != '') {
					$adrTbl->addBodyTr ( HTMLTable::makeTd ( 'Phone: ' . $rows [0] ['Phone_Num'] ) );
				}
				if ($rows [0] ['Email'] != '') {
					$adrTbl->addBodyTr ( HTMLTable::makeTd ( 'Email: ' . $rows [0] ['Email'] ) );
				}

				$mkup = $adrTbl->generateMarkup ();
			}
		}

		return $mkup;
	}

	public function makeEmailTbl($emFrom = "", $emSubject = "", $emAddrs = [], $emBody = "", $invNum = null){
        $emtableMarkup = "";
        $emTbl = new HTMLTable();

        $emTbl->addBodyTr(
			HTMLTable::makeTd('From', ['class'=>"tdlabel", 'style'=>"width: 110px"]) . 
			HTMLTable::makeTd($emFrom)
		);
		$emTbl->addBodyTr(
			HTMLTable::makeTd('Subject', ['class'=>"tdlabel", 'style'=>"width: 110px"]) . 
			HTMLTable::makeTd(HTMLInput::generateMarkup($emSubject, array('name' => 'txtSubject')))
		);
        $emTbl->addBodyTr(
			HTMLTable::makeTd('To', ['class'=>"tdlabel"]) . 
            HTMLTable::makeTd(HTMLInput::generateMarkup(implode(", ", $emAddrs), array('name' => 'txtEmail'))
            . ($invNum !== null ? HTMLInput::generateMarkup($invNum, array('name' => 'hdninvnum', 'type' => 'hidden')): ""))
		);
        $emTbl->addBodyTr(
			HTMLTable::makeTd('Body', ['class'=>"tdlabel"]) . 
            HTMLTable::makeTd(HTMLContainer::generateMarkup("textarea", $emBody, array('name' => 'txtBody', 'class' => 'hhk-autosize')))
		);
		$emTbl->addBodyTr(
			HTMLTable::makeTd('Attachment', ['class'=>"tdlabel"]) . 
			HTMLTable::makeTd(HTMLContainer::generateMarkup("a", 'Invoice.pdf <i class="ml-1 bi bi-cloud-arrow-down-fill"></i>', array('href' => 'ShowInvoice.php?invnum='.$invNum.'&pdfDownload', 'class' => 'hhk-autosize')))
		);

		if($this->invRs->EmailDate->getStoredVal()){
			$emailDT = new \DateTime($this->invRs->EmailDate->getStoredVal());
			$emTbl->addBodyTr(
				HTMLTable::makeTd('Last Sent', ['class'=>"tdlabel"]) . 
				HTMLTable::makeTd($emailDT->format("M j, Y g:i a"))
			);
		}

		$emtableMarkup .= HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup("h4", 'Email Invoice'), ['class' => "ui-widget ui-widget-header align-center ui-corner-top"]);

        $emtableMarkup .= HTMLContainer::generateMarkup("div", 
			$emTbl->generateMarkup(array("class"=>"emTbl mb-2")) . 
			HTMLContainer::generateMarkup("div", 
				HTMLContainer::generateMarkup('button', 'Send <i class="ml-2 bi bi-send-fill"></i>', array('style'=>'font-size: 0.9em;', 'class'=>'ui-button ui-corner-all ui-widget', 'name' => 'btnEmail', 'type' => 'submit')), ['class'=>'align-center']), ["class"=>"p-2 hhk-tdbox mb-3 ui-widget ui-widget-content ui-corner-bottom hhk-visitdialog"]);

        $emtableMarkup .= HTMLContainer::generateMarkup("div",
			HTMLInput::generateMarkup('Print', ["type" => "button", "id" => "btnPrint", "class" => "ui-button ui-corner-all ui-widget mr-3"])
        	//. HTMLInput::generateMarkup("Download MS Word", ["type"=>"submit", "name"=>"btnWord", "id"=>"btnWord", "class"=>"ui-button ui-corner-all ui-widget mr-3"])
			,
		["class"=>'mb-3']);

        return $emtableMarkup;
    }

	/**
	 * Summary of getBillToEmail
	 * @param \PDO $dbh
	 * @return string
	 */
	public function getBillToEmail(\PDO $dbh):string{
		$idName = $this->getSoldToId();
		if ($idName > 0) {

			$stmt = $dbh->query("select ifnull(e.Email, '') from name n left join name_email e ON n.idName = e.idName and n.Preferred_Email = e.Purpose where n.idName = $idName");

			$rows = $stmt->fetchAll ( \PDO::FETCH_NUM );

			if (count($rows) == 1) {
				return $rows[0][0];
			}
		};
		return "";
	}

	/**
	 * Get billTo info from `name` table
	 * @param \PDO $dbh
	 * @return array
	 */
	public function getBillTo(\PDO $dbh):array{
		$idName = $this->getSoldToId();
		if ($idName > 0) {

			$stmt = $dbh->query("select n.*, ifnull(e.Email, '') from name n left join name_email e ON n.idName = e.idName and n.Preferred_Email = e.Purpose where n.idName = $idName");

			$rows = $stmt->fetchAll ( \PDO::FETCH_ASSOC );

			if (count($rows) == 1) {
				return $rows[0];
			}
		};
		return [];
	}

	/**
	 * get name, phone and email of billing contact
	 * @param \PDO $dbh
	 * @param int $idName
	 * @return array
	 */
	public static function getBillToName(\PDO $dbh, int $idName){
		if ($idName > 0) {

			$stmt = $dbh->query("select
    ifnull(n.Company,'') as Company,
    ifnull(p.Phone_Num,'') as Phone_Num,
    ifnull(e.Email,'') as Email,
	n1.Name_First,
	n1.Name_Last,
	n1.Company as Company_Name
from
	name n1 left join
    name n on n1.Company_Id = n.idName
        left join
    name_phone p ON n1.idName = p.idName and n1.Preferred_Phone = p.Phone_Code
        left join
    name_email e ON n1.idName = e.idName and n1.Preferred_Email = e.Purpose
where
    n1.idName = $idName");

			$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

			if (count($rows) == 1) {

				return $rows[0];
			}
		}
		return [];
	}

	/**
	 * Summary of getBillToAddress
	 * @param \PDO $dbh
	 * @param mixed $idName
	 * @return HTMLTable
	 */
	public static function getBillToAddress(\PDO $dbh, $idName) {
		$adrTbl = new HTMLTable ();

		if ($idName > 0) {

			$stmt = $dbh->query ( "select
    ifnull(n.Company,'') as Company,
    ifnull(a.Address_1,'') as Address_1,
    ifnull(a.Address_2,'') as Address_2,
    ifnull(a.City,'') as City,
    ifnull(a.State_Province,'') as State,
    ifnull(a.Postal_Code,'') as Zip,
    ifnull(ab.Address_1,'') as Billing_1,
    ifnull(ab.Address_2,'') as Billing_2,
    ifnull(ab.City,'') as Billing_City,
    ifnull(ab.State_Province,'') as Billing_State,
    ifnull(ab.Postal_Code,'') as Billing_Zip,
    ifnull(ac.Address_1,'') as Company_1,
    ifnull(ac.Address_2,'') as Company_2,
    ifnull(ac.City,'') as Company_City,
    ifnull(ac.State_Province,'') as Company_State,
    ifnull(ac.Postal_Code,'') as Company_Zip,
    ifnull(p.Phone_Num,'') as Phone_Num,
    ifnull(e.Email,'') as Email,
	n1.Name_Full,
	n1.Company as Company_Name
from
	name n1 left join
    name n on n1.Company_Id = n.idName
        left join
    name_address ab ON n.idName = ab.idName  and ab.Purpose = 'b'
        left join
    name_address a ON n.idName = a.idName  and a.Purpose = n.Preferred_Mail_Address
        left join
    name_address ac ON n1.idName = ac.idName  and ac.Purpose = n1.Preferred_Mail_Address
        left join
    name_phone p ON n1.idName = p.idName and n1.Preferred_Phone = p.Phone_Code
        left join
    name_email e ON n1.idName = e.idName and n1.Preferred_Email = e.Purpose
where
    n1.idName = $idName" );

			$rows = $stmt->fetchAll ( \PDO::FETCH_ASSOC );

			if (count ( $rows ) == 1) {

				if ($rows [0] ['Billing_1'] != '') {
					// Use billing address
					$street = $rows [0] ['Billing_1'];

					if ($rows [0] ['Billing_2'] != '') {
						$street .= ', ' . $rows [0] ['Billing_2'];
					}

					$city = $rows [0] ['Billing_City'];
					$state = $rows [0] ['Billing_State'];
					$zip = $rows [0] ['Billing_Zip'];
					$careOf = $rows [0] ['Name_Full'];
					$company = $rows [0] ['Company'];
				} else if ($rows [0] ['Address_1'] != '') {

					$street = $rows [0] ['Address_1'];

					if ($rows [0] ['Address_2'] != '') {
						$street .= ', ' . $rows [0] ['Address_2'];
					}

					$city = $rows [0] ['City'];
					$state = $rows [0] ['State'];
					$zip = $rows [0] ['Zip'];
					$careOf = $rows [0] ['Name_Full'];
					$company = $rows [0] ['Company'];
				} else {

					$street = $rows [0] ['Company_1'];

					if ($rows [0] ['Company_2'] != '') {
						$street .= ', ' . $rows [0] ['Company_2'];
					}

					$city = $rows [0] ['Company_City'];
					$state = $rows [0] ['Company_State'];
					$zip = $rows [0] ['Company_Zip'];
					$careOf = '';
					$company = $rows [0] ['Company_Name'];

					if ($company == '') {
						$company = $rows [0] ['Name_Full'];
					}
				}

				$adrTbl->addBodyTr ( HTMLTable::makeTd ( $company ) );

				if ($careOf != '') {
					$adrTbl->addBodyTr ( HTMLTable::makeTd ( 'c/o: ' . $careOf ) );
				}

				$cityLine = "";
				if($city != ""){
					$cityLine = $city . ', ';
				}
				$cityLine .= $state . ' ' . $zip;

				$adrTbl->addBodyTr ( HTMLTable::makeTd ( $street ) );
				$adrTbl->addBodyTr ( HTMLTable::makeTd ( $cityLine ) );
				if ($rows [0] ['Phone_Num'] != '') {
					$adrTbl->addBodyTr ( HTMLTable::makeTd ( 'Phone: ' . $rows [0] ['Phone_Num'] ) );
				}
				if ($rows [0] ['Email'] != '') {
					$adrTbl->addBodyTr ( HTMLTable::makeTd ( 'Email: ' . $rows [0] ['Email'] ) );
				}
			}
		}

		return $adrTbl;
	}
 /**
  * Summary of delegateTo - Delegate (carry) $this invoice to the $delegatedToInvoice.
  * @param \PDO $dbh
  * @param \HHK\Payment\Invoice\Invoice $delegatedToInvoice
  * @param mixed $user 
  * @return bool
  */
	public function delegateTo(\PDO $dbh, Invoice $delegatedToInvoice, $user) {

		// only delegate to invoices with the same order number
		if ($this->getOrderNumber () != $delegatedToInvoice->getOrderNumber ()) {
			return FALSE;
		}

		// set this invoice as delegated
		if ($this->getStatus () == InvoiceStatus::Unpaid) {

			// update delegated invoice
			//
			$carriedAmt = $delegatedToInvoice->invRs->Carried_Amount->getStoredVal ();
			$delegatedToInvoice->invRs->Carried_Amount->setNewVal ( $carriedAmt + $this->getBalance () );

			$invItem = new Item ( $dbh, ItemId::InvoiceDue, $this->getBalance () );
			$invLine = new InvoiceInvoiceLine ();
			$invLine->createNewLine ( $invItem, 1, $this->getInvoiceNumber () );

			$delegatedToInvoice->addLine ( $dbh, $invLine, $user );

			// Update this invoice to Carried.
			//
			$this->invRs->Delegated_Invoice_Id->setNewVal ( $delegatedToInvoice->idInvoice );
			$this->invRs->Status->setNewVal ( InvoiceStatus::Carried );
			$this->invRs->Last_Updated->setNewVal ( date ( 'Y-m-d H:i:s' ) );
			$this->invRs->Updated_By->setNewVal ( $user );

			EditRS::update ( $dbh, $this->invRs, array (
					$this->invRs->Invoice_Number
			) );
			EditRS::updateStoredVals ( $this->invRs );

			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * Sums invoice line amounts and updates invoice amount total and balance
	 *
	 * @param \PDO $dbh
	 * @param string $user
	 */
	protected function updateInvoiceAmount(\PDO $dbh, $user) {
		$stmt = $dbh->query ( "Select sum(Amount) from invoice_line where Deleted = 0 and Invoice_Id = " . $this->idInvoice );
		$rows = $stmt->fetchAll ();

		if (count ( $rows ) == 1) {

			$newAmount = $rows [0] [0];
			$oldAmount = $this->invRs->Amount->getStoredVal ();
			$oldBalance = $this->invRs->Balance->getStoredVal ();

			$difAmount = $newAmount - $oldAmount;
			$newBalance = $oldBalance + $difAmount;

			$this->invRs->Amount->setNewVal ( $newAmount );
			$this->invRs->Balance->setNewVal ( $newBalance );
			$this->invRs->Last_Updated->setNewVal ( date ( 'Y-m-d H:i:s' ) );
			$this->invRs->Updated_By->setNewVal ( $user );

			EditRS::update ( $dbh, $this->invRs, array (
					$this->invRs->Invoice_Number
			) );

			EditRS::updateStoredVals ( $this->invRs );
		}
	}
	/**
	 * Summary of updateInvoiceStatus
	 * @param \PDO $dbh
	 * @param mixed $username
	 * @return void
	 */
	public function updateInvoiceStatus(\PDO $dbh, $username) {
		if ($this->invRs->Amount->getStoredVal () != 0 && $this->invRs->Balance->getStoredVal () == 0) {
			$this->invRs->Status->setNewVal ( InvoiceStatus::Paid );
		} else {
			$this->invRs->Status->setNewVal ( InvoiceStatus::Unpaid );
		}

		$this->invRs->Last_Updated->setNewVal ( date ( 'Y-m-d H:i:s' ) );
		$this->invRs->Updated_By->setNewVal ( $username );

		EditRS::update ( $dbh, $this->invRs, array (
				$this->invRs->Invoice_Number
		) );

		EditRS::updateStoredVals ( $this->invRs );
	}
	/**
	 * Summary of newInvoice
	 * @param \PDO $dbh
	 * @param mixed $amount
	 * @param mixed $soldToId
	 * @param mixed $idGroup
	 * @param mixed $orderNumber
	 * @param mixed $suborderNumber
	 * @param mixed $notes
	 * @param mixed $invoiceDate
	 * @param mixed $username
	 * @param mixed $description
	 * @param mixed $tax_exempt
	 * @return int|mixed
	 */
	public function newInvoice(\PDO $dbh, $amount, $soldToId, $idGroup, $orderNumber, $suborderNumber, $notes, $invoiceDate, $username, $description = '', $tax_exempt = 0) {
		$invRs = new InvoiceRs ();
		$invRs->Amount->setNewVal ( $amount );
		$invRs->Balance->setNewVal ( $amount );
		$invRs->Invoice_Number->setNewVal ( self::createNewInvoiceNumber ( $dbh ) );
		$invRs->Sold_To_Id->setNewVal ( $soldToId );
		$invRs->idGroup->setNewVal ( $idGroup );
		$invRs->Order_Number->setNewVal ( $orderNumber );
		$invRs->Suborder_Number->setNewVal ( $suborderNumber );
		$invRs->Notes->setNewVal ( $notes );
		$invRs->Invoice_Date->setNewVal ( $invoiceDate );
		$invRs->Status->setNewVal ( InvoiceStatus::Unpaid );
		$invRs->Description->setNewVal ( $description );
        $invRs->tax_exempt->setNewVal($tax_exempt);

		$invRs->Updated_By->setNewVal ( $username );
		$invRs->Last_Updated->setNewVal ( date ( 'Y-m-d H:i:s' ) );

		$this->idInvoice = EditRS::insert ( $dbh, $invRs );
		$invRs->idInvoice->setNewVal ( $this->idInvoice );

		EditRS::updateStoredVals ( $invRs );

		$this->invRs = $invRs;
		$this->invoiceNum = $invRs->Invoice_Number->getStoredVal ();
		$this->delegatedStatus = '';
		$this->delegatedInvoiceNumber = '';

		return $this->idInvoice;
	}
	/**
	 * Summary of setBillDate
	 * @param \PDO $dbh
	 * @param mixed $billDT
	 * @param mixed $user
	 * @param mixed $notes
	 * @return bool
	 */
	public function setBillDate(\PDO $dbh, $billDT, $user, $notes) {
		if (is_null ( $billDT ) === FALSE) {
			$this->invRs->BillDate->setNewVal ( $billDT->format ( 'Y-m-d' ) );
			$this->invRs->BillStatus->setNewVal ( BillStatus::Billed );
		}

		if ($notes != '') {
			$this->invRs->Notes->setNewVal ( $notes );
		}

		if ($this->idInvoice > 0) {

			$this->invRs->Last_Updated->setNewVal ( date ( 'Y-m-d H:i:s' ) );
			$this->invRs->Updated_By->setNewVal ( $user );

			EditRS::update ( $dbh, $this->invRs, array (
					$this->invRs->Invoice_Number
			) );

			EditRS::updateStoredVals ( $this->invRs );

			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Summary of setEmailDate
	 * @param \PDO $dbh
	 * @param mixed $billDT
	 * @param mixed $user
	 * @return bool
	 */
	public function setEmailDate(\PDO $dbh, \DateTimeInterface $emailDT, $user) {
		$this->invRs->EmailDate->setNewVal ( $emailDT->format ( 'Y-m-d H:i:s' ) );

		if ($this->idInvoice > 0) {

			$this->invRs->Last_Updated->setNewVal ( date ( 'Y-m-d H:i:s' ) );
			$this->invRs->Updated_By->setNewVal ( $user );

			EditRS::update ( $dbh, $this->invRs, array (
					$this->invRs->Invoice_Number
			) );

			EditRS::updateStoredVals ( $this->invRs );

			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * Summary of setAmountToPay
	 * @param mixed $amt
	 * @return static
	 */
	public function setAmountToPay($amt) {
		$this->amountToPay = $amt;
		return $this;
	}
	/**
	 * Summary of getAmountToPay
	 * @return int|mixed
	 */
	public function getAmountToPay() {
		return $this->amountToPay;
	}

	// Call on payment only.
	/**
	 * Summary of updateInvoiceBalance
	 * @param \PDO $dbh
	 * @param mixed $paymentAmount
	 * @param mixed $user
	 * @throws \HHK\Exception\PaymentException
	 * @return void
	 */
	public function updateInvoiceBalance(\PDO $dbh, $paymentAmount, $user) {
		// positive payment amounts reduce a balance.
		// Neg payments increase a bal.
		if ($this->idInvoice > 0) {

			$balAmt = $this->invRs->Balance->getStoredVal ();
			$newBal = $balAmt - $paymentAmount;

			$this->invRs->Balance->setNewVal ( $newBal );

			$attempts = $this->invRs->Payment_Attempts->getStoredVal ();
			$this->invRs->Payment_Attempts->setNewVal ( ++ $attempts );

			if ($newBal == 0) {
				$this->invRs->Status->setNewVal ( InvoiceStatus::Paid );
			} else {
				$this->invRs->Status->setNewVal ( InvoiceStatus::Unpaid );
			}

			$this->invRs->Last_Updated->setNewVal ( date ( 'Y-m-d H:i:s' ) );
			$this->invRs->Updated_By->setNewVal ( $user );

			EditRS::update ( $dbh, $this->invRs, array (
					$this->invRs->idInvoice
			) );
			EditRS::updateStoredVals ( $this->invRs );
		} else {
			throw new PaymentException( 'Cannot make payments on a blank invoice record.  ' );
		}
	}
	/**
	 * Summary of unwindCarriedInv
	 * @param \PDO $dbh
	 * @param mixed $id
	 * @param mixed $invIds
	 * @return void
	 */
	protected function unwindCarriedInv(\PDO $dbh, $id, &$invIds) {
		$stmt = $dbh->query ( "select idInvoice from invoice where Delegated_Invoice_Id = " . $id );
		$rows = $stmt->fetchAll ( \PDO::FETCH_NUM );

		foreach ( $rows as $r ) {

			$invIds [] = $r [0];
			$this->unwindCarriedInv ( $dbh, $r [0], $invIds );
		}
	}
	/**
	 * Summary of deleteCarriedInvoice
	 * @param \PDO $dbh
	 * @param mixed $user
	 * @throws \HHK\Exception\PaymentException
	 * @return bool
	 */
	protected function deleteCarriedInvoice(\PDO $dbh, $user) {
		if ($this->invRs->Carried_Amount->getStoredVal () == 0) {
			throw new PaymentException( 'This invoice has no carried amount. ' );
		}

		// Get all the carried invoices
		$invIds = array ();
		$this->unwindCarriedInv ( $dbh, $this->idInvoice, $invIds );

		$whAssoc = '';
		foreach ( $invIds as $a ) {

			if ($a != 0) {

				if ($whAssoc == '') {
					$whAssoc .= $a;
				} else {
					$whAssoc .= "," . $a;
				}
			}
		}

		$query = "select count(p.idPayment)
From payment p join payment_invoice pi on p.idPayment = pi.Payment_Id and p.Status_Code = 's' and p.Is_Refund = 0
where pi.Invoice_Id in ($whAssoc)";

		$stmn = $dbh->query ( $query );
		$rows = $stmn->fetchAll ( \PDO::FETCH_NUM );

		if (count ( $rows ) > 0 && $rows [0] [0] > 0) {
			throw new PaymentException( 'Unpaid or partially paid invoices cannot be deleted. Remove the payments first.' );
		}

		$bolDeld = TRUE;
		foreach ( $invIds as $id ) {

			if ($this->_deleteInvoice ( $dbh, $id, $user ) === FALSE) {
				$bolDeld = FALSE;
			}
		}

		// Dekete delegated invoice
		if ($bolDeld) {
			return $this->deleteMe ( $dbh, $user );
		}

		return FALSE;
	}
 /**
  * Summary of deleteInvoice
  * @param \PDO $dbh
  * @param mixed $user
  * @throws \HHK\Exception\PaymentException
  * @return mixed
  */
	public function deleteInvoice(\PDO $dbh, $user) {

		//
		if ($this->invRs->Carried_Amount->getStoredVal () != 0) {
			return $this->deleteCarriedInvoice ( $dbh, $user );
		}

		switch ($this->getStatus ()) {

			case InvoiceStatus::Paid :

				if ($this->getAmount () == 0) {
					// Delete any 0-amount CASH payment records...
					$dbh->exec ( "CALL `delete_Invoice_payments`(" . $this->idInvoice . ", " . PaymentMethod::Cash . ");" );
				}

				if ($this->countPayments ( $dbh ) == 0) {
					return $this->deleteMe ( $dbh, $user );
				}

				break;

			case InvoiceStatus::Unpaid :

				if ($this->getAmount () != 0 && $this->getBalance () != $this->getAmount ()) {
					throw new PaymentException( 'Partially paid invoices cannot be deleted. Remove the payments first.' );
				}

				$lines = $this->getLines ( $dbh );

				foreach ( $lines as $l ) {

					if ($l->getItemId () == ItemId::LodgingMOA && $this->is3rdParty ( $dbh, $this->getSoldToId () ) && $l->getAmount () > 0) {

						$moaAmt = Registration::loadLodgingBalance ( $dbh, $this->getIdGroup () );

						if ($l->getAmount () > $moaAmt) {
							throw new PaymentException( 'Cannot delete.  The Money On Account (MOA) balance for this guest is not enough: ' . number_format ( $moaAmt, 2 ) );
						}
					}
				}

				return $this->deleteMe ( $dbh, $user );
		}

		throw new PaymentException( 'Only unpaid invoices can be deleted. ' );
	}
	/**
	 * Summary of deleteMe
	 * @param \PDO $dbh
	 * @param mixed $user
	 * @return bool
	 */
	protected function deleteMe(\PDO $dbh, $user) {
		$result = $this->_deleteInvoice ( $dbh, $this->idInvoice, $user );

		if ($result) {
			$this->loadInvoice ( $dbh, $this->idInvoice );
		}

		return $result;
	}
	/**
	 * Summary of _deleteInvoice
	 * @param \PDO $dbh
	 * @param mixed $id
	 * @param mixed $user
	 * @return bool
	 */
	private function _deleteInvoice(\PDO $dbh, $id, $user) {
		if ($id > 0) {

			$dbh->exec ( "update invoice set Deleted = 1, Last_Updated = now(), Updated_By = '$user' where idInvoice = $id" );
			$dbh->exec ( "update invoice_line set Deleted = 1 where Invoice_Id = $id" );

			return TRUE;
		}

		return FALSE;
	}
	/**
	 * Summary of is3rdParty
	 * @param \PDO $dbh
	 * @param mixed $idName
	 * @return bool
	 */
	protected function is3rdParty(\PDO $dbh, $idName) {
		$stmt = $dbh->query ( "Select count(*) from name_volunteer2 where idName = $idName and Vol_Category = 'Vol_Type' and Vol_Code = '" . VolMemberType::ReferralAgent . "' and Vol_Status = '" . VolStatus::Active . "'" );
		$rows = $stmt->fetchAll ( \PDO::FETCH_NUM );

		if (count ( $rows ) > 0 && $rows [0] [0] > 0) {
			return TRUE;
		}

		return FALSE;
	}
	/**
	 * Summary of countPayments
	 * @param \PDO $dbh
	 * @return mixed
	 */
	protected function countPayments(\PDO $dbh) {
		$cnt = 0;
		$stmt = $dbh->query ( "select count(*) from payment_invoice pi where pi.Invoice_Id = " . $this->idInvoice );
		$rows = $stmt->fetchAll ( \PDO::FETCH_NUM );

		if (count ( $rows ) > 0) {
			$cnt = $rows [0] [0];
		}

		return $cnt;
	}
	/**
	 * Summary of createNewInvoiceNumber
	 * @param \PDO $dbh
	 * @return mixed
	 */
	private function createNewInvoiceNumber(\PDO $dbh) {
		return Common::incCounter ( $dbh, 'invoice' );
	}
	/**
	 * Summary of getIdInvoice
	 * @return mixed
	 */
	public function getIdInvoice() {
		return $this->invRs->idInvoice->getStoredVal ();
	}
	/**
	 * Summary of getInvoiceNumber
	 * @return mixed
	 */
	public function getInvoiceNumber() {
		return $this->invRs->Invoice_Number->getStoredVal ();
	}
	/**
	 * Summary of getAmount
	 * @return mixed
	 */
	public function getAmount() {
		return $this->invRs->Amount->getStoredVal ();
	}
	/**
	 * Summary of getBalance
	 * @return mixed
	 */
	public function getBalance() {
		return $this->invRs->Balance->getStoredVal ();
	}
	/**
	 * Summary of getDate
	 * @return mixed
	 */
	public function getDate() {
		return $this->invRs->Invoice_Date->getStoredVal ();
	}
	/**
	 * Summary of getNotes
	 * @return mixed
	 */
	public function getNotes() {
		return $this->invRs->Notes->getStoredVal ();
	}
	/**
	 * Summary of getStatus
	 * @return mixed
	 */
	public function getStatus() {
		return $this->invRs->Status->getStoredVal ();
	}
	/**
	 * Summary of getPayAttemtps
	 * @return mixed
	 */
	public function getPayAttemtps() {
		return $this->invRs->Payment_Attempts->getStoredVal ();
	}
	/**
	 * Summary of getSoldToId
	 * @return mixed
	 */
	public function getSoldToId() {
		return $this->invRs->Sold_To_Id->getStoredVal ();
	}
	/**
	 * Summary of setSoldToId
	 * @param mixed $id
	 * @return static
	 */
	public function setSoldToId($id) {
		$this->invRs->Sold_To_Id->setNewVal ( $id );
		return $this;
	}
	/**
	 * Summary of getIdGroup
	 * @return mixed
	 */
	public function getIdGroup() {
		return $this->invRs->idGroup->getStoredVal ();
	}
	/**
	 * Summary of getOrderNumber
	 * @return mixed
	 */
	public function getOrderNumber() {
		return $this->invRs->Order_Number->getStoredVal ();
	}
	/**
	 * Summary of getSuborderNumber
	 * @return mixed
	 */
	public function getSuborderNumber() {
		return $this->invRs->Suborder_Number->getStoredVal ();
	}
	/**
	 * Summary of getDelegatedInvoiceNumber
	 * @return mixed|string
	 */
	public function getDelegatedInvoiceNumber() {
		return $this->delegatedInvoiceNumber;
	}
	/**
	 * Summary of getDelegatedStatus
	 * @return mixed|string
	 */
	public function getDelegatedStatus() {
		return $this->delegatedStatus;
	}
	/**
	 * Summary of getInvoiceNotes
	 * @return mixed
	 */
	public function getInvoiceNotes() {
		return $this->invRs->Notes->getStoredVal ();
	}
	/**
	 * Summary of getBillDate
	 * @return mixed
	 */
	public function getBillDate() {
		return $this->invRs->BillDate->getStoredVal ();
	}
	/**
	 * Summary of getDescription
	 * @return mixed
	 */
	public function getDescription() {
		return $this->invRs->Description->getStoredVal ();
	}
	/**
	 * Summary of setDescription
	 * @param mixed $desc
	 * @return static
	 */
	public function setDescription($desc) {
		$this->invRs->Description->setNewVal ( $desc );
		return $this;
	}
	/**
	 * Summary of setDelegatedInvoiceNumber
	 * @param mixed $delegatedInvoiceNumber
	 * @return static
	 */
	public function setDelegatedInvoiceNumber($delegatedInvoiceNumber) {
		$this->delegatedInvoiceNumber = $delegatedInvoiceNumber;
		return $this;
	}
	/**
	 * Summary of setDelegatedStatus
	 * @param mixed $delegatedStatus
	 * @return static
	 */
	public function setDelegatedStatus($delegatedStatus) {
		$this->delegatedStatus = $delegatedStatus;
		return $this;
	}
	/**
	 * Summary of getInvRecordSet
	 * @return InvoiceRS
	 */
	public function getInvRecordSet() {
		return $this->invRs;
	}
	/**
	 * Summary of isDeleted
	 * @return bool
	 */
	public function isDeleted() {
		if ($this->invRs->Deleted->getStoredVal () == 0) {
			return FALSE;
		} else {
			return TRUE;
		}
	}
}