flywheel-framework
==================

Simple PHP Framework




How to use
==================
Asset Management for Flywheel Framework
 $config = array(
  'envi' => 'dev',
  'combine' => true,
  'minify' => true,
  'base_url' => '',
  'assets_path' => 'path/to/asset/folder',
  'assets_dir' => 'assets',
  'base_path' => 'assets',
  'cache_dir' => 'cache',
  'cache_path' => 'path/to/asset/folder', //
  'cache_url' => 'cache', // base_url/cache_dr
  'js_dir' => 'js',
  'js_path' => 'js', //
  'js_url' => 'js',
  'css_dir' => 'css',
  'css_path' => 'css', //
  'css_url' => 'css',
  );

 
 $cache = new \Flywheel\Asset\Asset;
 $cache->js('file.css','group_name');   //add css file to group 'group_name'
 $cache->js('file.css','group_name');   //add js file to group 'group_name'
 $cache->display();  //display assets
    



