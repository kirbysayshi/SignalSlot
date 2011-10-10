<?php

///////////////////////////////////////////////////////////////////////////////
// Aristotle
//
// Simple simple simple limited testing, when you don't want something bigger.
//
// To use: 
// 1) define a global array named $A_TESTS. 
// 2) Add keys pointing at functions that call any number of assert methods 
// (assert_equal, assert_not_equal, assert_throws, assert_not_throws). 
// 3) Include Aristotle at the bottom of your test file.
// 4) php name_of_your_file.php

class Aristotle {
	
	private static $instance = null;

	private $passed = 0;
	private $failed = 0;
	private $borked = 0;

	private $current_fails = 0;
	private $tests = array();

	private function __construct(){
		global $A_TESTS;

		if(!defined('DEBUG')) define('DEBUG', false);

		// sanity check
		if(empty($A_TESTS)) {
			$this->msg("No tests to test! (Be sure \$A_TESTS is populated)");
			$this->msg("ARISTOTLE IS DISPLEASED.");
			die();
		} else {
			$this->tests = $A_TESTS;
		}

	}

	public function msg($msg){
		echo $msg."\n";
	}

	public function info($msg){
		echo "\t".$msg."\n";
	}

	public static function getInstance(){
		if(is_null(self::$instance)){
			self::$instance = new Aristotle();
		}

		return self::$instance;
	}

	// adapted from : http://us2.php.net/manual/en/function.debug-backtrace.php#99752
	public function where_called( $level = 1 ) {
		$trace  = debug_backtrace();

		$file = $trace[$level]['file'];
		$line = $trace[$level]['line'];
		$func = $trace[$level]['function'];
		//$object = $trace[$level]['object'];
		//if (is_object($object)) { $object = get_class($object); }

		return "line $line of function $func \n\t(in $file)";
	}

	public function run(){

		foreach($this->tests as $name => $test){
	
			$this->current_fails = 0;

			$this->msg("TEST: " . $name);

			$pass = "RESULT: " . $name . " ... PASSED\n";
			$fail = "RESULT: " . $name . " ... FAILED\n";
			$bork = "RESULT: " . $name . " ... BORKED\n";

			try {
				$result = $test();
				
				if($this->current_fails === 0){
					$this->msg($pass);
					$this->passed += 1;
				} else {
					$this->msg($fail);
					$this->failed += 1;
				}

			} catch(Exception $e){
				$this->msg($bork);
				if(DEBUG) print($e);
				$this->borked += 1;
			}
		}

		$this->msg("->> $this->passed tests passed");
		$this->msg("->> $this->failed tests failed");
		$this->msg("->> $this->borked tests are borked.");

		if( $this->passed === count($this->tests) ) {
			$this->msg("\nARISTOTLE IS PLEASED.\n");
		}
	}

	public function fail($msg){
		$this->current_fails += 1;
		$this->info($msg);
		return false;
	}

}

///////////////////////////////////////////////////////////////////////////////
// global assertion functions

function assert_equal($a, $b, $desc = ''){
	$att = Aristotle::getInstance();

	if($a == $b) return true;
	else {
		$att->fail("Expected $a to equal $b" .($desc == '' ? '' : " for ".$desc) );
	}
}

function assert_not_equal($a, $b, $desc = ''){
	$att = Aristotle::getInstance();

	if($a == $b) {
		return $att->fail("Expected $a to not equal $b" .($desc == '' ? '' : " for ".$desc) );
	}
	else {
		return true;
	}
}

function assert_throws( $func ){
	$att = Aristotle::getInstance();

	$threw = false;
	try{
		$func();
	} catch(Exception $e){
		if(DEBUG) {
			$att->msg("\nDEBUG enabled, dumping caught exception");
			$att->msg($e);
		}
		$threw = true;
	}

	if($threw === true){
		return true;
	} else {
		return $att->fail('Expected exception in '.$att->where_called(1));
	}
}

function assert_not_throws( $func ){
	$att = Aristotle::getInstance();

	$threw = false;
	try{
		$func();
	} catch(Exception $e){
		if(DEBUG) {
			$att->msg("\nDEBUG enabled, dumping caught exception");
			$att->msg($e);
		}
		$threw = true;
	}

	if($threw === true){
		return $att->fail('Unexpected exception in '.$att->where_called(1));
	} else {
		return true;
	}
}


///////////////////////////////////////////////////////////////////////////////
// and go!

Aristotle::getInstance()->run();