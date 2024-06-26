<?php

namespace HHK;

/*
 * The MIT License
 *
 * Copyright 2019 ecran.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Description of Photo
 *
 * @author ecran
 */
class Photo {

    /**
     * Summary of image
     * @var string
     */
    protected $image;

    /**
     * Summary of imageId
     * @var int
     */
    protected $imageId;

    /**
     * Summary of imageType
     * @var string
     */
    protected $imageType;

    /**
     * Summary of imageSizePx
     * @var int
     */
    protected $imageSizePx;

    public function __construct() {

        $this->image = NULL;

    }

    /**
     * Summary of convertToSquareThumbnail
     * @param mixed $imageFile
     * @return void
     */
    protected function convertToSquareThumbnail($imageFile) {

        if ($this->getImageSizePx() > 0) {
            $this->image = makeThumbnail($imageFile, $this->getImageSizePx(), $this->getImageSizePx());
        }

    }

    public function loadGuestPhoto(\PDO $dbh, $idGuest, $defaultPhotoFilePath = '../images/defaultGuestPhoto.png') {

        $id = intval($idGuest, 10);

        $stmt = $dbh->query("SELECT photo.* "
                . "FROM photo JOIN name_demog demog ON photo.idPhoto = demog.Guest_Photo_Id "
                . "WHERE demog.idName = $id");

        $results = $stmt->fetchAll();

        if (count($results) > 0) {

            $this->setImageType($results[0]['Image_Type']);
            $this->setImage(base64_decode($results[0]['Image']));
            $this->setImageId($results[0]['idPhoto']);

        } else {
            $this->setImage('<svg xmlns="http://www.w3.org/2000/svg" width="' . $this->getImageSizePx() . '" height="' . $this->getImageSizePx() . '" fill="#8bbcdc" class="bi bi-person-fill" viewBox="0 0 16 16">
                <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
              </svg>');
            
              $this->setImageType('image/svg+xml');
        }

    }

    /**
     * Summary of saveGuestPhoto
     * @param \PDO $dbh
     * @param int $idGuest
     * @param string $imageFile
     * @param int $imageSizePx
     * @param string $userName
     * @param int $defaultSizePx
     * @return void
     */
    public function saveGuestPhoto(\PDO $dbh, $idGuest, $imageFile, $imageSizePx, $userName, $defaultSizePx = 100) {

        $id = intval($idGuest, 10);

        $stmt = $dbh->query("SELECT Guest_Photo_Id FROM `name_demog` nd JOIN photo p ON nd.Guest_Photo_Id = p.idPhoto WHERE `idName` = $id");
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->setImageSizePx($imageSizePx, $defaultSizePx);
        if(isset($results[0]['Guest_Photo_Id'])){
            $this->setImageId(intval($results[0]['Guest_Photo_Id'] , 10));
        }else{
            $this->setImageId(0);
        }

        $this->setImageType($imageFile['type']);

        $this->convertToSquareThumbnail($imageFile);

        if ($this->getImageId() > 0) {

            $update = "UPDATE photo SET `Image_Type` = '" . $this->getImageType() . "', `Image` = '" . $this->getImageBase64() . "' , `Updated_By` = '" . $userName . "', timestamp = current_timestamp WHERE `idPhoto` = " . $this->getImageId() . ";";
            $dbh->exec($update);

        } else {

            $insert = "INSERT INTO photo (`Image_Type`, `Image`, `Updated_By`) VALUES ('" . $this->getImageType() . "', '" . $this->getImageBase64() . "', '" . $userName . "');";
            $dbh->exec($insert);

            $this->setImageId($dbh->lastInsertId());

            $dbh->exec("UPDATE name_demog SET `Guest_Photo_Id` = " . $this->getImageId() . " WHERE `idName` = $id");
        }

    }

    public function getImage() {
        return $this->image;
    }

    public function getImageBase64() {
        return base64_encode($this->image);
    }

    public function getImageId() {
        return $this->imageId;
    }

    public function getImageType() {
        return $this->imageType;
    }

    public function setImage($image) {
        $this->image = $image;
        return $this;
    }

    public function setImageId($imageId) {
        $this->imageId = intval($imageId, 10);
        return $this;
    }

    public function setImageType($imageType) {
        $this->imageType = strtolower($imageType);
        return $this;
    }

    public function getImageSizePx() {
        return $this->imageSizePx;
    }

    public function setImageSizePx($imageSizePx, $defaultSizePx) {

        if ($imageSizePx > $defaultSizePx) {
            $this->imageSizePx = $imageSizePx;
        } else {
            $this->imageSizePx = $defaultSizePx;
        }

        return $this;
    }

}
