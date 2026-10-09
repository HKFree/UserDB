<?php

namespace App\MemberModule\Presenters;

use App\Presenters\PushNastaveniTrait;

class NotifikacePresenter extends BasePresenter
{
    use PushNastaveniTrait;

    private const PUBLIKUM = 'clenove';
}
