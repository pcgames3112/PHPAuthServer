<?php

class CronController extends Controller
{
	
	/**
	 * 
	 * @template none
	 */
	public function index() {
		$fh = fopen('bin/usr/.cron.lock', 'w+');
		if (flock($fh, LOCK_EX|LOCK_NB)) {
			#Send emails to the users
			EmailModel::deliver();
			
			flock($fh, LOCK_UN);
		}
	}
}
