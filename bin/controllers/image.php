<?php

class ImageController extends Controller
{
	
	private static $thumbSizes = Array( 32, 48, 64, 128, 256 );
	
	public function app($id, $size = 32) {
		$app  = db()->table('authapp')->get('_id', $id)->fetch();
		
		if (!$app) {
			throw new spitfire\exceptions\PublicException('Invalid app id');
		}
		
		$icon = $app->icon;
		
		/*
		 * Define the filename of the target, we store the thumbs for the objects
		 * inside the same directory they get stored to.
		 */
		$file = rtrim(dirname($icon), '\/') . DIRECTORY_SEPARATOR . $size . '_' . basename($icon);
		
		if(!in_array($size, self::$thumbSizes)) {
			throw new spitfire\exceptions\PublicException('Invalid size', 201604272250);
		}
		
		if (!file_exists($file)) {
			$img = new \spitfire\io\Image($icon);
			$img->fitInto($size, $size);
			$img->store($file);
		}
		
		$this->response->getHeaders()->set('Content-type', 'image/png');
		$this->response->getHeaders()->set('Cache-Control', 'no-transform,public,max-age=3600');
		$this->response->getHeaders()->set('Expires', date('r', time() + 3600));
		
		if (ob_get_length() !== 0) {
			throw new Exception('Buffer is not empty... Dumping: ' . __(ob_get_contents()), 201604272248);
		}
		
		return $this->response->setBody(file_get_contents($file));
		
	}
	
}