<?php
/**
 * ConvergeConnect.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class VerifyCvCurlResponse extends GatewayResponse implements iGatewayResponse {

    public function parseResponse(){

        if(is_array($this->response)){
            $this->result = $this->response;
        }else{
            throw new Hk_Exception_Payment("Curl transaction response is invalid.  ");
        }
            return '';
    }

    public function SignatureRequired() {
        return 0;
    }

    public function getResponseCode() {
        if (isset($this->result['ssl_result'])) {
            return $this->result['ssl_result'];
        }
        return '';
    }

    public function getResponseMessage() {
        if (isset($this->result['ssl_result_message'])) {
            return $this->result['ssl_result_message'];
        }
        return '';
    }

    public function getToken() {
        if (isset($this->result['ssl_token'])) {
            return $this->result['ssl_token'];
        }
        return '';
    }

    public function getCardType() {
        if (isset($this->result['ssl_card_short_description'])) {
            return $this->result['ssl_card_short_description'];
        }
        return '';
    }

    public function getMaskedAccount() {
        if (isset($this->result['ssl_card_number'])) {
            return $this->result['ssl_card_number'];
        }
        return '';
    }

    public function getCardHolderName() {
        $name = '';
        if (isset($this->result['ssl_last_name'])) {
            $name = $this->result['ssl_last_name'];
        }
        if (isset($this->result['ssl_first_name'])) {
            $name = $this->result['ssl_first_name'] . ' ' . $name;
        }
        return $name;
    }

    public function getExpDate() {

        if (isset($this->result['ssl_exp_date'])) {
            return $this->result['ssl_exp_date'];
        }
        return '';
    }

    public function getAuthorizedAmount() {

        if (isset($this->result['ssl_amount'])) {
            return $this->result['ssl_amount'];
        }

        return '';
    }

    public function getPartialPaymentAmount() {
        if (isset($this->result['ssl_amount'])) {
            return trim($this->result['ssl_amount']);
        }
        return '';
    }

    public function getRequestAmount() {
        if (isset($this->result['ssl_requested_amount'])) {
            return trim($this->result['ssl_requested_amount']);
        }
        return '';
    }

    public function getAuthCode() {

        if (isset($this->result['ssl_approval_code'])) {
            return $this->result['ssl_approval_code'];
        }
        return '';
    }

    public function getAVSAddress() {
        if (isset($this->result['ssl_avs_address'])) {
            return $this->result['ssl_avs_address'];
        }
        return '';
    }

    public function getAVSResult() {
        if (isset($this->result['ssl_avs_response'])) {
            return $this->result['ssl_avs_response'];
        }
        return '';
    }

    public function getAVSZip() {
        return '';
    }

    public function getCvvResult() {
        if (isset($this->result['ssl_cvv2_response'])) {
            return $this->result['ssl_cvv2_response'];
        }
        return '';
    }

    public function getInvoiceNumber() {
        if (isset($this->result['ssl_invoice_number'])) {
            return $this->result['ssl_invoice_number'];
        }

        return '';
    }

    public function getRefNo() {
        if (isset($this->result['ssl_oar_data'])) {
            return $this->result['ssl_oar_data'];
        }

        return '';
    }
    public function getAcqRefData() {
        if (isset($this->result['ssl_txn_id'])) {
            return $this->result['ssl_txn_id'];
        }
        return '';
    }
    public function getProcessData() {
        if (isset($this->result['ssl_ps2000_data'])) {
            return $this->result['ssl_ps2000_data'];
        }

        return '';
    }

    public function getOperatorId() {
        if (isset($this->result['userID'])) {
            return $this->result['userID'];
        }
        return '';
    }

    public function getTransactionStatus() {
        return '';
    }

    public function getTransactionType() {
        if (isset($this->result['ssl_transaction_type'])) {
            return $this->result['ssl_transaction_type'];
        }
        return '';
    }

    public function getTransPostTime() {
        if (isset($this->result['ssl_txn_time'])) {
            return $this->result['ssl_txn_time'];
        }
        return '';
    }

    public function getAuthorizationText() {
        return '';
    }

    public function getEMVApplicationIdentifier() {
//        if (isset($this->result['EMVApplicationIdentifier'])) {
//            return $this->result['EMVApplicationIdentifier'];
//        }
        return '';
    }
    public function getEMVTerminalVerificationResults() {
//        if (isset($this->result['EMVTerminalVerificationResults'])) {
//            return $this->result['EMVTerminalVerificationResults'];
//        }
        return '';
    }
    public function getEMVIssuerApplicationData() {
        if (isset($this->result['EMVIssuerApplicationData'])) {
            return $this->result['EMVIssuerApplicationData'];
        }
        return '';
    }
    public function getEMVTransactionStatusInformation() {
        if (isset($this->result['EMVTransactionStatusInformation'])) {
            return $this->result['EMVTransactionStatusInformation'];
        }
        return '';
    }
    public function getEMVApplicationResponseCode() {
        if (isset($this->result['EMVApplicationResponseCode'])) {
            return $this->result['EMVApplicationResponseCode'];
        }
        return '';
    }

    public function getErrorCode() {
        if (isset($this->result['errorCode'])) {
            return $this->result['errorCode'];
        }
        return '';
    }
    public function getErrorMessage() {
        if (isset($this->result['errorMessage'])) {
            return $this->result['errorMessage'];
        }
        return '';
    }
    public function getErrorName() {
        if (isset($this->result['errorName'])) {
            return $this->result['errorName'];
        }
        return '';
    }


}

