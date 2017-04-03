<?php 

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle;

class Events {
	const ADD_TOKEN_RESPONSE = 'gesdinet.jwtrefreshtoken.event.add_token_response';
	const GET_TOKEN_REQUEST = 'gesdinet.jwtrefreshtoken.event.get_token_request';
}