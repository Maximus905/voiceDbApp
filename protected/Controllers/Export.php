<?php

namespace App\Controllers;

use App\Models\Appliance;
use App\Models\DataPort;
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
        $types = ['router','switch','cmp','cms','ups','vg'];
        $appliances = (Appliance::findAll())->filter(
            function ($appliance) use ($types) {
                return in_array($appliance->type->type, $types);
            }
        );
        $spreadsheet = new Spreadsheet();

// ------ Worksheet - 'Appliances' ----------------------
        $spreadsheet->getActiveSheet()->setTitle('Appliances');

        // HEADER
        $spreadsheet->getActiveSheet()
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
            ->setCellValue('K1', 'Comment');

        // Format
        $columns = ['A','B','C','D','E','F','G','H','I','J','K'];
        foreach ($columns as $column) {
            $spreadsheet->getActiveSheet()->getColumnDimension($column)->setAutoSize(true);
        }
        $spreadsheet->getActiveSheet()->getStyle('A:K')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $spreadsheet->getActiveSheet()->getStyle('A1:K1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Body
        $n = 2;
        foreach ($appliances as $appliance) {
            $spreadsheet->getActiveSheet()
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
                ->setCellValue('K' . $n, $appliance->comment)
                ->getStyle('A' . $n . ':K' . $n)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            if (false === $appliance->inUse) {
                $spreadsheet->getActiveSheet()->getStyle('A' . $n . ':K' . $n)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_YELLOW);
            }

            $n++;
        }

        // Autofilter
        $spreadsheet->getActiveSheet()->setAutoFilter('B1:K' . ($n-1));
        $spreadsheet->getActiveSheet()->freezePane('A2');


// ------ Worksheet - 'Appliances with modules' ----------------------
        $objWorkSheet1 = $spreadsheet->createSheet(1);
        $objWorkSheet1->setTitle('Appliances with modules');

        // HEADER
        $objWorkSheet1
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

        // Format
        $columns = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N'];
        foreach ($columns as $column) {
            $objWorkSheet1->getColumnDimension($column)->setAutoSize(true);
        }
        $objWorkSheet1->getStyle('A:N')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $objWorkSheet1->getStyle('A1:N1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Body
        $n = 2;
        foreach ($appliances as $appliance) {
            if (0 < $appliance->modules->count()) {
                foreach ($appliance->modules as $module) {
                    $objWorkSheet1
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

                    if (false === $appliance->inUse) {
                        $objWorkSheet1->getStyle('A' . $n . ':N' . $n)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_YELLOW);
                    }
                    if (false === $module->inUse && false === $module->notFound) {
                        $objWorkSheet1->getStyle('K' . $n . ':N' . $n)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_YELLOW);
                    }
                    if (false === $module->inUse && true === $module->notFound) {
                        $objWorkSheet1->getStyle('K' . $n . ':N' . $n)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_RED);
                    }

                    $n++;
                }
            } else {
                $objWorkSheet1
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
                    ->getStyle('A' . $n . ':N' . $n)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                if (false === $appliance->inUse) {
                    $objWorkSheet1->getStyle('A' . $n . ':N' . $n)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_YELLOW);
                }

                $n++;
            }
        }

        // Autofilter
        $objWorkSheet1->setAutoFilter('B1:N' . ($n-1));
        $objWorkSheet1->freezePane('A2');


// ------ Worksheet - 'Phones' ----------------------
        $appliances = Appliance::findAllByType('phone');

        $objWorkSheet2 = $spreadsheet->createSheet(2);
        $objWorkSheet2->setTitle('Phones');

        // HEADER
        $sells = ['A1','B1','C1','D1','E1','F1','G1','H1','I1','J1','K1','L1','M1','N1','O1','P1','Q1','R1','S1','T1','U1','V1','W1','X1','Y1','Z1','AA1','AB1','AC1','AD1','AE1','AF1','AG1','AH1','AI1','AJ1','AK1','AL1'];

        $vals = ['№п/п', 'Регион', 'Офис', 'Publisher', 'Device', 'Name', 'IP', 'Partion', 'CSS', 'Prefix', 'DN', 'Status', 'Device ser', 'Software', 'Software ver.', 'Last update', 'Comment', 'Description', 'Device Pool', 'Alerting Name', 'Timezone', 'DHCP enable', 'DHCP server', 'Domain name', 'TFTP server 1', 'TFTP server 2', 'Default Router', 'DNS server 1', 'DNS server 2', 'Call manager 1', 'Call manager 2', 'Call manager 3', 'Call manager 4', 'VLAN ID', 'User locale', 'CDP neighbor device ID', 'CDP neighbor IP', 'CDP neighbor Port'];

        for ($i = 0; $i < count($sells); $i++) {
            $objWorkSheet2->setCellValue($sells[$i], $vals[$i]);
        }

        // Format
        $columns = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL'];
        foreach ($columns as $column) {
            $objWorkSheet2->getColumnDimension($column)->setAutoSize(true);
        }
        $objWorkSheet2->getStyle('A:AL')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $objWorkSheet2->getStyle('A1:AL1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Body
        $n = 2;
        foreach ($appliances as $appliance) {
            $objWorkSheet2
                ->setCellValue('A' . $n, $n-1) // '№п/п'
                ->setCellValue('B' . $n, $appliance->location->address->city->region->title) // Регион
                ->setCellValue('C' . $n, $appliance->location->title) // Офис
                ->setCellValue('D' . $n, '') // Publisher
                ->setCellValue('E' . $n, $appliance->phoneInfo->model) // Device
                ->setCellValue('F' . $n, $appliance->phoneInfo->name) // Name
                ->setCellValue('G' . $n, (false !== $appliance->managementIp) ? $appliance->managementIp : '') // IP
                ->setCellValue('H' . $n, $appliance->phoneInfo->partition) // Partion
                ->setCellValue('I' . $n, $appliance->phoneInfo->css) // CSS
                ->setCellValue('J' . $n, $appliance->phoneInfo->prefix) // Prefix
                ->setCellValue('K' . $n, $appliance->phoneInfo->phoneDN) // DN
                ->setCellValue('L' . $n, $appliance->phoneInfo->status) // Status
                ->setCellValue('M' . $n, $appliance->platform->serialNumber) // Device ser
                ->setCellValue('N' . $n, $appliance->software->software->title) // Software
                ->setCellValue('O' . $n, $appliance->software->version) // Software ver.
                ->setCellValue('P' . $n, (new \DateTime($appliance->lastUpdate))->format('d-m-Y')) // Last update
                ->setCellValue('Q' . $n, $appliance->comment) // Comment
                ->setCellValue('R' . $n, $appliance->phoneInfo->description) // Description
                ->setCellValue('S' . $n, $appliance->phoneInfo->devicePool) // Device Pool
                ->setCellValue('T' . $n, $appliance->phoneInfo->alertingName) // Alerting Name
                ->setCellValue('U' . $n, $appliance->phoneInfo->timezone) // Timezone
                ->setCellValue('V' . $n, $appliance->phoneInfo->dhcpEnabled) // DHCP enable
                ->setCellValue('W' . $n, $appliance->phoneInfo->dhcpServer) // DHCP server
                ->setCellValue('X' . $n, $appliance->phoneInfo->domainName) // Domain name
                ->setCellValue('Y' . $n, $appliance->phoneInfo->tftpServer1) // TFTP server 1
                ->setCellValue('Z' . $n, $appliance->phoneInfo->tftpServer2) // TFTP server 2
                ->setCellValue('AA' . $n, $appliance->phoneInfo->defaultRouter) // Default Router
                ->setCellValue('AB' . $n, $appliance->phoneInfo->dnsServer1) // DNS server 1
                ->setCellValue('AC' . $n, $appliance->phoneInfo->dnsServer2) // DNS server 2
                ->setCellValue('AD' . $n, $appliance->phoneInfo->callManager1) // 'Call manager 1
                ->setCellValue('AE' . $n, $appliance->phoneInfo->callManager2) // 'Call manager 2
                ->setCellValue('AF' . $n, $appliance->phoneInfo->callManager3) // 'Call manager 3
                ->setCellValue('AG' . $n, $appliance->phoneInfo->callManager4) // 'Call manager 4
                ->setCellValue('AH' . $n, $appliance->phoneInfo->vlanId) // VLAN ID
                ->setCellValue('AI' . $n, $appliance->phoneInfo->userLocale) // User locale
                ->setCellValue('AJ' . $n, $appliance->phoneInfo->cdpNeighborDeviceId) // CDP neighbor device ID
                ->setCellValue('AK' . $n, $appliance->phoneInfo->cdpNeighborIP) // CDP neighbor IP
                ->setCellValue('AL' . $n, $appliance->phoneInfo->cdpNeighborPort) // CDP neighbor Port
                ->getStyle('A' . $n . ':AL' . $n)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            if (false === $appliance->inUse) {
                $objWorkSheet2->getStyle('A' . $n . ':AL' . $n)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_YELLOW);
            }

            $n++;
        }

        // Autofilter
        $objWorkSheet2->setAutoFilter('B1:AL' . ($n-1));
        $objWorkSheet2->freezePane('A2');


// ------ Export ----------------------
        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $spreadsheet->setActiveSheetIndex(0);

        // Redirect output to a client’s web browser (Xlsx)
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

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');

        exit;
    }


    public function actionIpAppliances()
    {
        $switch = 'switch';
        $router = 'router';
        $vg = 'vg';
        $dataports = (DataPort::findAllByColumn('isManagement', true))->filter(
            function ($dataport) use ($router, $switch, $vg) {
                return $router == $dataport->appliance->type->type || $switch == $dataport->appliance->type->type || $vg == $dataport->appliance->type->type;
            }
        );

        // Semicolon format
        $outputData = '';
        foreach ($dataports as $dataport) {
            $outputData .= $dataport->appliance->details->hostname . ',' . preg_replace('~/.+~', '', $dataport->ipAddress) . ',' . $dataport->appliance->location->lotusId . ';';
        }
        echo $outputData;

        die;
    }

    public function actionIpCucm()
    {
        $cucm = 'cucm';

        $dataports = (DataPort::findAllByColumn('isManagement', true))->filter(
            function ($dataport) use ($cucm) {
                return $cucm == $dataport->appliance->type->type;
            }
        );

        // Semicolon format
        $outputData = '';
        foreach ($dataports as $dataport) {
            $outputData .= $dataport->appliance->details->hostname . ',' . preg_replace('~/.+~', '', $dataport->ipAddress) . ',' . $dataport->appliance->location->lotusId . ';';
        }
        echo $outputData;

        die;
    }
}
