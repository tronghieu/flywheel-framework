<?php
/**
 * Created by PhpStorm.
 * User: luuhieu
 * Date: 4/26/16
 * Time: 11:28
 */

namespace Flywheel\Profiler\Writer;


class FileWriter extends BaseWriter
{
    /**
     * @var string
     */
    protected $_filePath;

    /**
     * @var string
     */
    protected $_fileName;

    /**
     * @var bool
     */
    protected $_valid = false;

    /**
     * @var array
     */
    protected $_pathVariables;

    /**
     * FileWriter constructor.
     *
     * @param string $file_path
     * @param string $file_name
     *
     * @author LuuHieu
     */
    public function __construct($file_path = null, $file_name = null)
    {
        $this->_initPathVariables();

        if ($file_path) {
            $this->setFilePath($file_path);
        }

        if ($file_name) {
            $this->setFileName($file_name);
        }
    }

    /**
     * define folder for result file
     *
     * @param $path
     *
     * @author LuuHieu
     */
    public function setFilePath($path)
    {
        $path = $this->_parsePathVariables($path);

        if (is_writeable($path)) {
            $this->_filePath = $path;
            $this->_valid = true;
        }
    }

    /**
     * define name of result file
     *
     * @param $file_name
     *
     * @author LuuHieu
     */
    public function setFileName($file_name)
    {
        $file_name = $this->_parsePathVariables($file_name);

        $this->_fileName = $file_name;
    }

    /**
     * Write profile data
     *
     * @return void
     *
     * @author LuuHieu
     */
    public function write()
    {
        if (!$this->_valid) {
            return; //do nothing
        }

        $file_name = rtrim($this->_filePath, DIRECTORY_SEPARATOR). DIRECTORY_SEPARATOR .$this->_fileName;

        $data = [
            'system_info' => $this->getOwner()->getSystemActivityInfo(),
            'result' => $this->getOwner()->getResults()
        ];

        //write file
        $fh = fopen($file_name, 'a');
        fwrite($fh, json_encode($data) ."\n"); // write to file (filehandle, "data")
        fclose($fh);
    }

    /**
     * @param $name
     * @param $value
     *
     * @author kiennx
     */
    public function addPathVariable($name, $value) {
        $this->_pathVariables[$name] = $value;
    }

    /**
     * Make file name and folder rotatable by parsing variables in its path
     * @param $input
     * @return string
     *
     * @author kiennx
     */
    private function _parsePathVariables($input) {
        foreach ($this->_pathVariables as $key => $value) {
            $input = str_replace($key, $value, $input);
        }

        return $input;
    }

    /**
     * init default variables
     *
     * @author kiennx
     */
    private function _initPathVariables() {
        //make the $file_name rotatable by parsing variables
        $date = new \DateTime();
        $this->_pathVariables = [
            '%s' => $date->format('s'),
            '%i' => $date->format('i'),
            '%H' => $date->format('H'),
            '%d' => $date->format('d'),
            '%m' => $date->format('m'),
            '%Y' => $date->format('Y'),
        ];
    }
}