<?php
namespace Flywheel\Debug;

use Flywheel\Util\Folder;

class FileHandler implements IHandler {
    public $path;
    protected $_file_prefix = 'profile.';
    protected $_file_ext = '.log';

    public function __construct($otp = []) {
        foreach($otp as $k => $v) {
            $this->$k = $v;
        }
    }

    public function write($records) {
        $content = "\n**********BEGIN PROFILE***********\n";

        $content .= date('Y-m-d H:i:s') .' [' .$records['SERVER_ADDRESS']."]\n";
        $content .= "Memory usage: " .$records['memory']['memory_usage'] .'/' .$records['memory']['max_memory_allow']
            .' (' .round($records['memory']['memory_usage_percent'], 2) ."%)\n";
        $content .= "Total execute time: {$records['total_exec_time']} seconds\n";

        $content .= "\nSERVER VARIABLES:\n";
        $content .= "argv: \n" .$this->_serialize($records['argv']);
        $content .= "argc: \n" .$this->_serialize($records['argc']);
        $content .= "cookies: \n" .$this->_serialize($records['cookies']);
        $content .= "session: \n" .$this->_serialize($records['session']);
        $content .= "requests: \n" .$this->_serialize($records['requests']);

        //Mark
        $content .= "\nMARKS:\n";
        foreach ($records['activities'] as $act) {
            $content .= "{$act}\n";
        }

        //SQL
        $sql = $records['sql_queries'];
        $content .= "\nSQL QUERIES:\n";
        $content .= "Total queries: {$sql['total_queries']}, Execute time: {$sql['total_exec_time']}\n";

        foreach($sql['queries'] as $query) {
            $content .= $query['query'] ."\n";
            $content .= "\tTime: {$query['exec_time']} ({$query['memory']} MB)\n";
            if ($query['parameters']) {
                $content .= "\t parameters:\n";
                $content .= $this->_serialize($query['parameters'], "\t\t");
            }
        }

        //included files
        $content .= sizeof($records['included_files']) ." included files";

        $content .="\n************END PROFILE**********\n";

        $this->_writeFile($this->path, $content);
    }

    /**
     * @param $array
     * @param $prefix
     * @param int $deep
     * @return string
     */
    private function _serialize($array, $prefix = null, $deep = 1 ) {
        $t = '';
        foreach($array as $k=>$v) {
            if (is_numeric($k)) {
                $t .= "{$prefix} {$v}\n";
            } else {
                if (is_array($v)) {
                    $v = $this->_serialize($v, str_repeat($prefix, $deep+1), $deep++);
                }
                $t .= "{$prefix} {$k}: {$v}\n";
            }
        }

        return $t;
    }

    public function getName()
    {
        return 'FileHandler';
    }

    protected function _writeFile($path, $content) {
        Folder::create($path);

        if(!($id = session_id())) {
            $id = md5(uniqid() .mt_rand());
        }

        $filename = $this->_file_prefix. date('Y-m-d') .'-' .$id .$this->_file_ext;
        $file = $path .DIRECTORY_SEPARATOR .$filename;
        if (!file_exists($file)) {
            touch($file); // Create blank file
            chmod($file, 0777);
        }

        $stream = fopen($file, 'a');
        fwrite($stream, $content);
        fclose($stream);
    }
}