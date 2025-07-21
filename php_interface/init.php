<?php

declare(strict_types=1);

use \Bitrix\Main\Loader;

Loader::registerAutoLoadClasses($module = null, [
    'Service\\CatalogExport' => '/local/lib/Service/CatalogExport.php',
]);
