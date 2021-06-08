<?php

namespace HHK\Member;

class ProgressiveSearch {
	
	protected $nameFirst;
	protected $nameMiddle;
	protected $nameLast;

	protected $email;
	protected $phone;
	protected $addressStreet;
	protected $addressCity;
	protected $addressState;
	protected $addressZip;
	protected $addressCountry;
	

	
	/**
	 * @param string $nameFirst
	 */
	public function setNameFirst($nameFirst) {
		$this->nameFirst = $nameFirst;
	}

	/**
	 * @param string $nameMiddle
	 */
	public function setNameMiddle($nameMiddle) {
		$this->nameMiddle = $nameMiddle;
	}

	/**
	 * @param string $nameLast
	 */
	public function setNameLast($nameLast) {
		$this->nameLast = $nameLast;
	}

	/**
	 * @param string $email
	 */
	public function setEmail($email) {
		$this->email = trim(filter_var($email, FILTER_SANITIZE_EMAIL));
	}

	/**
	 * @param string $phone
	 */
	public function setPhone($phone) {
		$ary = array('+', '-');
		$this->phone = str_replace($ary, '', filter_var($phone, FILTER_SANITIZE_NUMBER_INT));
	}

	/**
	 * @param string $addressStreet
	 */
	public function setAddressStreet($addressStreet) {
		$this->addressStreet = $addressStreet;
	}

	/**
	 * @param string $addressCity
	 */
	public function setAddressCity($addressCity) {
		$this->addressCity = $addressCity;
	}

	/**
	 * @param string $addressState
	 */
	public function setAddressState($addressState) {
		$this->addressState = $addressState;
	}

	/**
	 * @param string $addressZip
	 */
	public function setAddressZip($addressZip) {
		$this->addressZip = $addressZip;
	}

	/**
	 * @param string $addressCountry
	 */
	public function setAddressCountry($addressCountry) {
		$this->addressCountry = $addressCountry;
	}

	public function __construct() {
		
	}
	
	
}

