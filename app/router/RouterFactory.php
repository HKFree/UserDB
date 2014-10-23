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
	public function createRouter()
	{
		$router = new RouteList();
		$router[] = new Route('/userdb/<presenter>/list/<id>', 'Homepage:list', Route::SECURED);
		$router[] = new Route('/userdb/<presenter>/<action>[/<id>]', 'Homepage:default', Route::SECURED);
		return $router;
	}

}
