<?php

// modified from: http://www.osebboy.com/blog/signals-and-slots-for-php/

interface ISignalSlot
{
	public function __construct();
	public function connect($signal, $context, $slot, $config = array());
	public function disconnect($signal, $context, $slot);
	public function emit($signal, $args = null);
}

class SignalSlot implements ISignalSlot {

	protected $signals = array();
	protected $slots = array();

	private $debug = false;

	public function __construct() {
		$this->setup();
	}

	private function setup(){
		$ref = new ReflectionClass($this);
		$consts = $ref->getConstants();

		foreach ($consts as $key => $val)
		{
			if (substr($key, 0, 7) === 'SIGNAL_' && $ref->hasMethod($val)) {

				$this->signals[$val] = array();

			} else if(substr($key, 0, 5) === 'SLOT_' && $ref->hasMethod($val)){

				$this->slots[$val] = array();
			}
		}
	}

	public function __call($name, $args){
		
		// see if this method call is a valid SIGNAL
		if( isset($this->signals[$name]) ){

			$this->debug && print 'Auto emitted SIGNAL_'.$name." with args: ". implode(',', $args) ." \n";
			
			// save original args for passthru call
			$orig = $args;
			array_unshift( $args, $name );

			$ref = new ReflectionClass($this);
			$return;

			// pass call to actual method
			$pass = $ref->getMethod($name);
			if($pass->isPrivate() || $pass->isProtected()) {
				$pass->setAccessible(true);
				$return = $pass->invokeArgs($this, $orig);
				//$pass->setAccessible(false);
			} else {
				$return = $pass->invokeArgs($this, $orig);
			}

			// notify listening slots
			$emit = $ref->getMethod('emit');
			$emit->invokeArgs($this, $args);

			return $return;
		}

		// see if method call request is valid slot
		// this bypasses visibility, since slots need to be externally callable
		if( isset($this->slots[$name]) ){

			$ref = new ReflectionClass($this);
			$meth = $ref->getMethod($name);

			if($meth->isPrivate() || $meth->isProtected()) {
				$meth->setAccessible(true);
				$return = $meth->invokeArgs($this, $args);
				$meth->setAccessible(false);
			} else {
				$return = $meth->invokeArgs($this, $args);
			}

			return $return;
		}
	}

	public function connect($signal, $context, $slot, $config = array()){

		if (!isset($this->signals[$signal])) {
			throw new Exception ("'$signal' signal is not declared in " . get_class($this));
		}
		
		$ctx_ref = new ReflectionClass($context);

		if(!$ctx_ref->hasMethod($slot)){
			throw new Exception( $slot . ' is not defined in '. (is_string($context) ? $context : get_class($context)) );
		}

		$this->signals[$signal][] = array(
			'context' => $context,
			'slot'    => $slot,
			'config'  => $config);
		
		$this->debug && var_dump("count for signal $signal: ".count( $this->signals[$signal] ) );
	}

	public function disconnect($signal, $context, $slot){

		if (!isset($this->signals[$signal]) || empty($this->signals[$signal])) {
			return; // throw exception if signal is not set??
		}

		$def = array('context' => $context, 'slot' => $slot);
		
		foreach ($this->signals[$signal] as $id => $receiver){
			unset($receiver['config']);
			
			if ($receiver === $def){
				unset($this->signals[$signal][$id]);
				return true;
			}
		}
		return false;
	}

	public function emit($signal, $args = null){

		$return = null;
		$args = array_slice(func_get_args(), 1);
		
		foreach ($this->signals[$signal] as $receiver){

			$this->debug && var_dump('receiver', $receiver);

			$context = $receiver['context'];
			$method  = $receiver['slot'];
			$config  = $receiver['config'];
			
			if (is_string($context)){
				$context = !empty($config) ? new $context($config) : new $context();
			}

			$ref = new ReflectionClass($context);
			$meth = $ref->getMethod($method);
			if($meth->isPrivate() || $meth->isProtected()) {
				$meth->setAccessible(true);
				$return = $meth->invokeArgs($context, $args);
				$meth->setAccessible(false);
			} else {
				$return = $meth->invokeArgs($context, $args);
			}
		}

		return $return;
	}
}


/*
	Static "global" version
*/

class SS {
	
	static private $pairs = array();

	static public function connect($signal_context, $signal, $slot_context, $slot){
		
		$signal_ref = new ReflectionClass($signal_context);
		$slot_ref = new ReflectionClass($slot_context);

		if(!$slot_ref->hasMethod($slot)){
			throw new Exception( $slot . ' is not defined in '.$slot_context );
		}
		
		if(!$signal_ref->hasMethod($signal)){
			throw new Exception( $signal . ' is not defined in '.$signal_context );
		}
		
		$signal_key = self::hash($signal_context, $signal);
		$slot_key = self::hash($slot_context, $slot);

		if (!isset(self::$pairs[$signal_key])){ 
			self::$pairs[$signal_key] = array(); 
		}
			
		self::$pairs[$signal_key][] = array(
			'signal_context' => $signal_context,
			'slot_context' => $slot_context,
			'signal' => $signal,
			'slot'    => $slot,
			'slot_hash' => $slot_key);

		return true;
	}

	static public function disconnect($signal_context, $signal, $slot_context, $slot){
		
		$signal_key = self::hash($signal_context, $signal);
		$slot_key = self::hash($slot_context, $slot);

		foreach(self::$pairs[$signal_key] as $i => $pair){
			if($pair['slot_hash'] === $slot_key){
				unset( self::$pairs[$signal_key][$i] );
				return;
			}
		}

	}

	public function emit($signal_context, $signal, $args = null) {

		$return = null;
		$signal_key = self::hash( $signal_context, $signal );
		$args = array_slice(func_get_args(), 2);
		
		foreach (self::$pairs[$signal_key] as $receiver){

			$slot_context = $receiver['slot_context'];
			$method  = $receiver['slot'];

			// this is to avoid using the much slower user_call_func_array
			switch (count($args)) {
				case 0: $return = $slot_context->{$method}(); break;
				case 1: $return = $slot_context->{$method}($args[0]); break;
				case 2:	$return = $slot_context->{$method}($args[0], $args[1]);	break;
				case 3:	$return = $slot_context->{$method}($args[0], $args[1], $args[2]); break;
				case 4:	$return = $slot_context->{$method}($args[0], $args[1], $args[2], $args[3]);	break;
				case 5:	$return = $slot_context->{$method}($args[0], $args[1], $args[2], $args[3], $args[4]); break;
				case 6: $return = $slot_context->{$method}($args[0], $args[1], $args[2], $args[3], $args[4], $args[5]);	break;
				default: $return = false;
			}
		}

		return $return;
	}

	static private function hash($context, $string){
			
		return spl_object_hash($context) . md5($string);
	}
}