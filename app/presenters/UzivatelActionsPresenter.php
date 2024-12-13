<?php

namespace App\Presenters;

use Nette;
use App\Model;
use App\Services;
use DateInterval;
use DateTime;
use Tracy\Debugger;

/**
 * Uzivatel actions presenter.
 */
class UzivatelActionsPresenter extends UzivatelPresenter
{
    private $accountActivation;
    private $uzivatel;
    private $pdfGenerator;
    private $mailService;
    private $smlouva;
    private Services\RequestDruzstvoContract $requestDruzstvoContract;

    public function __construct(Services\MailService $mailsvc, Services\PdfGenerator $pdf, Model\AccountActivation $accActivation, Model\Uzivatel $uzivatel, Model\Smlouva $smlouva, Services\RequestDruzstvoContract $requestDruzstvoContract) {
        $this->pdfGenerator = $pdf;
        $this->accountActivation = $accActivation;
        $this->uzivatel = $uzivatel;
        $this->mailService = $mailsvc;
        $this->smlouva = $smlouva;
        $this->requestDruzstvoContract = $requestDruzstvoContract;
    }

    public function actionMoneyActivate() {
        $id = $this->getParameter('id');
        if ($id) {
            if ($this->accountActivation->activateAccount($this->getUser(), $id)) {
                $this->flashMessage('Účet byl aktivován.');
            }

            $this->redirect('Uzivatel:show', array('id' => $id));
        }
    }

    public function actionMoneyReactivate() {
        $id = $this->getParameter('id');
        if ($id) {
            $result = $this->accountActivation->reactivateAccount($this->getUser(), $id);
            if ($result != '') {
                $this->flashMessage($result);
            }

            $this->redirect('Uzivatel:show', array('id' => $id));
        }
    }

    public function actionMoneyDeactivate() {
        $id = $this->getParameter('id');
        if ($id) {
            if ($this->accountActivation->deactivateAccount($this->getUser(), $id)) {
                $this->flashMessage('Účet byl deaktivován.');
            }

            $this->redirect('Uzivatel:show', array('id' => $id));
        }
    }

    public function actionExportPdf() {
        if ($this->getParameter('id')) {
            if ($uzivatel = $this->uzivatel->getUzivatel($this->getParameter('id'))) {
                $pdftemplate = $this->createTemplate()->setFile(__DIR__."/../templates/Uzivatel/pdf-form.latte");
                $pdf = $this->pdfGenerator->generatePdf($uzivatel, $pdftemplate);
                $this->sendResponse($pdf);
            }
        }
    }
    public function actionSendRegActivation() {
        if ($this->getParameter('id')) {
            if ($uzivatel = $this->uzivatel->getUzivatel($this->getParameter('id'))) {
                $hash = base64_encode($uzivatel->id . '-' . md5($this->context->parameters["salt"] . $uzivatel->zalozen));
                //\Tracy\Debugger::barDump($link);exit();
                $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());

                if ($uzivatel->druzstvo == 1) {
                    $link = "https://moje.hkfree.org/uzivatel/confirm-druzstvo/" . $hash;

                    $this->mailService->sendDruzstvoConfirmationRequest($uzivatel, $so, $link);
                    $this->mailService->sendDruzstvoConfirmationRequestCopy($uzivatel, $so);

                    $this->flashMessage('E-mail s žádostí o ověření emailu pro registraci do družstva byl odeslán.');
                } elseif ($uzivatel->spolek == 1) {
                    $link = "https://moje.hkfree.org/uzivatel/confirm/" . $hash;

                    $this->mailService->sendSpolekConfirmationRequest($uzivatel, $so, $link);
                    $this->mailService->sendSpolekConfirmationRequestCopy($uzivatel, $so);

                    $this->flashMessage('E-mail s žádostí o potvrzení registrace do spolku byl odeslán.');
                }

                $this->redirect('Uzivatel:show', array('id' => $uzivatel->id));
            }
        }
    }

    public function actionExportAndSendRegForm() {
        if ($this->getParameter('id')) {
            if ($uzivatel = $this->uzivatel->getUzivatel($this->getParameter('id'))) {
                if ($uzivatel->spolek == 0) {
                    $this->flashMessage('Uživatel není členem spolku, e-mail nebyl odeslán.');

                    $this->redirect('Uzivatel:show', array('id' => $uzivatel->id));
                } else {
                    $pdftemplate = $this->createTemplate()->setFile(__DIR__ . "/../templates/Uzivatel/pdf-form.latte");
                    $pdf = $this->pdfGenerator->generatePdf($uzivatel, $pdftemplate);

                    $this->mailService->mailPdf($pdf, $uzivatel, $this->getHttpRequest(), $this->getHttpResponse(), $this->getIdentity()->getUid());

                    $this->flashMessage('E-mail byl odeslán.');

                    $this->redirect('Uzivatel:show', array('id' => $uzivatel->id));
                }
            }
        }
    }

    private function checkTimeSinceLastGenerateContract(string $interval = 'PT5M') {
        $user_id = $this->getParameter('id');

        $last_generated = $this->smlouva->findAll()
            ->where('Uzivatel_id', $user_id)->order('kdy_vygenerovano DESC')->limit(1)->fetch();

        // Pokud neexistuje zadna smlouva, je to v pohode => return
        if (!$last_generated) {
            return;
        }

        $last_generated_datetime = \Nette\Utils\DateTime::from($last_generated['kdy_vygenerovano']);
        $interval_ago = (new DateTime())
            ->sub(new DateInterval($interval));

        if ($interval_ago < $last_generated_datetime) {
            $this->flashMessage('Ochrana proti přetížení: Nelze vytvořit další smlouvu dříve než 5 minut po předchozí. Pokud je to záměr, prosím chvíli počkej.', 'danger');
            $this->redirect('Uzivatel:show', array('id' => $user_id));
        }
    }

    public function actionHandleSubscriberContractPreview() {
        $user_id = $this->getParameter('id');
        $uzivatel =  $this->uzivatel->find($user_id);

        $tmpname = sprintf('/dev/shm/document_%u.pdf', rand(1, 1e9));
        $pdfData = \App\Services\GeneratorSmlouvy::nahledUcastnickeSmlouvy($uzivatel);
        file_put_contents($tmpname, $pdfData);

        $this->sendResponse(new Nette\Application\Responses\FileResponse($tmpname, "náhled smlouvy", 'application/pdf', false));

        unlink($tmpname);
    }

    public function actionHandleSubscriberContract() {
        // TODO: Logování změn

        if (!$this->getParameter('id')) {
            $this->flashMessage('Žádné id.');
            $this->redirect('UzivatelList:listall');
        }

        $user_id = $this->getParameter('id');
        $current_user = $this->uzivatel->find($user_id);

        if (!$current_user) {
            $this->flashMessage('Žádný uživatel s tímto id.');
            $this->redirect('UzivatelList:listall');
        }

        // Kontrola, že od poslední generace uběhlo aspoň 5 minut...
        // $this->checkTimeSinceLastGenerateContract();

        $newId = $this->requestDruzstvoContract->execute($user_id);

        $this->flashMessage(sprintf('Nová smlouva číslo %u bude odeslána na e-mail %s.', $newId, $current_user->email));
        // Tady call na generaci nove smlouvy a odeslani
        $this->redirect('Uzivatel:show', array('id' => $user_id));
    }
}
