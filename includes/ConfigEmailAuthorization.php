<?php

/*
 * Copyright (c) 2017 The MITRE Corporation
 *
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

class ConfigEmailAuthorization extends SpecialPage {

	function __construct() {
		parent::__construct( 'ConfigEmailAuthorization',
			'configemailauthorization' );
	}

	function execute( $par ) {
		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
			return;
		}

		$request = $this->getRequest();
		$this->setHeaders();
		$this->getOutput()->addModuleStyles( 'ext.EmailAuthorization' );

		$search = self::searchEmail( $request->getText( 'searchemail' ) );
		self::addEmail( $request->getText( 'addemail' ) );
		self::revokeEmail( $request->getText( 'revokeemail' ) );
		self::showAuthorizedUsers( $request->getText( 'authoffset' ) );
		self::showAllUsers( $request->getText( 'alloffset' ) );

		$title = Title::newFromText( 'Special:' . __CLASS__ );
		$url = $title->getFullURL();

		$html = Html::openElement( 'p' )
			. Html::openElement( 'b' )
			. wfMessage( 'configemailauthorization-instructions' )->parse()
			. Html::closeElement( 'b' )
			. Html::closeElement( 'p' );
		$this->getOutput()->addHtml( $html );

		$defaultAddEmail = '';
		if ( is_null( $search ) ) {
			$defaultAddEmail = trim( $request->getText( 'revokeemail' ) );
		} elseif ( !$search ) {
			$defaultAddEmail = trim( $request->getText( 'searchemail' ) );
		}
		self::showAddForm( $url, $defaultAddEmail );

		$defaultRevokeEmail = '';
		if ( is_null( $search ) ) {
			$defaultRevokeEmail = trim( $request->getText( 'addemail' ) );
		} elseif ( $search ) {
			$defaultRevokeEmail = trim( $request->getText( 'searchemail' ) );
		}
		self::showRevokeForm( $url, $defaultRevokeEmail );

		self::showSearchForm( $url );
		self::showAuthorizedUsersForm( $url );
		self::showAllUsersForm( $url );
	}

	private function displayMessage( $message ) {
		$html = Html::openElement( 'p', [
				'class' => 'emailauth-message'
			] )
			. $message
			. Html::closeElement( 'p' );
		$this->getOutput()->addHtml( $html );
	}

	private function searchEmail( $email ) {
		if ( !is_null( $email ) && strlen( $email ) > 0 ) {
			$email = mb_strtolower( htmlspecialchars( trim( $email ), ENT_QUOTES ) );
			if ( EmailAuthorization::isEmailAuthorized( $email ) ) {
				$this->displayMessage(
					wfMessage( 'configemailauthorization-authorized', $email )
				);
				return true;
			} else {
				$this->displayMessage(
					wfMessage( 'configemailauthorization-notauthorized', $email )
				);
				return false;
			}
		}
		return null;
	}

	private function addEmail( $email ) {
		if ( !is_null( $email ) && strlen( $email ) > 0 ) {
			$email = mb_strtolower( htmlspecialchars( trim( $email ), ENT_QUOTES ) );
			if ( self::insertEmail( $email ) ) {
				$this->displayMessage(
					wfMessage( 'configemailauthorization-added', $email )
				);
				wfRunHooks( 'EmailAuthorizationAdd', [ $email ] );
			} else {
				$this->displayMessage(
					wfMessage( 'configemailauthorization-alreadyauthorized', $email )
				);
			}
		}
	}

	private function revokeEmail( $email ) {
		if ( !is_null( $email ) && strlen( $email ) > 0 ) {
			$email = mb_strtolower( htmlspecialchars( trim( $email ), ENT_QUOTES ) );
			if ( self::deleteEmail( $email ) ) {
				$this->displayMessage(
					wfMessage( 'configemailauthorization-revoked', $email )
				);
				wfRunHooks( 'EmailAuthorizationRevoke', [ $email ] );
			} else {
				$this->displayMessage(
					wfMessage( 'configemailauthorization-notauthorized', $email )
				);
			}
		}
	}

	private function showAuthorizedUsers( $authoffset ) {

		if ( is_null( $authoffset ) || strlen( $authoffset ) == 0 ||
			!is_numeric( $authoffset ) || $authoffset < 0 ) {
			return;
		}

		$limit = 20;

		$emails = self::getAuthorizedEmails( $limit + 1, $authoffset );
		$next = false;

		if ( !$emails->valid() ) {
			$authoffset = 0;
			$emails = self::getAuthorizedEmails( $limit + 1, $authoffset );
			if ( !$emails->valid() ) {
				$this->displayMessage(
					wfMessage( 'configemailauthorization-noauthfound' )
				);
				return;
			}
		}

		$wikitext = '{| class="wikitable emailauth-wikitable"' . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'configemailauthorization-label-email' ) . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'configemailauthorization-label-username' ) . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'configemailauthorization-label-realname' ) . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'configemailauthorization-label-userpage' ) . PHP_EOL;

		$index = 0;
		$more = false;
		foreach ( $emails as $email ) {
			if ( $index < $limit ) {
				$wikitext .= '|-' . PHP_EOL;
				$email_addr = htmlspecialchars( $email->email, ENT_QUOTES );
				if ( strlen( $email_addr ) > 1 && $email_addr[0] == '@' ) {
					$wikitext .= '|'
						. wfMessage( 'configemailauthorization-value-domain', $email_addr )
						. PHP_EOL;
					$wikitext .= '| &nbsp;' . PHP_EOL;
					$wikitext .= '| &nbsp;' . PHP_EOL;
					$wikitext .= '| &nbsp;' . PHP_EOL;
				} else {
					$wikitext .= '|' . $email_addr . PHP_EOL;
					$users = self::getUserInfo( $email_addr );
					if ( !$users->valid() ) {
						$wikitext .= '| &nbsp;' . PHP_EOL;
						$wikitext .= '| &nbsp;' . PHP_EOL;
						$wikitext .= '| &nbsp;' . PHP_EOL;
					} else {
						$first = true;
						$wikitext .= '|';
						foreach ( $users as $user ) {
							$user_name = htmlspecialchars( $user->user_name, ENT_QUOTES );
							if ( $first ) {
								$first = false;
							} else {
 								$wikitext .= '<br />';
							}
 							$wikitext .= $user_name;
						}
 						$wikitext .= PHP_EOL;
						$first = true;
						$wikitext .= '|';
						foreach ( $users as $user ) {
							$real_name = htmlspecialchars( $user->user_real_name, ENT_QUOTES );
							if ( $first ) {
								$first = false;
							} else {
 								$wikitext .= '<br />';
							}
 							$wikitext .= $real_name;
						}
 						$wikitext .= PHP_EOL;
						$first = true;
						$wikitext .= '|';
						foreach ( $users as $user ) {
							$user_name = htmlspecialchars( $user->user_name, ENT_QUOTES );
							if ( $first ) {
								$first = false;
							} else {
 								$wikitext .= '<br />';
							}
							$wikitext .= '[[User:' . $user_name . ']]';
						}
 						$wikitext .= PHP_EOL;
					}
				}
				$index ++;
			} else {
				$more = true;
			}
		}

		$wikitext .= '|}' . PHP_EOL;
		$this->getOutput()->addWikiText( $wikitext );

		if ( $authoffset > 0 || $more ) {
			$this->addTableNavigation( $authoffset, $more, $limit, 'authoffset' );
		}

		$html = Html::element( 'hr' );
		$this->getOutput()->addHtml( $html );
	}

	private function showAllUsers( $alloffset ) {

		if ( is_null( $alloffset ) || strlen( $alloffset ) == 0 ||
			!is_numeric( $alloffset ) || $alloffset < 0 ) {
			return;
		}

		$limit = 20;

		$users = self::getUsers( $limit + 1, $alloffset );
		$next = false;

		if ( !$users->valid() ) {
			$alloffset = 0;
			$users = self::getUsers( $limit + 1, $alloffset );
			if ( !$users->valid() ) {
				$this->displayMessage(
					wfMessage( 'configemailauthorization-nousersfound' )
				);
				return;
			}
		}

		$wikitext = '{| class="wikitable emailauth-wikitable"' . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'configemailauthorization-label-email' ) . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'configemailauthorization-label-username' ) . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'configemailauthorization-label-realname' ) . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'configemailauthorization-label-userpage' ) . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'configemailauthorization-label-authorized' ) . PHP_EOL;

		$index = 0;
		$more = false;
		foreach ( $users as $user ) {
			if ( $index < $limit ) {
				$email = htmlspecialchars( $user->user_email, ENT_QUOTES );
				$user_name = htmlspecialchars( $user->user_name, ENT_QUOTES );
				$real_name = htmlspecialchars( $user->user_real_name, ENT_QUOTES );
				$email = htmlspecialchars( $user->user_email, ENT_QUOTES );
				$wikitext .= '|-' . PHP_EOL;
				$wikitext .= '|' . $email . PHP_EOL;
				$wikitext .= '|' . $user_name . PHP_EOL;
				$wikitext .= '|' . $real_name . PHP_EOL;
				$wikitext .= '|[[User:' . $user_name . ']]' . PHP_EOL;
				if ( EmailAuthorization::isEmailAuthorized( $email ) ) {
					$wikitext .= '| style="text-align:center;" | '
						. wfMessage( 'configemailauthorization-value-yes' )
						. PHP_EOL;
				} else {
					$wikitext .= '| style="text-align:center;" | '
						. wfMessage( 'configemailauthorization-value-no' )
						. PHP_EOL;
				}
				$index ++;
			} else {
				$more = true;
			}
		}

		$wikitext .= '|}' . PHP_EOL;
		$this->getOutput()->addWikiText( $wikitext );

		if ( $alloffset > 0 || $more ) {
			$this->addTableNavigation( $alloffset, $more, $limit, 'alloffset' );
		}

		$html = Html::element( 'hr' );
		$this->getOutput()->addHtml( $html );
	}

	private function addTableNavigation( $offset, $more, $limit, $paramname ) {

		$title = Title::newFromText( 'Special:ConfigEmailAuthorization' );
		$url = $title->getFullURL();

		$html = Html::openElement( 'table', [
				'class' => 'emailauth-navigationtable'
			] )
			. Html::openElement( 'tr' )
			. Html::openElement( 'td' );

		if ( $offset > 0 ) {
			$prevurl = $url . '?' . $paramname . '=' . ( $offset - $limit );
			$html .= Html::openElement( 'a', [
					'href' => $prevurl,
					'class' => 'emailauth-button'
				] )
				. wfMessage( 'configemailauthorization-button-previous' )
				. Html::closeElement( 'a' );
		}

		$html .= Html::closeElement( 'td' )
			. Html::openElement( 'td', [
				'style' => 'text-align:right;'
			] );

		if ( $more ) {
			$nexturl = $url . '?' . $paramname . '=' . ( $offset + $limit );
			$html .= Html::openElement( 'a', [
					'href' => $nexturl,
					'class' => 'emailauth-button'
				] )
				. wfMessage( 'configemailauthorization-button-next' )
				. Html::closeElement( 'a' );
		}

		$html .= Html::closeElement( 'td' )
			. Html::closeElement( 'tr' )
			. Html::closeElement( 'table' );
		$this->getOutput()->addHtml( $html );
	}

	private function showSearchForm( $url ) {
		$html = Html::openElement( 'form', [
				'method' => 'post',
				'action' => $url,
				'id' => 'SearchEmail'
			] )
			. Html::openElement( 'fieldset' )
			. Html::element( 'legend', null,
				wfMessage( 'configemailauthorization-legend-search' ) . ':' );
		list( $label, $input ) =
			Xml::inputLabelSep( 'Email address:', 'searchemail', 'searchemail',
			50 );
		$html .= $label . ' ' . $input . ' '
			. Xml::submitButton(
				wfMessage( 'configemailauthorization-button-search' ),
				[ 'class' => 'emailauth-button' ] )
			. Html::closeElement( 'fieldset' )
			. Html::closeElement( 'form' );
		$this->getOutput()->addHtml( $html );
	}

	private function showAddForm( $url, $default ) {
		$html = Html::openElement( 'form', [
				'method' => 'post',
				'action' => $url,
				'id' => 'AddEmail'
			] )
			. Html::openElement( 'fieldset' )
			. Html::element( 'legend', null,
				wfMessage( 'configemailauthorization-legend-add' ) . ':' );
		list( $label, $input ) =
			Xml::inputLabelSep( 'Email address:', 'addemail', 'addemail', 50,
			$default );
		$html .= $label . ' ' . $input . ' '
			. Xml::submitButton(
				wfMessage( 'configemailauthorization-button-add' ),
				[ 'class' => 'emailauth-button' ] )
			. Html::closeElement( 'fieldset' )
			. Html::closeElement( 'form' );
		$this->getOutput()->addHtml( $html );
	}

	private function showRevokeForm( $url, $default ) {
		$html = Html::openElement( 'form', [
				'method' => 'post',
				'action' => $url,
				'id' => 'RevokeEmail'
			] )
			. Html::openElement( 'fieldset' )
			. Html::element( 'legend', null,
				wfMessage( 'configemailauthorization-legend-revoke' ) . ':' );
		list( $label, $input ) =
			Xml::inputLabelSep( 'Email address:', 'revokeemail', 'revokeemail',
			50, $default );
		$html .= $label . ' ' . $input . ' '
			. Xml::submitButton(
				wfMessage( 'configemailauthorization-button-revoke' ),
				[ 'class' => 'emailauth-button' ] )
			. Html::closeElement( 'fieldset' )
			. Html::closeElement( 'form' );
		$this->getOutput()->addHtml( $html );
	}

	private function showAuthorizedUsersForm( $url ) {
		$html = Html::openElement( 'form', [
				'method' => 'post',
				'action' => $url,
				'id' => 'ShowAuthorizedUsers',
				'style' => 'display:inline;'
			] )
			. Html::hidden( 'authoffset', 0 )
			. Xml::submitButton(
				wfMessage( 'configemailauthorization-button-showauth' ),
				[ 'class' => 'emailauth-button' ] )
			. Html::closeElement( 'form' );
		$this->getOutput()->addHtml( $html );
	}

	private function showAllUsersForm( $url ) {
		$html = Html::openElement( 'form', [
				'method' => 'post',
				'action' => $url,
				'id' => 'ShowAllUsers',
				'style' => 'display:inline;'
			] )
			. Html::hidden( 'alloffset', 0 )
			. Xml::submitButton(
				wfMessage( 'configemailauthorization-button-showall' ),
				[ 'class' => 'emailauth-button' ] )
			. Html::closeElement( 'form' );
		$this->getOutput()->addHtml( $html );
	}

	private static function getAuthorizedEmails( $limit, $authoffset ) {
		$dbr = wfGetDB( DB_SLAVE );
		$emails = $dbr->select(
			'emailauth',
			[
				'email'
			],
			[],
			__METHOD__,
			[
				'ORDER BY' => 'email',
				'LIMIT' => $limit,
				'OFFSET' => $authoffset
			]
		);
		return $emails;
	}

	private static function getUsers( $limit, $alloffset ) {
		$dbr = wfGetDB( DB_SLAVE );
		$users = $dbr->select(
			'user',
			[
				'user_name',
				'user_real_name',
				'user_email'
			],
			[],
			__METHOD__,
			[
				'ORDER BY' => 'user_email',
				'LIMIT' => $limit,
				'OFFSET' => $alloffset
			]
		);
		return $users;
	}

	private static function getUserInfo( $email ) {
		$dbr = wfGetDB( DB_SLAVE );
		$users = $dbr->select(
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
		return $users;
	}

	private static function insertEmail( $email ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert(
			'emailauth',
			[
				'email' => $email
			],
			__METHOD__
		);
		if ( $dbw->affectedRows() === 1 ) {
			return true;
		} else {
			return false;
		}
	}

	private static function deleteEmail( $email ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'emailauth',
			[
				'email' => $email
			],
			__METHOD__
		);
		if ( $dbw->affectedRows() === 1 ) {
			return true;
		} else {
			return false;
		}
	}
}
