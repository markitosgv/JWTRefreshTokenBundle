<?php 

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\EventListener\TokenSetter;

use Gesdinet\JWTRefreshTokenBundle\Event\AddTokenResponseEvent;
use Symfony\Component\HttpFoundation\Cookie;

class ResponseCookieTokenSetterEventListener {
	private $name;
	private $ttl;
	public function __construct($name, $ttl){
		$this->name = $name;
		$this->ttl = $ttl;
	}
	public function onAddToken(AddTokenResponseEvent $event){
		$response = $event->getResponse();
		$token = $event->getToken();
		$response->headers->setCookie(new Cookie($this->name, $token, time() + $this->ttl, '/', null, false, true));
	}
}