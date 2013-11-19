<?php
namespace Flywheel;
class Loader {
    public static $classMap = array();
    public static $enableIncludePath = true;

    protected static $_namespaces = array();
    private static $_aliases = array();
    private static $_imports;
    private static $_includePaths;

    public static function register() {
        if (!isset(self::$_namespaces['Flywheel'])) {
            self::$_namespaces['Flywheel'] = dirname(dirname(__FILE__));
        }
        spl_autoload_register('\Flywheel\Loader::loadClass');
    }

    public static function addNamespace($ns, $path) {
        self::$_namespaces[$ns] = $path;
    }

    public static function removeNamespace($ns) {
        unset(self::$_namespaces[$ns]);
    }

    public static function loadClass($className) {
        $className = ltrim($className, '\\');
        if (class_exists($className, false) || interface_exists($className, false)) return true;
        if(isset(self::$classMap[$className])) {
            include(self::$classMap[$className]);
            return true;
        }

        if (strpos($className, '\\') !== false) {//class with namespace
            foreach (self::$_namespaces as $namespace => $path) {

                if (strpos($className, $namespace .'\\') === 0) {
                    
                    require $path .DIRECTORY_SEPARATOR .str_replace('\\', DIRECTORY_SEPARATOR, $className).'.php';
                    return true;
                }
            }
        } else {
            if(self::$enableIncludePath===false) {
                foreach(self::$_includePaths as $path)
                {
                    $classFile=$path.DIRECTORY_SEPARATOR.$className.'.php';
                    if(is_file($classFile)) {
                        include($classFile);
                        break;
                    }
                }

                $namespace=str_replace('\\','.',$className);
                if(($path=self::getPathOfAlias($namespace))!==false)
                    include($path.'.php');
                else
                    return false;
            }
            else {
                include($className.'.php');
            }
        }

        return class_exists($className,false) || interface_exists($className,false);
    }

    /**
     * Translates an alias into a file path.
     * Note, this method does not ensure the existence of the resulting file path.
     * It only checks if the root alias is valid or not.
     * @param string $alias alias (e.g. Ming.Application.Web)
     * @return mixed file path corresponding to the alias, false if the alias is invalid.
     */
    public static function getPathOfAlias($alias)
    {

        if(isset(self::$_aliases[$alias]))

            return self::$_aliases[$alias];
        else if(($pos=strpos($alias,'.'))!==false)
        {
            $rootAlias=substr($alias,0,$pos);
            if(isset(self::$_aliases[$rootAlias])) {
                return self::$_aliases[$alias]=rtrim(self::$_aliases[$rootAlias].DIRECTORY_SEPARATOR.str_replace('.',DIRECTORY_SEPARATOR,substr($alias,$pos+1)),'*'.DIRECTORY_SEPARATOR);
            }
        }
        return false;
    }

    /**
     * Create a path alias.
     * Note, this method neither checks the existence of the path nor normalizes the path.
     * @param string $alias alias to the path
     * @param string $path the path corresponding to the alias. If this is null, the corresponding
     * path alias will be removed.
     */
    public static function setPathOfAlias($alias,$path) {
        if(empty($path)) {
            unset(self::$_aliases[$alias]);
        } else {
            self::$_aliases[$alias] = rtrim($path,'\\/');
        }
    }

    /**
     * Imports a class or a directory.
     *
     * Importing a class is like including the corresponding class file.
     * The main difference is that importing a class is much lighter because it only
     * includes the class file when the class is referenced the first time.
     *
     * Importing a directory is equivalent to adding a directory into the PHP include path.
     * If multiple directories are imported, the directories imported later will take
     * precedence in class file searching (i.e., they are added to the front of the PHP include path).
     *
     * Path aliases are used to import a class or directory. For example,
     * <ul>
     *   <li><code>application.components.GoogleMap</code>: import the <code>GoogleMap</code> class.</li>
     *   <li><code>application.components.*</code>: import the <code>components</code> directory.</li>
     * </ul>
     *
     * The same path alias can be imported multiple times, but only the first time is effective.
     * Importing a directory does not import any of its subdirectories.
     *
     * Starting from version 1.1.5, this method can also be used to import a class in namespace format
     * (available for PHP 5.3 or above only). It is similar to importing a class in path alias format,
     * except that the dot separator is replaced by the backslash separator. For example, importing
     * <code>application\components\GoogleMap</code> is similar to importing <code>application.components.GoogleMap</code>.
     * The difference is that the former class is using qualified name, while the latter unqualified.
     *
     * Note, importing a class in namespace format requires that the namespace is corresponding to
     * a valid path alias if we replace the backslash characters with dot characters.
     * For example, the namespace <code>application\components</code> must correspond to a valid
     * path alias <code>application.components</code>.
     *
     * @param string $alias path alias to be imported
     * @param boolean $forceInclude whether to include the class file immediately. If false, the class file
     * will be included only when the class is being used. This parameter is used only when
     * the path alias refers to a class.
     * @param string $ext
     * @throws Exception
     * @return string the class name or the directory that this alias refers to
     */
    public static function import($alias, $forceInclude = false, $ext = '.php') {
        if (isset(self::$_imports[$alias])) return self::$_imports[$alias];
        if(class_exists($alias,false) || interface_exists($alias,false))
            return self::$_imports[$alias] = $alias;


        if(($pos=strrpos($alias,'\\'))!==false) // a class name in PHP 5.3 namespace format
        {
            $alias = ltrim($alias,'\\');
            foreach (self::$_namespaces as $ns => $path) {
                if (strpos($alias, $ns .'\\') === 0) {
                    if (file_exists($f = $path .DIRECTORY_SEPARATOR .str_replace('\\', DIRECTORY_SEPARATOR, $alias).'.php')) {
                        require $f;
                        return $alias;
                    }
                }
            }

            $namespace=str_replace('\\','.',ltrim(substr($alias,0,$pos),'\\'));

            if(($path=self::getPathOfAlias($namespace))!==false)
            {
                $classFile=$path.DIRECTORY_SEPARATOR.substr($alias,$pos+1).$ext;
                if($forceInclude)
                {
                    if(is_file($classFile))
                        require($classFile);
                    else
                        throw new Exception("Loader: Alias \"{$alias}\" is invalid. Make sure it points to an existing PHP file and the file is readable.");
                    self::$_imports[$alias]=$alias;
                }
                else
                    self::$classMap[$alias]=$classFile;
                return $alias;
            }
            else
                throw new Exception("Loader: Alias \"{$alias}\" is invalid. Make sure it points to an existing directory.");
        }
        if(($pos=strrpos($alias,'.'))===false)  // a simple class name
        {
            if($forceInclude && self::loadClass($alias))
                self::$_imports[$alias]=$alias;
            return $alias;
        }
        $className=(string)substr($alias,$pos+1);
        $isClass=$className!=='*';
        if($isClass && (class_exists($className,false) || interface_exists($className,false)))
            return self::$_imports[$alias]=$className;

        if(($path=self::getPathOfAlias($alias))!==false)
        {
            if($isClass)
            {
                if($forceInclude)
                {
                    if(is_file($path.$ext))
                        require($path.$ext);
                    else
                        throw new Exception("Loader: Alias \"{$alias}\" is invalid. Make sure it points to an existing PHP file and the file is readable.");
                    self::$_imports[$alias]=$className;
                }
                else
                    self::$classMap[$className]=$path.$ext;
                return $className;
            }
            else  // a directory
            {
                if(self::$_includePaths===null)
                {
                    self::$_includePaths=array_unique(explode(PATH_SEPARATOR,get_include_path()));
                    if(($pos=array_search('.',self::$_includePaths,true))!==false)
                        unset(self::$_includePaths[$pos]);
                }
                array_unshift(self::$_includePaths,$path);
                if(self::$enableIncludePath && set_include_path('.'.PATH_SEPARATOR.implode(PATH_SEPARATOR,self::$_includePaths))===false)
                    self::$enableIncludePath=false;
                return self::$_imports[$alias]=$path;
            }
        }
        else {
            throw new Exception("Loader: Alias \"{$alias}\" is invalid. Make sure it points to an existing directory or file.");
        }
    }
}