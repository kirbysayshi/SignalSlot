# SignalSlot

Signals and Slots system for PHP. Strongly inspired by http://www.osebboy.com/blog/signals-and-slots-for-php/. 

## What?

The [Signals and Slots](doc.qt.nokia.com/4.7/signalsandslots.html) mechanism is a programming pattern popularized by Qt. They allow strongly-typed languages to have type-safe callbacks whose return type and argument list can be verified at compile time. They are very similar to a Pub/Sub or EventEmitter system, but the main difference is that Signals and Slots do not use strings to name/trigger events or fire callbacks.

For example, a typical event system might work like this in PHP:

	class Foursquare_Client extends EventEmitter {
		
		public function query_user_data(){
			// do stuff to get data, http, etc

			$this->trigger('data', $data1, $data2, $etc);
		}

	}

	class User {

		public function init_from_array($data){
			// do processing...
		}

	}

	class MyApp {
		
		private $fs_client = new Foursquare_Client();
		private $user = new User();

		public function __construct(){
			
			// api option 1:
			$this->fs_client->on('data', array($this, 'on_fs_data'));

			$self = $this;

			// api option 2: 
			$this->fs_client->on('data', function($data) use ($self){
				$self->on_fs_data($data);
			});

			// get data
			$this->fs_client->query_user_data();
		}

		public function on_fs_data($data){

			$this->user->init_from_array($data);
			$this->log('data received from fs: '.$data);
		}

		public function log($msg){
			// do something with your logger...
		}
	}

Option 1 and 2 both have drawbacks. Option 1 is assumedly using `call_user_func` behind the scenes, and thus a PHP pseudo-type 'callback' is used. Option 2 uses an anonymous function from PHP 5.3, but we have to do keep a manual reference to `$this`, since before PHP 5.4, `$this` cannot be used inside an anonymous function. In both options, a string is used as the event name. This has the possiblity of introducing spelling errors that could be hard to track down, especially if an event or method is renamed later during development.

Obviously, this works, and is relatively simple. But, here's how you would do it with SignalSlot.php:

	class Foursquare_Client extends KirbySaysHi\SignalSlot {
		
		const SIGNAL_USER_DATA = 'user_data';

		public function query_user_data(){
			// do stuff to get data, http, etc

			$this->user_data($data1, $data2, $etc);
		}
	}

	class User extends KirbySaysHi\SignalSlot {
		
		const SLOT_INIT_FROM_ARRAY = 'init_from_array';

		public function init_from_array($data){
			// do processing...
		}

	}

	class MyApp extends KirbySaysHi\SignalSlot {
		
		const SLOT_LOG = 'log';
		const SLOT_ON_USER_DATA = 'on_user_data';

		private $fs_client = new Foursquare_Client();
		private $user = new User();

		public function __construct(){
			parent::__construct(); // sets up signals and slots

			// connect client signal to callback slot
			$this->fs_client->connect(Foursquare_Client::SIGNAL_USER_DATA,
				$this->user, User::SLOT_INIT_FROM_ARRAY);
			$this->fs_client->connect(Foursquare_Client::SIGNAL_USER_DATA, 
				$this, self::SLOT_ON_USER_DATA);
			$this->fs_client->connect(Foursquare_Client::SIGNAL_USER_DATA,
				$this, self::SLOT_LOG);

			// get data
			$this->fs_client->query_user_data();

			$this->log('Slots are just normal methods');
		}

		public function on_user_data($data){

			// disconnect after first reception
			$this->fs_client->disconnect(Foursquare_Client::SIGNAL_USER_DATA, $this, self::SLOT_ON_USER_DATA);
		}

		public function log($msg){
			// do something with your logger...
		}
	}

It's basically the same length. However, there are a few benefits here. The most important is that our events are now dependent on constants, which throw a fatal error if not defined. In addition, at "connect" time (whenever connect is called), the existence of both the slot and signal are checked, which further helps prevent silly errors (missing either throws an exception). We can also very easily hook up multiple slots to a single signal, and easily manage disconnecting slots from signals. Furthermore, a slot is just a method, so it can be called like any other for even more versatility. The slots and signals are also all defined up front, so developers need only look at the class definition to know what they can connect to. Technically only classes that have signals need to extend SignalSlot.

Signals can only be fired from within their defined class or a child class, so this further helps to promote decoupling.

## What's the point?

Honestly? For the fun of it. Signals and Slots work best in an event loop and with many similar but unique components, and PHP is definitely not that! So this was mostly brought up by me wanting to see if it was possible.

## Info

* Supports PHP 5.3 and up
* More a thought experiment than anything
* Has no external dependencies
* Also contains a static version that does not require objects to inherit from SignalSlot, but does require signals to be `emit`ed manually.

## TODO

* Probably should use [PHPUnit](https://github.com/sebastianbergmann/phpunit/). Instead I'm using a quick thing I wrote called Aristotle. 
* Support for creating a connection and specifying that any further attempts to create a connection should throw an error.
* Support for creating a "once" connection, or one that autodisconnects itself after one fire.
* Better exception standards