<?php

/**
 * @group nergal
 * @group nergal.modules
 * @group nergal.modules.asset
 */
class KohanaAssetTest extends PHPUnit_Framework_TestCase {

	static protected $test_instance;
	protected $test_filename;

	public function setUp()
	{
		$this->test_filename = '/tmp/test-asset-'.uniqid().'.tmp';
		self::$test_instance = new TestMockAsset;
		$this->tearDown();
	}

	public function tearDown()
	{
		if (file_exists($this->test_filename.'.gz')) {
			unlink($this->test_filename.'.gz');
		}
		if (file_exists($this->test_filename)) {
			unlink($this->test_filename);
		}
	}
	
	public function testInstance() 
	{
	}
	
	private function _clear_pool() {
		$i = self::$test_instance;
		foreach (array($i::POOL_JS, $i::POOL_CSS) as $pool) {
			$i->pool($pool, array());
		}
	}
	
	public function testAdd()
	{
		$i = self::$test_instance;

		$data = 'test.css';
		$i->add($data);
		$this->assertContains($data, $i->pool($i::POOL_JS));
		$this->_clear_pool();

		$i->add($data, $i::POOL_JS);
		$this->assertContains($data, $i->pool($i::POOL_JS));
		$this->_clear_pool();

		$i->add($data, $i::POOL_CSS);
		$this->assertContains($data, $i->pool($i::POOL_CSS));
		$this->_clear_pool();


		$data = array('test.css', 'test.js', 'test.php');
		$i->add($data);
		foreach ($data as $_data)
			$this->assertContains($_data, $i->pool($i::POOL_JS));
		$this->_clear_pool();

		$i->add($data, $i::POOL_JS);
		foreach ($data as $_data);
			$this->assertContains($_data, $i->pool($i::POOL_JS));
		$this->_clear_pool();

		$i->add($data, $i::POOL_CSS);
		foreach ($data as $_data)
			$this->assertContains($_data, $i->pool($i::POOL_CSS));
		$this->_clear_pool();
	}
	
	public function testRender()
	{
		$i = self::$test_instance;
		
		foreach(array($i::POOL_ALL, $i::POOL_JS, $i::POOL_CSS, NULL) as $point) {
			$mock = $this->getMockBuilder('TestMockAsset');
			$mock->setMethods(array('to_string'));
			$mock->disableOriginalConstructor();
			$mock = $mock->getMock();
			
			if ($point == $i::POOL_ALL OR $point === NULL) {
				$calle = $this->exactly(2);
				$expect = $this->onConsecutiveCalls($i::POOL_JS, $i::POOL_CSS);
			} else {
				$calle = $this->once();
				$expect = $this->onConsecutiveCalls($point);
			}
			
			$mock->expects($calle)
				->method('to_string')
				->will($expect);

			$i::setMock($mock);
			
			if ($point === NULL) {
				$out = $i::render();
			} else {
				$out = $i::render($point);
			}
		}
	}
	
	public function testPlainRender()
	{
	}
	
	public function testToString()
	{
	}

	
	public function testGetData()
	{
		
	}
	
	public function testWriteUncompressed()
	{
		$sample_data = md5(microtime(TRUE));
		
		// Plain write
		self::$test_instance
			->setConfig('minify_callback', NULL)
			->setConfig('minify_command',  NULL);
		self::$test_instance->write_uncompressed($this->test_filename, $sample_data);
		
		$this->assertFileExists($this->test_filename);
		$this->assertStringEqualsFile($this->test_filename, $sample_data);
		
		$this->tearDown();

		// Callback
		self::$test_instance
			->setConfig('minify_callback', 'base64_encode')
			->setConfig('minify_command',  NULL);
		self::$test_instance->write_uncompressed($this->test_filename, $sample_data);
		
		$this->assertFileExists($this->test_filename);
		$this->assertStringEqualsFile($this->test_filename, base64_encode($sample_data));
		
		$this->tearDown();

		// Command
		self::$test_instance
			->setConfig('minify_callback', NULL)
			->setConfig('minify_command',  'echo "'.$sample_data.'" > :filename');
		self::$test_instance->write_uncompressed($this->test_filename, $sample_data);
		
		$this->assertFileExists($this->test_filename);

		$a = file_get_contents($this->test_filename);
		$this->assertStringEqualsFile($this->test_filename, $sample_data."\n");
	}
	
	public function testWriteCompressed()
	{
		$sample_data = md5(microtime(TRUE));
		$default_gzip_level = 5;
		$sample_data_gz = gzencode($sample_data, $default_gzip_level);
		
		self::$test_instance->setConfig('gzip_level', $default_gzip_level);
		self::$test_instance->write_compressed($this->test_filename, $sample_data);
		
		$test_filename = $this->test_filename.'.gz';
		$this->assertFileExists($test_filename);
		
		$this->assertStringEqualsFile($test_filename, $sample_data_gz);
	}
}

class TestMockAsset extends Kohana_Asset {
	protected static $_mock;

	public function __construct() { parent::__construct(); }
	public function write_compressed($cache_file, $data) {
		$this->_write_compressed($cache_file, $data);
	}
	public function write_uncompressed($cache_file, $data) {
		$this->_write_uncompressed($cache_file, $data);
	}
	public function setConfig($key, $value = NULL) {
		$this->_config[$key] = $value;
		return $this;
	}
	public function pool($key = NULL, $value = NULL) {
		if ($value === NULL) {
			if ($key === NULL) {
				return $this->_pool;
			} else {
				return $this->_pool[$key];
			}
		} else {
			$this->_pool[$key] = $value;
			return $this;
		}
	}
	public function get_data($point) {
		return $this->_get_data($point);
	}
	public function add($file_names, $point = self::POOL_JS) {
		return $this->_add($file_names, $point);
	}
	public static function getMock() {
		return self::$_mock;
	}
	public static function instance() {
		return self::getMock();
	}
	public static function setMock($mock) {
		self::$_mock = $mock;
	}
}