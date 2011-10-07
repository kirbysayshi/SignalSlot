<?php

// external dependencies...
require_once('../src/SignalSlot.php');

// define master test array
$A_TESTS = array();
define('DEBUG', true);

// classes to help test

class Person extends SignalSlot {
	
	const SIGNAL_READY = 'ready';
	const SIGNAL_DEATH = 'death';
	const SIGNAL_LEVEL_UP = 'level_up';

	private function ready($msg = false, $when = false){
		if( $msg === false && $when === false ) throw new Exception('missing args');
		return 20;
	}

}


class Car extends SignalSlot {
	
	const SIGNAL_STARTUP = 'startup';
	const SIGNAL_IDLE = 'idle';

	const SLOT_MISSING = 'missing';
	const SLOT_OPEN_DOOR = 'open_door';

	public $is_door_open = false;
	public $open_count = 0;

	private function open_door($arr, $bool){
		if( count(func_get_args()) < 2 ) throw new Exception('missing args');
		$this->is_door_open = true;
		$this->open_count++;
	}

	private function startup(){
	}
}

class Tire {
	
	const SLOT_BLOW = 'blow';

	public $times_blown = 0;

	private function blow(){
		$this->times_blown++;
	}

}

class Windshield {
	
	const SLOT_BLOWUP = 'blowup';

	public $blownup_count = 0;

	public function blowup(){
		$this->blownup_count++;
	}
}

class Rock {
	
	const SIGNAL_THROWN = 'thrown';

	public $thrown_count = 0;

	public function thrown(){
		$this->thrown_count++;
		SS::emit($this, self::SIGNAL_THROWN);
	}

}


$A_TESTS['SignalSlot->connect'] = function(){

	$p = new Person();
	$c = new Car();

	// undefined slot
	assert_throws(function() use ($p, $c){
		$p->connect(Person::SIGNAL_READY, $c, Car::SLOT_MISSING);	
	});
	
	// undefined signal
	assert_throws(function() use ($p, $c){
		$p->connect(Person::SIGNAL_LEVEL_UP, $c, Car::SLOT_OPEN_DOOR);	
	});

	// passes args through
	assert_not_throws( function() use ($p) { 
		$p->ready("Let's go!", time()); 
	});

	// __call overload returns method value
	assert_equal( $p->ready("Let's go!", time()), 20 );

	$p->connect(Person::SIGNAL_READY, $c, Car::SLOT_OPEN_DOOR);

	// slot is fired
	assert_not_throws( function() use ($p, $c){
		$p->ready("Let's go!", time());
	});

	assert_equal( $c->is_door_open, true );
	assert_equal( $c->open_count, 1 );

	// double slot
	$p->connect(Person::SIGNAL_READY, $c, Car::SLOT_OPEN_DOOR);
	$p->ready("Let's go!", time());
	assert_equal( $c->open_count, 3 );
	
};

$A_TESTS['SignalSlot->disconnect'] = function(){
	
	$p = new Person();
	$c = new Car();

	$p->connect(Person::SIGNAL_READY, $c, Car::SLOT_OPEN_DOOR);
	$p->ready("Let's go!", time());
	assert_equal( $c->open_count, 1 );

	$p->disconnect(Person::SIGNAL_READY, $c, Car::SLOT_OPEN_DOOR);
	$p->ready("Let's go!", time());
	assert_equal( $c->open_count, 1 );	// should be unaffected by call

	$p->connect(Person::SIGNAL_READY, $c, Car::SLOT_OPEN_DOOR);
	$p->connect(Person::SIGNAL_READY, $c, Car::SLOT_OPEN_DOOR);
	$p->disconnect(Person::SIGNAL_READY, $c, Car::SLOT_OPEN_DOOR);

	$p->ready("Let's go!", time());
	assert_equal( $c->open_count, 2 );

};

$A_TESTS['SignalSlot with POPO'] = function(){
	
	// the receiver (where the slot is defined) doesn't have to inherit from SignalSlot

	$c = new Car();
	$t = new Tire();

	$c->connect(Car::SIGNAL_STARTUP, $t, Tire::SLOT_BLOW);
	$c->startup();
	$c->startup();

	assert_equal($t->times_blown, 2);
};

$A_TESTS['SS connect'] = function(){
	
	$w = new Windshield;
	$r = new Rock;

	assert_not_throws(function() use ($w, $r){
		SS::connect($r, Rock::SIGNAL_THROWN, $w, Windshield::SLOT_BLOWUP);	
	});
	
	$r->thrown();
	assert_equal( $w->blownup_count, 1 );
	assert_equal( $r->thrown_count, 1 );

	$r->thrown();
	$r->thrown();

	assert_equal( $w->blownup_count, 3 );
	assert_equal( $r->thrown_count, 3 );

	SS::connect($r, Rock::SIGNAL_THROWN, $w, Windshield::SLOT_BLOWUP);	
	SS::connect($r, Rock::SIGNAL_THROWN, $w, Windshield::SLOT_BLOWUP);

	$r->thrown();
	assert_equal( $w->blownup_count, 6 );
	assert_equal( $r->thrown_count, 4 );
};


$A_TESTS['SS disconnect'] = function(){
	
	$w = new Windshield;
	$r = new Rock;

	SS::connect($r, Rock::SIGNAL_THROWN, $w, Windshield::SLOT_BLOWUP);	
	$r->thrown();
	assert_equal( $w->blownup_count, 1 );
	assert_equal( $r->thrown_count, 1 );

	assert_not_throws(function() use ($w, $r){
		SS::disconnect($r, Rock::SIGNAL_THROWN, $w, Windshield::SLOT_BLOWUP);	
	});
	
	$r->thrown();
	assert_equal( $w->blownup_count, 1 );
	assert_equal( $r->thrown_count, 2 );

	SS::connect($r, Rock::SIGNAL_THROWN, $w, Windshield::SLOT_BLOWUP);	
	SS::connect($r, Rock::SIGNAL_THROWN, $w, Windshield::SLOT_BLOWUP);	
	SS::disconnect($r, Rock::SIGNAL_THROWN, $w, Windshield::SLOT_BLOWUP);	

	$r->thrown();
	assert_equal( $w->blownup_count, 2 );
	assert_equal( $r->thrown_count, 3 );
};

include('../lib/aristotle.php');