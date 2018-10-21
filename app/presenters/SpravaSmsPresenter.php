<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Nette\Application\UI\Form,
    Tracy\Debugger;

/**
 * Sprava presenter.
 */
class SpravaSmsPresenter extends SpravaPresenter
{
    private $spravceOblasti;

    function __construct(Model\SpravceOblasti $sob) {
        $this->spravceOblasti = $sob;
    }


    public function renderSms()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
    }

    protected function createComponentSmsForm() {
    	$form = new Form($this, "smsForm");
    	$form->addHidden('id');

        $form->addSelect('komu', 'Příjemce', array(0=>'SO',1=>'ZSO'))->setDefaultValue(0);
        $form->addTextArea('message', 'Text', 72, 10);

    	$form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'smsFormSucceded');

    	return $form;
    }

    public function smsFormSucceded($form, $values) {

        if($values->komu == 0)
        {
           $sos = $this->spravceOblasti->getSO();
        }
        else
        {
          $sos = $this->spravceOblasti->getZSO();
        }

        foreach($sos as $so)
        {
            $tl = $so->Uzivatel->telefon;
            if(!empty($tl) && $tl!='missing')
            {
                $validni[]=$tl;
            }
        }
        $tls = join(",",array_values($validni));

        $locale = 'cs_CZ.UTF-8';
        setlocale(LC_ALL, $locale);
        putenv('LC_ALL='.$locale);
        $command = escapeshellcmd('python /var/www/cgi/smsbackend.py -a https://aweg3.maternacz.com -l hkf'.$this->getUser()->getIdentity()->getId().'-'.$this->getUser()->getIdentity()->nick.':'.base64_decode($_SERVER['initials']).' -d '.$tls.' "'.$values->message.'"');
        $output = shell_exec($command);

        $this->flashMessage('SMS byly odeslány. Output: ' . $output);

    	$this->redirect('Sprava:nastroje', array('id'=>null));
    	return true;
    }
}