<?php

namespace HHK;

/**
 * Crypto.php
 * 
 * Contains methods for handling encryption
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Crypto {

    private const KEY = "017d609a4b2d8910685595C8df";

    private const IV = "fYfhHeDmf j98UUy4";

    public static function encryptMessage($input)
    {
        return static::encrypt_decrypt('encrypt', $input, static::KEY, static::IV);
    }

    public static function decryptMessage($encrypt)
    {
        return static::encrypt_decrypt('decrypt', $encrypt, static::KEY, static::IV);
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