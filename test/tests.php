<?php

// external dependencies...
require_once('../src/SignalSlot.php');
use KirbySaysHi\SignalSlot as SignalSlot;
use KirbySaysHi\SS as SS;

// define master test array
$A_TESTS = array();
define('DEBUG', false);

// classes to help test

class Person extends SignalSlot {
	
	// defining a const with a prefix of SIGNAL_ or SLOT_ sets up SignalSlot
	const SIGNAL_READY = 'ready';
	const SIGNAL_DEATH = 'death';

	public function fire_internal_signal(){
		$this->ready('I am ready from within', time());
	}

	public function fire_death(){
		$this->death('I am dead inside', time());
	}
}


class Car extends SignalSlot {
	
	const SIGNAL_STARTUP = 'startup';

	// if a signal tries to connect to SLOT_MISSING, an exception will be thrown
	// because there is no matching receiver method
	const SLOT_MISSING = 'missing';
	const SLOT_OPEN_DOOR = 'open_door';
	const SLOT_WITH_PARAMS = 'with_params';

	public $is_door_open = false;
	public $open_count = 0;

	public $death_msg = '';
	public $time_of_death = 0;

	// slots can be public, private, or protected
	private function open_door(){
		
		$this->is_door_open = true;
		$this->open_count++;
	}

	private function with_params($msg, $time){
		if( count(func_get_args()) < 2 ) throw new Exception('missing args');

		$this->death_msg = $msg;
		$this->time_of_death = $time;
	}

	public function turn_key(){
		$this->startup();
	}
}

class Tire {
	
	const SLOT_BLOW = 'blow';

	public $times_blown = 0;

	private function blow(){
		$this->times_blown++;
	}

}

// POPO: Plain Old PHP Object, one that does not extend SignalSlot
// Slots and signals must be externally visible, so can be protected
// in certain situations but most likely will need to be public.

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

		// POPOs must manually emit
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
	
	assert_not_throws(function() use ($p, $c){
		$p->connect(Person::SIGNAL_READY, $c, Car::SLOT_OPEN_DOOR);	
	});

	// multiple slots listening to same signal
	assert_not_throws(function() use ($p, $c){
		$p->connect(Person::SIGNAL_READY, $c, Car::SLOT_WITH_PARAMS);
	});

	// undefined signal automatically throws fatal error
	//assert_throws(function() use ($p, $c){
	//	$p->connect(Person::SIGNAL_LEVEL_UP, $c, Car::SLOT_OPEN_DOOR);	
	//});
	
};

$A_TESTS['SignalSlot->emit'] = function(){

	$p = new Person();
	$c = new Car();

	$p->connect(Person::SIGNAL_READY, $c, Car::SLOT_OPEN_DOOR);
	$p->connect(Person::SIGNAL_DEATH, $c, Car::SLOT_WITH_PARAMS);

	// slot is fired
	assert_not_throws( function() use ($p, $c){
		$p->fire_internal_signal(); 
	});

	assert_equal( $c->is_door_open, true );
	assert_equal( $c->open_count, 1 );

	assert_not_throws( function() use ($p, $c){
		$p->fire_death(); 
	});

	// check that params are passed through properly
	assert_equal( $c->death_msg, 'I am dead inside' );

	// double slot
	$p->connect(Person::SIGNAL_READY, $c, Car::SLOT_OPEN_DOOR);
	$p->fire_internal_signal(); 
	assert_equal( $c->open_count, 3 );

	// make sure __call isn't letting privates out
	assert_throws(function() use ($p){
		$p->emit();
	});

	// signals are inaccessible if called outside of their defined class or children
	assert_throws(function() use ($p){
		$p->ready();
	});

	//echo "\n....\n";
	//$p->ready();
};

$A_TESTS['SignalSlot->disconnect'] = function(){
	
	$p = new Person();
	$c = new Car();

	$p->connect(Person::SIGNAL_READY, $c, Car::SLOT_OPEN_DOOR);
	$p->fire_internal_signal(); 
	assert_equal( $c->open_count, 1 );

	$p->disconnect(Person::SIGNAL_READY, $c, Car::SLOT_OPEN_DOOR);
	$p->fire_internal_signal(); 
	assert_equal( $c->open_count, 1 );	// should be unaffected by call

	$p->connect(Person::SIGNAL_READY, $c, Car::SLOT_OPEN_DOOR);
	$p->connect(Person::SIGNAL_READY, $c, Car::SLOT_OPEN_DOOR);

	// one disconnect call should kill all of same object, slot, signal
	$p->disconnect(Person::SIGNAL_READY, $c, Car::SLOT_OPEN_DOOR);

	$p->fire_internal_signal(); 
	assert_equal( $c->open_count, 1 );

};

$A_TESTS['SignalSlot with POPO'] = function(){
	
	// the receiver (where the slot is defined) doesn't have to inherit from SignalSlot

	$c = new Car();
	$t = new Tire();

	$c->connect(Car::SIGNAL_STARTUP, $t, Tire::SLOT_BLOW);
	$c->turn_key();
	$c->turn_key();

	assert_equal($t->times_blown, 2);
};

$A_TESTS['SS::connect'] = function(){
	
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


$A_TESTS['SS::disconnect'] = function(){
	
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