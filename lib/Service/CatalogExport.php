<?php

declare(strict_types=1);

namespace Service;

use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Catalog\ProductTable;
use Bitrix\Catalog\PriceTable;

class CatalogExport
{
    public int $iblockId = 14;
    public string $targetUrl = 'http://147.45.154.73/test1.php';

    public function exportSection(): void
    {
        $rsSection = SectionTable::getList(array(
            'filter' => [
                'IBLOCK_ID' => $this->iblockId
            ],
            'select' => ['ID','IBLOCK_SECTION_ID','NAME', 'PICTURE'],
            'order' => ['IBLOCK_SECTION_ID' => 'DESC']
        ));

        $res = [];
        while ($arSection=$rsSection->fetch())
        {
            $res[] = $arSection;
        }

        $sectionSub = [];
        $sections = [];
        $res = [];
        foreach ($res as $section) {
            if (!empty($section['PICTURE'])) {
                $section['PICTURE_URL'] = \CFile::GetPath($section['PICTURE']);
                unset($section['PICTURE']);
            }

            if (!empty($sectionSub[$section['ID']])) {
                $section['SUB'] = $sectionSub[$section['ID']];
            }

            if (!empty($section['IBLOCK_SECTION_ID'])) {
                $sectionSub[$section['IBLOCK_SECTION_ID']][] = $section;
            } else {
                $sections[$section['ID']] = [
                    'NAME' => $section['NAME'],
                    'SUB' => $sectionSub[$section['ID']]
                ];
            }
        }

        $httpClient = new HttpClient();
        $httpClient->setHeader('Content-Type', 'application/json');
        $httpClient->post($this->targetUrl, json_encode($sections));

    }

    public function exportArchive(): void
    {
        $filePath = \Bitrix\Main\Application::getDocumentRoot() . '/local/archive.zip';
        $httpClient = new HttpClient();

        $httpClient->post($this->targetUrl,['zip_archive' => fopen($filePath, 'rb')],true);//вся магия в третьем параметре
    }

    public function exportCatalog(): void
    {
        if (!\CModule::IncludeModule("catalog")) {
            throw new \Exception('module catalog error');
        }

        $count = ProductTable::GetCount();

        $chunkSize = 2000;
        $page = 1;
        do {
            $res = ProductTable::GetList([
                'select' => [
                    'NAME' => 'IBLOCK_ELEMENT.NAME',
                    'PREVIEW_TEXT' => 'IBLOCK_ELEMENT.PREVIEW_TEXT',
                    'DETAIL_TEXT' => 'IBLOCK_ELEMENT.DETAIL_TEXT',
                    'PRICE_NUMBER' => 'PRICE.PRICE',
                    'SECTION_NAME' => 'IBLOCK_ELEMENT.IBLOCK_SECTION.NAME'
                ],
                'runtime' => [
                    'PRICE' => [
                        'data_type' => PriceTable::class,
                        'reference' => [
                            '=this.ID' => 'ref.PRODUCT_ID',
                        ]
                    ]
                ],
                'limit' => $chunkSize,
                'offset' => $chunkSize * ($page - 1)
            ]);

            $products = [];
            while ($product = $res->fetch()) {
                $products[] = $product;
            }
            $httpClient = new \Bitrix\Main\Web\HttpClient();
            $httpClient->setHeader('Content-Type', 'application/json');
            $httpClient->post($this->targetUrl, json_encode($products));

            $page++;
        } while ($page <= $count);
    }
}