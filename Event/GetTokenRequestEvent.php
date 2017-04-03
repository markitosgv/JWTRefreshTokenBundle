<?php 
/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\Event;

class GetTokenRequestEvent extends Event {
	private $token = null;
	public function __construct(Request $request){
		$this->request = $request;
	}
	public function getRequest(){
		return $this->request;
	}
	public function setToken($token){
		$this->token = $token;
	}
	public function getToken(){
		return $this->token;
	}
}