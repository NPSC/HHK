<?php

namespace HHK\sec\MFA;

abstract class AbstractMultiFactorAuth {

    protected $secret;
    protected $username;
    protected $_codeLength = 6;
    protected $discrepancy = 1; //code validity time in 30sec increments (1 = 30sec, 10 = 5min)

    /**
     * Generate secret for creating OTPs
     *
     * @param int $secretLength
     *
     * @return bool
     */
    public function createSecret(int $secretLength = 16) {
        try{
            $validChars = $this->_getBase32LookupTable();

            // Valid secret lengths are 80 to 640 bits
            if ($secretLength < 16 || $secretLength > 128) {
                throw new \Exception('Bad secret length');
            }
            $secret = '';
            $rnd = false;
            if (function_exists('random_bytes')) {
                $rnd = random_bytes($secretLength);
            } elseif (function_exists('mcrypt_create_iv')) {
                $rnd = mcrypt_create_iv($secretLength, MCRYPT_DEV_URANDOM);
            } elseif (function_exists('openssl_random_pseudo_bytes')) {
                $rnd = openssl_random_pseudo_bytes($secretLength, true);
            }

            if ($rnd !== false) {
                for ($i = 0; $i < $secretLength; ++ $i) {
                    $secret .= $validChars[ord($rnd[$i]) & 31];
                }
            } else {
                throw new \Exception('No source of secure random');
            }

            $this->secret = $secret;
            return true;
        }catch(\Exception $e){
            return false;
        }
    }


    /**
     * Save generated secret to user record
     *
     * @param \PDO $dbh
     *
     * @return bool
     */
    public abstract function saveSecret(\PDO $dbh): bool;

    /**
     * Disable this two factor method
     *
     * @param \PDO $dbh
     *
     * @return bool
     */
    public abstract function disable(\PDO $dbh): bool;


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
     * Verify a submitted code based on the supplied secret
     *
     * @param string $code
     *
     * @return bool
     */
    public function verifyCode(string $code) : bool
    {
        $currentTimeSlice = floor(time() / 30);

        if (strlen($code) != 6) {
            return false;
        }

        for ($i = - $this->discrepancy; $i <= $this->discrepancy; ++ $i) {
            $calculatedCode = $this->getCode($currentTimeSlice + $i);
            if ($this->timingSafeEquals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;

    }

    /**
     * Helper class to decode base32.
     *
     * @param $secret
     *
     * @return bool|string
     */
    protected function _base32Decode($secret)
    {
        if (empty($secret)) {
            return '';
        }

        $base32chars = $this->_getBase32LookupTable();
        $base32charsFlipped = array_flip($base32chars);

        $paddingCharCount = substr_count($secret, $base32chars[32]);
        $allowedValues = array(
            6,
            4,
            3,
            1,
            0
        );
        if (! in_array($paddingCharCount, $allowedValues)) {
            return false;
        }
        for ($i = 0; $i < 4; ++ $i) {
            if ($paddingCharCount == $allowedValues[$i] && substr($secret, - ($allowedValues[$i])) != str_repeat($base32chars[32], $allowedValues[$i])) {
                return false;
            }
        }
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = '';
        for ($i = 0; $i < count($secret); $i = $i + 8) {
            $x = '';
            if (! in_array($secret[$i], $base32chars)) {
                return false;
            }
            for ($j = 0; $j < 8; ++ $j) {
                $x .= str_pad(base_convert(@$base32charsFlipped[@$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
            }
            $eightBits = str_split($x, 8);
            for ($z = 0; $z < count($eightBits); ++ $z) {
                $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
            }
        }

        return $binaryString;
    }

    /**
     * Get array with all 32 characters for decoding from/encoding to base32.
     *
     * @return array
     */
    protected function _getBase32LookupTable()
    {
        return array(
            'A',
            'B',
            'C',
            'D',
            'E',
            'F',
            'G',
            'H', // 7
            'I',
            'J',
            'K',
            'L',
            'M',
            'N',
            'O',
            'P', // 15
            'Q',
            'R',
            'S',
            'T',
            'U',
            'V',
            'W',
            'X', // 23
            'Y',
            'Z',
            '2',
            '3',
            '4',
            '5',
            '6',
            '7', // 31
            '=' // padding char
        );
    }

    /**
     * A timing safe equals comparison
     * more info here: http://blog.ircmaxell.com/2014/11/its-all-about-time.html.
     *
     * @param string $safeString
     *            The internal (safe) value to be checked
     * @param string $userString
     *            The user submitted (unsafe) value
     *
     * @return bool True if the two strings are identical
     */
    protected function timingSafeEquals($safeString, $userString)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($safeString, $userString);
        }
        $safeLen = strlen($safeString);
        $userLen = strlen($userString);

        if ($userLen != $safeLen) {
            return false;
        }

        $result = 0;

        for ($i = 0; $i < $userLen; ++ $i) {
            $result |= (ord($safeString[$i]) ^ ord($userString[$i]));
        }

        // They are only identical strings if $result is exactly 0...
        return $result === 0;
    }

    public function getSecret(){
        return $this->secret;
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

?>