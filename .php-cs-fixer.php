<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('.Build')
    ->exclude('Resources')
    ->exclude('public')
    ->exclude('Documentation')
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
return $config->setFinder($finder)
    ->setRules([
        '@Symfony' => true,
        'phpdoc_align' => false,
        'no_superfluous_phpdoc_tags' => false,
//        'single_line_comment_spacing' => false,
    ])
    ->setLineEnding("\n")
;
