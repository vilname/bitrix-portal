<?php

declare(strict_types=1);

namespace Service;

use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Catalog\ProductTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Main\Application;

class CatalogExport
{
    public int $iblockId = 14;
    public string $targetUrl = 'http://147.45.154.73/test1.php';

    public function exportSectionsByParentSectionId(int $parentSectionId)
    {
        if (!\CModule::IncludeModule("catalog")) {
            throw new \Exception('module catalog error');
        }

        $sections = $this->getSections($parentSectionId);

        $sectionsIds = array_map(function ($section) {
            return $section['ID'];
        }, $sections);

        $count = ProductTable::GetCount();

        $chunkSize = 2000;
        $offset = 0;
        do {
            $res = ProductTable::GetList([
                'select' => [
                    'NAME' => 'IBLOCK_ELEMENT.NAME',
                    'PREVIEW_TEXT' => 'IBLOCK_ELEMENT.PREVIEW_TEXT',
                    'DETAIL_TEXT' => 'IBLOCK_ELEMENT.DETAIL_TEXT',
                    'PREVIEW_PICTURE' => 'IBLOCK_ELEMENT.PREVIEW_PICTURE',
                    'PRICE_NUMBER' => 'PRICE.PRICE',
                    'SECTION_ID' => 'IBLOCK_ELEMENT.IBLOCK_SECTION_ID'
                ],
                'filter' => ['IBLOCK_ELEMENT.IBLOCK_SECTION_ID' => $sectionsIds],
                'runtime' => [
                    'PRICE' => [
                        'data_type' => PriceTable::class,
                        'reference' => [
                            '=this.ID' => 'ref.PRODUCT_ID',
                        ]
                    ]
                ],
                'limit' => $chunkSize,
                'offset' => $offset
            ]);

            $products = [];
            $images = [];
            while ($product = $res->fetch()) {

                if (!empty($product['PREVIEW_PICTURE'])) {
                    $filePath = \CFile::GetPath($product['PREVIEW_PICTURE']);
                    $filePath = Application::getDocumentRoot() . $filePath;

                    $images['PREVIEW_PICTURE__'.\CUtil::translit($product['NAME'], "ru")] = fopen($filePath, 'rb');
                }

                unset($product['PREVIEW_PICTURE']);

                $products[] = $product;

            }

            $this->sendDates([
                'SECTIONS' => $sections,
                'PRODUCTS' => $products
            ]);

            if (count($images)) {
                $this->sendFiles($images);
            }

            $offset += $chunkSize;
        } while ($offset <= $count);
    }

    private function getSections(int $sectionId): array
    {
        $parentSection = SectionTable::getList(array(
            'filter' => [
                'IBLOCK_ID' => $this->iblockId,
                'ID' => $sectionId
            ],
            'select' => ['ID','IBLOCK_SECTION_ID','NAME', 'PICTURE', 'LEFT_MARGIN', 'RIGHT_MARGIN', 'DEPTH_LEVEL'],
            'order' => ['IBLOCK_SECTION_ID' => 'DESC']
        ))->fetch();

        $rsSection = SectionTable::getList(array(
            'filter' => [
                'IBLOCK_ID' => $this->iblockId,
                '>LEFT_MARGIN' => $parentSection['LEFT_MARGIN'],
                '<RIGHT_MARGIN' => $parentSection['RIGHT_MARGIN'],
                '>DEPTH_LEVEL' => $parentSection['DEPTH_LEVEL'],
            ],
            'select' => ['ID','IBLOCK_SECTION_ID','NAME', 'LEFT_MARGIN', 'RIGHT_MARGIN', 'DEPTH_LEVEL'],
            'order' => ['IBLOCK_SECTION_ID' => 'ASC']
        ));


        $sections = $this->addSectionField([], $parentSection);

        while ($arSection=$rsSection->fetch()) {
            $sections = $this->addSectionField($sections, $arSection);
        }

        return $sections;
    }

    private function addSectionField(array $sections, array $dataSection): array
    {
        $section = [
            'ID' => $dataSection['ID'],
            'NAME' => $dataSection['NAME'],
            'DEPTH_LEVEL' => $dataSection['DEPTH_LEVEL'],
        ];

        $sections[$section['ID']] = $section;

        return $sections;
    }

    private function sendDates(array $data)
    {
        $httpClient = new HttpClient();
        $httpClient->setHeader('Content-Type', 'application/json');
        $httpClient->post($this->targetUrl, json_encode($data));
    }

    private function sendFiles(array $files)
    {
        $httpClient = new HttpClient();
        $httpClient->setHeader('Content-Type', 'multipart/form-data');

        $httpClient->post($this->targetUrl, $files, true);
    }
}