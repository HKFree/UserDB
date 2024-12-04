<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
;

return (new PhpCsFixer\Config())
    ->setRules([
	    '@PSR12' => true,
        'braces' => ['position_after_functions_and_oop_constructs' => 'same']
    ])
    ->setFinder($finder)
;
