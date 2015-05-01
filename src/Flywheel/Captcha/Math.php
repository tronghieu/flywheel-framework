<?php

namespace Flywheel\Captcha;
use Flywheel\Session\Session;

//error_reporting(E_ALL); ini_set('display_errors', 1);

/**
 * Mathematics captcha
 * Class Math
 * @package Flywheel\Captcha
 */

class Math {
    public static $id = 'Mathematics captcha';

    protected $_private_id;

    /**
     * The TTF font file to use to draw the captcha code.
     *
     * Leave blank for default font AHGBold.ttf
     *
     * @var string
     */
    public $ttfFile;

    /**
     * The level of distortion.
     *
     * 0.75 = normal, 1.0 = very high distortion
     *
     * @var double
     */
    public $perturbation = 0.9;

    /**
     * How many lines to draw over the captcha code to increase security
     * @var int
     */
    public $numLines = 5;


    /**
     * The color of the noise that is drawn
     * @var ImageColor
     */
    public $noiseColor = '#A30000';

    /**
     * The background color of the captcha
     * @var ImageColor
     */
    public $imageBgColor = '#EEF2BF';

    /**
     * The color of the captcha text
     * @var ImageColor
     */
    public $textColor = '#A30000';

    /**
     * The color of the lines over the captcha
     * @var ImageColor
     */
    public $lineColor = '#A30000';

    /**
     * Flag indicating whether or not HTTP headers will be sent when outputting
     * captcha image/audio
     *
     * @var bool If true (default) headers will be sent, if false, no headers are sent
     */
    protected $_sendHeaders;

    /**
     * How transparent to make the text.
     * 0 = completely opaque, 100 = invisible
     * @var int
     */
    public $textTransparencyPercentage = 20;

    /**
     * The level of noise (random dots) to place on the image, 0-10
     * @var int
     */
    public $noiseLevel  = 10;

    /**
     * Captcha live time
     * @var int
     */
    public $timeout = 900;

    public $difficultLevel = 1;

    public $useTransparentText = true;

    public $imgWidth = 300;
    public $imgHeight = 80;
    public $scale = 10;

    public $limit = 100;
    protected $_display;
    protected $_backgroundImg;
    protected $_image;
    protected $_tmpImg;
    protected $_result;
    protected $_noExit;

    /**
     * Absolute path to securimage directory.
     *
     * This is calculated at runtime
     *
     * @var string
     */
    public $imagePath = null;

    /**
     * The GD color resource for the text color
     *
     * @var resource
     */
    protected $_gdTextColor;

    /**
     * The GD color resource for the background color
     *
     * @var resource
     */
    protected $_gdBackgroundColor;

    /**
     * The GD color resource for the line color
     *
     * @var resource
     */
    protected $_gdLineColor;

    /**
     * The GD color resource for the line color
     *
     * @var resource
     */
    protected $_gdNoiseColor;

    /**
     * The GD color resource for the signature text color
     *
     * @var resource
     */
    protected $_gdSignatureColor;

    protected $_fontRatio;

    public function __construct($options = array()) {
        if (isset($options['id']) && $options['id'] != '') {
            $this->_private_id = $options['id'];
            unset($options['id']);
        } else {
            $this->_private_id = self::$id;
        }

        $this->imagePath = dirname(__FILE__);

        if (is_array($options) && sizeof($options) > 0) {
            foreach($options as $prop => $val) {
                $this->$prop = $val;
            }
        }

        $this->imageBgColor = $this->initColor($this->imageBgColor, '#ffffff');
        $this->textColor = $this->initColor($this->textColor, '#616161');
        $this->lineColor = $this->initColor($this->lineColor, '#616161');
        $this->noiseColor = $this->initColor($this->noiseColor, '#616161');

        if (is_null($this->ttfFile)) {
            $this->ttfFile = $this->imagePath . '/AHGBold.ttf';
        }

        if (is_null($this->perturbation) || !is_numeric($this->perturbation)) {
            $this->perturbation = 0.75;
        }

        if (is_null($this->_noExit)) {
            $this->_noExit = false;
        }

        if (is_null($this->_sendHeaders)) {
            $this->_sendHeaders = true;
        }

        Session::getInstance()->start();
    }

    /**
     * set id for this captcha
     * in case have many form display and using same captcha code, we need identify its
     * @param $id
     */
    public function setId($id) {
        $this->_private_id = $id;
    }

    protected function _store($result) {
        $data = [
            'captcha' => $result,
            'live_time' => time() + $this->timeout];

        $_SESSION[md5($this->_private_id)] = $data;
        //setcookie(md5(self::$id), json_encode($data), time()+$this->timeout);
    }

    protected function _calculateResult() {
        $operators = array('_calculatePlusMinus');

        if ($this->difficultLevel == 2) {
            $operators[] = '_calculateMultiplicationDivision';
        }

        if ($this->difficultLevel == 3) {
            $this->limit = 1000;
        }

        $operator = $operators[array_rand($operators)];
        call_user_func(array($this, $operator));
        $this->_display .= '=?';
    }

    protected function _calculatePlusMinus() {
        $first = mt_rand(1, $this->limit);
        do {
            $second = mt_rand(1, $this->limit);
        } while($second == $first);

        $operator = (mt_rand(0,1))? '+' : '-';
        if ('-' == $operator && $first < $second) {
            $t = $first;
            $first = $second;
            $second = $t;
        }

        switch ($operator) {
            case '-' :
                $this->_result = $first - $second;
                break;
            case '+' :
                $this->_result = $first + $second;
        }

        $this->_display = "{$first}{$operator}{$second}";
        $this->_store($this->_result);
    }

    protected function _calculateMultiplicationDivision() {
        $first = mt_rand(1, $this->limit);
        $second = mt_rand(1, $this->limit);
        $operator = (mt_rand(0,1))? '*' : '/';

        $result = $first*$second;

        if ('/' == $operator) {
            $t = $first;
            $first = $result;
            $result = $t;
        }

        $this->_display = "{$first}{$operator}{$second}";
        $this->_result = $result;
        $this->_store($this->_result);
    }

    protected function _genImage() {
        if( ($this->useTransparentText == true)
            && function_exists('imagecreatetruecolor')) {
            $imagecreate = 'imagecreatetruecolor';
        } else {
            $imagecreate = 'imagecreate';
        }

        $this->_image    = $imagecreate($this->imgWidth, $this->imgHeight);
        $this->_tmpImg = $imagecreate($this->imgWidth * $this->scale, $this->imgHeight * $this->scale);

        $this->allocateColors();
        imagepalettecopy($this->_tmpImg, $this->_image);

        $this->_calculateResult();

        $this->_setBackground();

        if ($this->noiseLevel > 0) {
            $this->_drawNoise();
        }

        $this->drawWord();

        if ($this->perturbation > 0 && is_readable($this->ttfFile)) {
            $this->_distortedCopy();
        }

        if ($this->numLines > 0) {
            $this->_drawLines();
        }

        $this->output();
    }

    /**
     * Allocate the colors to be used for the image
     */
    protected function allocateColors() {
        // allocate bg color first for imagecreate
        $this->_gdBackgroundColor = imagecolorallocate($this->_image,
            $this->imageBgColor->r,
            $this->imageBgColor->g,
            $this->imageBgColor->b);

        $alpha = intval($this->textTransparencyPercentage / 100 * 127);

        if ($this->useTransparentText == true) {
            $this->_gdTextColor = imagecolorallocatealpha($this->_image,
                $this->textColor->r,
                $this->textColor->g,
                $this->textColor->b,
                $alpha);

            $this->_gdLineColor = imagecolorallocatealpha($this->_image,
                $this->lineColor->r,
                $this->lineColor->g,
                $this->lineColor->b,
                $alpha);
            $this->_gdNoiseColor = imagecolorallocatealpha($this->_image,
                $this->noiseColor->r,
                $this->noiseColor->g,
                $this->noiseColor->b,
                $alpha);
        } else {
            $this->_gdTextColor = imagecolorallocate($this->_image,
                $this->textColor->r,
                $this->textColor->g,
                $this->textColor->b);
            $this->_gdLineColor = imagecolorallocate($this->_image,
                $this->lineColor->r,
                $this->lineColor->g,
                $this->lineColor->b);
            $this->_gdNoiseColor = imagecolorallocate($this->_image,
                $this->noiseColor->r,
                $this->noiseColor->g,
                $this->noiseColor->b);
        }
    }

    /**
     * The the background color, or background image to be used
     */
    protected function _setBackground() {
        // set background color of image by drawing a rectangle since imagecreatetruecolor doesn't set a bg color
        imagefilledrectangle($this->_image, 0, 0,
            $this->imgWidth, $this->imgHeight,
            $this->_gdBackgroundColor);

        imagefilledrectangle($this->_tmpImg, 0, 0,
            $this->imgWidth * $this->scale, $this->imgHeight * $this->scale,
            $this->_gdBackgroundColor);
    }

    /**
     * Draws random noise on the image
     */
    protected function _drawNoise()
    {
        if ($this->noiseLevel > 10) {
            $noise_level = 10;
        } else {
            $noise_level = $this->noiseLevel;
        }

        $t0 = microtime(true);

        $noise_level *= 125; // an arbitrary number that works well on a 1-10 scale

        $points = $this->imgWidth*$this->imgHeight*$this->scale;
        $height = $this->imgHeight*$this->scale;
        $width  = $this->imgWidth*$this->scale;

        for ($i = 0; $i < $noise_level; ++$i) {
            $x = mt_rand(10, $width);
            $y = mt_rand(10, $height);
            $size = mt_rand(7, 10);
            if ($x - $size <= 0 && $y - $size <= 0) continue; // dont cover 0,0 since it is used by imagedistortedcopy
            imagefilledarc($this->_tmpImg, $x, $y, $size, $size, 0, 360, $this->_gdNoiseColor, IMG_ARC_PIE);
        }

        $t1 = microtime(true);

        $t = $t1 - $t0;
    }

    /**
     * Draws the captcha code on the image
     */
    protected function drawWord() {
        $width2  = $this->imgWidth * $this->scale;
        $height2 = $this->imgHeight * $this->scale;
        $ratio   = ($this->_fontRatio) ? $this->_fontRatio : 0.4;

        if ((float)$ratio < 0.1 || (float)$ratio >= 1) {
            $ratio = 0.4;
        }

        if (!is_readable($this->ttfFile)) {
            imagestring($this->_image, 4, 10, ($this->imgHeight / 2) - 5, 'Failed to load TTF font file!', $this->_gdTextColor);
        } else {
            if ($this->perturbation > 0) {
                $font_size = $height2 * $ratio;
                $bb = imageftbbox($font_size, 0, $this->ttfFile, $this->_display);
                $tx = $bb[4] - $bb[0];
                $ty = $bb[5] - $bb[1];
                $x  = floor($width2 / 2 - $tx / 2 - $bb[0]);
                $y  = round($height2 / 2 - $ty / 2 - $bb[1]);

                imagettftext($this->_tmpImg, $font_size, 0, $x, $y, $this->_gdTextColor, $this->ttfFile, $this->_display);
            } else {
                $font_size = $this->imgHeight * $ratio;
                $bb = imageftbbox($font_size, 0, $this->ttfFile, $this->_display);
                $tx = $bb[4] - $bb[0];
                $ty = $bb[5] - $bb[1];
                $x  = floor($this->imgWidth / 2 - $tx / 2 - $bb[0]);
                $y  = round($this->imgHeight / 2 - $ty / 2 - $bb[1]);

                imagettftext($this->_image, $font_size, 0, $x, $y, $this->_gdTextColor, $this->ttfFile, $this->_display);
            }
        }
    }

    /**
     * Copies the captcha image to the final image with distortion applied
     */
    protected function _distortedCopy() {
        $num_poles = 3; // distortion factor
        // make array of poles AKA attractor points
        for ($i = 0; $i < $num_poles; ++ $i) {
            $px[$i]  = mt_rand($this->imgWidth  * 0.2, $this->imgWidth  * 0.8);
            $py[$i]  = mt_rand($this->imgHeight * 0.2, $this->imgHeight * 0.8);
            $rad[$i] = mt_rand($this->imgHeight * 0.2, $this->imgHeight * 0.8);
            $tmp     = ((- $this->frand()) * 0.15) - .15;
            $amp[$i] = $this->perturbation * $tmp;
        }

        $bgCol = imagecolorat($this->_tmpImg, 0, 0);
        $width2 = $this->scale * $this->imgWidth;
        $height2 = $this->scale * $this->imgHeight;
        imagepalettecopy($this->_image, $this->_tmpImg); // copy palette to final image so text colors come across
        // loop over $img pixels, take pixels from $tmpimg with distortion field
        for ($ix = 0; $ix < $this->imgWidth; ++ $ix) {
            for ($iy = 0; $iy < $this->imgHeight; ++ $iy) {
                $x = $ix;
                $y = $iy;
                for ($i = 0; $i < $num_poles; ++ $i) {
                    $dx = $ix - $px[$i];
                    $dy = $iy - $py[$i];
                    if ($dx == 0 && $dy == 0) {
                        continue;
                    }
                    $r = sqrt($dx * $dx + $dy * $dy);
                    if ($r > $rad[$i]) {
                        continue;
                    }
                    $rscale = $amp[$i] * sin(3.14 * $r / $rad[$i]);
                    $x += $dx * $rscale;
                    $y += $dy * $rscale;
                }
                $c = $bgCol;
                $x *= $this->scale;
                $y *= $this->scale;
                if ($x >= 0 && $x < $width2 && $y >= 0 && $y < $height2) {
                    $c = imagecolorat($this->_tmpImg, $x, $y);
                }
                if ($c != $bgCol) { // only copy pixels of letters to preserve any background image
                    imagesetpixel($this->_image, $ix, $iy, $c);
                }
            }
        }
    }

    /**
     * Draws distorted lines on the image
     */
    protected function _drawLines() {
        for ($line = 0; $line < $this->numLines; ++ $line) {
            $x = $this->imgWidth * (1 + $line) / ($this->numLines + 1);
            $x += (0.5 - $this->frand()) * $this->imgWidth / $this->numLines;
            $y = mt_rand($this->imgHeight * 0.1, $this->imgHeight * 0.9);

            $theta = ($this->frand() - 0.5) * M_PI * 0.7;
            $w = $this->imgWidth;
            $len = mt_rand($w * 0.4, $w * 0.7);
            $lwid = mt_rand(0, 2);

            $k = $this->frand() * 0.6 + 0.2;
            $k = $k * $k * 0.5;
            $phi = $this->frand() * 6.28;
            $step = 0.5;
            $dx = $step * cos($theta);
            $dy = $step * sin($theta);
            $n = $len / $step;
            $amp = 1.5 * $this->frand() / ($k + 5.0 / $len);
            $x0 = $x - 0.5 * $len * cos($theta);
            $y0 = $y - 0.5 * $len * sin($theta);

            $ldx = round(- $dy * $lwid);
            $ldy = round($dx * $lwid);

            for ($i = 0; $i < $n; ++ $i) {
                $x = $x0 + $i * $dx + $amp * $dy * sin($k * $i * $step + $phi);
                $y = $y0 + $i * $dy - $amp * $dx * sin($k * $i * $step + $phi);
                imagefilledrectangle($this->_image, $x, $y, $x + $lwid, $y + $lwid, $this->_gdLineColor);
            }
        }
    }

    /**
     * Sends the appropriate image and cache headers and outputs image to the browser
     */
    protected function output()
    {
        if ($this->_canSendHeaders() || $this->_sendHeaders == false) {
            if ($this->_sendHeaders) {
                // only send the content-type headers if no headers have been output
                // this will ease debugging on misconfigured servers where warnings
                // may have been output which break the image and prevent easily viewing
                // source to see the error.
                header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
                header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
                header("Cache-Control: no-store, no-cache, must-revalidate");
                header("Cache-Control: post-check=0, pre-check=0", false);
                header("Pragma: no-cache");
            }

            if ($this->_sendHeaders) header("Content-Type: image/jpeg");
            imagejpeg($this->_image, null, 90);

        } else {
            echo '<hr /><strong>'
                .'Failed to generate captcha image, content has already been '
                .'output.<br />This is most likely due to misconfiguration or '
                .'a PHP error was sent to the browser.</strong>';
        }

        imagedestroy($this->_image);
        restore_error_handler();

        if (!$this->_noExit) exit;
    }

    /**
     * Convert an html color code to a ImageColor
     * @param string $color
     * @param string $default The default color to use if $color is invalid
     *
     * @return \Flywheel\Captcha\ImageColor
     *
     * @throw \Exception
     */
    protected function initColor($color, $default) {
        if ($color == null) {
            return new ImageColor($default);
        } else if (is_string($color)) {
            try {
                return new ImageColor($color);
            } catch(\Exception $e) {
                return new ImageColor($default);
            }
        } else if (is_array($color) && sizeof($color) == 3) {
            return new ImageColor($color[0], $color[1], $color[2]);
        } else {
            return new ImageColor($default);
        }
    }

    /**
     * Checks to see if headers can be sent and if any error has been output
     * to the browser
     *
     * @return bool true if it is safe to send headers, false if not
     */
    protected function _canSendHeaders() {
        if (headers_sent()) {
            // output has been flushed and headers have already been sent
            return false;
        } else if (strlen((string)ob_get_contents()) > 0) {
            // headers haven't been sent, but there is data in the buffer that will break image and audio data
            return false;
        }

        return true;
    }

    /**
     * Show captcha image
     */
    public function show() {
        set_error_handler(array(&$this, 'errorHandler'));
        $this->_genImage();
    }


    /**
     * get stored captcha by id
     *
     * @param $id
     * @return bool
     */
    public static function getStoredCaptcha($id) {
        return isset($_SESSION[md5($id)])? $_SESSION[md5($id)]: false;
//        return isset($_COOKIE[md5($id)])? json_decode($_COOKIE[md5($id)], true) : false;
    }

    /**
     * Check match stored captcha with input
     * @param $input
     * @param null $id
     * @return bool
     */
    public static function check($input, $id = null) {
        if (!$id) {
            $id = self::$id;
        }

        //get $captcha data
        if (!($data = self::getStoredCaptcha($id))) {
            return false;
        }

        if (time() > $data['live_time']) {
            return false;
        }

        //clear session
        unset($_SESSION[md5($id)]);

        return $input == $data['captcha'];
    }

    /**
     * Return a random float between 0 and 0.9999
     *
     * @return float Random float between 0 and 0.9999
     */
    public function frand()
    {
        return 0.0001 * mt_rand(0,9999);
    }

    /**
     * The error handling function used when outputting captcha image or audio.
     *
     * This error handler helps determine if any errors raised would
     * prevent captcha image or audio from displaying.  If they have
     * no effect on the output buffer or headers, true is returned so
     * the script can continue processing.
     *
     * @param int $err_no  PHP error number
     * @param string $err_str  String description of the error
     * @param string $err_file  File error occurred in
     * @param int $err_line  Line the error occurred on in file
     * @param array $err_context  Additional context information
     * @return boolean true if the error was handled, false if PHP should handle the error
     */
    public function errorHandler($err_no, $err_str, $err_file = '', $err_line = 0, $err_context = array())
    {
        // get the current error reporting level
        $level = error_reporting();

        // if error was supressed or $err_no not set in current error level
        if ($level == 0 || ($level & $err_no) == 0) {
            return true;
        }

        return false;
    }
}