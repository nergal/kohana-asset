<?php defined('SYSPATH') or die('No direct script access.');

return array(
    'cache_dir'       => DOCROOT.'cache/',  // Directory in which to write bundled javascript
    'doc_root'        => DOCROOT,           // Directory in which to look for js files
    'js_prefix'       => 'js',              // Path the generated bundle is publicly accessible under
	'css_prefix'      => 'css',             // Path the generated bundle is publicly accessible under
    'gzip_level'      => FALSE,             // Gzip level passed to gzencode()
    'gzip_encoding'   => FORCE_GZIP,        // Encoding type passed to gzencode() FORCE_GZIP|FORCE_DEFLATE
    'minify_command'  => NULL,              // External command used to minify javascript, The token ':filename'  must be present in command
    'minify_callback' => NULL,              // Callback to minify javascript within PHP.
                                            // Callback must accept a single string param which is the JS to be minified.
                                            // Callback must return a string which is the minified JS.
);