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

class ResponseBodyTokenSetterEventListener {
	private $name;
	public function __construct($name){
		$this->name = $name;
	}
	public function onAddToken(AddTokenResponseEvent $event){
		$token = $event->getToken();
		$data = $event->getData();
		$data[$this->name] = $token;
		$event->setData($data);
	}
}