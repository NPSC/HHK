<?php
namespace HHK\sec;

class Crypto {

    private const key = "017d609a4b2d8910685595C8df";
    private const IV = "fYfhHeDmf j98UUy4";

    public static function encryptMessage($input)
    {
        return self::encrypt_decrypt('encrypt', $input, SELF::key, SELF::IV);
    }

    private static function getNotesKey($keyPart)
    {
        return "E4HD9h4DhS56DY" . trim($keyPart) . "3Nf";
    }

    public static function encryptNotes($input, $pw)
    {
        $crypt = "";
        if ($pw != "" && $input != "") {
            $key = self::getNotesKey($pw);
            $iv = self::IV;

            $crypt = self::encrypt_decrypt('encrypt', $input, $key, $iv);
        }

        return $crypt;
    }

    public static function decryptNotes($encrypt, $pw)
    {
        $clear = "";

        if ($pw != "" && $encrypt != "") {

            $key = self::getNotesKey($pw);
            $clear = self::encrypt_decrypt('decrypt', $encrypt, $key, SELF::IV);
        }

        return $clear;
    }

    public static function decryptMessage($encrypt)
    {
        return self::encrypt_decrypt('decrypt', $encrypt, SELF::key, SELF::IV);
    }

    /**
     * simple method to encrypt or decrypt a plain text string
     * initialization vector(IV) has to be the same when encrypting and decrypting
     *
     * @param string $action:
     *            can be 'encrypt' or 'decrypt'
     * @param string $string:
     *            string to encrypt or decrypt
     *
     * @return string
     */
    private static function encrypt_decrypt($action, $string, $secret_key, $secret_iv)
    {
        $output = false;
        $encrypt_method = "AES-256-CBC";
        // $secret_key = 'This is my secret key';
        // $secret_iv = 'This is my secret iv';
        // hash
        $key = hash('sha256', $secret_key);

        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hash('sha256', $secret_iv), 0, 16);
        if ($action == 'encrypt') {
            $output = base64_encode(openssl_encrypt($string, $encrypt_method, $key, 0, $iv));
        } else if ($action == 'decrypt') {
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }
        return $output;
    }

}

?>