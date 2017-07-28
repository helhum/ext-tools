<?php
return [
    'controllers' => [
        \Helhum\ExtTools\Command\ComposerJsonCommandController::class,
    ],
    'runLevels' => [
        'helhum/ext-tools:composerjson:*' => \Helhum\Typo3Console\Core\Booting\RunLevel::LEVEL_COMPILE,
    ],
    'bootingSteps' => [
    ],
];
