<?php
namespace HHK\Member\ProgressiveSearch\SearchNameData;

/**
 *
 * @author Eric
 *
 */
interface iSearchNameData
{

    public function setId($id);
    public function setNameFirst($nameFirst);
    public function setNameMiddle($nameMiddle);
    public function setNameLast($nameLast);
    public function setNickname($nickname);

    public function setGender($gender);
    public function setBirthDate($strBirthDate);

    public function setRelationship($rel);

    public function setEmail($email);

    public function setPhone($phone);

    public function setAddressStreet1($addressStreet1);

    public function setAddressStreet2($addressStreet2);

    public function setAddressCity($addressCity);

    public function setAddressCounty($addressCounty);

    public function setAddressState($addressState);

    public function setAddressZip($addressZip);

    public function setAddressCountry($addressCountry);

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

    /**
     * @return string
     */
    public function getBirthDate();

    /**
     * @return string
     */
    public function getGender();

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

}

