<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Kohana asset manager
 *
 * @author nergal
 * @package asset
 */
abstract class Kohana_Asset
{
	const POOL_JS  = 4;
	const POOL_CSS = 8;
	const POOL_ALL = 12;

	/**
	 * @var array
	 */
	protected $_config = array(
		'base_url'        => '',               // Local reference to $view->base_url()
	    'cache_dir'       => 'cache/',         // Directory in which to write bundled javascript
	    'doc_root'        => DOCROOT,          // Directory in which to look for js files
	    'js_prefix'       => 'js',             // Path the generated bundle is publicly accessible under
		'css_prefix'      => 'css',            // Path the generated bundle is publicly accessible under
	    'gzip_level'      => FALSE,            // Gzip level passed to gzencode()
	    'gzip_encoding'   => FORCE_GZIP,       // Encoding type passed to gzencode() FORCE_GZIP|FORCE_DEFLATE
	    'minify_command'  => NULL,             // External command used to minify javascript, The token ':filename'  must be present in command
	    'minify_callback' => NULL,             // Callback to minify javascript within PHP.
                                               // Callback must accept a single string param which is the JS to be minified.
                                               // Callback must return a string which is the minified JS.
	);

	/**
	 * @var array
	 */
	protected $_js_pool = array();

	/**
	 * @var array
	 */
	protected $_css_pool = array();

	/**
	 * @var Kohana_Asset
	 */
	protected static $_instance = NULL;

	/**
	 * Singleton method for asset class
	 *
	 * @static
	 * @return Kohana_Asset
	 */
	public static function instance()
	{
		if (self::$_instance === NULL) {
			$config = Kohana::config('asset')->as_array();
			self::$_instance = new Asset($config);
		}

		return self::$_instance;
	}

	/**
	 * @constructor
	 * @param array $config
	 * @return Kohana_Asset
	 */
    protected function __construct(Array $config = array())
    {
		$this->_config = array_merge($this->_config, $config);

		$this->_pool = array(
			self::POOL_JS => array(),
			self::POOL_CSS => array(),
		);
    }

	/**
	 * Добавление файла в список
	 *
	 * @param array $file_names
	 * @param integer $point
	 */
	protected function add($file_names, $point = self::POOL_JS)
	{
		$file_names = (array) $file_names;

		foreach ($file_names as $file_name) {
			$this->_pool[$point][] = $file_name;
		}

	}

	/**
	 * Добавление js файла в список
	 *
	 * @param array $file_names
	 */
	public static function add_js($file_names)
	{
		self::instance()->add($file_names, self::POOL_JS);
	}

	/**
	 * Добавление css файла в список
	 *
	 * @param array $file_names
	 */
	public static function add_css($file_names)
	{
		self::instance()->add($file_names, self::POOL_CSS);
	}

	/**
	 * Вывод всех скриптов
	 *
	 * @param integer $point
	 * @return string
	 */
	public static function render($point = self::POOL_ALL)
	{
		$self = self::instance();
		$output = array();

		if ($point & self::POOL_JS) {
			$output[] = $self->to_string(self::POOL_JS);
		}

		if ($point & self::POOL_CSS) {
			$output[] = $self->to_string(self::POOL_CSS);
		}

		return implode("\n", $output);
	}

	/**
	 * Вывести собранные файлы
	 *
	 * @param integer $point
	 * @return string
	 */
	public static function plain_render($point = self::POOL_ALL)
	{
		$self = self::instance();
		$output = array();

		if ($point & self::POOL_JS) {
			foreach ($self->_pool[self::POOL_JS] as $url) {
        		$output[] = '<script type="text/javascript" src="'.$url.'"></script>';
			}
        }

        if ($point & self::POOL_CSS) {
        	foreach ($self->_pool[self::POOL_CSS] as $url) {
        		$output[] = '<link type="text/css" rel="stylesheet" href="'.$url.'" />';
        	}
        }

        return implode("\n", $output);
	}

    /**
     * Генерация кэша
     *
     * @param integer $point
     * @return string
     */
    public function to_string($point)
    {
        $filelist = '';
        $mostrecent = 0;

        $ext = 'js';
        if ($point == self::POOL_CSS) {
        	$ext = 'css';
        }

        $prefix = $ext.'_prefix';

        foreach ($this->_pool[$point] as $src) {
			if ($this->_config['base_url'] AND strpos($src, $this->_config['base_url']) !== FALSE) {
				$src =  substr($src, strlen($this->_config['base_url']));
			}

			$mtime = filemtime($this->_config['doc_root'].$src);
			if ($mtime > $mostrecent) {
				$mostrecent = $mtime;
			}

            $filelist.= $src;
        }

        $hash = md5($filelist);
        $cache_file = "{$this->_config['cache_dir']}{$this->_config[$prefix]}/bundle_{$hash}.{$ext}";

        // suppress warning for file DNE
        $cache_time = @filemtime($cache_file);
        if ($cache_time === FALSE OR $cache_time < $mostrecent) {
            $data = $this->_get_data($point);

            $this->_write_uncompressed($cache_file, $data);

            if ($this->_config['gzip_level']) {
                $this->_write_compressed($cache_file, $data);
            }
            $cache_time = filemtime($cache_file);
        }

        $url_path = "{$this->_config['base_url']}/cache/{$this->_config[$prefix]}/bundle_{$hash}.{$ext}?{$cache_time}";

        if ($ext == 'js') {
        	$ret = '<script type="text/javascript" src="'.$url_path.'"></script>';
        } elseif ($ext == 'css') {
        	$ret = '<link type="text/css" rel="stylesheet" href="'.$url_path.'" />';
        }

        return $ret;
    }

    /**
     * Получение данных из файлов
     *
     * @param integer $point
     * @return string
     */
    protected function _get_data($point)
    {
        ob_start();

        foreach ($this->_pool[$point] as $src) {
			if ($this->_config['base_url'] AND strpos($src, $this->_config['base_url']) !== FALSE) {
				$src =  substr($src, strlen($this->_config['base_url']));
			}

            echo file_get_contents($this->_config['doc_root'].$src).PHP_EOL;
        }

        $data = ob_get_clean();
        return $data;
    }

    /**
     * Запись сырого файла
     *
     * @param string $cache_file
     * @param string $data
     */
    protected function _write_uncompressed($cache_file, $data)
    {
		if ( ! empty($this->_config['minify_callback'])) {
			$data = call_user_func($this->_config['minify_callback'], $data);
			file_put_contents($cache_file, $data);
		} elseif ( ! empty($this->_config['minify_command'])) {
			$command = str_replace(':filename', $cache_file, $this->_config['minify_command']);
			$handle = popen("{$command}" , 'w');
			fwrite($handle, $data);
			pclose($handle);
		} else {
			file_put_contents($cache_file, $data);
		}
    }

    /**
     * Запись gzip
     *
     * @param string $cache_file
     * @param string $data
     */
    protected function _write_compressed($cache_file, $data)
    {
        $data = gzencode($data, $this->_config['gzip_level']);
        file_put_contents("{$cache_file}.gz", $data);
    }
}
