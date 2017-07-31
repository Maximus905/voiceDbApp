<?php
namespace App\Migrations;

use T4\Orm\Migration;

class m_1501491115_alterPhoneInfoTable
    extends Migration
{

    public function up()
    {
        $sql['alter_table_equipment.phoneInfo_add_columns'] = '
            ALTER TABLE equipment."phoneInfo"
                ADD COLUMN timezone VARCHAR(100),
                ADD COLUMN "dhcpEnabled" BOOLEAN,
                ADD COLUMN "dhcpServer" INET,
                ADD COLUMN "domainName" VARCHAR(100),
                ADD COLUMN "tftpServer1" INET,
                ADD COLUMN "tftpServer2" INET,
                ADD COLUMN "defaultRouter" INET,
                ADD COLUMN "dnsServer1" INET,
                ADD COLUMN "dnsServer2" INET,
                ADD COLUMN "callManager1" VARCHAR(100),
                ADD COLUMN "callManager2" VARCHAR(100),
                ADD COLUMN "callManager3" VARCHAR(100),
                ADD COLUMN "callManager4" VARCHAR(100),
                ADD COLUMN "vlanId" INTEGER,
                ADD COLUMN "userLocale" VARCHAR(200),
                ADD COLUMN "cdpNeighborDeviceId" VARCHAR(200),
                ADD COLUMN "cdpNeighborIP" INET,
                ADD COLUMN "cdpNeighborPort" VARCHAR(100)';

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
        $sql['alter_table_equipment.phoneInfo_drop_columns'] = '
            ALTER TABLE equipment."phoneInfo"
                DROP COLUMN timezone,
                DROP COLUMN "dhcpEnabled",
                DROP COLUMN "dhcpServer",
                DROP COLUMN "domainName",
                DROP COLUMN "tftpServer1",
                DROP COLUMN "tftpServer2",
                DROP COLUMN "defaultRouter",
                DROP COLUMN "dnsServer1",
                DROP COLUMN "dnsServer2",
                DROP COLUMN "callManager1",
                DROP COLUMN "callManager2",
                DROP COLUMN "callManager3",
                DROP COLUMN "callManager4",
                DROP COLUMN "vlanId",
                DROP COLUMN "userLocale",
                DROP COLUMN "cdpNeighborDeviceId",
                DROP COLUMN "cdpNeighborIP",
                DROP COLUMN "cdpNeighborPort"';

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
