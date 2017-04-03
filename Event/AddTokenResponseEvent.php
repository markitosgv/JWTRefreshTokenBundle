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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\Event;

class AddTokenResponseEvent extends Event {
	private $data = [];
	public function __construct($token, Response $response){
		$this->token = $token;
		$this->response = $response;
	}
	public function getToken(){
		return $this->token;
	}
	public function getResponse(){
		return $this->response;
	}
	public function setData(array $data){
		$this->data = $data;
	}
	public function getData(){
		return $this->data;
	}
}