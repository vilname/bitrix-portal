<?php

declare(strict_types=1);

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

$input = file_get_contents('php://input');

//echo "<pre>";
//print_r(1233331);
//echo "</pre>";
file_put_contents($_SERVER['DOCUMENT_ROOT'].'/upload.txt', print_r($input, true), FILE_APPEND);
//file_put_contents($_SERVER['DOCUMENT_ROOT'].'/upload1.txt', print_r($_POST, true), FILE_APPEND);

echo json_encode([]);
