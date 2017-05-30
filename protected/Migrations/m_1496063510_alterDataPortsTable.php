<?php

namespace App\Migrations;

use T4\Orm\Migration;

class m_1496063510_alterDataPortsTable
    extends Migration
{

    public function up()
    {
        $sql['equipment.dataPorts'] = 'ALTER TABLE equipment."dataPorts" ADD COLUMN "isManagement"  BOOLEAN DEFAULT FALSE';

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
        $sql['equipment.dataPorts'] = 'ALTER TABLE equipment."dataPorts" DROP COLUMN "isManagement"';

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
