<?php

namespace App\Model;

use Nette,
  App\Model,
	Nette\Security,
	Nette\Utils\Strings,
    Tracy\Debugger;

/**
 * Users authenticator.
 */
class Authenticator extends Nette\Object implements Security\IAuthenticator
{
	protected $context;
  private $fakeUser;
  private $spravceOb;
	
	public function __construct($fakeUser, Nette\Database\Context $ctx)
	{
		$this->context = $ctx;
    $this->fakeUser = $fakeUser;
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
      
    $spravcepro = $this->context->table("SpravceOblasti")->where('Uzivatel_id', $username)->fetchAll();
    $roles = array();
    foreach ($spravcepro as $key => $value) {
      if($value->Oblast)
        { $roles[] = $value->ref('TypSpravceOblasti', 'TypSpravceOblasti_id')->text."-".$value->Oblast; }
      else
        { $roles[] = $value->ref('TypSpravceOblasti', 'TypSpravceOblasti_id')->text; }
    }
    //Debugger::dump( $roles );

		if($this->fakeUser != false)			/// debuging identity
		{
			//$roles = array_merge();
            /*if(is_array($this->fakeUser["userRoles"]))
                $roles = $this->fakeUser["userRoles"];   */  //reseno na urovni DB
			$args = array('nick' => $this->fakeUser["userName"]);
			return new Nette\Security\Identity($this->fakeUser["userID"], $roles, $args);
		}

    //role z LDAP
		/*$roles_string = $_SERVER['ismemberof'];
		$roles_ldap = explode(';',$roles_string);
		foreach ($roles_ldap as $role_ldap) {
			if (preg_match('/^cn=(.+?),ou=roles,dc=hkfree,dc=org$/', $role_ldap, $matches)) {
				$role = $matches[1];
				$roles []= $role;
				if ($role == 'VV' || $role == 'MOBILADM') {
					$roles []= '@ADMIN';
				}
			}
		}*/

		$arr = array('nick' => $_SERVER['givenName']);
		return new Nette\Security\Identity($username, $roles, $arr);
	}
	

}
