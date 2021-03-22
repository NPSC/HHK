<?php
namespace HHK\GlStmt;

use HHK\SysConst\RoomRateCategories;

class VisitIntervalCalculator {
	
	
	protected $intervalCharge = 0;
	protected $preIntervalCharge = 0;
	protected $fullIntervalCharge = 0;
	protected $subsidyCharge = 0;
	
	protected $intervalPay = 0;
	protected $preIntervalPay = 0;  // ForwardPay
	protected $intervalWaiveAmt = 0;
	protected $preIntervalWaiveAmt = 0;
	
	protected $preIntervalDiscount = 0;
	protected $intervalDiscount = 0;
	
	protected $paymentFromPast = 0;
	protected $paymentToPast = 0;
	protected $paymentToNow = 0;
	protected $paymentToFuture = 0;
	protected $unallocatedPayments = 0;
	protected $unpaidCharges = 0;

	
	/**
	 * @return number
	 */
	public function getPaymentFromPast() {
		return $this->paymentFromPast;
	}

	/**
	 * @return number
	 */
	public function getPaymentToPast() {
		return $this->paymentToPast;
	}

	/**
	 * @return number
	 */
	public function getPaymentToNow() {
		return $this->paymentToNow;
	}

	/**
	 * @return number
	 */
	public function getPaymentToFuture() {
		return $this->paymentToFuture;
	}

	/**
	 * @return number
	 */
	public function getUnallocatedPayments() {
		return $this->unallocatedPayments;
	}

	/**
	 * @return number
	 */
	public function getUnpaidCharges() {
		return $this->unpaidCharges;
	}

	public function closeInterval() {
		
		$overWaive = 0;
		$overDiscount = 0;
		
		// pre-Discounts diminish pre-lodging charge amounts, already paid by house.
		if ($this->preIntervalCharge >= abs($this->preIntervalDiscount)) {
			$this->preIntervalCharge += $this->preIntervalDiscount;
		} else {
			// more discounts than charges.
			$overDiscount = $this->preIntervalDiscount + $this->preIntervalCharge;
			$this->preIntervalDiscount = 0 - $this->preIntervalCharge;
			$this->preIntervalCharge = 0;
		}
		
		// Deal with pre-interval waive amounts
		if ($this->preIntervalCharge >= abs($this->preIntervalWaiveAmt)) {
			$this->preIntervalCharge += $this->preIntervalWaiveAmt;  // Reduce Charge to guest
			$this->preIntervalPay += $this->preIntervalWaiveAmt;  // remove "fake" payment
		} else {
			// Waive bleed over to this month
			$overWaive = $this->preIntervalWaiveAmt + $this->preIntervalCharge;  // Waive forwarded to next month
			$this->preIntervalWaiveAmt = 0 - $this->preIntervalCharge;
			$this->preIntervalCharge = 0;
			
			// There may be extra payments beyond the waive.
			if ($this->preIntervalPay >= abs($this->preIntervalWaiveAmt)) {
				$this->preIntervalPay += $this->preIntervalWaiveAmt;  // Reduce payment by waive amount
			} else {
				// This is an error!
				throw new \Exception('Pre-interval waive not matched by pre-interval payments.  ');
			}
		}
		
		// The interval charge is reduced by any overage from pre-interval
		$this->intervalCharge += ($overDiscount + $overWaive);
		
		// Remove discounts from charges
		if ($this->intervalCharge >= abs($this->intervalDiscount)) {
			$this->intervalCharge += $this->intervalDiscount;
		} else {
			// more discounts than charges.
			$overDiscount = $this->intervalDiscount + $this->intervalCharge;
			$this->intervalDiscount = 0 - $this->intervalCharge;
			$this->intervalCharge = 0;
		}
		
		// Interval Waive amounts
		if ($this->intervalCharge >= abs($this->intervalWaiveAmt)) {
			$this->intervalCharge += $this->intervalWaiveAmt;
			$this->intervalPay += $this->intervalWaiveAmt;
		} else {
			
			// More waives than charges.  Waives meant for the past?
			$unpaidCharges = $this->preIntervalCharge - $this->preIntervalPay;
			
			if ($unpaidCharges > 0 && $unpaidCharges >= abs($this->intervalWaiveAmt)) {
				// All interval waives goes to preinterval.
				$this->preIntervalCharge += $this->intervalWaiveAmt;
				$this->intervalPay += $this->intervalWaiveAmt;
				$this->intervalWaiveAmt = 0;
				
			} else if ($unpaidCharges > 0) {
				// interval waive amount split between pre and now interval.
				$this->intervalWaiveAmt += $unpaidCharges;
				$this->preIntervalCharge -= $unpaidCharges;
				$this->intervalPay  += $this->intervalWaiveAmt;
				$this->intervalCharge += $this->intervalWaiveAmt;
				
			} else if ($this->intervalPay >= abs($this->intervalWaiveAmt)) {
				// There may be extra payments beyond the waive.
				$this->intervalPay += $this->intervalWaiveAmt;  // Reduce payment by waive amount
			} else {
				// This is an error!
				throw new \Exception('Interval waive not matched by interval payments.  ');
			}
		}
		
		// leftover Payments from past (C23)
		$pfp = 0;
		if ($this->preIntervalPay - $this->preIntervalCharge > 0) {
			$pfp = $this->preIntervalPay - $this->preIntervalCharge;
		}
		
		// leftover charge after previous payments (C22)
		$cfp = 0;
		if ($this->preIntervalCharge - $this->preIntervalPay > 0) {
			$cfp = $this->preIntervalCharge - $this->preIntervalPay;
		}
		
		
		// Payments to the past
		$ptp = 0;
		if ($cfp > 0) {
			if($this->intervalPay >= $cfp) {
				$ptp = $cfp;
			} else {
				$ptp = $this->intervalPay;
			}
		}
		
		// Payments to now
		$ptn = 0;
		if ($cfp <= $this->intervalPay) {
			if ($this->intervalPay - $cfp > $this->intervalCharge) {
				$ptn = $this->intervalCharge;
			} else {
				$ptn = $this->intervalPay - $cfp;
			}
		}
		
		// Payments to Future ongoing visits
		$ptf = 0;
		if ($ptp + $ptn < $this->intervalPay) {
			$ptf = $this->intervalPay - $ptp - $ptn;
		}
		
	
		// Payments Carried Forward - unallocated payments
		if (($this->preIntervalPay + $this->intervalPay) - $this->preIntervalCharge - $this->intervalCharge - $ptf > 0) {
			$this->unallocatedPayments = ($this->preIntervalPay + $this->intervalPay) - $this->preIntervalCharge - $this->intervalCharge - $ptf;
		} else {
			$this->unpaidCharges = ($this->preIntervalPay + $this->intervalPay) - $this->preIntervalCharge - $this->intervalCharge - $ptf;
		}
		
		$this->paymentFromPast = $pfp;
		$this->paymentToPast = $ptp;
		$this->paymentToNow = $ptn;
		$this->paymentToFuture = $ptf;
		
		return $this;
	}
	
	
	
	/**
	 * @return number
	 */
	public function getIntervalCharge() {
		return $this->intervalCharge;
	}

	/**
	 * @return number
	 */
	public function getPreIntervalCharge() {
		return $this->preIntervalCharge;
	}

	/**
	 * @return number
	 */
	public function getFullIntervalCharge() {
		return $this->fullIntervalCharge;
	}

	/**
	 * @return number
	 */
	public function getSubsidyCharge() {
		return $this->subsidyCharge;
	}

	/**
	 * @return number
	 */
	public function getIntervalPay() {
		return $this->intervalPay;
	}

	/**
	 * @return number
	 */
	public function getPreIntervalPay() {
		return $this->preIntervalPay;
	}

	/**
	 * @return number
	 */
	public function getIntervalWaiveAmt() {
		return $this->intervalWaiveAmt;
	}

	/**
	 * @return number
	 */
	public function getPreIntervalWaiveAmt() {
		return $this->preIntervalWaiveAmt;
	}

	/**
	 * @return number
	 */
	public function getPreIntervalDiscount() {
		return $this->preIntervalDiscount;
	}

	/**
	 * @return number
	 */
	public function getIntervalDiscount() {
		return $this->intervalDiscount;
	}

	/**
	 *
	 * @param number $charge
	 * @param number $fullCharge
	 * @param number $adjRatio
	 * @param string $rateCategory
	 */
	public function updateIntervalCharge($charge, $fullCharge, $adjRatio, $rateCategory) {
		
		$this->intervalCharge += $charge;
		
		// Adjust ratio
		if ($adjRatio > 0) {
			$this->fullIntervalCharge += ($fullCharge * $adjRatio);
		} else {
			$this->fullIntervalCharge += $fullCharge;
		}
		
		// Subsidy
		if ($rateCategory != RoomRateCategories::FlatRateCategory) {
			$this->subsidyCharge += $fullCharge - $charge;
		}
	}
	
	public function updatePreIntervalCharge($v) {
		$this->preIntervalCharge += $v;
	}
		
	public function updateIntervalPay($v) {
		$this->intervalPay += $v;
	}
	
	public function updatePreIntervalPay($v) {
		$this->preIntervalPay += $v;
	}
	
	public function updateIntervalWaiveAmt($v) {
		$this->intervalWaiveAmt += $v;
	}
	
	public function updatePreIntervalWaiveAmt($v) {
		$this->preIntervalWaiveAmt += $v;
	}
	
	public function updateIntervalDiscount($v) {
		$this->intervalDiscount += $v;
	}
	
	public function updatePreIntervalDiscount($v) {
		$this->preintervalDiscount += $v;
	}
	
	
}