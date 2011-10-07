<?php

require_once '../lib/benchmark.php';
$benchmark = new Benchmark;


class Test {

	public function noop(){
		
	}

	public function call(){
		call_user_func_array( array($this, 'noop'), array(0,1,2,3,4));
	}

	public function invoke(){
		$ref = new ReflectionMethod("Test", "noop");
		$ref->invokeArgs($this, array(0,1,2,3,4));
	}

	public function wrap_call_user_func_array($a, $p) {
		if(method_exists($this, $a))
		switch(count($p)) {
			case 0: return $this->{$a}(); break;
			case 1: return $this->{$a}($p[0]); break;
			case 2: return $this->{$a}($p[0], $p[1]); break;
			case 3: return $this->{$a}($p[0], $p[1], $p[2]); break;
			case 4: return $this->{$a}($p[0], $p[1], $p[2], $p[3]); break;
			case 5: return $this->{$a}($p[0], $p[1], $p[2], $p[3], $p[4]); break;
			default: return call_user_func_array(array($this, $a), $p);  break;
		}
	} 

}


function noop(){
	$t = new Test();
	$t->noop();
}

function benchmark_call_user_func_array(){
	$t = new Test();
	$t->call();
}

function benchmark_reflection_invoke(){
	$t = new Test();
	$t->invoke();
}

function benchmark_wrap_array(){
	$t = new Test();
	$t->wrap_call_user_func_array('noop', array(0,1,2,3,4));
}

function benchmark_inline_wrap_array(){
	$t = new Test();
	$p = array(0,1,2,3,4);//func_get_args();
	$n = 'noop';
	switch(count($p)) {
		case 0: return $t->{$n}(); break;
		case 1: return $t->{$n}($p[0]); break;
		case 2: return $t->{$n}($p[0], $p[1]); break;
		case 3: return $t->{$n}($p[0], $p[1], $p[2]); break;
		case 4: return $t->{$n}($p[0], $p[1], $p[2], $p[3]); break;
		case 5: return $t->{$n}($p[0], $p[1], $p[2], $p[3], $p[4]); break;
		default: return call_user_func_array(array($this, $a), $p);  break;
	}
}

$benchmark->addFunction('noop', 'noop');
$benchmark->addFunction('call_user_func_array', 'benchmark_call_user_func_array');
$benchmark->addFunction('ReflectionMethod::invokeArgs', 'benchmark_reflection_invoke');
$benchmark->addFunction('wrap_call_user_func_array', 'benchmark_wrap_array');
$benchmark->addFunction('inlined args', 'benchmark_inline_wrap_array');

// Benchmark by time
echo "Benchmark by time...\n";
$time = $benchmark->benchmarkTime(2); // two functions * 2 seconds = 4 seconds
$time->outputTable();

// Benchmark by calls
echo "Benchmark by calls...\n";
$count = $benchmark->benchmarkCount(1000000); // two functions * 100000 calls = 200000 calls
$count->outputTable();