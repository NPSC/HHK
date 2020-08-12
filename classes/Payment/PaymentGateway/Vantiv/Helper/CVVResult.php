<?php

namespace HHK\Payment\PaymentGateway\Vantiv\Helper;

/**
 * CVVResult.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */



/**
* Non Profit Software Corporation HostedCheckout PHP Client
*
* ©2013-2017 Non Profit Software Corporation - all rights reserved.
*
* Disclaimer:
* This software and all specifications and documentation contained
* herein or provided to you hereunder (the "Software") are provided
* free of charge strictly on an "AS IS" basis. No representations or
* warranties are expressed or implied, including, but not limited to,
* warranties of suitability, quality, merchantability, or fitness for a
* particular purpose (irrespective of any course of dealing, custom or
* usage of trade), and all such warranties are expressly and
* specifically disclaimed. on Profit Software Corporation shall have no
* liability or responsibility to you nor any other person or entity
* with respect to any liability, loss, or damage, including lost
* profits whether foreseeable or not, or other obligation for any cause
* whatsoever, caused or alleged to be caused directly or indirectly by
* the Software. Use of the Software signifies agreement with this
* disclaimer notice.
*/

// Mercury helper classes

class CVVResult {

    const M = '(M) CVV Match.';
    const N = '(N) CVV No Match.';
    const P = '(P) CVV Not Processed.';
    const S = '(S) CVV should be on card but merchant indicated it is not present (Visa/Discover only).';
    const U = '(U) CVV Issuer is Not Certified, CID not checked (AMEX only).';
    const BLANK = '';

    const MATCH = 'm';
    const NO_MATCH = 'nm';
    const NOT_VERIFIED = 'nv';
    const NO_ADVISE = 'na';


    protected $code;
    protected $resultMessage;
    protected $cvvResult;

    public function __construct($cvvCode) {
        $this->code = $cvvCode;
        $this->setCvvResult($cvvCode);
    }

    protected function setCvvResult($code) {

        switch ($code) {
            case 'M':
                $this->resultMessage = self::M;
                $this->cvvResult = self::MATCH;
                break;

            case 'N':
                $this->resultMessage = self::N;
                $this->cvvResult = self::NO_MATCH;
                break;

            case 'P':
                $this->resultMessage = self::P;
                $this->cvvResult = self::NOT_VERIFIED;
                break;

            case 'S':
                $this->resultMessage = self::S;
                $this->cvvResult = self::NO_ADVISE;
                break;

            case 'U':
                $this->resultMessage = self::U;
                $this->cvvResult = self::NO_ADVISE;
                break;

            case '':
                $this->resultMessage = self::BLANK;
                $this->cvvResult = self::NO_ADVISE;
                break;

            default:
                $this->resultMessage = 'CVV Unknown Code: ' . $code;
                $this->cvvResult = self::NO_ADVISE;

        }

    }

    public function isCvvMatch() {
        if ($this->getCvvResult() == self::MATCH) {
            return TRUE;
        }

        return FALSE;
    }

    public function getResultMessage() {
        return $this->resultMessage;
    }

    public function getCvvResult() {
        return $this->cvvResult;
    }

    public function getCvvCode() {
        return $this->code;
    }

}
?>