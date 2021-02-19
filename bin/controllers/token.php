<?php

use access\RefreshModel;
use access\TokenModel;
use spitfire\core\Environment;
use spitfire\exceptions\PublicException;
use spitfire\storage\database\pagination\Paginator;

class TokenController extends BaseController
{
	
	public function index() {
		
		$query = db()->table('token')->getAll();
		if (!$this->isAdmin) { $query->addRestriction('user', $this->user); }
		
		$query->group()
				->addRestriction('expires', null, 'IS')
				->addRestriction('expires', time(), '>');
		
		$pages = new Paginator($query);
		
		$this->view->set('pagination', $pages);
		$this->view->set('records',    $pages->records());
	}
	
	/**
	 * @todo Allow trading refresh tokens for fresh tokens
	 * 
	 * @throws PublicException
	 */
	public function create() 
	{
		$type    = $_POST['grant_type']?? 'code';
		$appid   = isset($_POST['client'])? $_POST['client'] : $_GET['client'];
		$secret  = $_POST['secret']?? null;
		$expires = Environment::get('phpas.token.expiration')?: 14400;
		
		/*
		 * Check if an app with the provided ID does indeed exist.
		 */
		$app = db()->table('authapp')->get('appID', $appid)->first();
		if (!$app) { throw new PublicException('No application found', 403); }
		
		/*
		 * In order to search for the application, we need to make sure that we're
		 * querying the secrets to find whether the application has an appropriate
		 * secret available.
		 * 
		 * While I originally had a much leaner version that would just run a search
		 * for this:
		 * 
		 * $app = db()->table('authapp')->get('appID', $appid)
		 *   ->addRestriction('credentials', db()->table('client\credential')->get('secret', $secret)->group()->where('expires', null)->where('expires', '<', time()))->fetch();
		 * 
		 * Which would run in a single query, the security of it was severely compromised
		 * by the fact that database searches are rather lenient. While this only meant a 
		 * cost in enthropy, it still makes more sense to separate the queries and 
		 * test the result in PHP.
		 */
		$credentials = db()->table('client\credential')
			->get('secret', $secret)
			->where('client', $app)
			->group()->where('expires', null)->where('expires', '>', time())->endGroup()
			->all();
		
		/*
		 * If the application was issued credentials, it MUST provide a valid credential.
		 * In case the application is not issued credentials, because it runs on a
		 * user controlled device only, we can accept an "unauthenticated" request.
		 */
		if ($credentials && !$credentials->extract('secret')->contains($secret)) {
			throw new PublicException('Invalid credentials', 403);
		}
		
		if ($type === 'code')
		{
			/*
			 * Read the code the client sent
			 */
			$code = db()->table('access\code')->get('code', $_POST['code']?? null)->where('expires', '>', time())->first(true);

			/*
			 * Verify that the code the client sent, is actually the client's code
			 */
			if ($code->client->_id !== $app->_id) {
				throw new PublicException('Code is for another client', 403);
			}

			/*
			 * Check the code verifier
			 */
			list($algo, $hash) = explode(':', $code->challenge);

			if (hash($algo, $_POST['verifier']) !== $hash) {
				throw new PublicException('Hash failed', 403);
			}
			
			/*
			 * 
			 */
			$code->expires = time();
			$code->store();

			$token = TokenModel::create($code->session, $app, null, $code->user, $expires);
			$refresh = RefreshModel::create($app, null, $code->user, time() + 86400 * 365 * 5);
		}
		elseif ($type === 'refresh_token') {
			/**
			 * The provided refresh token. The application MUST use this to validate
			 * the client's claims.
			 * 
			 * @var RefreshModel
			 */
			$provided = $_POST['refresh_token']?? null;
			
			if ($provided->client->_id !== $app->appID) {
				throw new PublicException('Tried refreshing a token owned by a different client', 403);
			}
			
			$token = TokenModel::create($provided->session, $app, null, $provided->owner, $expires);
			$refresh = RefreshModel::create($app, null, $provided->owner, time() + 86400 * 365 * 5);
		}
		else {
			throw new PublicException('Invalid grant_type selected', 400);
		}
		
		//Send the token to the view so it can render it
		$this->view->set('token', $token);
		$this->view->set('refresh', $refresh);
	}
	
	/**
	 * 
	 * @template none
	 * @param string $tokenid
	 */
	public function end($tokenid) {
		$token = db()->table('token')->get('token', $tokenid)->fetch();
		
		if (!$token) { throw new PublicException('No token found', 404); }
		if ($token->expires && $token->expires < time()) { throw new PublicException('Token already expired', 403); }
		
		$token->expires = time();
		$token->store();
		
		$this->response->getHeaders()->redirect(new URL('token', Array('message' => 'ended')));
	} 
	
}
