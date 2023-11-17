<?php

namespace UniLog;

include_once "colors.php";

#
# Custom logging destinations support via traits
#

trait log2Database
{
	private $db;

	public function logDatabase($message, $error_level) {
		if(empty($db_config = $this->destinations["database"])) {
			$this->error = "Couldn`t write to database";
			return false;
		}

		$this->db = new \Unidb\Database($db_config);
		if(empty($this->db->status)) {
			$this->db->connect();
		}

		if(empty($this->db->status)) {
			$this->error = "Couldn`t connect to database: {$db_config["name"]}@{$db_config["host"]}";
			return false;
		}

		if($this->isDuplicate('database', $tag, $message, $error_level)) {
			return true;
		}

		if($this->db->query("INSERT INTO {$this->destinations["database"]["table"]} VALUES (?)", ['s', $message]) === false) {
			$this->error = "Couldn`t write to database {$db_config["name"]}@{$db_config["host"]}: {$this->db->error}";
			var_dump($this->error);
			return false;
		}

		return true;
	}
}

trait log2File
{
	public function logFile($message, $error_level) {
		if(!file_exists(($path = $this->destinations["file"]["path"] ?? ""))) {
		    mkdir($path, 0777, true);
		}

		$file_name = $path.strtolower(basename(__FILE__, '.php')).".log";
		$reduction = $this->events[$error_level]["reduction"] ?? $error_level;
		$release = empty($this->release) ? "" : $this->release." ";

		if($this->isDuplicate('file', $tag, $message, $error_level)) {
			return true;
		}

		if(!$this->appendFile($file_name, "{$release}[$reduction] $message")) {
			$this->error = "Couldn`t write to $file_name";
			return false;
		}

		return true;
	}

	private function appendFile($file_name, $message) {
		if(($file = fopen($file_name, 'a')) === false) {
			return false;
		}

		if(!is_writable($file_name)) {
			fclose($file);
			return false;
		}

		$message = str_replace(["\r","\n"], ["\\r","\\n"], $message);
		$fwrite = fwrite($file, date('Y/m/d H:i:s')." $message\n");
		fclose($file);

		if($fwrite === false) return false;

		return true;
	}
}

trait log2Telegram
{
	public function logTelegram($message, $error_level) {
		if($this->isDuplicate('telegram', $tag, $message, $error_level)) {
			return true;
		}
		// Localization not applied
		# $error_level_localized = static::$_langMessages[$error_level] ?? $error_level;
		$emoji = $this->events[$error_level]["emoji"] ?? "";
		$emoji = $this->emojies[$emoji] ?? "";
		$chat_id = $this->destinations["telegram"]["chat_id"] ?? "";
		$header = empty($this->destinations["telegram"]["header"]) ? $this->release : $this->destinations["telegram"]["header"];

		if(empty($this->telegram)) {
			$this->telegram = new \TelegramBot\Api\BotApi($this->destinations["telegram"]["api_token"]);
		}

		if(empty($this->telegram)) {
			$this->error = "Error starting telegram messaging";
			return false;
		}

		$search = array('_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!');
		$replace = array('\_', '\*', '\[', '\]', '\(', '\)', '\~', '\`', '\>', '\#', '\+', '\-', '\=', '\|', '\{', '\}', '\.', '\!');
		$message = str_replace($search, $replace, $message);

		if($this->telegram->sendMessage($chat_id, "`$header`\n$emoji $message", "MarkdownV2") === false) {
			$this->error ="Error sending telegram message";
			return false;
		}

		return true;
	}
}

trait log2Screen
{
	public function logScreen($message, $error_level, $tag) {
		if($this->isDuplicate('screen', $tag, $message, $error_level) === true) {
			return true;
		}

		$this->out($message, $this->events[$error_level]["color"] ?? null);

		return true;
	}

	private function out($message, $color = COLOR_REGULAR) {
		$color = empty($color) ? COLOR_REGULAR : $color;
		$end_of_line = "\n";	//'<br>'
		$release = empty($this->release) ? "" : $this->release." ";

		if(posix_ttyname(STDOUT)) {
			print COLOR_DARK.date('H:i:s')." $release".constant($color).$message.COLOR_REGULAR.$end_of_line;
		} else {
			print $message.$end_of_line;
		}
	}
}

class Logger
{
	use log2File, log2Telegram, log2Screen, log2Database;

    protected static $_lang;
    protected static $_langDir;
    protected static $_langMessages = [];
	private $destinations;
	private $telegram;
	private $emojies;
	private $events;
    private $path;
	private $last_messages = [];	// Stores all kinds of last messages to know about their last time
	public $error, $release = "";

    /**
	* @param  array $log_config[] = array of(
	* $log_config["destinations"] = []
	* $log_config["emojies"] = []
	* $log_config["events"] = []			)
	* all arrays above should be loaded from ini files (in "config templates" folder) by parse_ini_file()
	* @param  string $lang
	* @throws \InvalidArgumentException
	*/
	function __construct($log_config, $lang = 'en')
	{
		$this->path = $log_config["path"] ?? 'logs';

		if(empty($log_config)) {
            throw new InvalidArgumentException('No config was given');
		}

		$this->destinations = $log_config['destinations'];
		$this->emojies = $log_config['emojies'];
		$this->events = $log_config['events'];
		static::$_lang = empty($lang) ? en : $lang;
		static::$_langDir = dirname(dirname(__DIR__)).DIRECTORY_SEPARATOR."lang";

        // Load language file in directory
        if(stream_resolve_include_path($langFile = rtrim(static::$_langDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.static::$_lang.".php")) {
            $langMessages = include $langFile;
            static::$_langMessages = array_merge(static::$_langMessages, $langMessages);
        } else {
            throw new \InvalidArgumentException("Fail to load language file `$langFile`");
        }

		register_shutdown_function([$this, 'flushMessages']);
	}

	public function flushMessages()
	{
		foreach($this->last_messages as $destination => $data) {
			foreach($data as $tag => $message_stat) {
				if(!isset($this->destinations[$destination])) {
		            $this->error = "No destination `$destination` set for `{$message_stat['error_level']}` event";
					continue;
				}

				$destination_method = "log".ucfirst($destination);

				if(method_exists($this, $destination_method)) {
					$date_time = date("H:i:s", $message_stat['since']);
					$this->$destination_method("Since $date_time were {$message_stat['times']} `{$message_stat['message']}` tagged $tag", $message_stat['error_level'], null);
					unset($data[$tag]);
				}
				else $this->error = "No logging method $destination_method";
			}
		}

		if(!empty($this->error)) return false;
		return true;
	}

	//
	// Duplicates are ignored in order not to make many repeated logs
	// If last message with set tag is in $duplicates_period, new one is ignored
	// $duplicates_period stores default duplicates_period for each logging method
	//
	private function isDuplicate($destination_method, $tag, $message, $error_level)
	{
		if(empty($tag) || empty($duplicates_period = $this->destinations[$destination_method]["duplicates_period"] ?? null)) {
			return false;
		}

		if(!isset($this->last_messages[$destination_method][$tag])) {
			$this->last_messages[$destination_method][$tag] = [];
			$this->last_messages[$destination_method][$tag]['times'] = 1;
			$this->last_messages[$destination_method][$tag]['since'] = time();
			$this->last_messages[$destination_method][$tag]['message'] = $message;
			$this->last_messages[$destination_method][$tag]['error_level'] = $error_level;
		}
		else $this->last_messages[$destination_method][$tag]['times']++;

		if(time() - $this->last_messages[$destination_method][$tag]['since'] < $duplicates_period) {
			return true;
		}

		return $this->last_messages[$destination_method][$tag];
	}

	// Main class function
	public function logs($message, $error_level = "normal", $tag = null)
	{
		$this->error = "";
		// There is no support while for custom emoji for specific message
#		if($custom_emoji === NULL) $custom_emoji = $this->emojies[$error_level];
#		$bt = debug_backtrace();
#		$line = array_shift($bt)['line'] ?? null;
		if(empty($event = $this->events[$error_level])) {
            $this->error = "No event logging set for $error_level";
			return false;
		}

		foreach($event['log'] as $log)
		{
			if(!isset($this->destinations[$log])) {
	            $this->error = "No destination `$log` set for `$error_level` event";
				continue;
			}

			$destination_method = "log".ucfirst($log);

			if(method_exists($this, $destination_method)) {
				$this->$destination_method($message, $error_level, $tag);
			}
			else $this->error = "No logging method $destination_method";
		}

		if(!empty($this->error)) return false;

		return true;
	}
}
