<?php

// Fixture classes in tests/Fixtures are loaded on first use through the autoload-dev
// classmap in composer.json, not required here: several fixtures extend
// Pux\Controller\Controller, which still implements the removed PHPSGI\App interface until
// Stage 1, and an eager require would abort the whole run in the bootstrap.
require __DIR__ . '/../vendor/autoload.php';
