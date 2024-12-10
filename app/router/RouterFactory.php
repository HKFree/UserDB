<?php

namespace App;

use Nette;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\SimpleRouter;

/**
 * Router factory.
 */
class RouterFactory
{
    public function createRouter($urlPrefix): Nette\Routing\Router {
        $router = new RouteList();
        $router->addRoute($urlPrefix . '/uzivatel/list/<id>', 'UzivatelList:list', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/uzivatel/listall', 'UzivatelList:listall', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/sprava/schvalovanicc', 'SpravaCc:schvalovanicc', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/sprava/prehledcc', 'SpravaCc:prehledcc', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/sprava/usersgraph', 'SpravaGrafu:usersgraph', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/sprava/mailinglist', 'SpravaMailu:mailinglist', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/sprava/novaoblast', 'SpravaOblasti:novaoblast', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/sprava/noveap', 'SpravaOblasti:noveap', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/sprava/odchoziplatby', 'SpravaPlateb:odchoziplatby', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/sprava/platbycu', 'SpravaPlateb:platbycu', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/sprava/nesparovane', 'SpravaNesparovanych:nesparovane', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/sprava/prevod', 'SpravaNesparovanych:prevod', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/sprava/sms', 'SpravaSms:sms', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/sprava/ucty', 'SpravaUctu:ucty', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/uzivatel/sms/<id>', 'UzivatelMailSms:sms', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/uzivatel/email/<id>', 'UzivatelMailSms:email', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/uzivatel/smsall/<id>', 'UzivatelMailSms:smsall', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/uzivatel/emailall/<id>', 'UzivatelMailSms:emailall', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/uzivatel/editcc/<id>', 'UzivatelRightsCc:editcc', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/uzivatel/editrights/<id>', 'UzivatelRightsCc:editrights', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/uzivatel/account/<id>', 'UzivatelAccount:account', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/uzivatel/platba/<id>', 'UzivatelAccount:platba', Route::ONE_WAY);
        $router->addRoute($urlPrefix . '/stitky/saveLabel', ['presenter' => 'SpravaStitku','action' => 'saveLabel',]);
        $router->addRoute($urlPrefix . '/stitky/deleteLabel', ['presenter' => 'SpravaStitku','action' => 'deleteLabel',]);

        $router[] = new Route($urlPrefix . '/api/<presenter>[/<action=default>[/<id>]]', [
            'module' => 'Api'
        ]);
        $router[] = new Route($urlPrefix.'/<presenter>/list/<id>', 'Homepage:list');
        $router[] = new Route($urlPrefix.'/<presenter>/<action>[/<id>]', 'Homepage:default');
        return $router;
    }
}
