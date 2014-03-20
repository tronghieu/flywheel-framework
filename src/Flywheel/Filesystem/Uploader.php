<?php

namespace Flywheel\Filesystem;


use Flywheel\Config\ConfigHandler;
use Flywheel\Util\Folder;

class Uploader {
    protected $_filterType;
    protected $_requiredCheckMimeType = true;
    protected $_allowedMineType = array();
    protected $_error = array();
    protected $_maxSize = 2; //2MB
    protected $_dir;
    protected $_data = array();
    protected $_encryptFileName = false;
    protected $_overwrite = false;
    protected $_ansiName = true;
    protected $_removeSpaceName = true;
    protected $_field;
    protected $_newName;
    protected $_fileMod = 0755;

    /**
     * Constructor
     *
     * @param string	$dir
     * @param string	$field
     * @param array		$config
     */
    public function __construct($dir, $field = null, $config = array()) {
        $this->_allowedMimeType = self::getDefinedMime();
        $this->_dir = $dir;
        $this->setFieldUpload($field);
        if (count($config) > 0) {
            if (isset($config['filter_type'])) {
                $this->setFilterType($config['filter_type']);
            }

            if (isset($config['max_size'])) {
                $this->setMaximumFileSize($config['max_size']);
            }

            if (isset($config['encrypt_file_name'])) {
                $this->setIsEncryptFileName($config['encrypt_file_name']);
            }

            if (isset($config['overwrite'])) {
                $this->setOverwriteIfExists($config['overwrite']);
            }

            if (isset($config['ansi_name'])) {
                $this->setUsingOnlyAnsiName($config['ansi_name']);
            }

            if (isset($config['remove_white_space'])) {
                $this->setRemoveWhiteSpaceInName($config['remove_white_space']);
            }

            if (isset($config['new_name']) && null != $config['new_name']) {
                $this->setNameAfterUploaded($config['new_name']);
            }
            if (isset($config['check_mime_type'])) {
                $this->setRequiredCheckMimeType($config['check_mime_type']);
            }

            if (isset($config['file_mode'])) {
                $this->setFilePermissionMod($config['file_mode']);
            }
        }
    }

    /**
     * set field name handler file upload
     * @param string		$field
     */
    public function setFieldUpload($field) {
        if ($this->_field != $field) {
            $this->_error = array(); //reset error file
            $this->_field = $field;
        }
    }

    /**
     * set has check mime type of file upload before move file upload
     *
     * @param boolean	$required
     */
    public function setRequiredCheckMimeType($required) {
        $this->_requiredCheckMimeType = (boolean) $required;
    }

    /**
     * set filter type filter mime type of file upload.
     * filter type is a string with file extension. Extension included has dot '.' or not.
     * you can filter one or many extension, each extension seperate with ',' char.
     * example: .jpg, .jpeg, .png, .bmp
     * @param string	$types
     */
    public function setFilterType($types) {
        $this->_filterType = $types;
    }

    /**
     * set max file upload size by Megabyte
     *
     * @param double $size
     */
    public function setMaximumFileSize($size) {
        $this->_maxSize = $size;
    }

    /**
     * set using encrypt file name after upload.
     * if set true this option. The file's name after upload has unique random string
     * @param boolean	$isEncrypt
     */
    public function setIsEncryptFileName($isEncrypt) {
        $this->_encryptFileName = (boolean) $isEncrypt;
    }

    /**
     * set overwrite exists file option
     *
     * @param boolean	$isOverwrite
     */
    public function setOverwriteIfExists($isOverwrite) {
        $this->_overwrite = (boolean) $isOverwrite;
    }

    /**
     * set using file name has only ansi character.
     *
     * @param boolean $isAnsiName
     */
    public function setUsingOnlyAnsiName($isAnsiName) {
        $this->_ansiName = (boolean) $isAnsiName;
    }

    /**
     * set remove white space file name
     * @param boolean $removeSpace
     */
    public function setRemoveWhiteSpaceInName($removeSpace) {
        $this->_removeSpaceName = (boolean) $removeSpace;
    }

    /**
     * set new name of file after uploaded
     * @param string	$name
     */
    public function setNameAfterUploaded($name) {
        $this->_newName = $name;
    }

    /**
     * set file permission
     *
     * @param integer	$mod
     */
    public function setFilePermissionMod($mod) {
        $this->_fileMod	= $mod;
    }

    /**
     * get original upload file name
     */
    public function getUploadFileName() {
        if ($this->_field != null) {
            return (isset($_FILES[$this->_field]['name']))
                ? $_FILES[$this->_field]['name'] : false;
        }

        return false;
    }

    /**
     * get Data of file after upload
     *
     * @return array
     */
    public function getData() {
        return $this->_data;
    }

    /**
     * do upload file
     * @param string	$field form input name of file upload
     *
     * @return boolean
     */
    public function upload($field = null) {
        if ($field != null) {
            $this->setFieldUpload($field);
        } else {
            $field = $this->_field;
        }

        if (false === $this->validate()) {
            return false;
        }

        $this->_dir = rtrim($this->_dir, DIRECTORY_SEPARATOR) .DIRECTORY_SEPARATOR;
        $this->_data['file_temp'] 		= $_FILES[$field]['tmp_name'];
        $this->_data['file_size'] 		= $_FILES[$field]['size'];
        $this->_data['file_origin_name']= $_FILES[$field]['name'];
        $this->_data['file_extension']	= $this->getExtension($this->_data['file_origin_name']);

        $this->_data['file_name'] 		= $this->_makeFileName();

        /*
         * Move the file to the final destination
         * To deal with different server configurations
         * we'll attempt to use copy() first.  If that fails
         * we'll use move_uploaded_file().  One of the two should
         * reliably work in most environments
         */
        if (!@copy($this->_data['file_temp'], $this->_dir .$this->_data['file_name'])) {
            if (!@move_uploaded_file($this->_data['file_temp'], $this->_dir .$this->_data['file_name'])) {
                $this->_error[] = 'Upload fail';
                return false;
            }

        }
        @chmod($this->_dir .$this->_data['file_name'], $this->_fileMod);
        return true;
    }

    /**
     * validate upload field
     * @param string $field
     *
     * @return boolean
     */
    public function validate($field = null) {
        if (null == $field) {
            $field = $this->_field;
        }
        $valid = true;

        if (null == $field || !isset($_FILES[$field])) {
            $this->_error[] = 'Empty file upload or file upload not found in temp dir';
            return false;
        }
        $this->_dir = Folder::clean($this->_dir);
        if (null == $this->_dir) {
            $this->_error[] = 'Upload directory empty';
            $valid = false;
        }

        if (!is_dir($this->_dir) || !is_writeable($this->_dir)) {
            $this->_error[] = 'Upload directory not found or can not writable';
            $valid = false;
        }

        if ($_FILES[$field]['error'] != 0) {
            switch ($_FILES[$field]['error']) {
                case 1:
                    $this->_error[] = 'The file is too large (server)';
                    break;
                case 2:
                    $this->_error[] = 'The file is too large (form)';
                    break;
                case 3:
                    $this->_error[] = 'The file was only partially uploaded';
                    break;
                case 4:
                    $this->_error[] = 'No file was uploaded';
                    break;
                case 5:
                    $this->_error[] = 'The servers temporary folder is missing';
                    break;
                case 6:
                    $this->_error[]  = 'Failed to write to the temporary folder';
                    break;
            }
            return false;
        }

        if (true === $this->_requiredCheckMimeType) {
            if (false === $this->checkMineType($field)) {
                $valid = false;
            }
        }

        //check file size
        if ($_FILES[$field]['size']/(1024*1024) > $this->_maxSize) {
            $this->_error[] = 'File size (' .$_FILES[$field]['size']/(1024*1024) .') is too big (more than allowed size:' .$this->_maxSize .' Mb)';
            $valid = false;
        }

        return $valid;
    }

    /**
     * check mine type of file upload
     *
     * @param $field
     * @return boolean
     */
    public function checkMineType($field) {
        if (null != $this->_filterType) { //defined filter by Type
            $ext = explode(',', $this->_filterType);
            $mime = array();
            $mime = array();
            for ($i = 0; $i < sizeof($ext); ++$i) {
                $ext[$i] = strtolower(ltrim(trim($ext[$i]), '.'));
                $mime[$ext[$i]] = $this->_allowedMimeType[$ext[$i]];
            }
        } else {
            $mime = $this->_allowedMimeType;
        }
        $ext = $this->getExtension($_FILES[$field]['name'], false);
        $expectMimeType = $this->getMimeTypeByExtension($ext, $mime);

        $fileMimeType = $this->_getUploadedFileMimeType($field);

        if (is_array($expectMimeType)) {
            if (!in_array($fileMimeType, $expectMimeType)) {
                $this->_error[] = 'Mime (' .$ext .'-' .$fileMimeType .') type does not allow';
                return false;
            }
        } elseif (is_string($expectMimeType)) {
            if ($expectMimeType != $fileMimeType) {
                $this->_error[] = 'Mime (' .$ext .'-' .$fileMimeType .') type does not allow';
                return false;
            }
        } elseif (false == $expectMimeType) {
            $this->_error[] = 'Mime (' .$ext .'-' .$fileMimeType .') type does not allow';
            return false;
        }

        return true;
    }

    /**
     * get file extension
     * @param string	$fileName
     * @param boolean	$includeDot return extension include dot char like ".jpg"
     *
     * @return string
     */
    public function getExtension($fileName, $includeDot = true) {
        $x = explode('.', $fileName);
        return ($includeDot)? '.' .strtolower(end($x)) : strtolower(end($x));
    }

    /**
     * get mime type by file extension
     * @param string	$extension
     * @param array		$mime. default null, mean using defined system's mime type
     *
     * @return mixed string|array
     * 				false	if extension not has defined mime type
     */
    public function getMimeTypeByExtension($extension, $mime = null) {
        if (null === $mime || !is_array($mime) || count($mime) == 0) {
            $mime = $this->_allowedMimeType;
        }

        return ((isset($mime[$extension]))? $mime[$extension] : false);
    }

    /**
     * make file name after upload
     *
     * @return string filename included extension
     */
    protected function _makeFileName() {
        if (true == $this->_encryptFileName) {
            return (uniqid() .$this->_data['file_extension']);
        }

        if (null != $this->_newName) {
            $name = Folder::cleanFileName($this->_newName);
            $name = str_replace($this->_data['file_extension'], '', $name);
        } else {
            $name = Folder::cleanFileName($this->_data['file_origin_name']);
            $name = str_replace($this->_data['file_extension'], '', $name);
        }

        if (false !== $this->_ansiName) {
            $name = preg_replace('/[^A-Za-z0-9_\-]/', '', $name);
        }

        if (false !== $this->_removeSpaceName) {
            $name = preg_replace('/\s+/', '-', $name);
        }

        if (true !== $this->_overwrite
            && (file_exists($this->_dir .$name .$this->_data['file_extension']))) {
            $i = 1;
            do {
                $_t = $name .'(' .$i .')';
                ++$i;
            } while (file_exists($this->_dir .$_t .$this->_data['file_extension']));
            $name = $_t;
        }

        return $name .$this->_data['file_extension'];
    }

    /**
     * @return array|bool
     */
    public function hasError() {
        return (boolean) sizeof($this->_error);
    }

    public function getError() {
        return $this->_error;
    }

    /**
     * reset config
     * @param boolean	 $includeResetMimeType
     */
    public function reset($includeResetMimeType = false) {
        $this->_filterType = null;
        if (true === $includeResetMimeType) {
            $this->_allowedMimeType = array();
        }
        $this->_error = array();
        $this->_maxSize = 2; //2MB
        $this->_dir = null;
        $this->_data = array();
        $this->_encryptFileName = false;
        $this->_overwrite = false;
        $this->_ansiName = true;
        $this->_removeSpaceName = true;
        $this->_field = null;
    }

    /**
     * get uploaded file's mimetype
     *
     * @param $field
     * @return mixed|null|string
     */
    private function _getUploadedFileMimeType($field) {
        if(function_exists('mime_content_type')) {
            $mime_type = mime_content_type($_FILES[$field]['tmp_name']);
            return $mime_type;
        }

        if(function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mime_type = finfo_file($finfo, $_FILES[$field]['tmp_name']);
            finfo_close($finfo);
            return $mime_type;
        }

        return null;
    }

    /**
     * @return array
     */
    public static function getDefinedMime() {
        return array(	'hqx'	=>	'application/mac-binhex40',
            'cpt'	=>	'application/mac-compactpro',
            'csv'	=>	array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel'),
            'bin'	=>	'application/macbinary',
            'dms'	=>	'application/octet-stream',
            'lha'	=>	'application/octet-stream',
            'lzh'	=>	'application/octet-stream',
            'exe'	=>	'application/octet-stream',
            'class'	=>	'application/octet-stream',
            'psd'	=>	'application/x-photoshop',
            'so'	=>	'application/octet-stream',
            'sea'	=>	'application/octet-stream',
            'dll'	=>	'application/octet-stream',
            'oda'	=>	'application/oda',
            'pdf'	=>	array('application/pdf', 'application/x-download'),
            'ai'	=>	'application/postscript',
            'eps'	=>	'application/postscript',
            'ps'	=>	'application/postscript',
            'smi'	=>	'application/smil',
            'smil'	=>	'application/smil',
            'mif'	=>	'application/vnd.mif',
            'xls'	=>	array('application/excel', 'application/vnd.ms-excel', 'application/msexcel'),
            'ppt'	=>	array('application/powerpoint', 'application/vnd.ms-powerpoint'),
            'wbxml'	=>	'application/wbxml',
            'wmlc'	=>	'application/wmlc',
            'dcr'	=>	'application/x-director',
            'dir'	=>	'application/x-director',
            'dxr'	=>	'application/x-director',
            'dvi'	=>	'application/x-dvi',
            'gtar'	=>	'application/x-gtar',
            'gz'	=>	'application/x-gzip',
            'php'	=>	'application/x-httpd-php',
            'php4'	=>	'application/x-httpd-php',
            'php3'	=>	'application/x-httpd-php',
            'phtml'	=>	'application/x-httpd-php',
            'phps'	=>	'application/x-httpd-php-source',
            'js'	=>	'application/x-javascript',
            'swf'	=>	'application/x-shockwave-flash',
            'sit'	=>	'application/x-stuffit',
            'tar'	=>	'application/x-tar',
            'tgz'	=>	'application/x-tar',
            'xhtml'	=>	'application/xhtml+xml',
            'xht'	=>	'application/xhtml+xml',
            'zip'	=>  array('application/x-zip', 'application/zip', 'application/x-zip-compressed'),
            'rar'	=> 	array('application/x-rar-compressed', 'application/x-rar'),
            'rev'	=>	array('application/x-rar-compressed', 'application/x-rar'),
            'mid'	=>	'audio/midi',
            'midi'	=>	'audio/midi',
            'mpga'	=>	'audio/mpeg',
            'mp2'	=>	'audio/mpeg',
            'mp3'	=>	array('audio/mpeg', 'audio/mpg'),
            'aif'	=>	'audio/x-aiff',
            'aiff'	=>	'audio/x-aiff',
            'aifc'	=>	'audio/x-aiff',
            'ram'	=>	'audio/x-pn-realaudio',
            'rm'	=>	'audio/x-pn-realaudio',
            'rpm'	=>	'audio/x-pn-realaudio-plugin',
            'ra'	=>	'audio/x-realaudio',
            'rv'	=>	'video/vnd.rn-realvideo',
            'wav'	=>	'audio/x-wav',
            'bmp'	=>	'image/bmp',
            'gif'	=>	'image/gif',
            'jpeg'	=>	array('image/jpeg', 'image/pjpeg'),
            'jpg'	=>	array('image/jpeg', 'image/pjpeg'),
            'jpe'	=>	array('image/jpeg', 'image/pjpeg'),
            'png'	=>	array('image/png',  'image/x-png'),
            'tiff'	=>	'image/tiff',
            'tif'	=>	'image/tiff',
            'css'	=>	'text/css',
            'html'	=>	'text/html',
            'htm'	=>	'text/html',
            'shtml'	=>	'text/html',
            'txt'	=>	'text/plain',
            'text'	=>	'text/plain',
            'log'	=>	array('text/plain', 'text/x-log'),
            'rtx'	=>	'text/richtext',
            'rtf'	=>	'text/rtf',
            'xml'	=>	'text/xml',
            'xsl'	=>	'text/xml',
            'mpeg'	=>	'video/mpeg',
            'mpg'	=>	'video/mpeg',
            'mpe'	=>	'video/mpeg',
            'qt'	=>	'video/quicktime',
            'mov'	=>	'video/quicktime',
            'avi'	=>	'video/x-msvideo',
            'movie'	=>	'video/x-sgi-movie',
            'doc'	=>	'application/msword',
            'docx'	=>	'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx'	=>	'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'word'	=>	array('application/msword', 'application/octet-stream'),
            'xl'	=>	'application/excel',
            'eml'	=>	'message/rfc822'
        );
    }

    public function __destruct() {
        $this->_error = array();
        $this->_allowedMimeType = array();
        $this->_data = array();
    }
}