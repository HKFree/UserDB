<?php

$finder = (new PhpCsFixer\Finder())
  ->in(__DIR__)
  ->exclude(['temp'])
;

return (new PhpCsFixer\Config())
  ->setRules([
    '@PSR12' => true,
    "single_space_around_construct" => true,
    "control_structure_braces" => true,
    "control_structure_continuation_position" => true,
    "declare_parentheses" => true,
    "no_multiple_statements_per_line" => true,
    'braces_position' => [
      'functions_opening_brace' => 'same_line'
    ],
    "statement_indentation" => true,
    "no_extra_blank_lines" => true
  ])
  ->setFinder($finder)
;
