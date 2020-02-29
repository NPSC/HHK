<?php
/**
 * Hk_Exception.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 *
 * @author Eric Crane
 */

class Hk_Exception_InvalidArguement
              extends RuntimeException {

}

class Hk_Exception_UnexpectedValue
              extends UnexpectedValueException {

}

class Hk_Exception_Runtime
              extends RuntimeException {
}

class Hk_Exception_Payment
              extends RuntimeException {
}

class Hk_Exception_Upload
              extends RuntimeException {
}

class Hk_Exception_Duplicate
        extends Exception {

}

class Hk_Exception_Member extends RuntimeException {

}
