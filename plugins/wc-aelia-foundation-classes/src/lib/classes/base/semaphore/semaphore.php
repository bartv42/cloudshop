<?php
namespace Aelia\WC;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Semaphore Lock Management.
 * Adapted from WP Social under the GPL. Thanks to Alex King
 *
 * @link https://github.com/crowdfavorite/wp-social
 */
class Semaphore extends Base_Class {
	// @var bool Indicates if the lock was broken.
	protected $lock_broke = false;
	// @var string Identifies the lock.
	protected $lock_name = 'lock';
	// @var string Convenience variable. Stores the AFC plugin text domain.
	protected $text_domain;

	const DEFAULT_SEMAPHORE_LOCK_WAIT = 180;
	const SEMAPHORE_ROWS = 3;

	/**
	 * Class constructor.
	 *
	 * @param string lock_name The name to assign to the lock.
	 * @param int The amount of seconds after which a "locked lock" is considered
	 * stuck and should be forcibly unlocked.
	 */
	public function __construct($lock_name, $semaphore_lock_wait = self::DEFAULT_SEMAPHORE_LOCK_WAIT) {
		parent::__construct();

		$this->semaphore_lock_wait = $semaphore_lock_wait;
		$this->text_domain = WC_AeliaFoundationClasses::$text_domain;
		if(empty($lock_name)) {
			throw new \InvalidArgumentException('Invalid lock name specified for semaphore.',
																					$this->text_domain);
		}
		$this->lock_name = $lock_name;
	}

	/**
	 * Initializes the semaphore object.
	 *
	 * @static
	 * @return Semaphore
	 */
	public static function factory($lock_name, $semaphore_lock_wait = self::DEFAULT_SEMAPHORE_LOCK_WAIT) {
		$result = new self($lock_name, $semaphore_lock_wait);
	}

	/**
	 * Initializes the lock.
	 */
	public function initialize() {
		global $wpdb;

		// First check for the semaphore options, they need to be added before the
		// semaphore can be used
		$results = $wpdb->get_results("
			SELECT option_id
			FROM
				$wpdb->options
			WHERE
				(option_name IN ('aelia_locked_" . $this->lock_name . "', 'aelia_unlocked_" . $this->lock_name . "'))
		");

		if(count($results) < self::SEMAPHORE_ROWS) {
			// Insert the rows used by the semaphore. Ignore duplicates on INSERT,
			// if they occur it means that another process started and will take care
			// of the updates
			$results = $wpdb->query("
				INSERT IGNORE INTO {$wpdb->options}
					(option_name, option_value, autoload)
				VALUES
					('aelia_unlocked_{$this->lock_name}', 1, 'no'),
					('aelia_last_lock_time_{$this->lock_name}', NOW(), 'no'),
					('aelia_semaphore_{$this->lock_name}', 0, 'no')
			");
		}
	}

	/**
	 * Attempts to start the lock. If the rename works, the lock is started.
	 *
	 * @return bool
	 */
	public function lock() {
		global $wpdb;

		// Attempt to set the lock
		$affected = $wpdb->query("
			UPDATE
				$wpdb->options
			SET
				option_name = 'aelia_locked_" . $this->lock_name . "'
			WHERE
				(option_name = 'aelia_unlocked_" . $this->lock_name . "')
		");

		if(($affected == '0') and !$this->stuck_check()) {
			$this->log(sprintf(__('Semaphore lock "%s" failed (line %s).', $this->text_domain),
												 $this->lock_name,
												 __LINE__));
			return false;
		}

		// Check to see if all processes are complete
		$affected = $wpdb->query("
			UPDATE
				$wpdb->options
			SET
				option_value = CAST(option_value AS UNSIGNED) + 1
			WHERE
				(option_name = 'aelia_semaphore_" . $this->lock_name . "') AND
				(option_value = '0')
		");

		if($affected != '1') {
			if(!$this->stuck_check()) {
				$this->log(sprintf(__('Semaphore lock "%s" failed (line %s).', $this->text_domain),
													 $this->lock_name,
													 __LINE__));
				return false;
			}

			// Reset the semaphore to 1
			$wpdb->query("
				UPDATE
					$wpdb->options
				SET option_value = '1'
				WHERE
					(option_name = 'aelia_semaphore_".$this->lock_name."')
			");
			$this->log(sprintf(__('Semaphore "%s" reset to 1.', $this->text_domain),
												 $this->lock_name));
		}

		// Set the lock time
		$current_time = current_time('mysql', 1);
		$wpdb->query($wpdb->prepare("
			UPDATE
				$wpdb->options
			SET
				option_value = %s
			WHERE
				(option_name = 'aelia_last_lock_time_" . $this->lock_name . "')
		", $current_time));

		$this->log(sprintf(__('Set semaphore last lock "%s" time to %s.', $this->text_domain),
											 $this->lock_name,
											 $current_time));
		$this->log(sprintf(__('Set semaphore lock "%s" complete.', $this->text_domain),
											 $this->lock_name));
		return true;
	}

	/**
	 * Increment the semaphore.
	 *
	 * @param	array	$filters
	 * @return aelia_Semaphore
	 */
	public function increment(array $filters = array()) {
		global $wpdb;

		if(count($filters)) {
			// Loop through all of the filters and increment the semaphore
			foreach($filters as $priority) {
				for($i = 0, $j = count($priority); $i < $j; ++$i) {
					$this->increment();
				}
			}
		}
		else {
			$wpdb->query("
				UPDATE
					$wpdb->options
				SET
					option_value = CAST(option_value AS UNSIGNED) + 1
				WHERE
					(option_name = 'aelia_semaphore_" . $this->lock_name . "')
			");
			$this->log(sprintf(__('Incremented the semaphore "%s" by 1.', $this->text_domain),
												 $this->lock_name));
		}

		return $this;
	}

	/**
	 * Decrements the semaphore.
	 *
	 * @return void
	 */
	public function decrement() {
		global $wpdb;

		$wpdb->query("
			UPDATE $wpdb->options
			SET
				option_value = CAST(option_value AS UNSIGNED) - 1
			WHERE
				(option_name = 'aelia_semaphore_" . $this->lock_name . "') AND
				(CAST(option_value AS UNSIGNED) > 0)
		");
		$this->log(sprintf(__('Decremented the semaphore "%s" by 1.', $this->text_domain),
									 $this->lock_name));
	}

	/**
	 * Unlocks the process.
	 *
	 * @return bool
	 */
	public function unlock() {
		global $wpdb;

		// Decrement for the master process.
		$this->decrement();

		$result = $wpdb->query("
			UPDATE
				$wpdb->options
			SET
				option_name = 'aelia_unlocked_" . $this->lock_name . "'
			WHERE
				(option_name = 'aelia_locked_" . $this->lock_name . "')
		");

		if($result == '1') {
			$this->log(sprintf(__('Semaphore "%s" unlocked.', $this->text_domain),
									 $this->lock_name));
			return true;
		}

		$this->log(sprintf(__('Semaphore "%s" still locked.', $this->text_domain),
								 $this->lock_name));
		return false;
	}

	/**
	 * Attempts to jiggle the stuck lock loose.
	 *
	 * @return bool
	 */
	private function stuck_check() {
		global $wpdb;

		// Check to see if we already broke the lock.
		if($this->lock_broke) {
			return true;
		}

		$current_time = current_time('mysql', 1);
		$three_minutes_before = gmdate('Y-m-d H:i:s', time()-(defined('SEMAPHORE_LOCK_WAIT') ? SEMAPHORE_LOCK_WAIT : 180));

		$affected = $wpdb->query($wpdb->prepare("
			UPDATE $wpdb->options
			SET
				option_value = %s
			WHERE
				(option_name = 'aelia_last_lock_time_".$this->lock_name."') AND
				(option_value <= %s)
		", $current_time, $three_minutes_before));

		if($affected == '1') {
			$this->log(sprintf(__('Semaphore "%s" was stuck, set lock time to %s.', $this->text_domain),
												 $this->lock_name,
												 $current_time));
			$this->lock_broke = true;
			return true;
		}

		return false;
	}
}
