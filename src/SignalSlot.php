<?php
namespace KirbySaysHi;

// inspired by and modified from: http://www.osebboy.com/blog/signals-and-slots-for-php/

interface ISignalSlot
{
	public function __construct();
	public function connect($signal, $context, $slot, $config = array());
	public function disconnect($signal, $context, $slot);
	//public function emit($signal, $args = null);
}

class MemberAccessException extends \Exception {}
class UndeclaredSignalException extends \Exception {}
class UndeclaredSlotException extends \Exception {}

class SignalSlot implements ISignalSlot {
	
	private $signals = array();
	private $slots = array();
	private $debug = false;
	private $reflector = null;

	public function __call($name, $args){

		// see if this method call is a valid SIGNAL
		if( isset($this->signals[$name]) ){

			// get immediate caller
			list(,, $caller) = debug_backtrace(false);
			
			// a signal cannot be called from outside
			if( isset($caller['class']) && is_subclass_of($caller['class'], __CLASS__) ){

				$this->debug && print 'emitting SIGNAL_'.$name." with args: ". implode(',', $args) ." \n";
				$this->debug && var_dump($caller);

				// notify listening slots
				// do not return, signals have no return type
				array_unshift( $args, $name );
				$emit = $this->reflector->getMethod('emit');
				$emit->setAccessible(true);
				$emit->invokeArgs($this, $args);
				$emit->setAccessible(false);

			} else {
				throw new MemberAccessException(
					'Signal "' . $name . '" is inaccessible from caller: ' 
					. (isset($caller['class']) ? $caller['class'] : '')
					. (isset($caller['function']) ? $caller['function'] : '' )
				);
			}
		} else {
			throw new MemberAccessException('Method "'.$name.'" is either inaccessible or does not exist');
		}
	}

	public function __construct() {
		$this->setup();
	}

	public function connect($signal, $context, $slot, $config = array()){

		if (!isset($this->signals[$signal])) {
			throw new UndeclaredSignalException (
				"Signal \"$signal\" const is not declared in " . get_class($this)
			);
		}
		
		$ctx_ref = new \ReflectionClass($context);

		if(!$ctx_ref->hasMethod($slot)){
			throw new UndeclaredSlotException( 
				'Slot '
				. '"'.$slot.'"'
				. ' is not defined in '
				. (is_string($context) ? $context : get_class($context))
			);
		}

		$this->signals[$signal][] = array(
			'context' => $context,
			'slot'    => $slot,
			'config'  => $config,
			'reflector' => $ctx_ref);
		
		$this->debug && var_dump("count for signal $signal: ".count( $this->signals[$signal] ) );
	}

	// disconnects all matching signal-slot connections
	public function disconnect($signal, $context, $slot){

		if (!isset($this->signals[$signal]) || empty($this->signals[$signal])) {
			return; // throw exception if signal is not set??
		}

		$def = array('context' => $context, 'slot' => $slot);
		
		foreach ($this->signals[$signal] as $id => $receiver){
			unset($receiver['config']);
			unset($receiver['reflector']);
			
			if ($receiver === $def){
				unset($this->signals[$signal][$id]);
			}
		}
		return false;
	}

	private function emit($signal, $args = null){

		$args = array_slice(func_get_args(), 1);
		
		foreach ($this->signals[$signal] as $receiver){

			$this->debug && var_dump('receiver', $receiver);

			$context = $receiver['context'];
			$method  = $receiver['slot'];
			$config  = $receiver['config'];
			$ctx_ref = $receiver['reflector'];
			
			if (is_string($context)){
				$context = !empty($config) ? new $context($config) : new $context();
			}

			// slots called normally obey access level rules
			// when called as a slot, however, even private methods can be called
			$meth = $ctx_ref->getMethod($method);
			if($meth->isPrivate() || $meth->isProtected() || $meth->isVirtual()) {
				$meth->setAccessible(true);	
			} 
			
			$meth->invokeArgs($context, $args);
		}
	}

	private function setup(){
		$this->reflector = new \ReflectionClass($this);
		$consts = $this->reflector->getConstants();

		foreach ($consts as $key => $val)
		{
			if (substr($key, 0, 7) === 'SIGNAL_') {
				if($this->reflector->hasMethod($val)){
					throw new Exception('Spurious method declaration "'.$val.'" in '.get_class($this));
				}
				$this->signals[$val] = array();

			} else if(substr($key, 0, 5) === 'SLOT_' && $this->reflector->hasMethod($val)){

				$this->slots[$val] = array();
			}
		}
	}
}


/*
	Static "global" version
*/

class SS {
	
	static private $pairs = array();

	static public function connect($signal_context, $signal, $slot_context, $slot){
		
		$signal_ref = new \ReflectionClass($signal_context);
		$slot_ref = new \ReflectionClass($slot_context);

		if(!$slot_ref->hasMethod($slot)){
			throw new UndeclaredSlotException( 
				'Slot '
				. '"'.$slot.'"'
				. ' is not defined in '
				. get_class($slot_context)
			);
		}
		
		if(!$signal_ref->hasMethod($signal)){
			throw new UndeclaredSignalException (
				"Signal \"$signal\" is not declared in " . get_class($signal_context)
			);
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