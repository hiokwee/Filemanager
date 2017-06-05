# Filemanager

> ### methods

1. **File(string  $_target_dir)**
*** *$_target_dir	The target directory where the files will be saved*

**upload(string  $param_name) : boolean**__
__*$param_name	The HTML form input field name*

**delFileByName(string  $name) : boolean**
*$name		Name of the file*

**getFileByName(string  $name) : boolean**
*$name		Name of the file*

**getFileList() : string**
*getTargetFolder() : string*
  
**setAllowedExtensions(array<mixed,string>  $extensions)**
*$extensions	The permitted extension types*

**setOnlyAllowImage(boolean  $image_only)**
*$image_only	Only image files are permitted*

**setMaxFileSize(integer  $max_size)** 
*$max_size	Maximum permitted file size in bytes*

**setScanFile(boolean  $scan_file)**
*$scan_file	Enable anti-virus file scan*


> ### example

```php
$fm = new File("uploads/");
$fm->setAllowedExtensions(["PNG", "GIF", "TXT"]);
$fm->setOnlyAllowImage(true);
$fm->setMaxFileSize(5120);
$fm->setScanFile(true);

if (isset($_POST["act"])) {

        // upload file
        if ($_POST["act"] === "upload") {
                try {
                        $fm->upload("fileToUpload");
                }
                catch (Exception $e) {
                }
        }
        
        // download file by name
        elseif ($_POST["act"] === "download") {
                if (isset($_POST["filename"])) {
                        try {
                                $fm->getFileByName($_POST["filename"]);
                        }
                        catch (Exception $e) {
                        }
                }
        }
        
        // delete file by name
        elseif ($_POST["act"] === "delete") {
                if (isset($_POST["filename"])) {
                        try {
                                $fm->delFileByName($_POST["filename"]);
                        }
                        catch (Exception $e) {
                        }
                }
        }
}

```
