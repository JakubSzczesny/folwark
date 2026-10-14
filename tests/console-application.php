<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Console\Application;

return new Application(require __DIR__.'/phpstan-kernel.php');
