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
	public function createRouter($dbg, $urlPrefix)
	{
		$router = new RouteList();
		$router[] = new Route($urlPrefix.'/<presenter>/list/<id>', 'Homepage:list', ($dbg ? null : Route::SECURED));
		$router[] = new Route($urlPrefix.'/<presenter>/<action>[/<id>]', 'Homepage:default', ($dbg ? null : Route::SECURED));
		return $router;
	}

}
