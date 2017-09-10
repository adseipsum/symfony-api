<?php

namespace AppBundle;

class Utils
{

    /**
     * create file with content, and create folder structure if doesn't exist
     * @param String $filepath
     * @param String $message
     */
    public function forceFilePutContents($filepath, $message)
    {
        try {
            $isInFolder = preg_match("/^(.*)\/([^\/]+)$/", $filepath, $filepathMatches);
            if ($isInFolder) {
                $folderName = $filepathMatches[1];
                $fileName = $filepathMatches[2];
                if (!is_dir($folderName)) {
                    mkdir($folderName, 0777, true);
                }
            }
            file_put_contents($filepath, $message);
        } catch (Exception $e) {
            echo "ERR: error writing '$message' to '$filepath', ". $e->getMessage();
        }
    }

}