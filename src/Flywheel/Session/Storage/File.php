<?php

/**
 * Simple Cache class
 * API Documentation: https://github.com/cosenary/Simple-PHP-Cache
 *
 * @author Christian Metz
 * @since 22.12.2011
 * @copyright Christian Metz - MetzWeb Networks
 * @version 1.4
 * @license BSD http://www.opensource.org/licenses/bsd-license.php
 */
namespace Flywheel\Session\Storage;

use Flywheel\Session\Exception;

class File implements ISessionHandler
{
    private $_config;
    /**
     * Redis driver
     *
     * @var \Redis
     */
    protected $_driver;

    public function __construct($config = null)
    {
        if (!isset($config['savePath'])) {
            $config['savePath'] = session_save_path();
        }
        $this->_config = $config;
    }

    /**
     * Set config path save session
     * @param string $savePath
     * @param string $sessionName
     * @return bool|void
     */
    public function open($savePath, $sessionName)
    {
        /*if(!isset($this->_config['savePath'])) {
            $this->_config['savePath'] = session_save_path();
        }*/
    }

    public function read($sid)
    {
        $sessionContent = array();
        $file_type = isset($this->_config['type']) ? $this->_config['type'] : 'json';
        $filePath = $this->_config['savePath'] . $sid . '.txt';
        $fp = fopen($filePath, 'r');

        if (file_exists($filePath)) {
            switch ($file_type) {
                case 'json':
                    $sessionContent = filesize($filePath) > 0 ? json_decode(fread($fp, filesize($filePath)), true) : array();
                    break;
                case 'serialize':
                    $sessionContent = filesize($filePath) > 0 ? unserialize(fread($fp, filesize($filePath))) : array();
                    break;
                default:
                    break;
            }

            if (isset($sessionContent)) {
                // Check life time
                if ($sessionContent['last_modified'] + (int)@$this->_config['lifetime'] > time()) {
                    return $sessionContent['data'];
                }
                // Delete file
                unlink($filePath);
            }
        }

        return null;
    }

    public function close()
    {
    }

    /**
     * Write session to file
     * @param string $sid
     * @param string $data
     * @return bool|void
     */
    public function write($sid, $data)
    {
        // File type
        $file_type = isset($this->_config['type']) ? $this->_config['type'] : 'json';
        $filePath = $this->_config['savePath'] . $sid . '.txt';
        $fp = fopen($filePath, 'w+');
        $content = array(
            'last_modified' => time(),
            'data' => $data
        );
        switch ($file_type) {
            case 'json':
                fputs($fp, json_encode($content));
                break;
            case 'serialize':
                fputs($fp, serialize($content));
                break;
            default:
                fputs($fp, json_encode($content));
                break;
        }
    }

    public function destroy($sid)
    {

    }

    public function gc($sessMaxLifeTime = 0)
    {
        return true;
    }

}