<?php
namespace HHK\Member\BackgroundChecker;

/**
 *
 * @author Eric
 *
 */
class Subject
{

    protected $id;
    protected $first;
    protected $last;
    protected $phone;
    protected $email;


    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getFirst() {

        return $this->first;
    }

    /**
     * @return mixed
     */
    public function getLast() {

        return $this->last;
    }

    /**
     * @return mixed
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     */
    public function __construct($id, $first, $last, $phone, $email) {

        $this->first = preg_replace_callback("/(&#[0-9]+;)/",
            function($m) {
                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
            },
            $first);

        $this->last = preg_replace_callback("/(&#[0-9]+;)/",
            function($m) {
                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
            },
            $last);

        $this->email = trim(filter_var($email, FILTER_SANITIZE_EMAIL));

        $this->phone = str_replace(['+', '-'], '', filter_var($phone, FILTER_SANITIZE_NUMBER_INT));

        $this->id = intval($id, 10);

    }


}

