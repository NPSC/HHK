<?php
namespace HHK\Exception;

/**
 * SmsException.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 *
 * @author Will Ireland
 */

class SmsException extends \RuntimeException {
    protected array $responseData;

    public function __construct(string $message = "", int $code = 0, \Throwable|null $previous = null, array $responseData = []){
        $this->responseData = $responseData;
        parent::__construct($message, $code, $previous);
    }
}
