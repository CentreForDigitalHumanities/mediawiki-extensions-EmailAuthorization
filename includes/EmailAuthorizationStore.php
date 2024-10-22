<?php
/*
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

namespace MediaWiki\Extension\EmailAuthorization;

use Wikimedia\Rdbms\IResultWrapper;

class EmailAuthorizationStore {
	/**
	 * @param string $email
	 * @return bool
	 */
	public function insertEmail( string $email ): bool {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->upsert(
			'emailauth',
			[
				'email' => $email
			],
			[
				'email'
			],
			[
				'email' => $email
			],
			__METHOD__
		);
		return $dbw->affectedRows() === 1;
	}

	/**
	 * @param string $email
	 * @return bool
	 */
	public function deleteEmail( string $email ): bool {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->delete(
			'emailauth',
			[
				'email' => $email
			],
			__METHOD__
		);
		return $dbw->affectedRows() === 1;
	}

	/**
	 * @param string|null $email
	 * @return bool
	 */
	public function isEmailAuthorized( ?string $email ): bool {
        $dbr = wfGetDB( DB_REPLICA );
        if ( $email === null ) {
            return false;
        }
        $email = mb_strtolower( htmlspecialchars( trim( $email ), ENT_QUOTES ) );
		$row = $dbr->selectRow(
			'emailauth',
			[
				'email'
			],
			[
				'email' => $email
			],
			__METHOD__
		);
		return $row !== false;
	}

	/**
	 * @return int
	 */
	public function getAuthorizedEmailsCount(): int {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->estimateRowCount( 'emailauth' );
	}

	/**
	 * @param string $offset
	 * @param string $limit
	 * @param string $contains
	 * @param array $columns
	 * @param array $order
	 * @return IResultWrapper
	 */
	public function getAuthorizedEmails(
		string $offset,
		string $limit,
		string $contains,
		array $columns,
		array $order
	): IResultWrapper {
		$dbr = wfGetDB( DB_REPLICA );
		$orderOptions = array_map( static function ( $orderOption ) use ( $columns ) {
			$validOption = preg_match( "/(\d+)(asc|desc)/i", $orderOption, $matches );
			if ( $validOption === 1 ) {
				switch ( $columns[intval( $matches[1] )] ) {
					case 'email':
						return "emailauth.email $matches[2]";
					case 'userNames':
						return "user.user_name $matches[2]";
					case 'realNames':
						return "user.user_real_name $matches[2]";
					default:
						return '';
				}
			} else {
				return '';
			}
		}, $order );
		$orderOptions = array_filter( $orderOptions );
		$orderOptions = implode( ', ', $orderOptions );
		if ( $orderOptions === '' ) {
			$orderOptions = 'emailauth.email asc';
		}
		if ( strlen( $contains ) > 0 ) {
			$likeClause = $dbr->buildLike( $dbr->anyString(), $contains, $dbr->anyString() );
			$conds = $dbr->makeList( [
				"emailauth.email $likeClause",
				"user.user_name $likeClause",
				"user.user_real_name $likeClause"
			], $dbr::LIST_OR );
		} else {
			$conds = "";
		}
		$tables = [
			'emailauth',
			'user'
		];
		$joinConds = [
			'user' => [
				'LEFT JOIN',
				'emailauth.email = user.user_email'
			]
		];
		return $dbr->select(
			$tables,
			[
				'emailauth.email'
			],
			$conds,
			__METHOD__,
			[
				'ORDER BY' => $orderOptions,
				'LIMIT' => $limit,
				'OFFSET' => $offset,
				'DISTINCT'
			],
			$joinConds
		);
	}

	/**
	 * @param string $email
	 * @return IResultWrapper
	 */
	public function getUserInfo( string $email ): IResultWrapper {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->select(
			'user',
			[
				'user_name',
				'user_real_name'
			],
			[
				'user_email' => $email
			],
			__METHOD__,
			[
				'ORDER BY' => 'user_name',
			]
		);
	}

	/**
	 * @return int
	 */
	public function getUsersCount(): int {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->estimateRowCount( 'user' );
	}

	/**
	 * @param string $offset
	 * @param string $limit
	 * @param string $contains
	 * @param array $columns
	 * @param array $order
	 * @return IResultWrapper
	 */
	public function getUsers(
		string $offset,
		string $limit,
		string $contains,
		array $columns,
		array $order
	): IResultWrapper {
		$dbr = wfGetDB( DB_REPLICA );
		$orderOptions = array_map( static function ( $orderOption ) use ( $columns ) {
			$validOption = preg_match( "/(\d+)(asc|desc)/i", $orderOption, $matches );
			if ( $validOption === 1 ) {
				switch ( $columns[intval( $matches[1] )] ) {
					case 'email':
						return "user_email $matches[2]";
					case 'userName':
						return "user_name $matches[2]";
					case 'realName':
						return "user_real_name $matches[2]";
					default:
						return '';
				}
			} else {
				return '';
			}
		}, $order );
		$orderOptions = array_filter( $orderOptions );
		$orderOptions = implode( ', ', $orderOptions );
		if ( $orderOptions === '' ) {
			$orderOptions = 'user_email asc';
		}
		if ( strlen( $contains ) > 0 ) {
			$likeClause = $dbr->buildLike( $dbr->anyString(), $contains, $dbr->anyString() );
			$conds = $dbr->makeList( [
				"user_name $likeClause",
				"user_real_name $likeClause",
				"user_email $likeClause"
			], $dbr::LIST_OR );
		} else {
			$conds = "";
		}
		return $dbr->select(
			'user',
			[
				'user_id'
			],
			$conds,
			__METHOD__,
			[
				'ORDER BY' => $orderOptions,
				'LIMIT' => $limit,
				'OFFSET' => $offset
			]
		);
	}

	/**
	 * @param string $limit
	 * @param string $offset
	 * @return IResultWrapper
	 */
	public function getRequests( string $limit, string $offset ): IResultWrapper {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->select(
			'emailrequest',
			[
				'email',
				'request',
			],
			[],
			__METHOD__,
			[
				'ORDER BY' => 'email',
				'LIMIT' => $limit,
				'OFFSET' => $offset
			]
		);
	}

	/**
	 * @param string $email
	 * @return mixed|string
	 */
	public function getRequestFields( string $email ) {
		$dbr = wfGetDB( DB_REPLICA );
		$request = $dbr->selectRow(
			'emailrequest',
			[
				'request',
			],
			[
				'email' => $email
			],
			__METHOD__
		);
		if ( $request === false ) {
			return '';
		}
		return json_decode( $request->request );
	}

	/**
	 * @param string $email
	 * @param string $json
	 * @return bool
	 */
	public function insertRequest( string $email, string $json ): bool {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->upsert(
			'emailrequest',
			[
				'email' => $email,
				'request' => $json,
			],
			[ 'email' ],
			[
				'request' => $json,
			],
			__METHOD__
		);
		return $dbw->affectedRows() === 1;
	}

	/**
	 * @param string $email
	 */
	public function deleteRequest( string $email ) {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->delete(
			'emailrequest',
			[
				'email' => $email
			],
			__METHOD__
		);
	}

	/**
	 * @return IResultWrapper
	 */
	public function getBureaucrats(): IResultWrapper {
		$db = wfGetDB( DB_REPLICA );
		return $db->select(
			[
				'user_groups',
				'user'
			],
			[
				'ug_user',
				'ug_expiry'
			],
			[
				'ug_group' => 'bureaucrat'
			],
			__METHOD__,
			[],
			[
				'user' => [ 'INNER JOIN', [ 'ug_user = user_id' ] ]
			]
		);
	}
}
