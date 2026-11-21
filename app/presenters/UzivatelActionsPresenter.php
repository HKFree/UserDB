<?php

namespace App\Presenters;

use Nette;
use App\Model;
use App\Services;
use Tracy\Debugger;

/**
 * Uzivatel actions presenter.
 * @property-read Nette\Bridges\ApplicationLatte\Template $template
 * @method Nette\Bridges\ApplicationLatte\Template createTemplate(?string $class = null)
 */
class UzivatelActionsPresenter extends UzivatelPresenter
{
    private Model\Parameters $parameters;
    private Model\AccountActivation $accountActivation;
    private Model\Uzivatel $uzivatel;
    private Services\PdfGenerator $pdfGenerator;
    private Services\MailService $mailService;

    private Services\RequestDruzstvoContract $requestDruzstvoContract;

    public function __construct(
        Model\Parameters $parameters,
        Services\MailService $mailsvc,
        Services\PdfGenerator $pdf,
        Model\AccountActivation $accActivation,
        Model\Uzivatel $uzivatel,
        Services\RequestDruzstvoContract $requestDruzstvoContract,
        Services\CryptoSluzba $cryptosvc,
        Model\PovoleneSMTP $alowedSMTP,
        Model\DNat $dnat,
        Model\SloucenyUzivatel $slUzivatel,
        Model\Smlouva $smlouva,
        Services\SmlouvaStavSluzba $smlouvaStavSluzba,
        Model\SpravceOblasti $prava,
        Model\CestneClenstviUzivatele $cc,
        Model\TypPravniFormyUzivatele $typPravniFormyUzivatele,
        Model\TypClenstvi $typClenstvi,
        Model\ZpusobPripojeni $zpusobPripojeni,
        Model\TechnologiePripojeni $technologiePripojeni,
        Model\IPAdresa $ipAdresa,
        Model\TypZarizeni $typZarizeni,
        Model\Log $log,
        Model\IdsConnector $idsConnector,
        Model\AplikaceToken $aplikaceToken,
        Model\Stitek $stitek,
        Model\StitekUzivatele $stitkyUzivatele,
    ) {
        parent::__construct(
            mailsvc: $mailsvc,
            cryptosvc: $cryptosvc,
            alowedSMTP: $alowedSMTP,
            dnat: $dnat,
            parameters: $parameters,
            slUzivatel: $slUzivatel,
            smlouva: $smlouva,
            smlouvaStavSluzba: $smlouvaStavSluzba,
            prava: $prava,
            cc: $cc,
            typPravniFormyUzivatele: $typPravniFormyUzivatele,
            typClenstvi: $typClenstvi,
            zpusobPripojeni: $zpusobPripojeni,
            technologiePripojeni: $technologiePripojeni,
            uzivatel: $uzivatel,
            ipAdresa: $ipAdresa,
            typZarizeni: $typZarizeni,
            log: $log,
            idsConnector: $idsConnector,
            aplikaceToken: $aplikaceToken,
            stitek: $stitek,
            stitkyUzivatele: $stitkyUzivatele,
        );
        $this->parameters = $parameters;
        $this->pdfGenerator = $pdf;
        $this->accountActivation = $accActivation;
        $this->uzivatel = $uzivatel;
        $this->mailService = $mailsvc;
        $this->requestDruzstvoContract = $requestDruzstvoContract;
        $this->cryptosvc = $cryptosvc;
    }

    public function actionMoneyActivate(): void {
        $id = $this->getParameter('id');
        if ($id) {
            if ($this->accountActivation->activateAccount($this->getUser(), $id)) {
                $this->flashMessage('Účet byl aktivován.');
            }

            $this->redirect('Uzivatel:show', array('id' => $id));
        }
    }

    public function actionMoneyReactivate(): void {
        $id = $this->getParameter('id');
        if ($id) {
            $result = $this->accountActivation->reactivateAccount($this->getUser(), $id);
            if ($result != '') {
                $this->flashMessage($result);
            }

            $this->redirect('Uzivatel:show', array('id' => $id));
        }
    }

    public function actionMoneyDeactivate(): void {
        $id = $this->getParameter('id');
        if ($id) {
            if ($this->accountActivation->deactivateAccount($this->getUser(), $id)) {
                $this->flashMessage('Účet byl deaktivován.');
            }

            $this->redirect('Uzivatel:show', array('id' => $id));
        }
    }

    public function actionExportPdf(): void {
        if ($this->getParameter('id')) {
            if ($uzivatel = $this->uzivatel->getUzivatel($this->getParameter('id'))) {
                $pdftemplate = $this->createTemplate()->setFile(__DIR__."/../templates/Uzivatel/pdf-form.latte");
                $pdf = $this->pdfGenerator->generatePdf($uzivatel, $pdftemplate);
                $this->sendResponse($pdf);
            }
        }
    }
    public function actionSendRegActivation(): void {
        if ($this->getParameter('id')) {
            if ($uzivatel = $this->uzivatel->getUzivatel($this->getParameter('id'))) {
                $hash = base64_encode($uzivatel->id . '-' . md5($this->parameters->salt . $uzivatel->zalozen));
                //\Tracy\Debugger::barDump($link);exit();
                $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());

                $link = $this->link('//SelfService:confirmEmail', $hash);

                if ($uzivatel->druzstvo == 1) {

                    $this->mailService->sendDruzstvoConfirmationRequest($uzivatel, $so, $link);
                    $this->mailService->sendDruzstvoConfirmationRequestCopy($uzivatel, $so);

                    $this->flashMessage('E-mail s žádostí o ověření emailu pro registraci do družstva byl odeslán.');
                } elseif ($uzivatel->spolek == 1) {

                    $this->mailService->sendSpolekConfirmationRequest($uzivatel, $so, $link);
                    $this->mailService->sendSpolekConfirmationRequestCopy($uzivatel, $so);

                    $this->flashMessage('E-mail s žádostí o potvrzení registrace do spolku byl odeslán.');
                }

                $this->redirect('Uzivatel:show', array('id' => $uzivatel->id));
            }
        }
    }

    public function actionExportAndSendRegForm(): void {
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

    public function actionHandleSubscriberContractPreview(): void {
        $user_id = $this->getParameter('id');
        $uzivatel =  $this->uzivatel->find($user_id);

        $pdfData = \App\Services\GeneratorSmlouvy::nahledUcastnickeSmlouvy($uzivatel);
        $this->sendResponse(new Nette\Application\Responses\CallbackResponse(function (Nette\Http\IRequest $request, Nette\Http\IResponse $response) use ($pdfData): void {
            $response->setContentType('application/pdf');
            $response->setHeader('Content-Disposition', 'inline; filename="contract-preview.pdf"');
            echo $pdfData;
        }));
    }

    public function actionHandleSubscriberContract(): void {
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

        $newId = $this->requestDruzstvoContract->execute($user_id);

        $this->flashMessage(sprintf('Nová smlouva číslo %u bude odeslána na e-mail %s.', $newId, $current_user->email));

        $this->redirect('Uzivatel:show', array('id' => $user_id));
    }

    public function actionSendUserEmail(): void {
        $uid = $this->getParameter('id');
        $variant = $this->getParameter('variant');
        $uzivatel =  $this->uzivatel->find($uid);

        // generate auth code and encrypt it to DB if not already generated
        if (!$uzivatel->oneclick_auth) {
            $code_length = 32;
            $oneclick_auth_code = substr(str_shuffle(str_repeat($x = '23456789abcdefghijmnopqrstuvwxyzABCDEFGHJMNPQRSTUVWXYZ', (int) ceil($code_length / strlen($x)))), 1, $code_length);
            $oneclick_auth_code_encrypted = $this->cryptosvc->encrypt($oneclick_auth_code);
            $this->uzivatel->update($uzivatel->id, [
                'oneclick_auth' => $oneclick_auth_code_encrypted,
            ]);
        } else {
            $oneclick_auth_code = $this->cryptosvc->decrypt($uzivatel->oneclick_auth);
            $this->uzivatel->update($uzivatel->id, ['oneclick_auth_used_at' => null]);
        }

        $this->template->UID = $uid;
        $this->template->oneclick_auth_code = $oneclick_auth_code;

        switch ($variant) {
            case 'Televize2026AnoNe':
                $template = $this->mailService->addLinkGeneratorToTemplate($this->template);
                $subject = "Služba SledovaniTV zdarma do 31.7.2026 v síti hkfree";
                $template->setFile(__DIR__ . '/../templates/email/Televize2026AnoNe.latte');
                $this->mailService->sendEmailFromTemplate($uzivatel, $subject, $template);

                $this->flashMessage(sprintf('E-mail %s odeslán na %s.', $variant, $uzivatel->email));
                break;
            default:
                $this->flashMessage(sprintf('Error: undefined UserEmail variant "%s", no action', $variant));
        }
        $this->redirect('Uzivatel:show', array('id' => $uid));
    }
}
