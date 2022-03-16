<?php
namespace HHK\Neon;

/*
 * RelationshipMapper.php
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


/**
 *
 * @author Eric
 *
 */
class RelationshipMapper
{

    protected $map;
    protected $patientToPG;
    protected $guestToPG;
    protected $mapNeonTypes;

    protected $PGtoPatient;

    /**
     * @return mixed
     */
    public function getPatientToPG()
    {
        return $this->patientToPG;
    }

    /**
     * @return mixed
     */
    public function getGuestToPG()
    {
        return $this->guestToPG;
    }

    /**
     * @param mixed $patientToPG
     */
    public function setPatientToPG($patientToPG)
    {
        $this->patientToPG = $patientToPG;
    }

    /**
     * @param mixed $guestToPG
     */
    public function setGuestToPG($guestToPG)
    {
        $this->guestToPG = $guestToPG;
    }

    /**
     */
    public function __construct(\PDO $dbh, $mappedNeonTypes) {

        $rstmt = $dbh->query("Select * from map_relations;");

        $this->map = $rstmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->mapNeonTypes = $mappedNeonTypes;

    }

    public function relateGuest($guestToPatient) {

        foreach ($this->map as $m) {

            if ($m['PG_Patient'] == $this->PGtoPatient && $m['Guest_Patient'] = $guestToPatient) {
                $this->setGuestToPG($m['Guest_PG']);
                $this->setPatientToPG($m['Patient_PG']);
                break;
            }
        }


        return $this->getGuestToPGId();
    }

    public function setPGtoPatient($hhkRelCode) {
        $this->PGtoPatient = $hhkRelCode;
    }

    /**
     * @return mixed
     */
    public function getPatientToPGId()
    {
        if (isset($this->mapNeonTypes[$this->getPatientToPG()])) {
            return $this->mapNeonTypes[$this->getPatientToPG()];
        }
        return 0;
    }

    /**
     * @return mixed
     */
    public function getGuestToPGId()
    {
        if (isset($this->mapNeonTypes[$this->getGuestToPG()])) {
            return $this->mapNeonTypes[$this->getGuestToPG()];
        }
        return 0;
    }

}

