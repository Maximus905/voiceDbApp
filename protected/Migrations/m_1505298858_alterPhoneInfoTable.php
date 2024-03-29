<?php

namespace App\Migrations;

use T4\Orm\Migration;

class m_1505298858_alterPhoneInfoTable
    extends Migration
{

    public function up()
    {
        $sql['alter_table_equipment.phoneInfo_add_column'] = '
            ALTER TABLE equipment."phoneInfo"
                ADD COLUMN "unknownLocation" BOOLEAN NOT NULL DEFAULT FALSE';

        // For main DB
        foreach ($sql as $key => $query) {
            if (true === $this->db->execute($query)) {
                echo 'Main DB: ' . $key . ' - OK' . PHP_EOL;
            }
        }
        // For test DB
        $this->setDb('phpUnitTest');
        foreach ($sql as $key => $query) {
            if (true === $this->db->execute($query)) {
                echo 'Test DB: ' . $key . ' - OK' . PHP_EOL;
            }
        }
    }


    public function down()
    {
        $sql['alter_table_equipment.phoneInfo_drop_column'] = '
            ALTER TABLE equipment."phoneInfo"
                DROP COLUMN "unknownLocation"';

        // For main DB
        foreach ($sql as $key => $query) {
            if (true === $this->db->execute($query)) {
                echo 'Main DB: ' . $key . ' - OK' . PHP_EOL;
            }
        }
        // For test DB
        $this->setDb('phpUnitTest');
        foreach ($sql as $key => $query) {
            if (true === $this->db->execute($query)) {
                echo 'Test DB: ' . $key . ' - OK' . PHP_EOL;
            }
        }
    }
    
}
