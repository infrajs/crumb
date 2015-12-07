<?php

namespace infrajs\crumb;
use infrajs\sequence\Sequence;


class Crumb
{
	public $name;
	public $parent;
	public $child;
	public $value;//Строка или null
	public $query;
	public static $childs = array();
	public $counter = 0;
	public static $globalcounter = 0;
	public $path;//Путь текущей крошки
	public static $params;//Всё что после первого амперсанда
	public static $get;
	public $is;
	protected function __construct($right)
	{
	}
	public function getRoot()
	{
		$root = $this;
		while ($root->parent) {
			$root = $root->parent;
		}

		return $root;
	}
	public function __toString(){	
		return implode('/', $this->path);
	}
	public function getInst($name = '')
	{
		$right = $this->path;
		return self::getInstance($name, $right);
	}
	public static function getInstance($name = '', $right = array())
	{
		$right = self::right(array_merge($right, self::right($name)));

		if (@$right[0] === '') {
			$right = array();
		}

		$short = self::short($right);

		if (empty(self::$childs[$short])) {
			$that = new self($right);

			$that->path = $right;
			$that->name = @$right[sizeof($right) - 1];
			$that->value = $that->query = $that->is = $that->counter = null;
			self::$childs[$short] = $that;

			if ($that->name) {
				$that->parent = $that->getInst('//');
			}
		}

		return self::$childs[$short];
	}
	public static function right($short)
	{
		return Sequence::right($short, '/');
	}
	public static function short($right)
	{
		return Sequence::short($right, '/');
	}
	public function getGET()
	{
		return self::$get;
	}
	public static function change($query)
	{
		$amp = explode('&', $query, 2);

		$eq = explode('=', $amp[0], 2);
		$sl = explode('/', $eq[0], 2);
		if (sizeof($eq) !== 1 && sizeof($sl) === 1) {
			//В первой крошке нельзя использовать символ "=" для совместимости с левыми параметрами для главной страницы, которая всё равно покажется
			$params = $query;
			$query = '';
		} else {
			$params = (string) @$amp[1];
			$query = $amp[0];
		}
		self::$params = $params;
		parse_str($params, self::$get);

		$right = self::right($query);
		$counter = ++self::$globalcounter;

		$inst = self::getInstance();
		$old = $inst->path;
		//Crumb::$path=$right;
		//Crumb::$value=(string)@$right[0];
		//Crumb::$query=Crumb::short($right);
		//Crumb::$child=Crumb::getInstance((string)@$right[0]);
		$that = self::getInstance($right);
		$child = null;

		while ($that) {
			$that->counter = $counter;
			$that->is = true;
			$that->child = $child;
			$that->value = (string) @$right[sizeof($that->path)];

			$that->query = self::short(array_slice($right, sizeof($that->path)));
			$child = $that;
			$that = $that->parent;
		};
		$that = self::getInstance($old);
		if (!$that) {
			return;
		}
		while ($that) {
			if ($that->counter == $counter) {
				break;
			}
			$that->is = $that->child = $that->value = $that->query = null;
			$that = $that->parent;
		};
	}
	public static function init()
	{
		//Crumb::$child=Crumb::getInstance();
		$query = urldecode(Path::toutf($_SERVER['QUERY_STRING']));
		self::change($query);
	}
	public function toString()
	{
		return $this->short($this->path);
	}
	public static function set(&$layer, $name, &$value)
	{
		if (!isset($layer['dyn'])) {
			$layer['dyn'] = array();
		}
		$layer['dyn'][$name] = $value;
		if (isset($layer['parent'])) {
			$root = &$layer['parent'][$name];
		} else {
			$root = &ext\Crumb::getInstance();
		}
		if ($layer['dyn'][$name]) {
			$layer[$name] = &$root->getInst($layer['dyn'][$name]);
		} else {
			$layer[$name] = &$root;
		}
	}
}
