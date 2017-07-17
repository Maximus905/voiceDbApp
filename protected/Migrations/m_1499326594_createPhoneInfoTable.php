<?php
namespace App\Migrations;

use T4\Orm\Migration;

class m_1499326594_createPhoneInfoTable
    extends Migration
{

    public function up()
    {
        $sql['create_table_equipment.phoneInfo'] = 'CREATE TABLE equipment."phoneInfo" (
            __id SERIAL,
            __appliance_id BIGINT NOT NULL,
            "name" VARCHAR(30) NOT NULL,
            "type" VARCHAR(100),
            "macAddress" MACADDR,
            "prefix" INTEGER,
            "phoneDN" INTEGER,
            "status" VARCHAR(20),
            "description" TEXT,
            "css" VARCHAR(200),
            "devicePool" VARCHAR(200),
            "alertingName" VARCHAR(200),
            "partition" VARCHAR(200),
            PRIMARY KEY (__id),
            CONSTRAINT fk_phone_id FOREIGN KEY (__appliance_id)
            REFERENCES equipment."appliances" (__id)
            ON UPDATE CASCADE 
            ON DELETE RESTRICT
        )';

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
        $sql['drop_table_equipment.phoneInfo'] = 'DROP TABLE equipment."phoneInfo"';

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
