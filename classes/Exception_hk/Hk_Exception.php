<?php
/**
 * Hk_Exception.php
 *
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */
namespace npsc;
/**
 *
 * @author Eric Crane
 */
interface Hk_Exception {

}

class Hk_Exception_InvalidArguement
              extends RuntimeException
              implements Hk_Exception {

}

class Hk_Exception_UnexpectedValue
              extends UnexpectedValueException
              implements Hk_Exception {

}

class Hk_Exception_Runtime
              extends RuntimeException
              implements Hk_Exception {
}

class Hk_Exception_Payment
              extends RuntimeException
              implements Hk_Exception {
}

class Hk_Exception_Upload
              extends RuntimeException
              implements Hk_Exception {
}

class Hk_Exception_Duplicate
        extends Exception implements Hk_Exception {

}
?>
