<?php

namespace App\Presenters;

use App\Model\Uzivatel;
use App\Services\CryptoSluzba;
use App\Services\RequestDruzstvoContract;

class SelfServicePresenter extends \Nette\Application\UI\Presenter
{
    public function __construct(
        private RequestDruzstvoContract $requestDruzstvoContract,
        private Uzivatel $uzivatelModel,
        private CryptoSluzba $cryptosvc,
    ) {
    }

    public function renderRequestDruzstvoContract($id, $hash) {
        $this->setLayout('pub');
        $this->template->error = '';
        $uzivatel = $this->uzivatelModel->getUzivatel($id);
        if (!$uzivatel) {
            $this->template->error = 'Uživatel nenalezen';
        } else {
            if (!$uzivatel->oneclick_auth) {
                $this->template->error = 'Neplatný odkaz';
            } else {
                $oneclick_auth_code = $this->cryptosvc->decrypt($uzivatel->oneclick_auth);
                if ($oneclick_auth_code !== $hash) {
                    $this->template->error = 'Neplatný odkaz';
                } else {
                    $this->requestDruzstvoContract->execute($uzivatel->id);
                }
            }
        }
    }

}
