<?php
namespace infrajs\crumb;
use infrajs\controller\Controller;
use infrajs\controller\Each;
use infrajs\path\Path;
use infrajs\event\Event;
use infrajs\sequence\Sequence;
use infrajs\template\Template;
use infrajs\controller\External;

//Должны собраться внешние описания слоёв
Path::reqif('*controller/infra.php');

Event::handler('oninit', function () {
	$root = Crumb::getInstance();
	
	Sequence::set(Template::$scope, Sequence::right('infra.Crumb.query'), $root->query);
	Sequence::set(Template::$scope, Sequence::right('infra.Crumb.params'), Crumb::$params);
	Sequence::set(Template::$scope, Sequence::right('infra.Crumb.get'), Crumb::$get);

	$cl = function ($mix = null) {
		return ext\Crumb::getInstance($mix);
	};
	Sequence::set(Template::$scope, Sequence::right('infra.Crumb.getInstance'), $cl);
	External::add('child', 'layers');
	External::add('childs', function (&$now, &$ext) {
		//Если уже есть значения этого свойства то дополняем
		if (!$now) {
			$now = array();
		}
		Each::forx($ext, function (&$n, $key) use (&$now) {
			if (@$now[$key]) {
				return;
			}
			$now[$key] = array('external' => &$n);
		});

		return $now;
	});
	External::add('crumb', function (&$now, &$ext, &$layer, &$external, $i) {//проверка external в onchange
		Crumb::set($layer, 'crumb', $ext);
		return $layer[$i];
	});
	Controller::runAddKeys('childs');
	Controller::runAddList('child');
});



Event::handler('layer.oninit', function (&$layer) {
	//это из-за child// всё что после child начинает плыть. по этому надо crumb каждый раз определять, брать от родителя.
	//crumb
	if (!isset($layer['dyn'])) {
		//Делается только один раз
		Crumb::set($layer, 'crumb', $layer['crumb']);
	}
}, 'crumb:external');
Event::handler('layer.oninit', function (&$layer) {
	//crumb
	if (empty($layer['parent'])) {
		return;
	}
	Crumb::set($layer, 'crumb', $layer['dyn']['crumb']);//Возможно у родителей обновился crumb из-за child у детей тоже должен обновиться хотя они не в child
}, 'crumb:external');

Event::handler('layer.oninit', function (&$layer) {

	//crumb child
	if (@!$layer['child']) return;//Это услвие после Crumb::set
	$crumb = &$layer['crumb']->child;
	if ($crumb) {
		$name = $crumb->name;
	} else {
		$name = '###child###';
	}
	Each::fora($layer['child'], function (&$l) use (&$name) {
		Crumb::set($l, 'crumb', $name);
	});
}, 'crumb:external');
Event::handler('layer.oninit', function (&$layer) {
	Each::forx($layer['childs'], function (&$l, $key) {
		//У этого childs ещё не взять external
		if (empty($l['crumb'])) {
			Crumb::set($l, 'crumb', $key);
		}
	});
}, 'crumb:external');


Event::handler('layer.ischeck', function (&$layer) {
	if (!$layer['crumb']->is) return false;

}, 'crumb:external');