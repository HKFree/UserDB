<?php

namespace App\Model;

use Nette;

class MyMacroSet extends Nette\Latte\Macros\MacroSet
{
    public static function install(\Latte\Compiler $compiler)
    {
        $set = new Nette\Latte\Macros\MacroSet($compiler);
        $set->addMacro('ifphp', 'if (%node.args):', 'endif');
    }
}