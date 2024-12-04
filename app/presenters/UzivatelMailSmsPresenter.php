<?php

namespace App\Presenters;

use App\Model,
    Nette\Application\UI\Form,
    Nette\Mail\Message,
    Nette\Utils\Validators,
    App\Components;
use App\Services\SmsSender;

/**
 * Uzivatel presenter.
 */
class UzivatelMailSmsPresenter extends UzivatelPresenter
{
    private $uzivatel;
    private $ap;

    /** @var Components\LogTableFactory @inject **/
    public $logTableFactory;

    private $smsSender;

    function __construct(Model\Uzivatel $uzivatel, Model\AP $ap, SmsSender $smsSender) {
    	$this->uzivatel = $uzivatel;
    	$this->ap = $ap;
    	$this->smsSender = $smsSender;
    }


    public function renderEmail()
    {
        $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->uzivatel->getUzivatel($this->getParameter('id'))->Ap_id, $this->getUser());
        $this->template->u = $this->uzivatel->getUzivatel($this->getParameter('id'));
    }

    protected function createComponentEmailForm() {
    	$form = new Form($this, "emailForm");
    	$form->addHidden('id');

        $user = $this->uzivatel->getUzivatel($this->getParameter('id'));
        $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());
        $form->addSelect('from', 'Odesílatel', array(0=>$so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>',1=>'oblast'.$user->Ap->Oblast_id.'@hkfree.org'))->setDefaultValue(0);

        $form->addText('email', 'Příjemce', 70)->setDisabled(TRUE);
        $form->addText('subject', 'Předmět', 70)->setRequired('Zadejte předmět');
        $form->addTextArea('message', 'Text', 72, 10);

    	$form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'emailFormSucceded');

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParameter('id') && !$submitujeSe) {

            if($user) {
                $form->setValues($user);
                $form->setDefaults(array(
                        'from' => 0,
                        'subject' => 'Zpráva od správce sítě hkfree.org',
                    ));
    	    }
    	}

    	return $form;
    }

    public function emailFormSucceded($form, $values) {
    	$idUzivatele = $values->id;

        $user = $this->uzivatel->getUzivatel($this->getParameter('id'));

        $mail = new Message;

        if($values->from == 0)
        {
           $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());
           $mail->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
            ->addTo($user->email)
            ->setSubject($values->subject)
            ->setBody($values->message);
        }
        else
        {
          $mail->setFrom('oblast'.$user->Ap->Oblast_id.'@hkfree.org')
            ->addTo($user->email)
            ->setSubject($values->subject)
            ->setBody($values->message);
        }


        $this->mailer->send($mail);

        $this->flashMessage('E-mail byl odeslán.');

    	$this->redirect('Uzivatel:show', array('id'=>$idUzivatele));
    	return true;
    }

    public function renderEmailall()
    {
        $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->getParameter('id'), $this->getUser());
        $this->template->ap = $this->ap->getAP($this->getParameter('id'));
    }

    protected function createComponentEmailallForm() {
    	$form = new Form($this, "emailallForm");
    	$form->addHidden('id');

        if($this->getParameter('id')) {
            $ap = $this->ap->getAP($this->getParameter('id'));
            $oblastMail='oblast'.$ap->Oblast_id.'@hkfree.org';
        }
        $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());
        $form->addSelect('from', 'Odesílatel', array(0=>$so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>',1=>$oblastMail))->setDefaultValue(0);

        $form->addTextArea('email', 'Příjemce', 72, 20)->setDisabled(TRUE);
        $form->addText('subject', 'Předmět', 70)->setRequired('Zadejte předmět');
        $form->addTextArea('message', 'Text', 72, 10);

    	$form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'emailallFormSucceded');

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParameter('id') && !$submitujeSe) {
            $emaily = $ap->related('Uzivatel.Ap_id')->where('TypClenstvi_id>1')->fetchPairs('id', 'email');
            foreach($emaily as $email)
            {
                if(Validators::isEmail($email)){
                    $validni[]=$email;
                }
            }
            $tolist = join(";",array_values($validni));

            if($ap) {
                $form->setValues($ap);
                $form->setDefaults(array(
                        'from' => 0,
                        'email' => $tolist,
                        'subject' => 'Zpráva od správce sítě hkfree.org',
                    ));
    	    }
    	}

    	return $form;
    }

    public function emailallFormSucceded($form, $values) {
    	$idUzivatele = $values->id;

        $ap = $this->ap->getAP($this->getParameter('id'));
        $emaily = $ap->related('Uzivatel.Ap_id')->where('TypClenstvi_id>1')->fetchPairs('id', 'email');


        $mail = new Message;
        if($values->from == 0)
        {
           $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());
           $mail->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
            ->setSubject($values->subject)
            ->setBody($values->message);
        }
        else
        {
          $mail->setFrom('oblast'.$ap->Oblast_id.'@hkfree.org')
            ->setSubject($values->subject)
            ->setBody($values->message);
        }


        //TODO: check if mail is valid
        foreach($emaily as $email)
        {
            if(Validators::isEmail($email)){
                $mail->addBcc($email);
            }
        }

        $this->mailer->send($mail);

        $this->flashMessage('E-mail byl odeslán.');

    	$this->redirect('UzivatelList:list', array('id'=>$this->getParameter('id')));
    	return true;
    }

    public function renderSms()
    {
        $user = $this->uzivatel->getUzivatel($this->getParameter('id'));
        $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($user->Ap_id, $this->getUser());
        $this->template->uziv = $this->uzivatel->getUzivatel($this->getParameter('id'));
    }

    protected function createComponentSmsForm() {
        $form = new Form($this, "smsForm");
    	$form->addHidden('id');

        $form->addText('komu', 'Příjemce', 20)->setDisabled(TRUE);
        $form->addTextArea('message', 'Text', 72, 10);

        $user = $this->uzivatel->getUzivatel($this->getParameter('id'));

        if(!empty($user->telefon) && $user->telefon!='missing')
        {
            $form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');
            $form->onSuccess[] = array($this, 'smsFormSucceded');
        }

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParameter('id') && !$submitujeSe) {

            if($user) {
                $form->setValues($user);
                $form->setDefaults(array(
                        'komu' => $user->telefon
                    ));
    	    }
    	}

    	return $form;
    }

    public function smsFormSucceded($form, $values) {
    	$user = $this->uzivatel->getUzivatel($this->getParameter('id'));

        $this->sendSMSAndValidate($this->getIdentity(), [ $user->telefon ], $values->message);

    	$this->redirect('Uzivatel:show', array('id'=>$this->getParameter('id')));
    	return true;
    }

    public function renderSmsall()
    {
        $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->getParameter('id'), $this->getUser());
        $this->template->ap = $this->ap->getAP($this->getParameter('id'));
    }

    protected function createComponentSmsallForm() {
    	$form = new Form($this, "smsallForm");
    	$form->addHidden('id');

        $form->addTextArea('komu', 'Příjemce', 72, 20)->setDisabled(TRUE);
        $form->addTextArea('message', 'Text', 72, 10);

    	$form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'smsallFormSucceded');

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParameter('id') && !$submitujeSe) {
            $ap = $this->ap->getAP($this->getParameter('id'));
            $telefony = $ap->related('Uzivatel.Ap_id')->where('TypClenstvi_id>1')->fetchPairs('id', 'telefon');
            foreach($telefony as $tl)
            {
                if(!empty($tl) && $tl!='missing')
                {
                    $validni[]=$tl;
                }
            }
            $tls = join(",",array_values($validni));

            if($ap) {
                $form->setValues($ap);
                $form->setDefaults(array(
                        'komu' => $tls
                    ));
    	    }
    	}

    	return $form;
    }

    public function smsallFormSucceded($form, $values) {
    	$ap = $this->ap->getAP($this->getParameter('id'));

        $telefony = $ap->related('Uzivatel.Ap_id')->where('TypClenstvi_id>1')->fetchPairs('id', 'telefon');

        $validni = [];
        foreach($telefony as $tl)
        {
            if(!empty($tl) && $tl!='missing')
            {
                $validni[]=$tl;
            }
        }

        $this->sendSMSAndValidate($this->getIdentity(), $validni, $values->message);

    	$this->redirect('UzivatelList:list', array('id'=>$this->getParameter('id')));
    	return true;
    }

    private function sendSMSAndValidate($ident, $numbers, $message) {
        $output = $this->smsSender->sendSms($ident, $numbers, $message);

        if($output["status"] == SmsSender::STATUS_OK) {
            $this->flashMessage('SMS byla odeslána. Output: ' . $output["msg"]);
        } else {
            $this->flashMessage('SMS nebyla odeslána. ' . $output["msg"], "danger");
        }
    }
}
