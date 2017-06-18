<?php
namespace Hiokwee\Filemanager;

require_once('RuntimeException.php');

/**
 * Class File
 *
 * @version 1.0.2
 * @package Filemanager
 */
class File
{
	/** @const SYSFOLDER system folder (.Fileupload/) */
	const SYSFOLDER = ".Fileupload/";
	/** @const ALLOWEDEXT default permitted extension types (jpg, jpeg, gif, png, pdf, doc, txt) */
	const ALLOWEDEXT = ['jpg','jpeg','gif','png','pdf','doc','txt'];
	/** @const MAXEXTLEN default max permitted extension length (5) */
	const MAXEXTLEN = 5;
	/** @const MAXFILESIZE default max permitted file size in bytes (500000) */
	const MAXFILESIZE = 500000;
	/** @const ONLYIMG default control for only allowing image files (false) */
	const ONLYIMG = false;
	/** @const SCANFILE default av scan setting (true) */
	const SCANFILE = true;

	/** @var string $target_dir Taregt directory where the files will be saved */
	private $target_dir;
	/** @var int $max_size max permitted file size in bytes */
	private $max_file_size;
	/** @var string[] $allowed_formats permitted extension types */
	private $allowed_formats;
	/** @var bool $image_only control for only allowing image files */
	private $image_only;
	/** @var bool $image_only control for av scan */
	private $scan_file;


	/**
	 * Instantiate a Hiokwee\Filemanager instance
	 *
	 * Check if $_target_dir exists and initialise variables to their default values
	 * The system folder self::SYSFOLDER will be created if it does not exist
	 * 
	 * @throws RuntimeException
	 * @param string $_target_dir The target directory
	 */
	function __construct($_target_dir) {

		//remove empty space and
		//enforce folder restriction. it should neither be empty or should the folder specified be hidden

		$_target_dir = trim($_target_dir);
		if ($_target_dir === "" || substr($_target_dir, 0, 1) === "." ) {
			throw new RuntimeException("Invalid folder");
		}

		//append '/' to the string if needed
		if (substr($_target_dir, strlen($_target_dir)-1) !== "/") {
			$_target_dir .= "/";
		}

		//check if folder exists; module will not handle folder creation
		//create system folder if it does not exist
		if (is_dir($_target_dir)) {
			$this->target_dir = $_target_dir;
			if (!is_dir(self::SYSFOLDER)) {
				if (!mkdir(self::SYSFOLDER, 0700, true)) {
					throw new RuntimeException("Failed to create system folder");
				}
			}
		}
		else {
			throw new RuntimeException("Folder not found");
		}

		//initialise variables
		$this->allowed_ext = self::ALLOWEDEXT;
		$this->max_file_size = self::MAXFILESIZE;
		$this->only_image = self::ONLYIMG;
	}

	/**
	 * Upload the file specified
	 *
	 * The actual file will be renamed to its md5 hash and saved to the system folder
	 * A symlink will be created using the original filename in the specified folder
	 * Subsequent file with the same md5 hash will be represented using symlink
	 * A 0 byte touch file will also be created to help determine if there are any symlinks left
	 * Using ClamAV for anti-virus scan
	 *
	 * @see https://packagist.org/packages/xenolope/quahog PHP client library for ClamAV clamd daemon
	 * @see https://www.clamav.net Open source antivirus engine
	 * @throws RuntimeException Any errors encountered during upload
	 * @param string $param_name The HTML form input field name
	 * @return bool Return true if the upload was successful
	 */
	function upload($param_name) {

		$target_tmp_path = $_FILES[$param_name]["tmp_name"];

		/* define names of the 3 files required and their file paths */
		//symlink with the original filename
		$target_link = $_FILES[$param_name]["name"];
		$target_link_path = $this->target_dir . $target_link; //user defined directory

		//actual file which will be renamed to its hash
		$target_file = md5_file($target_tmp_path);
		$target_file_path = self::SYSFOLDER . $target_file; //system folder

		//0 byte file for detecting any remaining symlinks
		$target_touch_file = $target_file . "_" .  str_replace("/", "_", $target_link_path);
		$target_touch_file_path = self::SYSFOLDER . $target_touch_file; //system folder


		//0.check error code
		if (!isset($_FILES[$param_name]["error"]) || is_array($_FILES[$param_name]["error"])) {
			throw new RuntimeException("Invalid parameters");
		}
		switch ($_FILES[$param_name]["error"]) {
			case UPLOAD_ERR_OK:
				break;
			case UPLOAD_ERR_NO_FILE:
				error_log("No file sent");
				return false;
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				error_log("Exceeded filesize limit");
				return false;
			default:
				error_log("Unknown errors");
				return false;
		}

		//1.check file extension
		$ext = strtolower(pathinfo($target_link_path, PATHINFO_EXTENSION));
		if (in_array($ext, $this->allowed_ext) === false){
			error_log("File type not supported");
			return false;
		}

		//2.check if image file
		if ($this->image_only) {
			$image_size = getimagesize($target_tmp_path);
			if ($image_size === false) {
				error_log("Not an image file");
				return false;
			}
		}

		//3.check if file already exists
		if (is_link($target_link_path)) {
			error_log("File with the same name exists");
			return false;
		}

		//4.check file size
		if ($_FILES[$param_name]["size"] > $this->max_file_size) {
			error_log("Exceeded max file size");
			return false;
		}

		//5.anti-virus scan
		if ($this->scan_file) {
			$socket = (new \Socket\Raw\Factory())->createClient('unix:///var/run/clamav/clamd.ctl');
			$quahog = new \Xenolope\Quahog\Client($socket);
			$result = $quahog->scanStream([$target_tmp_path], 1024);
			//$result = $quahog->scanStream(file_get_contents([$target_tmp_path]), 1024);
			//$result = $quahog->scanFile([$target_tmp_path]);

			if ($result['status'] !== 'OK') {
				error_log("Failed virus scan (" . $result['reason'] . ")");
				return false;
			}
		}

		//copy & rename file to system folder if it does not exist
		if (!file_exists($target_file_path)) {
			//new file
			if (!move_uploaded_file($target_tmp_path, $target_file_path)) {
				throw new RuntimeException("Failed to save file");
			}
		}

		//proceed to create symlink and touch file
		if (!touch($target_touch_file_path)) {
			throw new RuntimeException("Failed to create touch file");
		}
		if (!symlink($target_file_path, $target_link_path)) {
			throw new RuntimeException("Failed to create link");
		}

		return true;
	}

	/**
	 * Delete file by name
	 *
	 * The symlink with the same name will be unlinked
	 * At the same time, the matching touch file in the system folder will be deleted
	 *
	 * @throws RuntimeException
	 * @param string $name Name of the file
	 * @return bool Return true if the file was deleted
	 */
	function delFileByName($name) {
		$target_link_path = $this->target_dir . $name;

		//check if file exists
		if (!is_link($target_link_path)) {
			error_log($name . " link does not exist");
			return false;
		}

		//construct touch file path
		$target_touch_file_path = str_replace("/", "_", $target_link_path);
		$target_file_path = readlink($target_link_path);
		$target_touch_file_path = $target_file_path . "_" . $target_touch_file_path;

		if (!unlink($target_link_path)) {
			throw new RuntimeException("Failed to delete link");
		}
		if (!unlink($target_touch_file_path)) {
			throw new RuntimeException("Failed to delete touch file");
		}

		//check if there are any remaining symlinks of the file (by checking the number of touch file left)
		$files = glob($target_file_path . "*");

		//remove 'actual file' if no more symlink left
		if (count($files) == 1) {
			if (!unlink($target_file_path)) {
				throw new RuntimeException("Failed to delete file");
			}
 		}

		return true;
	}

	/**
	 * Download file by name
	 *
	 * @throws RuntimeException
	 * @param string $name Name of the file
	 * @return bool Return true if the download was successful
	 */
	function getFileByName($name) {
		$target_link_path = $this->target_dir . $name;

		//check if link/file exists
		if (!is_link($target_link_path)) {
			error_log($name . " link does not exist");
			return false;
		}
		$target_file_path = readlink($target_link_path);
		if (!file_exists($target_file_path)) {
			throw new RuntimeException("File not found");
		}

		//proceed to download file
		header("Content-Type: application/octet-stream");
		header("Content-Transfer-Encoding: Binary");
		header("Content-disposition: attachment; filename=\"" . basename($target_link_path) . "\"");
		set_time_limit(0);
		$file = @fopen($target_file_path,"rb");
		while(!feof($file))
		{
			print(@fread($file, 1024*8));
			ob_flush(); //discard any data in the output buffer (if possible)
			flush(); // flush headers (if possible)
		}
		exit();
		return true;
	}


	/**
	 * Return names of files found in the folder
	 *
	 * @return string The JSON file list
	 */
	function getFileList() {
		$files_in_dir = scandir($this->target_dir, 0);
		$files_in_dir = array_diff($files_in_dir, array('..', '.'));
		return json_encode(array_values($files_in_dir));
	}


	/**
	 * Return the target folder
	 *
	 * @return string Name of the target folder
	 */
	function getTargetFolder() {
		return $this->target_dir;
	}


	/**
	 * Set the permitted file extension types
	 * Max extension length determined by self::MAXEXTLEN
	 *
	 * @throws RuntimeException
	 * @param string[] $extensions The permitted extension types
	 */
	function setAllowedExtensions($extensions) {
		if (!is_array($extensions)) {
			throw new RuntimeException("Invalid parameter");
		}
		foreach ($extensions as $keys => $extension) {
			//check validity of extension
			if (!ctype_alpha($extension) || strlen($extension) > self::MAXEXTLEN) {
				throw new RuntimeException("Invalid extension format");
			}
			$extensions[$keys] = strtolower($extension);
		}
		$this->allowed_ext = array_unique($extensions);
	}


	/**
	 * Set if only image files are allowed
	 *
	 * @throws RuntimeException
	 * @param bool $image_only Only image files are permitted
	 */
	function setOnlyAllowImage($image_only) {
		if (!is_bool($image_only)) {
			throw new RuntimeException("Invalid parameter");
		}
		$this->image_only = $image_only;
	}


	/**
	 * Set the maximum permitted file size
	 *
	 * @throws RuntimeException
	 * @param int $max_file_size Maximum permitted file size in bytes
	 */
	function setMaxFileSize($max_file_size) {
		if (!is_int($max_file_size)) {
			throw new RuntimeException("Invalid parameter");
		}
		$this->max_file_size = $max_file_size;
	}

	/**
	 * Enable clamav anti-virus scan
	 *
	 * @throws RuntimeException
	 * @param bool $scan_file Enable anti-virus file scan
	 */
	function setScanFile($scan_file) {
		if (!is_bool($scan_file)) {
			throw new RuntimeException("Invalid parameter");
		}
		$this->scan_file = $scan_file;
	}
}
?>