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

class RequestHeaderTokenExtractorEventListener {
	private $name;
	public function __construct($name){
		$this->name = $name;
	}
	public function onGetToken(GetTokenRequestEvent $event){
		$request = $event->getRequest();
		$refreshTokenString = $request->headers->get($this->name);

		if($refreshTokenString){
            $event->setToken($refreshTokenString);
            $event->stopPropagation();
        }
	}
}
