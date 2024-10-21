<?php
namespace HHK\CrmExport\Salesforce;


class CompositeSubresponse {

    /**
     * Summary of response
     * @var
     */
    protected $response;

    /**
     * Summary of body
     * @var array
     */
    protected array $body;

    /**
     * Summary of httpHeaders
     * @var array
     */
    protected array $httpHeaders;

    /**
     * Summary of httpStatusCode
     * @var int
     */
    protected $httpStatusCode;

    /**
     * Summary of referenceId
     * @var string
     */
    protected $referenceId;


    /**
     * Summary of __construct
     * @param array $response
     */
    public function __construct(array $response)
    {
        $this->response = $response;
        $this->body = $response['body'];
        $this->httpHeaders = $response['httpHeaders'];
        $this->httpStatusCode = $response['httpStatusCode'];
        $this->referenceId = $response['referenceId'];

    }

    public function getBody_id()
    {
        if (isset($this->body['id'])) {
            return $this->body['id'];
        } else {
            return '';
        }
    }

    public function getBody_success()
    {
        if (isset($this->body['success'])) {
            return filter_var($this->body['success'], FILTER_VALIDATE_BOOLEAN);
        } else {
            return false;
        }
    }

    public function getBody_errors(): mixed
    {
        if (isset($this->body['errors'])) {
            return $this->body['errors'];
        } else {
            return [];
        }
    }

    public function getBody_errorCode()
    {
        if (isset($this->body['errorCode'])) {
            return $this->body['errorCode'];
        } else {
            return '';
        }
    }

    public function getBody_message()
    {
        if (isset($this->body['message'])) {
            return $this->body['message'];
        } else {
            return '';
        }
    }


	/**
	 * Summary of httpStatusCode
	 * @return int
	 */
	public function getHttpStatusCode() {
		return $this->httpStatusCode;
	}

	/**
	 * Summary of referenceId
	 * @return string
	 */
	public function getReferenceId() {
		return $this->referenceId;
	}

    public function getHttpHeaders_location() {
        if (isset($this->httpHeaders['location'])) {
            return $this->httpHeaders['location'];
        } else {
            return '';
        }
    }
}