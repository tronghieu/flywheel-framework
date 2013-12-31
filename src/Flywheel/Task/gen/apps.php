<?php
use Flywheel\Util\Folder;
use Flywheel\Util\Inflection;
use Symfony\Component\Yaml;
use Symfony\Component\Finder;
use Symfony\Component\Filesystem;
use Flywheel\Config\ConfigHandler;

function apps_execute() {

    $params = func_get_arg(0);
    $builder = new BuildApps();
    if(!isset($params['type']) || !in_array($params['type'],array('w','a','c'))){
        die("Type of aplication is empty or not correct(must be in w,a,c) \n");
    }
    $builder->setAppType($params['type']);
    if(!isset($params['name']) || $params['name'] == ''){
        die("Name of aplication is empty \n");
    }
    $builder->setAppName($params['name']);
    $builder->run();
}

class BuildApps {
    public  $appType = '',
        $appName = '',
        $destinationDir = '';
    public $structApps = array(
        //Web Application
        'w' => array(
            'Controller',
            'Template',
            'Widget',
            'Config',
            'Library'
        ),
        //Console Application
        'c'=> array(
            'Task',
            'Library',
            'Config'
        ),
        //Api Application
        'a'=> array(
            'Controller',
            'Library',
            'Config'
        )
    );

    public $typeApps = array(
        'w'=>'web',
        'a'=>'api',
        'c'=>'console'
    );
    public function __construct() {
        $this->destinationDir  = ROOT_PATH.DIRECTORY_SEPARATOR.'apps'.DIRECTORY_SEPARATOR;
    }

    public function setAppType($appType){
        $this->appType = $appType;
    }

    public function setAppName($appName){
        $this->appName = Inflection::hungaryNotationToCamel($appName);
    }

    public function help(){}


    public function _generateApps(){
        $appDir = $this->destinationDir;
        $fs = new Filesystem\Filesystem();
        $config = array(
            'dir'=>$appDir,
            'structs'=>$this->structApps[$this->appType],
            'name'=>$this->appName
        );

        if( $fs->exists($appDir) === false ){
            $fs->mkdir('apps',0755);
        }
        $classGenerated = '';
        switch ($this->appType){
            case 'w':
                $app = new BuildAppWeb($config);
                $classGenerated = $app->run();
                break;
            case 'c':
                $app = new BuildAppConsole($config);
                $classGenerated = $app->run();
                break;
            case 'a':
                $app = new BuildAppApi($config);
                $classGenerated = $app->run();
                break;
        }
        return $classGenerated;
    }


    public function run(){
        if($this->appType == ''){
            echo 'App type is missing!';exit;
        }
        $classGenerated = $this->_generateApps($this->appType);
        echo "\n\n";
        echo $classGenerated.' generate successed!';
    }
}

class BuildAppWeb {
    public $appType = 'web';
    public $appName = '';
    public $appDir = '';

    public $structs = array();

    public function __construct($config = array()){

        if(isset($config['name'])) $this->appName = Inflection::hungaryNotationToCamel($config['name']);
        if(isset($config['dir'])) $this->appDir = $config['dir'].$this->appName.DIRECTORY_SEPARATOR;
        if(isset($config['structs'])) $this->structs = $config['structs'];
    }

    public function _generateFolder(){

        $fs = new Filesystem\Filesystem();
        if(!$fs->exists($this->appDir)){
            $fs->mkdir($this->appDir,0755);
        }

        if(!empty($this->structs)){
            echo "\n\n";
            foreach ( $this->structs as $fn ) {
                if($fs->exists($this->appDir.$fn) === false){
                    $fs->mkdir($this->appDir.$fn,0755);
                    echo "-- ".$fn." folder is generated!".PHP_EOL;
                }
            }
        }
    }

    public function _generateFileDefault(){

        //generate config file
        $this->_generateMainConfig();
        $this->_generateRoutingConfig();
        $this->_generateSettingConfig();

        //generate controller file
        $this->_generateBaseController();
        $this->_generateDefaultController();

        //generate Template

        $this->_generateDefaultTemplate();
    }

    public function _generateMainConfig() {
        $cameHungaryApp = Inflection::hungaryNotationToCamel($this->appName);
        $temp = "<?php".PHP_EOL;
        $temp.= "defined('APP_PATH') or define('APP_PATH', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);".PHP_EOL.
            "\\Flywheel\\Loader::addNamespace('".$cameHungaryApp."', dirname(APP_PATH));\n\n";
        $temp.=
            "return array(" .PHP_EOL
            . "  'app_name'=>'".$cameHungaryApp."',".PHP_EOL
            . "  'app_path'=> APP_PATH,".PHP_EOL
            . "  'view_path'=> APP_PATH .DIRECTORY_SEPARATOR.'Template/',".PHP_EOL
            . "  'import'=>array(".PHP_EOL
            . "    'app.Library.*',".PHP_EOL
            . "    'app.Controller.*',".PHP_EOL
            . "    'root.model.*'".PHP_EOL
            . "  ),".PHP_EOL
            . "  'namespace'=> '".$cameHungaryApp."',".PHP_EOL
            . "  'timezone'=>'Asia/Ho_Chi_Minh',".PHP_EOL
            . "  'template'=>'Default'".PHP_EOL
            . ');';
        $fs = new Filesystem\Filesystem();
        $destinationDir = $this->appDir.'Config'.DIRECTORY_SEPARATOR.'main.cfg.php';
        if($fs->exists($destinationDir) === false){
            $fs->dumpFile($destinationDir,$temp);
            echo "-- main.cfg.php is generated success !\n";
        }else {
            echo "-- main.cfg.php is exists, aborted !\n";
        }
    }

    public function _generateRoutingConfig(){
        $temp = "<?php".PHP_EOL;

        $temp.=
            '$r = array(' .PHP_EOL
            . '  \'__urlSuffix__\'=>\'.html\','.PHP_EOL
            . '  \'__remap__\'=> array('.PHP_EOL
            . '     \'route\'=>\'home/default\''.PHP_EOL
            . '  ),'.PHP_EOL
            . '  \'/\'=>array('.PHP_EOL
            . '     \'route\'=>\'home/default\''.PHP_EOL
            . '  ),'.PHP_EOL
            . '  \'{controller}\'=>array( '.PHP_EOL
            . '    \'route\'=>\'{controller}/default\' '.PHP_EOL
            . '  ),'.PHP_EOL
            . '  \'{controller}/{action}\'=>array( '.PHP_EOL
            . '    \'route\'=>\'{controller}/{action}\' '.PHP_EOL
            . '  ),'.PHP_EOL
            . '  \'{controller}/{action}/{id:\d+}\'=>array( '.PHP_EOL
            . '    \'route\'=>\'{controller}/{action}\' '
            . '  ),'.PHP_EOL
            . ');'.PHP_EOL
            . 'return $r;';

        $fs = new Filesystem\Filesystem();
        $destinationDir = $this->appDir.'Config'.DIRECTORY_SEPARATOR.'routing.cfg.php';
        if($fs->exists($destinationDir) === false){
            $fs->dumpFile($destinationDir,$temp);
            echo "-- routing.cfg.php is generated success !\n";
        }else{
            echo "-- routing.cfg.php is exists, aborted !\n";
        }
    }

    public function _generateSettingConfig(){
        $temp = "<?php".PHP_EOL;
        $temp.='$setting = array('.PHP_EOL
            . '' . PHP_EOL
            . ');' . PHP_EOL
            . 'return $setting;';
        $fs = new Filesystem\Filesystem();
        $destinationDir = $this->appDir.'Config'.DIRECTORY_SEPARATOR.'setting.cfg.php';
        if($fs->exists($destinationDir) === false){
            $fs->dumpFile($destinationDir,$temp);
            echo "-- setting.cfg.php is generated success !\n";
        } else {
            echo "-- setting.cfg.php is exists, aborted !\n";
        }
    }

    public function _generateDefaultController(){
        $fs = new Filesystem\Filesystem();

        $class = Inflection::hungaryNotationToCamel($this->appName);
        $baseClass = $class.'Base';

        $destinationDefaultFile = $this->appDir.'Controller'.DIRECTORY_SEPARATOR.$class.'.php';
        $temp = '<?php'.PHP_EOL;
        $temp.=
            'namespace '.$class.'\\Controller;'.PHP_EOL.
            'use '.$class.'\Controller\\'.$baseClass.';'.PHP_EOL.
            'class '.$class.' extends '.$baseClass.'{'.PHP_EOL
            . '' . PHP_EOL
            . '    public function executeDefault(){'.PHP_EOL
            . '      return $this->renderComponent();'.PHP_EOL
            . '    }'.PHP_EOL
            . '}' . PHP_EOL;
        if($fs->exists($destinationDefaultFile) === false){
            $fs->dumpFile($destinationDefaultFile,$temp);
            echo "-- DefaultController is generated success !".PHP_EOL;
        }

    }

    public function _generateBaseController(){
        $temp = "<?php".PHP_EOL;
        $class = $this->appName.'Base';

        $webController = 'Web';
        $appName = Inflection::hungaryNotationToCamel($this->appName);

        $temp.='namespace '.$appName.'\Controller;'.PHP_EOL
               .'use Flywheel\Controller\Web;'.PHP_EOL;
        $temp.='abstract class '.$class.' extends '.$webController.'{'.PHP_EOL
            . '' . PHP_EOL
            . '}' . PHP_EOL;
        $fs = new Filesystem\Filesystem();
        $destinationDir = $this->appDir.'Controller'.DIRECTORY_SEPARATOR.$class.'.php';
        if($fs->exists($destinationDir) === false){
            $fs->dumpFile($destinationDir,$temp);
            echo "-- ".$class." is generated success !\n";
        } else {
            echo "-- ".$class." is exists, aborted !\n";
        }
    }

    public function _generateDefaultTemplate(){

        $destinationDir = $this->appDir.'Template'.DIRECTORY_SEPARATOR.'Default'.DIRECTORY_SEPARATOR;

        $fs = new Filesystem\Filesystem();
        if($fs->exists($destinationDir) === false) {
            $fs->mkdir($destinationDir,0755);
            $defaultLayout = $destinationDir.DIRECTORY_SEPARATOR.'default.phtml';
            $template =
                '<!DOCTYPE html>
                <html>
                    <head>
                        <title>Hello Word</title>
                    </head>
                    <body>
                        <?php echo $buffer; ?>
                    </body>
                </html>';
            if($fs->exists($defaultLayout) == false){
                $fs->dumpFile($defaultLayout,$template);
            }
            echo "-- Default/Template generated success !".PHP_EOL;
        }

        $destinationDirController = $destinationDir.DIRECTORY_SEPARATOR.'Controller';
        if($fs->exists($destinationDirController) === false) {

            $fs->mkdir($destinationDirController,0755);

            echo "-- Template/Controller generated success !".PHP_EOL;
        }
        /*
         * Gen Default folder in Controller Folder
         * */
        $childFolderDefault = $destinationDirController.DIRECTORY_SEPARATOR
            .Inflection::hungaryNotationToCamel($this->appName);

        if($fs->exists($childFolderDefault)){
            $fs->mkdir($childFolderDefault,0755);
        }

        /*
         * Gen default view in Default Folder
         * */
        $defaultView = $childFolderDefault.DIRECTORY_SEPARATOR.'default.phtml';
        if($fs->exists($defaultView) == false){
            $fs->dumpFile($defaultView,"<h1>Hello Flywheel !</h1>");
        }

        /*
         * Gen Widget Folder
         * */
        $destinationDirWidget = $destinationDir.DIRECTORY_SEPARATOR.'Widget';
        if($fs->exists($destinationDirWidget) === false) {
            $fs->mkdir($destinationDirWidget,0755);
            echo "-- Template/Widget generated success !".PHP_EOL;
        }
    }

    public function run(){

        $this->_generateFolder();
        $this->_generateFileDefault();
        return $this->appName;
    }
}
class BuildAppConsole{
    public  $appType = 'console',
        $appName = '',
        $appDir = '';

    public function __construct($config = array()){
        if(isset($config['name'])) $this->appName = Inflection::hungaryNotationToCamel($config['name']);
        if(isset($config['dir'])) $this->appDir = $config['dir'].$this->appName.DIRECTORY_SEPARATOR;
        if(isset($config['structs'])) $this->structs = $config['structs'];
    }

    public function _generateFolder(){

        $fs = new Filesystem\Filesystem();
        if(!$fs->exists($this->appDir)){
            $fs->mkdir($this->appDir,0755);
        }

        if(!empty($this->structs)){
            echo "\n\n";
            foreach ( $this->structs as $fn ) {
                if($fs->exists($this->appDir.$fn) === true){
                    continue;
                }
                $fs->mkdir($this->appDir.$fn,0755);
                echo "-- ".$fn." folder is generated!".PHP_EOL;
            }
        }
    }

    public function _generateFileDefault(){

        //generate config file
        $this->_generateMainConfig();
        $this->_generateSettingConfig();

        //controller
        $this->_generateBaseController();
    }

    public function _generateMainConfig() {
        $cameHungaryApp = Inflection::hungaryNotationToCamel($this->appName);
        $temp = "<?php".PHP_EOL;
        $temp.= "defined('APP_PATH') or define('APP_PATH', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);".PHP_EOL.
            "\\Flywheel\\Loader::addNamespace('".$cameHungaryApp."', dirname(APP_PATH));\n\n";
        $temp.=
            "return array(" .PHP_EOL
            . "  'app_name'=>'".$cameHungaryApp."',".PHP_EOL
            . "  'app_path'=> APP_PATH,".PHP_EOL
            . "  'import'=>array(".PHP_EOL
            . "    'app.Library.*',".PHP_EOL
            . "    'app.Task.*',".PHP_EOL
            . "    'root.model.*'".PHP_EOL
            . "  ),".PHP_EOL
            . "  'namespace'=> '".$cameHungaryApp."',".PHP_EOL
            . "  'timezone'=>'Asia/Ho_Chi_Minh',".PHP_EOL
            . ');';
        $fs = new Filesystem\Filesystem();
        $destinationDir = $this->appDir.'Config'.DIRECTORY_SEPARATOR.'main.cfg.php';
        if($fs->exists($destinationDir) === false){
            $fs->dumpFile($destinationDir,$temp);
            echo "-- main.cfg.php is generated success !\n";
        }
    }

    public function _generateSettingConfig(){
        $temp = "<?php".PHP_EOL;
        $temp.='$setting = array('.PHP_EOL
            . '' . PHP_EOL
            . ');' . PHP_EOL
            . 'return $setting;';
        $fs = new Filesystem\Filesystem();
        $destinationDir = $this->appDir.'Config'.DIRECTORY_SEPARATOR.'setting.cfg.php';
        if($fs->exists($destinationDir) === false){
            $fs->dumpFile($destinationDir,$temp);
            echo "-- setting.cfg.php is generated success !\n";
        } else {
            echo "-- setting.cfg.php is exists, aborted !\n";
        }
    }

    public function _generateBaseController(){
        $temp = "<?php".PHP_EOL;
        $class = $this->appName.'Base';

        $webController = 'ConsoleTask';
        $appName = Inflection::hungaryNotationToCamel($this->appName);

        $temp.='namespace '.$appName.'\Task;'.PHP_EOL;

        $temp.='use Flywheel\Controller\ConsoleTask;'.PHP_EOL;

        $temp.='abstract class '.$class.' extends '.$webController.'{'.PHP_EOL
            . '' . PHP_EOL
            . '}' . PHP_EOL;
        $fs = new Filesystem\Filesystem();
        $destinationDir = $this->appDir.'Task'.DIRECTORY_SEPARATOR.$class.'.php';
        if($fs->exists($destinationDir) === false){
            $fs->dumpFile($destinationDir,$temp);
            echo "-- ".$class." is generated success !\n";
        } else {
            echo "-- ".$class." is exists, aborted !\n";
        }
    }

    public function run(){
        $this->_generateFolder();
        $this->_generateFileDefault();
        return $this->appName;
    }
}
class BuildAppApi {

    public  $appType = 'api',
        $appName = '',
        $appDir = '';

    public function __construct($config = array()){
        if(isset($config['name'])) $this->appName = Inflection::hungaryNotationToCamel($config['name']);
        if(isset($config['dir'])) $this->appDir = $config['dir'].$this->appName.DIRECTORY_SEPARATOR;
        if(isset($config['structs'])) $this->structs = $config['structs'];
    }
    public function _generateFolder(){

        $fs = new Filesystem\Filesystem();
        if(!$fs->exists($this->appDir)){
            $fs->mkdir($this->appDir,0755);
        }

        if(!empty($this->structs)){
            echo "\n\n";
            foreach ( $this->structs as $fn ) {
                if($fs->exists($this->appDir.$fn) === true){
                    continue;
                }
                $fs->mkdir($this->appDir.$fn,0755);
                echo "-- ".$fn." folder is generated!".PHP_EOL;
            }
        }
    }

    public function _generateFileDefault(){
        //generate config file
        $this->_generateMainConfig();
        $this->_generateSettingConfig();

        //controller
        $this->_generateBaseController();
    }

    public function _generateMainConfig() {
        $cameHungaryApp = Inflection::hungaryNotationToCamel($this->appName);
        $temp = "<?php".PHP_EOL;
        $temp.= "defined('APP_PATH') or define('APP_PATH', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);".PHP_EOL.
            "\\Flywheel\\Loader::addNamespace('".$cameHungaryApp."', dirname(APP_PATH));\n\n";
        $temp.=
            "return array(" .PHP_EOL
            . "  'app_name'=>'".$cameHungaryApp."',".PHP_EOL
            . "  'app_path'=> APP_PATH,".PHP_EOL
            . "  'import'=>array(".PHP_EOL
            . "    'app.Library.*',".PHP_EOL
            . "    'app.Controller.*',".PHP_EOL
            . "    'root.model.*'".PHP_EOL
            . "  ),".PHP_EOL
            . "  'namespace'=> dirname(APP_PATH),".PHP_EOL
            . "  'timezone'=>'Asia/Ho_Chi_Minh',".PHP_EOL
            . ');';
        $fs = new Filesystem\Filesystem();
        $destinationDir = $this->appDir.'Config'.DIRECTORY_SEPARATOR.'main.cfg.php';
        if($fs->exists($destinationDir) === false){
            $fs->dumpFile($destinationDir,$temp);
            echo "-- main.cfg.php is generated success !\n";
        }
    }

    public function _generateSettingConfig(){
        $temp = "<?php".PHP_EOL;
        $temp.='$setting = array('.PHP_EOL
            . '' . PHP_EOL
            . ');' . PHP_EOL
            . 'return $setting;';
        $fs = new Filesystem\Filesystem();
        $destinationDir = $this->appDir.'Config'.DIRECTORY_SEPARATOR.'setting.cfg.php';
        if($fs->exists($destinationDir) === false){
            $fs->dumpFile($destinationDir,$temp);
            echo "-- setting.cfg.php is generated success !\n";
        } else {
            echo "-- setting.cfg.php is exists, aborted !\n";
        }
    }

    public function _generateBaseController(){
        $temp = "<?php".PHP_EOL;
        $appName = Inflection::hungaryNotationToCamel($this->appName);
        $class = $this->appName.'Base';

        $webController = 'ApiController';

        $temp.='namespace '.$appName.'\Controller;'.PHP_EOL;
        $temp.='use Flywheel\Controller\ApiController;'.PHP_EOL;

        $temp.='abstract class '.$class.' extends '.$webController.'{'.PHP_EOL
            . '' . PHP_EOL
            . '}' . PHP_EOL;
        $fs = new Filesystem\Filesystem();
        $destinationDir = $this->appDir.'Controller'.DIRECTORY_SEPARATOR.$class.'.php';
        if($fs->exists($destinationDir) === false){
            $fs->dumpFile($destinationDir,$temp);
            echo "-- ".$class." is generated success !\n";
        } else {
            echo "-- ".$class." is exists, aborted !\n";
        }
    }
    public function run(){
        $this->_generateFolder();
        $this->_generateFileDefault();
        return $this->appName;
    }
}