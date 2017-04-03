<?php 
/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\EventListener\TokenExtractor;

use Gesdinet\JWTRefreshTokenBundle\Event\GetTokenRequestEvent;

class RequestBodyTokenExtractorEventListener {
	private $name;
    public function __construct($name){
		$this->name = $name;
	}
	public function onGetToken(GetTokenRequestEvent $event){
		$request = $event->getRequest();
		$refreshTokenString = null;
        if ($request->headers->get('content_type') == 'application/json') {
            $content = $request->getContent();
            $params = !empty($content) ? json_decode($content, true) : array();
            $refreshTokenString = isset($params[$this->name]) ? trim($params[$this->name]) : null;
        } elseif (null !== $request->get($this->name)) {
            $refreshTokenString = $request->get($this->name);
        } elseif (null !== $request->request->get($this->name)) {
            $refreshTokenString = $request->request->get($this->name);
        }

        if($refreshTokenString){
            $event->setToken($refreshTokenString);
            $event->stopPropagation();
        }
	}
}