# Filemanager

> ### methods

1. **File (string  $_target_dir)** <br />
*$_target_dir*  The target directory where the files will be saved

2. **upload (string  $param_name) : boolean** <br />
*$param_name*  The HTML form input field name

3. **delFileByName (string  $name) : boolean** <br />
*$name*  Name of the file

4. **getFileByName (string  $name) : boolean** <br />
*$name*  Name of the file

5. **getFileList () : string** <br />

6. **getTargetFolder() : string** <br />
  
7. **setAllowedExtensions (array\<mixed,string\>  $extensions)** <br />
*$extensions* The permitted extension types

8. **setOnlyAllowImage(boolean  $image_only)** <br />
*$image_only*  Only image files are permitted

9. **setMaxFileSize(integer  $max_size)** <br />
*$max_size*  Maximum permitted file size in bytes

10. **setScanFile(boolean  $scan_file)** <br />
*$scan_file*  Enable anti-virus file scan




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
