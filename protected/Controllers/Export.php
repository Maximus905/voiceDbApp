<?php

namespace App\Controllers;

use App\Models\Appliance;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use T4\Mvc\Controller;

class Export extends Controller
{
    public function actionHardInvExcel()
    {
        $spreadsheet = new Spreadsheet();

        // HEADER
        $spreadsheet->setActiveSheetIndex(0)
            ->setCellValue('A1', '№п/п')
            ->setCellValue('B1', 'Регион')
            ->setCellValue('C1', 'Офис')
            ->setCellValue('D1', 'Hostname')
            ->setCellValue('E1', 'Type')
            ->setCellValue('F1', 'Device')
            ->setCellValue('G1', 'Device ser')
            ->setCellValue('H1', 'Software')
            ->setCellValue('I1', 'Software ver.')
            ->setCellValue('J1', 'Appl. last update')
            ->setCellValue('K1', 'Module')
            ->setCellValue('L1', 'Module ser.')
            ->setCellValue('M1', 'Module last update')
            ->setCellValue('N1', 'Comment');

        $appliances = Appliance::findAll();

        // Format
        $columns = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N'];
        foreach ($columns as $column) {
            $spreadsheet->getActiveSheet()->getColumnDimension($column)->setAutoSize(true);
        }
        $spreadsheet->getActiveSheet()->getStyle('A:N')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $spreadsheet->getActiveSheet()->getStyle('A1:N1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Body
        $n = 2;
        foreach ($appliances as $appliance) {
            foreach ($appliance->modules as $module) {
                $spreadsheet->setActiveSheetIndex(0)
                    ->setCellValue('A' . $n, $n-1)
                    ->setCellValue('B' . $n, $appliance->location->address->city->region->title)
                    ->setCellValue('C' . $n, $appliance->location->title)
                    ->setCellValue('D' . $n, $appliance->details->hostname)
                    ->setCellValue('E' . $n, $appliance->type)
                    ->setCellValue('F' . $n, $appliance->platform->platform->vendor->title . ' ' .$appliance->platform->platform->title)
                    ->setCellValue('G' . $n, $appliance->platform->serialNumber)
                    ->setCellValue('H' . $n, $appliance->software->software->title)
                    ->setCellValue('I' . $n, $appliance->software->version)
                    ->setCellValue('J' . $n, (new \DateTime($appliance->lastUpdate))->format('d-m-Y'))
                    ->setCellValue('K' . $n, $module->module->title)
                    ->setCellValue('L' . $n, $module->serialNumber)
                    ->setCellValue('M' . $n, (new \DateTime($module->lastUpdate))->format('d-m-Y'))
                    ->setCellValue('N' . $n, $module->comment)
                    ->getStyle('A' . $n . ':N' . $n)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                if (false === $module->inUse && false === $module->notFound) {
                    $spreadsheet->getActiveSheet()->getStyle('K' . $n . ':M' . $n)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_YELLOW);
                }
                if (false === $module->inUse && true === $module->notFound) {
                    $spreadsheet->getActiveSheet()->getStyle('K' . $n . ':M' . $n)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_RED);
                }

                $n++;
            }
        }

        // Rename worksheet
        $spreadsheet->getActiveSheet()->setTitle('Appliances');

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $spreadsheet->setActiveSheetIndex(0);

        // Redirect output to a client’s web browser (Excel2007)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Hard inventory__'. gmdate('d M Y') . '.xlsx"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $writer = IOFactory::createWriter($spreadsheet, 'Excel2007');
        $writer->save('php://output');
        exit;
    }
}
