<?php
namespace HHK\Member\ProgressiveSearch\SearchNameData;

use HHK\Member\Address\CleanAddress;

/**
 *
 * @author Eric
 *
 */
interface SearchNameDataInterface
{

    public function setId($id);
    public function setNameFirst($nameFirst);
    public function setNameMiddle($nameMiddle);
    public function setNameLast($nameLast);
    public function setNickname($nickname);
    public function setPrefix($prefix);
    public function setSuffix($suffix);

    public function setGender($gender);
    public function setBirthDate($strBirthDate);

    public function setRelationship($rel);

    public function setEmail($email);

    public function setPhone($phone);

    public function setAddressStreet($addressStreet, CleanAddress $cleanAddress = NULL, $include = FALSE);

    public function setAddressStreet1($addressStreet1);

    public function setAddressStreet2($addressStreet2);

    public function setAddressCity($addressCity);

    public function setAddressCounty($addressCounty);

    public function setAddressState($addressState);

    public function setAddressZip($addressZip);

    public function setAddressCountry($addressCountry);

    public function setNoReturn($v);

    public function setEmrgFirst($v);
    public function setEmrgLast($v);
    public function setEmrgPhone($v);
    public function setEmrgAltPhone($v);
    public function setEmrgRelation($v);

    public function getId();

    public function getNameFirst();

    public function getNameMiddle();

    /**
     * @return string
     */
    public function getNameLast();

    /**
     * @return string
     */
    public function getNickname();

    public function getPrefix();
    public function getSuffix();

    /**
     * @return string
     */
    public function getBirthDate();

    /**
     * @return string
     */
    public function getGender();

    public function getEthnicity();

    /**
     * @return string
     */
    public function getRelationship();

    /**
     * @return string
     */
    public function getEmail();

    /**
     * @return mixed
     */
    public function getPhone();

    /**
     * @return string
     */
    public function getSMS_Status();

    public function getAddressStreet();

    /**
     * @return string
     */
    public function getAddressStreet1();

    /**
     * @return string
     */
    public function getAddressStreet2();

    /**
     * @return string
     */
    public function getAddressCity();

    /**
     * @return string
     */
    public function getAddressCounty();

    /**
     * @return string
     */
    public function getAddressState();

    public function getAddressZip();

    /**
     * @return string
     */
    public function getAddressCountry();

    public function getNoReturn();

    public function getEmrgFirst();
    public function getEmrgLast();
    public function getEmrgPhone();
    public function getEmrgAltPhone();
    public function getEmrgRelation();


    public function loadMeFrom(array $r, SearchNameDataInterface $formData = null);
}

