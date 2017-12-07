<?php

/* 
 * The MIT License
 *
 * Copyright 2017 César de la Cal Bretschneider <cesar@magic3w.com>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

class ContextController extends BaseController
{
	
	public function index($appid) {
		
	}
	
	public function create() {
		
		$signature = explode(':', isset($_GET['signature'])? $_GET['signature'] : '');
		$context   = isset($_GET['context'])? $_GET['context'] : null;

		switch(count($signature)) {
			case 4:
				list($algo, $src, $salt, $hash) = $signature;
				$remote = null;
				break;
			case 5:
				list($algo, $src, $target, $salt, $hash) = $signature;
				$remote = db()->table('authapp')->get('appID', $target)->fetch();

				if(!$remote) { throw new PublicException('No remote found', 404); }
				break;
			default:
				throw new PublicException('Invalid signature', 400);
		}

		$app = db()->table('authapp')->get('appID', $src)->fetch();

		/*
		 * Reconstruct the original signature with the data we have about the 
		 * source application to verify whether the apps are the same, and
		 * should therefore be granted access.
		 */
		switch(strtolower($algo)) {
			case 'sha512':
				$calculated = hash('sha512', implode('.', array_filter([$app->appID, $remote? $remote->appID : null, $app->appSecret, $salt])));
				break;
			default:
				throw new PublicException('Invalid algorithm', 400);
		}

		if ($hash !== $calculated) {
			throw new PublicException('Invalid signature', 403);
		}
		
		if ($remote) {
			throw new \spitfire\exceptions\PublicException('Applications cannot create remote contexts', 400);
		}
		
		/*@var $record \connection\ContextModel*/
		$record = db()->table('connection\context')->newRecord();
		$record->ctx     = $context;
		$record->app     = $app;
		$record->title   = _def($_POST['name'], 'Unnamed context');
		$record->descr   = _def($_POST['description'], 'Missing description');
		$record->expires = _def($_POST['expires'], 86400 * 90) + time();
		$record->store();
		
		$this->view->set('result', $record);
	}
	
	public function edit($appid, $ctx) {
		
	}
	
	public function delete($appid, $ctx) {
		
	}
	
}