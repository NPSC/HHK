<?php
/**
 * Description of GatewayConnect
 *
 * @author Eric
 */
abstract class GatewayResponse {

    /**
     *
     * @var array
     */
    protected $response;
    protected $errors;

    /**
     *
     * @var array
     */
    protected $result;

    protected $tranType;

    /**
     * The child is expected to define $result.
     *
     * @param array $response
     * @throws Hk_Exception_Payment
     */
    function __construct($response) {
        if (is_array($response) || is_object($response)) {
            $this->response = $response;
        } else {
            throw new Hk_Exception_Payment('Empty response object. ');
        }

        $this->parseResponse();
    }

    // Returns Result
    protected abstract function parseResponse();

    public abstract function getResponseCode();


    public function getResultArray() {
        if (isset($this->result)) {
            return $this->result;
        }
        return array();
    }

    public function getTranType() {
        return $this->tranType;
    }


    public function getAuthorizeAmount() {
        return 0;
    }

}


abstract class SoapRequest {

    protected $gateWay;

    public function submit(array $req, $url, $trace = FALSE) {

        try {
            // Create the Soap, prepre the data
            $txClient = new SoapClient('../service.xml', array('trace'=>$trace));

            $xaction = $this->execute($txClient, $req);

        } catch (SoapFault $sf) {

            throw new Hk_Exception_Payment('Problem with HHK web server contacting the payment gateway:  ' . $sf->getMessage() .     ' (' . $sf->getCode() . '); ' . ' Trace: ' . $sf->getTraceAsString());
        }

        try {
            if ($trace) {
                file_put_contents(REL_BASE_DIR . 'patch' . DS . 'soapLog.xml', $txClient->__getLastRequest() . $txClient->__getLastResponse(), FILE_APPEND);
            }

        } catch(Exception $ex) {

            throw new Hk_Exception_Payment('Trace file error:  ' . $ex->getMessage());
        }

        return $xaction;
    }

    protected abstract function execute(SoapClient $sc, $data);

}

