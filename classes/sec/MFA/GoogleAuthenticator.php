<?php

namespace HHK\sec\MFA;

use HHK\sec\UserClass;
use chillerlan\QRCode\QRCode;

/**
 * PHP Class for handling Google Authenticator 2-factor authentication.
 *
 * Forked and modified for HHK
 *
 * @author Michael Kliewe
 * @author Will Ireland
 * @copyright 2012 Michael Kliewe
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 *
 * @link http://www.phpgangsta.de/
 */
class GoogleAuthenticator extends AbstractMultiFactorAuth
{

    protected $_codeLength = 6;

    /**
     * @param array $userAr
     */
    public function __construct(array $userAr){
        $this->secret = $userAr['totpSecret'];
        $this->username = $userAr['User_Name'];
    }

    public function saveSecret(\PDO $dbh): bool {
        if($this->username && $this->secret != ''){
            $query = "update w_users set totpSecret = :secret, Last_Updated = now() where User_Name = :username and Status='a';";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(
                ':secret' => $this->secret,
                ':username' => $this->username
            ));

            if ($stmt->rowCount() == 1) {
                $this->insertUserLog($dbh, UserClass::OTPSecChanged, $this->username);
            }
            return true;
        }else{
            return false;
        }
    }

    /**
     * Calculate the code, with given secret and point in time.
     *
     * @param int|null $timeSlice
     *
     * @return string
     */
    public function getCode($timeSlice = null)
    {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretkey = $this->_base32Decode($this->secret);

        // Pack time into binary string
        $time = chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', $timeSlice);
        // Hash it with users secret key
        $hm = hash_hmac('SHA1', $time, $secretkey, true);
        // Use last nipple of result as index/offset
        $offset = ord(substr($hm, - 1)) & 0x0F;
        // grab 4 bytes of the result
        $hashpart = substr($hm, $offset, 4);

        // Unpak binary value
        $value = unpack('N', $hashpart);
        $value = $value[1];
        // Only 32 bits
        $value = $value & 0x7FFFFFFF;

        $modulo = pow(10, $this->_codeLength);

        return str_pad($value % $modulo, $this->_codeLength, '0', STR_PAD_LEFT);
    }

    /**
     * Get QR-Code URL for image, from google charts.
     *
     * @param string $title
     *
     * @return string
     */
    public function getQRCodeImage($title = null)
    {

        $data = 'otpauth://totp/' . $this->username . '?secret=' . $this->secret . '';
        if (isset($title)) {
            $data .= '&issuer=' . urlencode($title);
        }

        return (new QRCode)->render($data);
    }

    /**
     * Check if the code is correct.
     *
     * @param string $code
     *
     * @return bool
     */
    public function verifyCode($code): bool
    {
        $discrepancy = 1;
        $currentTimeSlice = floor(time() / 30);

        if (strlen($code) != 6) {
            return false;
        }

        for ($i = - $discrepancy; $i <= $discrepancy; ++ $i) {
            $calculatedCode = $this->getCode($this->secret, $currentTimeSlice + $i);
            if ($this->timingSafeEquals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set the code length, should be >=6.
     *
     * @param int $length
     *
     * @return GoogleAuthenticator
     */
    public function setCodeLength($length)
    {
        $this->_codeLength = $length;

        return $this;
    }

}
