<?php
namespace Flywheel\Filesystem;


use Flywheel\Filter\Input;
use InvalidArgumentException;

class Image {
    /**
     * The prior image (before manipulation)
     *
     * @var resource
     */
    protected $_source;

    /**
     * The working image (used during manipulation)
     *
     * @var resource
     */
    protected $_workingImage;

    /**
     * The working image file extension include dot "."
     *
     * @var string
     */
    protected $_workingImageExtension;

    /**
     * The name of the file we're manipulating
     *
     * This must include the path to the file (absolute paths recommended)
     *
     * @var string
     */
    protected $_fileName;

    /**
     * The current dimensions of the image
     *
     * @var array
     */
    protected $_currentDimensions = array();

    protected $_mime;

    /**
     * The options for this class
     *
     * This array contains various options that determine the behavior in
     * various functions throughout the class.  Functions note which specific
     * option key / values are used in their documentation
     *
     * @var array
     */
    protected $_options = array('resize_up'				=> true,
        'jpeg_quality'			=> 100,
        'correct_permissions'	=> false,
        'preserve_alpha'		=> true,
        'alpha_mask_color'		=> array(255, 255, 255),
        'preserve_transparency'	=> true,
        'transparency_mask_color'=> array(255, 255, 255));

    /**
     * option using exception
     */
    protected $_exception = true;

    /**
     * The last error message raised
     *
     * @var array
     */
    protected $_error = array();

    /**
     * Class Constructor
     *
     * @param string $fileName
     * @param array $options
     * @param bool $isDataStream
     */
    public function __construct($fileName, $options = array(), $isDataStream = false) {
        $this->_fileName	= $fileName;

        if (true === $isDataStream) {
            $this->_source = @imagecreatefromstring($fileName);
            if($this->_source) {
                $this->_currentDimensions = array (
                    'width' 	=> imagesx($this->_source),
                    'height'	=> imagesy($this->_source)
                );
            } else $this->_source = null;
        } else {
            if (!file_exists($fileName)) {
                $this->_triggerError("File {$fileName} không tồn tại",
                    new \Exception("File {$fileName} not found!"));
            }
            $size = @getimagesize(htmlentities($fileName));
            if (false === $size) {
                $this->_triggerError('File ' .$fileName .' xử lý không đúng định dạng file ảnh',
                    new \Exception("File {$fileName} not valid image!"));
                return false;
            }

            switch ($size['mime']) {
                case 'image/gif':
                    $this->_workingImageExtension = '.gif';
                    $this->_source = imagecreatefromgif($fileName);
                    break;
                case 'image/jpeg':
                    $this->_workingImageExtension = '.jpg';
                    $this->_source = imagecreatefromjpeg($fileName);
                    break;
                case 'image/png':
                    $this->_workingImageExtension = '.png';
                    $this->_source = imagecreatefrompng($fileName);
                    break;
                default:
                    $this->_triggerError('Không hỗ trợ định dạng: ' .$size['mime'],
                        new \Exception("Image format not supported:" .$size['mime']));
            }
            $this->_mime = $size['mime'];

            $this->_currentDimensions = array (
                'width'		=> $size[0],
                'height'	=> $size[1]
            );
        }

        $this->setOptions($options);

        // TODO: Port gatherImageMeta to a separate function that can be called to extract exif data
    }

    /**
     * set using exception
     *
     * @param boolean $bol
     */
    public function usingException($bol) {
        $this->_exception = (boolean) $bol;
    }

    /**
     * get image source mime
     *
     * @return string
     */
    public function getMime() {
        return $this->_mime;
    }

    /**
     * get current working dimensions
     *
     * @return array('width'	=> working image's width,
     * 				'height'	=> working image's height)
     */
    public function getCurrentDimensions() {
        return $this->_currentDimensions;
    }

    /**
     * return source image file's extension (included dot)
     *
     * @return string
     */
    public function getSourceImageExtension() {
        return $this->_workingImageExtension;
    }

    /**
     * config resize up
     *
     * @param boolean $isUp
     */
    public function setResizeUp($isUp) {
        $this->_options['resize_up'] = (boolean) $isUp;
    }

    /**
     * config set jpeg quality (in percent) for working image
     *
     * @param int $quality
     */
    public function setJpegQuality($quality) {
        $this->_options['jpeg_quality'] = (int) $quality;
    }

    /**
     * set correct permission
     *
     * @param boolean $correct
     */
    public function setCorrectPermission($correct) {
        $this->_options['correct_permissions'] = (boolean) $correct;
    }

    /**
     * set enable preserver alpha
     *
     * @param boolean $preserveAlpha
     */
    public function setPreserverAlpha($preserveAlpha) {
        $this->_options['preserve_alpha'] = (boolean) $preserveAlpha;
    }

    /**
     * set alpha mask color (RGB)
     *
     * @param array $rgbColorValues
     */
    public function setAlphaMaskColor($rgbColorValues) {
        $this->_options['alpha_mask_color'] = $rgbColorValues;
    }

    /**
     * set enable preserver transparency
     *
     * @param boolean $preserver
     */
    public function setPreserverTransparency($preserver) {
        $this->_options['preserve_transparency'] = (boolean) $preserver;
    }

    /**
     * set enable transparency mask color
     *
     * @param boolean $rgbColorValues
     */
    public function setTransparencyMaskColor($rgbColorValues) {
        $this->_options['transparency_mask_color'] = $rgbColorValues;
    }


    /**
     * Class Destructor
     *
     */
    public function __destruct () {
        if (is_resource($this->_source)) {
            imagedestroy($this->_source);
        }

        if (is_resource($this->_workingImage)) {
            imagedestroy($this->_workingImage);
        }
    }

    /**
     * Sets $this->options to $options
     *
     * @param array $options
     */
    public function setOptions($options = array()) {
        if (is_array($options)) {
            $this->_options = array_merge($this->_options, $options);
        }
    }

    /**
     * has error
     * @return boolean
     */
    public function hasError() {
        return (boolean) sizeof($this->_error);
    }

    /**
     * get error message
     *
     * @return array
     */
    public function getErrorMessage() {
        return $this->_error;
    }

    /**
     * Resizes an image to be no larger than $maxWidth or $maxHeight
     *
     * If either param is set to zero, then that dimension will not be considered as a part of the resize.
     * Additionally, if $this->_options['resize_up'] is set to true (false by default), then this function will
     * also scale the image up to the maximum dimensions provided.
     *
     * @param int $maxWidth The maximum width of the image in pixels
     * @param int $maxHeight The maximum height of the image in pixels
     * @return boolean
     */
    public function resize($maxWidth, $maxHeight = 0) {
        $maxWidth	= Input::clean($maxWidth, 'INT', 0);
        $maxHeight	= Input::clean($maxHeight, 'INT', 0);

        if ($maxWidth == 0 && $maxHeight == 0) {
            $this->_triggerError('Ít nhất chiều rộng hoặc chiều dài > 0.',
                new InvalidArgumentException("maxWidth or maxHeight must be numeric and greater than zero"));
            return false;
        }

        if (false === $this->_options['resize_up']) {
            $maxWidth	= ($maxWidth > $this->_currentDimensions['width'])?
                $this->_currentDimensions['width']: $maxWidth;
            $maxHeight	= ($maxHeight > $this->_currentDimensions['height'])?
                $this->_currentDimensions['height'] : $maxHeight;
        }

        $dimensions = $this->_calcImageSize($this->_currentDimensions['width'], $this->_currentDimensions['height'], $maxWidth, $maxHeight);

        // create the working image
        if (function_exists('imagecreatetruecolor')) {
            $this->_workingImage = imagecreatetruecolor($dimensions['newWidth'], $dimensions['newHeight']);
        }
        else {
            $this->_workingImage = imagecreate($dimensions['newWidth'], $dimensions['newHeight']);
        }

        $this->_preserveAlpha();

        // and create the newly sized image
        imagecopyresampled(
            $this->_workingImage,
            $this->_source,
            0, 0, 0, 0,
            $dimensions['newWidth'],
            $dimensions['newHeight'],
            $this->_currentDimensions['width'],
            $this->_currentDimensions['height']
        );

        $this->_source						= $this->_workingImage;
        $this->_currentDimensions['width'] 	= $dimensions['newWidth'];
        $this->_currentDimensions['height'] = $dimensions['newHeight'];
        return true;
    }

    /**
     * Adaptively Resizes the Image
     *
     * This function attempts to get the image to as close to the provided dimensions as possible, and then crops the
     * remaining overflow (from the center) to get the image to be the size specified
     *
     * @param int $maxWidth
     * @param int $maxHeight
     * @return boolean
     */
    public function adaptiveResize ($maxWidth, $maxHeight = 0) {
        $maxWidth	= Input::clean($maxWidth, 'INT');
        $maxHeight	= Input::clean($maxHeight, 'INT');
        if ($maxWidth == 0 && $maxHeight == 0) {
            $this->_triggerError('Ít nhất chiều rộng hoặc chiều dài > 0.',
                new InvalidArgumentException("maxWidth or maxHeight must be numeric and greater than zero"));
            return false;
        }

        if (false === $this->_options['resize_up']) {
            $maxWidth	= ($maxWidth > $this->_currentDimensions['width'])?
                $this->_currentDimensions['width']: $maxWidth;
            $maxHeight	= ($maxHeight > $this->_currentDimensions['height'])?
                $this->_currentDimensions['height'] : $maxHeight;
        }

        $dimensions = $this->_calcImageSizeStrict($this->_currentDimensions['width'], $this->_currentDimensions['height'], $maxWidth, $maxHeight);
        if (false === $this->resize($dimensions['newWidth'], $dimensions['newHeight'])) {
            return false;
        }

        $cropX 		= 0;
        $cropY 		= 0;

        // now, figure out how to crop the rest of the image...
        if ($this->_currentDimensions['width'] > $maxWidth) {
            $cropX = ceil(($this->_currentDimensions['width'] - $maxWidth) / 2);
        }
        elseif ($this->_currentDimensions['height'] > $maxHeight) {
            $cropY = ceil(($this->_currentDimensions['height'] - $maxHeight) / 2);
        }

        return $this->crop($cropX, $cropY, $maxWidth, $maxHeight);
    }

    /**
     * Resize an image by given percent uniformly
     *
     * Percentage should be whole number representation
     *
     * @param int $percent
     * @return bool
     */
    public function resizePercent($percent = 0) {
        $percent = Input::clean($percent, 'INT', 0);
        if (0 == $percent) {
            $this->_triggerError('Tỉ lệ phần trăm phải lớn hơn 0.',
                new InvalidArgumentException("percent must be numeric and greater than zero"));
            return false;
        }
    }

    /**
     * Vanilla Cropping - Crops from x,y with specified width and height
     *
     * @param int $startX
     * @param int $startY
     * @param int $cropWidth
     * @param int $cropHeight
     * @return bool
     */
    public function crop($startX, $startY, $cropWidth, $cropHeight) {
        // do some calculations
        $cropWidth	= ($this->_currentDimensions['width'] < $cropWidth)?
            $this->_currentDimensions['width'] : $cropWidth;
        $cropHeight = ($this->_currentDimensions['height'] < $cropHeight)?
            $this->_currentDimensions['height'] : $cropHeight;

        // ensure everything's in bounds
        if (($startX + $cropWidth) > $this->_currentDimensions['width']) {
            $startX = ($this->_currentDimensions['width'] - $cropWidth);

        }

        if (($startY + $cropHeight) > $this->_currentDimensions['height']) {
            $startY = ($this->_currentDimensions['height'] - $cropHeight);
        }

        if ($startX < 0) {
            $startX = 0;
        }

        if ($startY < 0) {
            $startY = 0;
        }

        // create the working image
        if (function_exists('imagecreatetruecolor')) {
            $this->_workingImage = imagecreatetruecolor($cropWidth, $cropHeight);
        }
        else {
            $this->_workingImage = imagecreate($cropWidth, $cropHeight);
        }

        $this->_preserveAlpha();

        imagecopyresampled (
            $this->_workingImage,
            $this->_source,
            0, 0, $startX, $startY,
            $cropWidth, $cropHeight, $cropWidth, $cropHeight
        );

        $this->_source 						= $this->_workingImage;
        $this->_currentDimensions['width'] 	= $cropWidth;
        $this->_currentDimensions['height'] = $cropHeight;
        return true;
    }

    /**
     * crops and image from the center with provided dimensions
     *  if no height is given, the width will be used as a height, thus creating a square crop
     * @param int	$cropWidth
     * @param int	$cropHeight
     *
     * @return boolean
     * @throws InvalidArgumentException
     */
    public function cropFromCenter($cropWidth, $cropHeight = null) {
        $cropWidth = Input::clean($cropWidth, 'INT', 0);
        if (null != $cropHeight) {
            $cropHeight = Input::clean($cropHeight, 'INT', 0);
        } else {
            $cropHeight = $cropWidth;
        }

        if (0 == $cropWidth && 0 == $cropHeight) {
            $this->_triggerError('Ít nhất chiều rộng hoặc chiều dài > 0.',
                new InvalidArgumentException("cropWidth or cropHeight must be numeric and greater than zero"));
            return false;
        }

        $cropWidth = ($this->_currentDimensions['width'] < $cropWidth) ?
            $this->_currentDimensions['width'] : $cropWidth;
        $cropHeight = ($this->_currentDimensions['height'] < $cropHeight)?
            $this->_currentDimensions['height'] : $cropHeight;

        $cropX = intval(($this->_currentDimensions['width'] - $cropWidth)/2);
        $cropY = intval(($this->_currentDimensions['height'] - $cropHeight)/2);

        return $this->crop($cropX, $cropY, $cropWidth, $cropHeight);
    }

    /**
     * resize and make an square image with new dimension
     *  if no newWidth given, the smallest side will be used as newWidth
     *
     * @param int $newWidth
     *
     * @return boolen
     * @throws InvalidArgumentException
     */
    public function square($newWidth = null) {
        if (null == $newWidth) {
            $newWidth = ($this->_currentDimensions['width'] < $this->_currentDimensions['height'])?
                $this->_currentDimensions['width'] : $this->_currentDimensions['height'];
        } else {
            $newWidth = Input::clean($newWidth, 'INT', 0);
            if (0 === $newWidth) {
                $this->_triggerError('kích thước hình phải lớn hơn 0.',
                    new \InvalidArgumentException('newWidth must be numeric and greater than zero.'));
                return false;
            }

            if ($this->_currentDimensions['width'] < $this->_currentDimensions['height']) {//resize by width
                $newWidth = ($this->_currentDimensions['width'] < $newWidth) ?
                    $this->_currentDimensions['width'] : $newWidth;

                $result = $this->resize($newWidth);
            } else { //resize by height
                $newWidth = ($this->_currentDimensions['height'] < $newWidth) ?
                    $this->_currentDimensions['height'] : $newWidth;
                $result = $this->resize(0, $newWidth);
            }

            if (false === $result) {
                return false;
            }
        }

        return $this->cropFromCenter($newWidth);
    }


    /**
     * Calculates the new image dimensions
     *
     * These calculations are based on both the provided dimensions and $this->maxWidth and $this->maxHeight
     *
     * @param int $width
     * @param int $height
     *
     * @param $maxWidth
     * @param $maxHeight
     * @return array
     */
    protected function _calcImageSize($width, $height, $maxWidth, $maxHeight) {
        $newSize = array (
            'newWidth'	=> $width,
            'newHeight'	=> $height);

        if ($maxWidth > 0) {
            $newSize = $this->_calcWidth($width, $height, $maxWidth);
            if ($maxHeight > 0 && $newSize['newHeight'] > $maxHeight) {
                $newSize = $this->_calcHeight($newSize['newWidth'], $newSize['newHeight'], $maxHeight);
            }
        }

        if ($maxHeight > 0) {
            $newSize = $this->_calcHeight($width, $height, $maxHeight);

            if ($maxWidth > 0 && $newSize['newWidth'] > $maxWidth) {
                $newSize = $this->_calcWidth($newSize['newWidth'], $newSize['newHeight'], $maxHeight);
            }
        }
        return $newSize;
    }

    /**
     * Calculates new image dimensions, not allowing the width and height to be less than either the max width or height
     *
     * @param int $width
     * @param int $height
     * @param int $maxWidth
     * @param $maxHeight
     * @param int $maxHeight
     * @return array
     */
    protected function _calcImageSizeStrict($width, $height, $maxWidth, $maxHeight) {
        // first, we need to determine what the longest resize dimension is..
        if ($maxWidth >= $maxHeight) {
            // and determine the longest original dimension
            if ($width > $height) {
                $newDimensions = $this->_calcHeight($width, $height, $maxHeight);

                if ($newDimensions['newWidth'] < $maxWidth) {
                    $newDimensions = $this->_calcWidth($width, $height, $maxWidth);
                }
            }
            elseif ($height >= $width) {
                $newDimensions = $this->_calcWidth($width, $height, $maxWidth);

                if ($newDimensions['newHeight'] < $maxHeight)
                {
                    $newDimensions = $this->_calcHeight($width, $height, $maxHeight);
                }
            }
        }
        elseif ($maxHeight > $maxWidth) {
            if ($width >= $height) {
                $newDimensions = $this->_calcWidth($width, $height, $maxWidth);
                if ($newDimensions['newHeight'] < $maxHeight) {
                    $newDimensions = $this->_calcHeight($width, $height, $maxHeight);
                }
            }
            elseif ($height > $width) {
                $newDimensions = $this->_calcHeight($width, $height, $maxHeight);

                if ($newDimensions['newWidth'] < $maxWidth) {
                    $newDimensions = $this->_calcWidth($width, $height, $maxWidth);
                }
            }
        }

        return $newDimensions;
    }

    /**
     * Calculates a new width and height for the image based on $this->maxWidth and the provided dimensions
     *
     * @return array
     * @param int $width
     * @param int $height
     * @param $maxWidth
     */
    protected function _calcWidth($width, $height, $maxWidth) {
        $newWidthPercentage	= (100 * $maxWidth) / $width;
        $newHeight			= ($height * $newWidthPercentage) / 100;

        return array ('newWidth'	=> ceil($maxWidth),
            'newHeight'	=> ceil($newHeight));
    }

    /**
     * Calculates a new width and height for the image based on $this->maxWidth and the provided dimensions
     *
     * @return array
     * @param int $width
     * @param int $height
     * @param $maxHeight
     */
    protected function _calcHeight($width, $height, $maxHeight) {
        $newHeightPercentage	= (100 * $maxHeight) / $height;
        $newWidth 				= ($width * $newHeightPercentage) / 100;

        return array ('newWidth'	=> ceil($newWidth),
            'newHeight'	=> ceil($maxHeight)
        );
    }

    /**
     * Preserves the alpha or transparency for PNG and GIF files
     *
     * Alpha / transparency will not be preserved if the appropriate options are set to false.
     * Also, the GIF transparency is pretty skunky (the results aren't awesome), but it works like a
     * champ... that's the nature of GIFs tho, so no huge surprise.
     *
     * This functionality was originally suggested by commenter Aimi (no links / site provided) - Thanks! :)
     *
     */
    protected function _preserveAlpha()
    {
        if ($this->_mime == 'image/png' && $this->_options['preserve_alpha'] === true) {
            imagealphablending($this->_workingImage, false);
            imagesavealpha($this->_workingImage, true);
            $colorTransparent = imagecolorallocatealpha (
                $this->_workingImage,
                $this->_options['alpha_mask_color'][0],
                $this->_options['alpha_mask_color'][1],
                $this->_options['alpha_mask_color'][2],
                127);
            imagefill($this->_workingImage, 0, 0, $colorTransparent);
        }

        if (($this->_mime == 'image/gif' || $this->_mime == 'image/png')
            && $this->_options['preserve_transparency'] === false) {
            $output = imagecreatetruecolor($this->_currentDimensions['width'], $this->_currentDimensions['height']);
            $colorTransparent = @imagecolorallocate (
                $output,
                $this->_options['transparency_mask_color'][0],
                $this->_options['transparency_mask_color'][1],
                $this->_options['transparency_mask_color'][2]);

            imagefilledrectangle($output, 0, 0
                , $this->_currentDimensions['width']
                , $this->_currentDimensions['height']
                , $colorTransparent);
            imagecopy($output, $this->_source, 0, 0, 0, 0
                , $this->_currentDimensions['width']
                , $this->_currentDimensions['height']);
            $this->_source = $output;
        }

        // preserve transparency in GIFs... this is usually pretty rough tho
        //if (($this->_mime == 'image/gif' || $this->_mime == 'image/png') && $this->_options['preserve_transparency'] === true) {
        if ($this->_mime == 'image/gif' && $this->_options['preserve_transparency'] === true) {
            $colorTransparent = @imagecolorallocate (
                $this->_workingImage,
                $this->_options['transparency_mask_color'][0],
                $this->_options['transparency_mask_color'][1],
                $this->_options['transparency_mask_color'][2]);

            imagecolortransparent($this->_workingImage, $colorTransparent);
            imagetruecolortopalette($this->_workingImage, true, 256);
        }
    }

    /**
     * Shows an image
     *
     * This function will show the current image by first sending the appropriate header
     * for the format, and then outputting the image data. If headers have already been sent,
     * a runtime exception will be thrown
     */
    public function show() {
        if (headers_sent()) {
            throw new \RuntimeException('Cannot show image, headers have already been sent');
        }

        if (null == $this->_mime) {
            $this->_mime = 'image/png';
        }

        header("Content-Disposition: filename={$this->_fileName};");
        header("Content-Type: {$this->_mime}");
        header('Content-Transfer-Encoding: binary');
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', time()).' GMT');
        switch($this->_mime) {
            case 'image/gif':
                imagegif($this->_source);
                break;
            case 'image/jpeg':
                imagejpeg($this->_source, null, $this->_options['jpeg_quality']);
                break;
            case 'image/png':
                imagepng($this->_source);
                break;
        }
    }

    /**
     * save image to disk as $file name
     * @param string $file
     * @param string $mime export file mime type
     * 					default null mean export by filename extension
     *
     * @return boolean
     */
    public function save($file, $mime = null) {
        if (null == $mime) {
            $_t = explode('.', $file);
            $mime = end($_t);
        }

        if (null == $this->_mime) {
            $this->_mime = 'image/png';
        }

        switch ($mime) {
            case 'gif' :
                $mime = 'image/gif';
                break;
            case 'jpg':
            case 'jpeg' :
            case 'jpe' :
                $mime = 'image/jpeg';
                break;
            default :
                $mime = $this->_mime;
                break;
        }

        $dir = dirname($file);
        if (!is_dir($dir) || !is_writeable($dir)) {
            $this->_error[] = 'Thư mục không tồn tại hoặc không có quyền ghi.';
            return false;
        }

        switch($mime) {
            case 'image/gif':
                @imagegif($this->_source, $file);
                break;
            case 'image/jpeg':
                @imagejpeg($this->_source, $file, $this->_options['jpeg_quality']);
                break;
            case 'image/png':
                @imagepng($this->_source, $file);
                break;
        }

        return true;
    }

    /**
     * trigger error
     *    set error message and throws an exception if config exception is set
     *
     * @param string $mess //error mess
     * @param \Exception $e
     * @throws \Exception
     */
    protected function _triggerError($mess, \Exception $e = null) {
        $this->_error[] = $mess;
        if (null != $e && $e instanceof \Exception && true == $this->_exception) {
            throw $e;
        }
    }
}