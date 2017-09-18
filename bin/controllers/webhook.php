<?php

use spitfire\exceptions\HTTPMethodException;
use spitfire\exceptions\PublicException;
use spitfire\validation\ValidationException;
use webhook\HookModel;

class WebhookController extends BaseController
{
	
	public function attach($appid) {
		#Check the user's privileges
		if (!$this->isAdmin) { throw new PublicException('Requires authorization', 403); }
		
		#Check whether the app exists
		$app = db()->table('authapp')->get('_id', $appid)->fetch();
		if (!$app) { throw new PublicException('No such app found', 404); }
		
		try {
			
			#If the request was posted, then the user can store the hook
			if (!$this->request->isPost()) { throw new HTTPMethodException('Needs to be posted', 1709131431); }
			
			#Read the variables.
			$type   = (int)$_POST['type'];
			$action = (int)$_POST['action'];
			
			#Validate the hook
			$validators = [];
			$validators['url']    = validate($_POST['url'])->asURL('URL needs to be valid');
			
			$validators['type']   = validate($type)->addRule(new ClosureValidationRule(function ($v) {
				return array_reduce(
					[HookModel::APP & $v, HookModel::USER & $v, HookModel::TOKEN & $v, HookModel::GROUP & $v], 
					function ($e, $p) { return $e? $p + 1 : $p; }, 0 
				) === 1? false : 'Type error';
			}));
			
			$validators['action']  = validate($action)->addRule(new ClosureValidationRule(function ($v) {
				return array_reduce(
					[HookModel::CREATED & $v, HookModel::UPDATED & $v, HookModel::DELETED & $v, HookModel::MEMBER & $v], 
					function ($e, $p) { return $e? $p + 1 : $p; }, 0 
				) === 1? false : 'Type error';
			}));
			
			$validators['listen'] = validate($type & $action)->addRule(new ClosureValidationRule(function ($v) {
				return (!($v & HookModel::MEMBER) || $v & HookModel::APP)? false : 'Type error';
			}));
			
			validate($validators);
			
			#Store the hook
			$hook = db()->table('webhook\hook')->newRecord();
			$hook->app = $app;
			$hook->name   = _def($_POST['name'], '');
			$hook->listen = $validators['listen']->getValue();
			$hook->url    = $validators['url']->getValue();
			$hook->store();
			
			#Redirect to the new hook
			$this->response->getHeaders()->redirect(url('webhook', 'edit', $hook->_id));
			return;
		} 
		catch (HTTPMethodException$e) {}
		catch (ValidationException$e) {
			$this->view->set('messages', $e->getResult());
		}
	}
	
	public function edit($hookid) {
		#Check the user's privileges
		if (!$this->isAdmin) { throw new PublicException('Requires authorization', 403); }
		
		#Check whether the app exists
		$hook = db()->table('webhook\hook')->get('_id', $hookid)->fetch();
		if (!$hook) { throw new PublicException('No such webhook found', 404); }
		
		try {
			
			#If the request was posted, then the user can store the hook
			if (!$this->request->isPost()) { throw new HTTPMethodException('Needs to be posted', 1709131431); }
			
			#Read the variables.
			$type   = (int)$_POST['type'];
			$action = (int)$_POST['action'];
			
			#Validate the hook
			$validators = [];
			$validators['url']    = validate($_POST['url'])->asURL('URL needs to be valid');
			
			$validators['type']   = validate($type)->addRule(new ClosureValidationRule(function ($v) {
				return array_reduce(
					[HookModel::APP & $v, HookModel::USER & $v, HookModel::TOKEN & $v, HookModel::GROUP & $v], 
					function ($e, $p) { return $e? $p + 1 : $p; }, 0 
				) === 1? false : 'Type error';
			}));
			
			$validators['action']  = validate($action)->addRule(new ClosureValidationRule(function ($v) {
				return array_reduce(
					[HookModel::CREATED & $v, HookModel::UPDATED & $v, HookModel::DELETED & $v, HookModel::MEMBER & $v], 
					function ($e, $p) { return $e? $p + 1 : $p; }, 0 
				) === 1? false : 'Type error';
			}));
			
			$validators['listen'] = validate($type & $action)->addRule(new ClosureValidationRule(function ($v) {
				return (!($v & HookModel::MEMBER) || $v & HookModel::APP)? false : 'Type error';
			}));
			
			validate($validators);
			
			#Store the hook
			$hook->name   = _def($_POST['name'], '');
			$hook->listen = $validators['listen']->getValue();
			$hook->url    = $validators['url']->getValue();
			$hook->store();
			
			#Redirect to the new hook
			$this->response->getHeaders()->redirect(url('webhook', 'edit', $hook->_id));
			return;
		} 
		catch (HTTPMethodException$e) {}
		catch (ValidationException$e) {
			$this->view->set('messages', $e->getResult());
		}
		
		$this->view->set('webhook', $hook);
	}
	
	public function delete($webhookid, $hash = null) {
		
		$hook = db()->table('webhook\hook')->get('_id', $webhookid)->fetch();
		$app  = $hook->app;
		
		if (!$hook) { throw new PublicException('No hook found', 404); }
		
		$expected = sha1($hook->_id . $hook->url . date('Y-m-d'));
		
		if ($hash === $expected) {
			$hook->delete();
			
			$this->response->getHeaders()->redirect(url('app', 'detail', $app->_id));
			return;
		}
		
		$this->view->set('confirmURL', url('webhook', 'delete', $hook->_id, $expected));
		$this->view->set('cancelURL', url('app', 'detail', $app->_id));
	}
	
}

