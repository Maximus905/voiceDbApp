<?php

namespace App\Migrations;

use T4\Orm\Migration;

class m_1496991537_alterCompanyOfficesTable
    extends Migration
{

    public function up()
    {
        $sql['company.offices'] = 'ALTER TABLE company."offices" ADD COLUMN "isPropertyBank"  BOOLEAN DEFAULT TRUE';

        // For main DB
        foreach ($sql as $key => $query) {
            if (true === $this->db->execute($query)) {
                echo 'Main DB: Table ' . $key . ' is altered' . PHP_EOL;
            }
        }
        // For test DB
        $this->setDb('phpUnitTest');
        foreach ($sql as $key => $query) {
            if (true === $this->db->execute($query)) {
                echo 'Test DB: Table ' . $key . ' is altered' . PHP_EOL;
            }
        }
    }

    public function down()
    {
        $sql['company.offices'] = 'ALTER TABLE company."offices" DROP COLUMN "isPropertyBank"';

        // For main DB
        foreach ($sql as $key => $query) {
            if (true === $this->db->execute($query)) {
                echo 'Main DB: Table ' . $key . ' is altered' . PHP_EOL;
            }
        }
        // For test DB
        $this->setDb('phpUnitTest');
        foreach ($sql as $key => $query) {
            if (true === $this->db->execute($query)) {
                echo 'Test DB: Table ' . $key . ' is altered' . PHP_EOL;
            }
        }
    }
}
