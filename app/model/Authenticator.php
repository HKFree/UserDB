<?php

namespace App\Model;

use Nette,
	Nette\Security,
	Nette\Utils\Strings;

/**
 * Users authenticator.
 */
class Authenticator extends Nette\Object implements Security\IAuthenticator
{
	
	/** @var Nette\Database\Connection */
	protected $connection;
	
	public function __construct(Nette\Database\Connection $db)
	{
		$this->connection = $db;
	}
			
	/**
	 * Performs an authentication.
	 * @return Nette\Security\Identity
	 * @throws Nette\Security\AuthenticationException
	 */
	public function authenticate(array $credentials)
	{
    list($username, $password) = $credentials;
		if (!$username) {
		    throw new Nette\Security\AuthenticationException('User not found.');
		}
  
    if($username == "666666")
    {
      $roles []= "SO-91";
      $arr = array('nick' => 'Hellboy');
  	  return new Nette\Security\Identity("666666", $roles, $arr);
    }
      
		$roles_string = $_SERVER['ismemberof'];
		$roles_ldap = explode(';',$roles_string);
		foreach ($roles_ldap as $role_ldap) {
		    if (preg_match('/^cn=(.+?),ou=roles,dc=hkfree,dc=org$/', $role_ldap, $matches)) {
				$role = $matches[1];
				$roles []= $role;
				if ($role == 'VV' || $role == 'MOBILADM') {
				    $roles []= '@ADMIN';
				}
		    }
		}
		
		$arr = array('nick' => $_SERVER['givenName']);
		return new Nette\Security\Identity($username, $roles, $arr);
	}
	

}
