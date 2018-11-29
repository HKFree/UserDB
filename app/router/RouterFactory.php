<?php

namespace App;

use Nette,
	Nette\Application\Routers\RouteList,
	Nette\Application\Routers\Route,
	Nette\Application\Routers\SimpleRouter;


/**
 * Router factory.
 */
class RouterFactory
{

	/**
	 * @return \Nette\Application\IRouter
	 */
	public function createRouter($urlPrefix)
	{
		$router = new RouteList();

		$router[] = new Route($urlPrefix.'/uzivatel/list/<id>', 'UzivatelList:list', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/uzivatel/listall', 'UzivatelList:listall', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/sprava/schvalovanicc', 'SpravaCc:schvalovanicc', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/sprava/prehledcc', 'SpravaCc:prehledcc', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/sprava/usersgraph', 'SpravaGrafu:usersgraph', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/sprava/mailinglist', 'SpravaMailu:mailinglist', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/sprava/novaoblast', 'SpravaOblasti:novaoblast', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/sprava/noveap', 'SpravaOblasti:noveap', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/sprava/odchoziplatby', 'SpravaPlateb:odchoziplatby', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/sprava/platbycu', 'SpravaPlateb:platbycu', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/sprava/nesparovane', 'SpravaNesparovanych:nesparovane', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/sprava/prevod', 'SpravaNesparovanych:prevod', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/sprava/sms', 'SpravaSms:sms', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/sprava/ucty', 'SpravaUctu:ucty', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/uzivatel/sms/<id>', 'UzivatelMailSms:sms', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/uzivatel/email/<id>', 'UzivatelMailSms:email', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/uzivatel/smsall/<id>', 'UzivatelMailSms:smsall', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/uzivatel/emailall/<id>', 'UzivatelMailSms:emailall', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/uzivatel/editcc/<id>', 'UzivatelRightsCc:editcc', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/uzivatel/editrights/<id>', 'UzivatelRightsCc:editrights', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/uzivatel/account/<id>', 'UzivatelAccount:account', Route::ONE_WAY);
		$router[] = new Route($urlPrefix.'/uzivatel/platba/<id>', 'UzivatelAccount:platba', Route::ONE_WAY);
		

        $router[] = new Route($urlPrefix . '/api/<presenter>[/<action=default>[/<id>]]', [
                    'module' => 'Api'
                ]);
		$router[] = new Route($urlPrefix.'/<presenter>/list/<id>', 'Homepage:list');
		$router[] = new Route($urlPrefix.'/<presenter>/<action>[/<id>]', 'Homepage:default');        
		return $router;
	}
}
