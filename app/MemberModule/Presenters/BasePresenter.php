<?php

namespace App\MemberModule\Presenters;

use App\Model\MemberAuthenticator;
use App\Settings;
use Nette\Application\UI\Presenter;
use Nette\Security\AuthenticationException;

/** Členská část (moje.hkfree.org): jen na memberHost, jen aktivní členové (R4, R5). */
abstract class BasePresenter extends Presenter
{
    protected Settings $settings;
    private MemberAuthenticator $authenticator;

    public function injectMember(Settings $settings, MemberAuthenticator $authenticator): void {
        $this->settings = $settings;
        $this->authenticator = $authenticator;
    }

    protected function startup(): void {
        parent::startup();
        if ($this->getHttpRequest()->getUrl()->getHost() !== $this->settings->memberHost) {
            $this->error();
        }
        $uid = $this->settings->fakeUser ? $this->settings->fakeUser['userID'] : ($_SERVER['HTTP_UID'] ?? '');
        try {
            $this->getUser()->login($this->authenticator->authenticate((string) $uid));
        } catch (AuthenticationException $e) {
            $this->error($e->getMessage(), 403);
        }
    }
}
