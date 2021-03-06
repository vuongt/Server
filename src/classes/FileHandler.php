<?php
/**
 * Created by PhpStorm.
 * User: vuong
 * Date: 11/11/2016
 * Time: 17:25
 */
class FileHandler{

    private $log;

    public function __construct($logger){
        // create a log channel
        $this->log = $logger;
    }

    /**
     * @param $appId
     * @param $fieldName : field name of the file in POST request
     * @return null|string the path to the file
     * save an image to app's folder (back ground or image)
     * app's folder is named by it id and placed inside res/app/
     */
    public function saveImageToApp($appId,$fieldName){
        $this->log->addInfo("Saving image " . $fieldName ." of ".$appId);
        $this->log->addInfo("check if folder exists");
        if (!file_exists("../../res/app/".$appId)) {
            mkdir("../../res/app/".$appId, 0777, true);
        }
        $target_dir = "../../res/app/".$appId."/";
        $target_file = $target_dir . basename($_FILES[$fieldName]["name"]);
        $uploadOk = 1;
        $imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
        // Check if image file is a actual image or fake image
        /*$check = getimagesize($_FILES[$fieldName]["tmp_name"]);
        if($check !== false) {
            $this->log->addInfo("File is an image - " . $check["mime"] . ".");
            $uploadOk = 1;
        } else {
            $this->log->addInfo("File is not an image.");
            $uploadOk = 0;
        }*/
        // Check if file already exists
        if (file_exists($target_file)) {
            $uploadOk = 0;
            $this->log->addInfo("File already exists");
        }
        // Check file size <5Mb
        if ($_FILES[$fieldName]["size"] > 6*1024*1024) {
            //TODO return code
            $uploadOk = 0;
            $this->log->addInfo("File too big");
        }
        // Allow certain file formats
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
            && $imageFileType != "gif" ) {
            $this->log->addInfo("File's format is not JPG, JPEG, PNG or GIF");
            $uploadOk = 0;
        }
        // Check if $uploadOk is set to 0 by an error
        if ($uploadOk == 0) {
            return NULL;
        // if everything is ok, try to upload file
        } else {
            if (move_uploaded_file($_FILES[$fieldName]["tmp_name"], $target_file)) {
                $this->log->addInfo("The file ". basename($_FILES[$fieldName]["name"]). " has been uploaded.");
                return $target_file;
            } else {
                $this->log->addInfo("There was an error uploading the file.");
                return NULL;
            }
        }
    }

    /**
     * @param $moduleId
     * @param $fieldName : field name of the file in POST request
     * @return null|string
     * Save an image to media module of type image
     */
    public function saveImageToModule($moduleId,$fieldName){
        $type = "image";
        $this->log->addInfo("Saving ".$type." to ".$moduleId);
        $this->log->addInfo("check if folder exists");
        if (!file_exists(RES_MODULE_PATH.$moduleId."/".$type)) {
            mkdir(RES_MODULE_PATH.$moduleId."/".$type, 0777, true);
        }
        $target_dir = RES_MODULE_PATH.$moduleId."/".$type."/";
        $target_file = $target_dir . basename($_FILES[$fieldName]["name"]);
        $uploadOk = 1;
        $imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
        // Check if image file is a actual image or fake image
        /*$check = getimagesize($_FILES[$fieldName]["tmp_name"]);
        if($check !== false) {
            $this->log->addInfo("File is an image - " . $check["mime"] . ".");
            $uploadOk = 1;
        } else {
            $this->log->addInfo("File is not an image.");
            $uploadOk = 0;
        }*/
        // Check if file already exists
        if (file_exists($target_file)) {
            $uploadOk = 0;
            $this->log->addInfo("File already exists");
        }
        // Check file size less than 5Mb
        $this->log->addInfo("File size is " .$_FILES[$fieldName]["size"]/(1024*1024)). " Mb";
        if ($_FILES[$fieldName]["size"] > 6*1024*1024) {
            //TODO return code
            $uploadOk = 0;
            $this->log->addInfo("File too big");
        }
        // Allow certain file formats
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
            && $imageFileType != "gif" ) {
            $this->log->addInfo("File's format is not JPG, JPEG, PNG or GIF");
            $uploadOk = 0;
        }
        // Check if $uploadOk is set to 0 by an error
        if ($uploadOk == 0) {
            return NULL;
            // if everything is ok, try to upload file
        } else {
            if (move_uploaded_file($_FILES[$fieldName]["tmp_name"], $target_file)) {
                $this->log->addInfo("The file ". basename($_FILES[$fieldName]["name"]). " has been uploaded.");
                return $target_file;
            } else {
                $this->log->addInfo("There was an error uploading the file.");
                return NULL;
            }
        }
    }

    /**
     * @param $moduleId
     * @param $fieldName : field name of the file in POST request
     * @param $type
     * @return null|string
     * save a documents or files in module of type document
     */
    public function saveContentToModule($moduleId,$fieldName, $type){
        $this->log->addInfo("Saving ".$type." to ".$moduleId);
        $this->log->addInfo("check if folder exists");
        if (!file_exists(RES_MODULE_PATH.$moduleId."/".$type)) {
            mkdir(RES_MODULE_PATH.$moduleId."/".$type, 0777, true);
        }
        $target_dir = RES_MODULE_PATH.$moduleId."/".$type."/";
        $target_file = $target_dir . basename($_FILES[$fieldName]["name"]);
        $uploadOk = 1;
        $fileType = pathinfo($target_file,PATHINFO_EXTENSION);
        // Check if image file is a actual image or fake image
        /*$check = getimagesize($_FILES[$fieldName]["tmp_name"]);
        if($check !== false) {
            $this->log->addInfo("File is an image - " . $check["mime"] . ".");
            $uploadOk = 1;
        } else {
            $this->log->addInfo("File is not an image.");
            $uploadOk = 0;
        }*/
        // Check if file already exists
        if (file_exists($target_file)) {
            $uploadOk = 0;
            $this->log->addInfo("File already exists");
        }
        // Check file size less than 5Mb
        $this->log->addInfo("File size is " .$_FILES[$fieldName]["size"]/(1024*1024)). " Mb";
        if ($_FILES[$fieldName]["size"] > 6*1024*1024) {
            //TODO return code
            $uploadOk = 0;
            $this->log->addInfo("File too big");
        }
        // Allow certain file formats
        if ($type=="image"){
            if($fileType != "jpg" && $fileType != "png" && $fileType != "jpeg"
                && $fileType != "gif" ) {
                $this->log->addInfo("File's format is not JPG, JPEG, PNG or GIF");
                $uploadOk = 0;
            }
        }
        // Check if $uploadOk is set to 0 by an error
        if ($uploadOk == 0) {
            return NULL;
            // if everything is ok, try to upload file
        } else {
            if (move_uploaded_file($_FILES[$fieldName]["tmp_name"], $target_file)) {
                $this->log->addInfo("The file ". basename($_FILES[$fieldName]["name"]). " has been uploaded.");
                return $target_file;
            } else {
                $this->log->addInfo("There was an error uploading the file.");
                return NULL;
            }
        }
    }

    /**
     * @param $dir
     *  delete a directory with all files in it
     */
    public function deleteDir($dir){
        if (is_dir($dir)){
            $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it,
                RecursiveIteratorIterator::CHILD_FIRST);
            foreach($files as $file) {
                if ($file->isDir()){
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($dir);
        }
    }

    /**
     * @param $path
     * @return bool true if success
     * delete a file
     */
    public function deleteFile($path){
        if (file_exists($path)){
            return unlink($path);
        }
        return false;
    }

}
