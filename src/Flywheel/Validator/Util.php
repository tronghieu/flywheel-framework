<?php
namespace Flywheel\Validator;
class Util
{
    /**
     * Check empty array, null value, empty string
     * @param $value
     * @param bool $trim
     * @return bool
     */
    public static function isEmpty($value, $trim = false) {
        return $value===null || $value===array() || $value==='' || $trim && is_scalar($value) && trim($value)==='';
    }

    /**
     * Check valid email format
     * @param $email
     * @return int
     */
    public static function isValidEmail($email){
        return preg_match('/^([a-z0-9]+([_\.\-]{1}[a-z0-9]+)*){1}([@]){1}([a-z0-9]+([_\-]{1}[a-z0-9]+)*)+(([\.]{1}[a-z]{2,6}){0,3}){1}$/i', $email);
    }

    /**
     * Check valid price format
     *
     * @param $value
     * @return int
     */
    public static function isValidPriceFormat($value) {
        return preg_match("/^-?[0-9]+(?:\.[0-9]{1,2})?$/", $value);
    }

    /**
     * Check valid username format
     * username allow a-zA-Z0-9, "_" and "-", length from 3 to 16 characters
     * @param $name
     * @return int
     */
    public static function isValidUsername($name){
        return preg_match("/^[A-Za-z0-9_-]{3,16}$/",$name);
    }

    /**
     * Check is valid password
     * @param $password
     * @return int
     */
    public static function isValidPassword($password){
        return preg_match("/^[a-z0-9_-]{6,18}$/",$password);
    }

    /**
     * Check is valid url
     *
     * @param $url
     * @return int
     */
    public static function isValidUrl($url){
        return preg_match("/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/",$url);
    }

    /**
     * Check valid ip address
     * @param $ip
     * @return int
     */
    public static function isValidIPAddress($ip){
        return preg_match("/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/",$ip);
    }

    /**
     * Check valid html tags
     * @param $tag
     * @return int
     */
    public static function isValidHtmlTag($tag){
        return preg_match("/^<([a-z]+)([^<]+)*(?:>(.*)<\/\1>|\s+\/>)$/",$tag);
    }

    /**
     * Check valid hex value
     * @param $value
     * @return int
     */
    public static function isValidHexValue($value){
        return preg_match("/^#?([a-f0-9]{6}|[a-f0-9]{3})$/",$value);
    }

    /**
     * Check valid phone number
     * @param $phone
     * @return int
     */
    public static function isValidPhoneNumber($phone) {
        return preg_match("/^([0-9\(\)\/\+ \-]*)$/", $phone);
    }

    /**
     * Validate date with format
     * @param $date
     * @param string $format
     * @return bool
     */
    public static function validateDate($date, $format = 'Y-m-d H:i:s') {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }
}