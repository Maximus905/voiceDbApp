<?php

namespace App\Migrations;

use T4\Orm\Migration;

class m_1548671305_moveForeignHdsTableToHdsDailyAgentsDnStatisticsTable
    extends Migration
{

    public function up()
    {
        $view = 'view.hds_stats_by_yesterday_phones';

        $sql['drop_old_view__HdsStatsByYesterdayPhones'] = 'DROP VIEW IF EXISTS '.$view;
        $sql['drop_table__hds.foreign_hds'] = 'DROP TABLE hds.foreign_hds';

        $sql['create_table__hds.hds_daily_agents_dn_statistics'] = '
            CREATE TABLE hds.hds_daily_agents_dn_statistics (
                prefix citext,
                dn citext,
                "day_of_statistics" TIMESTAMP
            )
        ';

        $sql['create_view__HdsStatsByYesterdayPhones'] =
            'CREATE VIEW '.$view.' AS
                WITH
                  phones AS (
                    WITH
                      "hdsPhones" AS (
                        WITH
                          hds AS (
                            WITH
                              "phoneInfo" AS (
                              WITH "appliance" AS (SELECT __id, "lastUpdate" FROM equipment.appliances)
                              SELECT prefix, "phoneDN", "lastUpdate"
                              FROM equipment."phoneInfo"
                              LEFT JOIN appliance ON "phoneInfo".__appliance_id = appliance.__id
                            )
                            SELECT hds.prefix, hds.dn, max("phoneInfo"."lastUpdate") AS "lastUpdate"
                            FROM hds.hds_daily_agents_dn_statistics AS hds
                            JOIN "phoneInfo" ON hds.prefix = "phoneInfo".prefix AND hds.dn = "phoneInfo"."phoneDN" AND (((date_part(\'epoch\' :: TEXT, age(now(), "phoneInfo"."lastUpdate")) / (3600) :: DOUBLE PRECISION)) :: INTEGER) < 73
                            GROUP BY hds.prefix, hds.dn
                          ),
                          phone AS (
                            WITH appliance AS (SELECT __id, "lastUpdate" FROM equipment.appliances)
                            SELECT prefix, "phoneDN" AS dn, __appliance_id, "lastUpdate"
                            FROM equipment."phoneInfo"
                            JOIN appliance ON "phoneInfo".__appliance_id = appliance.__id
                          )
                        SELECT hds.prefix, __appliance_id
                        FROM hds
                        JOIN phone ON hds.prefix = phone.prefix AND hds.dn = phone.dn AND hds."lastUpdate" = phone."lastUpdate"
                      ),
                
                      appliance AS (
                        WITH
                          "platformItems" AS (
                            WITH platform AS (SELECT __id, title, "isHW" FROM equipment.platforms)
                            SELECT
                              "platformItems".__id,
                              platform.__id AS "platformId",
                              platform.title AS "platformTitle",
                              platform."isHW"
                            FROM equipment."platformItems"
                            LEFT JOIN platform ON "platformItems".__platform_id = platform.__id
                          ),
                          "applianceType" AS (SELECT * FROM equipment."applianceTypes")
                        SELECT
                          appliances.__id,
                          appliances.__location_id,
                          appliances."lastUpdate",
                          "platformItems"."platformId",
                          "platformItems"."platformTitle",
                          "platformItems"."isHW",
                          "applianceType".type
                        FROM equipment.appliances
                        LEFT JOIN "platformItems" ON appliances.__platform_item_id = "platformItems".__id
                        LEFT JOIN "applianceType" ON appliances.__type_id = "applianceType".__id
                      ),
                
                      office AS (
                        WITH
                          address AS (
                            WITH
                              city AS (
                                WITH region AS (SELECT * FROM geolocation.regions)
                                SELECT
                                  region.__id AS "regioinId",
                                  region.title AS "regionTitle",
                                  city.__id,
                                  city.title
                                FROM geolocation.cities AS city
                                LEFT JOIN region ON city.__region_id = region.__id
                              )
                            SELECT
                              address.__id,
                              city.__id AS "regioinId",
                              city.title AS "regionTitle",
                              city.__id AS "cityId",
                              city.title AS "cityTitle"
                            FROM geolocation.addresses AS address
                            LEFT JOIN city ON address.__city_id = city.__id
                          ),
                
                          "lotusData" AS (SELECT lotus_id, employees FROM view.lotus_db_data),
                
                          "hwPhonesActive" AS (
                            SELECT
                              appliance.__location_id,
                              count(*) FILTER (WHERE (((date_part(\'epoch\' :: TEXT, age(now(), appliance."lastUpdate")) / (3600) :: DOUBLE PRECISION)) :: INTEGER) < 73) AS "hwPhonesActive"
                            FROM appliance WHERE appliance.type = \'phone\' AND appliance."isHW" IS TRUE
                            GROUP BY appliance.__location_id
                          )
                
                        SELECT
                          office.__id,
                          office."lotusId",
                          office.title AS "officeTitle",
                          address."regioinId",
                          address."regionTitle",
                          address."cityId",
                          address."cityTitle",
                          "lotusData".employees,
                          "hwPhonesActive"."hwPhonesActive"
                        FROM company.offices AS office
                        LEFT JOIN address ON office.__address_id = address.__id
                        LEFT JOIN "lotusData" ON "lotusData".lotus_id = office."lotusId"
                        LEFT JOIN "hwPhonesActive" ON office.__id = "hwPhonesActive".__location_id
                      )
                
                    SELECT
                      "hdsPhone".__appliance_id AS "applianceId",
                      (\'Prefix \' || "hdsPhone".prefix) AS prefix,
                      appliance."platformId",
                      appliance."platformTitle",
                      office."__id" AS "officeId",
                      office."lotusId",
                      office."officeTitle",
                      office."regioinId",
                      office."regionTitle",
                      office."cityId",
                      office."cityTitle",
                      office.employees,
                      office."hwPhonesActive"
                    FROM "hdsPhones" AS "hdsPhone"
                    LEFT JOIN appliance ON "hdsPhone".__appliance_id = appliance.__id
                    LEFT JOIN office ON appliance.__location_id = office.__id
                  )
                
                SELECT * FROM "phones"
            ';

        // For main DB
        foreach ($sql as $key => $query) {
            if (true === $this->db->execute($query)) {
                echo 'Main DB: ' . $key . ' - OK' . PHP_EOL;
            }
        }
    }

    public function down()
    {
        $view = 'view.hds_stats_by_yesterday_phones';

        $sql['drop_old_view__HdsStatsByYesterdayPhones'] = 'DROP VIEW IF EXISTS '.$view;
        $sql['drop_table__hds.hds_daily_agents_dn_statistics'] = 'DROP TABLE hds.hds_daily_agents_dn_statistics';

        $sql['create_table__hds.foreign_hds'] = '
            CREATE TABLE hds.foreign_hds (
                prefix citext,
                dn citext,
                "lastUpdate" TIMESTAMP
            )
        ';

        $sql['create_view__HdsStatsByYesterdayPhones'] =
            'CREATE VIEW '.$view.' AS
                WITH
                  phones AS (
                    WITH
                      "hdsPhones" AS (
                        WITH
                          hds AS (
                            WITH
                              "phoneInfo" AS (
                              WITH "appliance" AS (SELECT __id, "lastUpdate" FROM equipment.appliances)
                              SELECT prefix, "phoneDN", "lastUpdate"
                              FROM equipment."phoneInfo"
                              LEFT JOIN appliance ON "phoneInfo".__appliance_id = appliance.__id
                            )
                            SELECT hds.prefix, hds.dn, max("phoneInfo"."lastUpdate") AS "lastUpdate"
                            FROM hds.foreign_hds AS hds
                            JOIN "phoneInfo" ON hds.prefix = "phoneInfo".prefix AND hds.dn = "phoneInfo"."phoneDN" AND (((date_part(\'epoch\' :: TEXT, age(now(), "phoneInfo"."lastUpdate")) / (3600) :: DOUBLE PRECISION)) :: INTEGER) < 73
                            GROUP BY hds.prefix, hds.dn
                          ),
                          phone AS (
                            WITH appliance AS (SELECT __id, "lastUpdate" FROM equipment.appliances)
                            SELECT prefix, "phoneDN" AS dn, __appliance_id, "lastUpdate"
                            FROM equipment."phoneInfo"
                            JOIN appliance ON "phoneInfo".__appliance_id = appliance.__id
                          )
                        SELECT hds.prefix, __appliance_id
                        FROM hds
                        JOIN phone ON hds.prefix = phone.prefix AND hds.dn = phone.dn AND hds."lastUpdate" = phone."lastUpdate"
                      ),
                
                      appliance AS (
                        WITH
                          "platformItems" AS (
                            WITH platform AS (SELECT __id, title, "isHW" FROM equipment.platforms)
                            SELECT
                              "platformItems".__id,
                              platform.__id AS "platformId",
                              platform.title AS "platformTitle",
                              platform."isHW"
                            FROM equipment."platformItems"
                            LEFT JOIN platform ON "platformItems".__platform_id = platform.__id
                          ),
                          "applianceType" AS (SELECT * FROM equipment."applianceTypes")
                        SELECT
                          appliances.__id,
                          appliances.__location_id,
                          appliances."lastUpdate",
                          "platformItems"."platformId",
                          "platformItems"."platformTitle",
                          "platformItems"."isHW",
                          "applianceType".type
                        FROM equipment.appliances
                        LEFT JOIN "platformItems" ON appliances.__platform_item_id = "platformItems".__id
                        LEFT JOIN "applianceType" ON appliances.__type_id = "applianceType".__id
                      ),
                
                      office AS (
                        WITH
                          address AS (
                            WITH
                              city AS (
                                WITH region AS (SELECT * FROM geolocation.regions)
                                SELECT
                                  region.__id AS "regioinId",
                                  region.title AS "regionTitle",
                                  city.__id,
                                  city.title
                                FROM geolocation.cities AS city
                                LEFT JOIN region ON city.__region_id = region.__id
                              )
                            SELECT
                              address.__id,
                              city.__id AS "regioinId",
                              city.title AS "regionTitle",
                              city.__id AS "cityId",
                              city.title AS "cityTitle"
                            FROM geolocation.addresses AS address
                            LEFT JOIN city ON address.__city_id = city.__id
                          ),
                
                          "lotusData" AS (SELECT lotus_id, employees FROM view.lotus_db_data),
                
                          "hwPhonesActive" AS (
                            SELECT
                              appliance.__location_id,
                              count(*) FILTER (WHERE (((date_part(\'epoch\' :: TEXT, age(now(), appliance."lastUpdate")) / (3600) :: DOUBLE PRECISION)) :: INTEGER) < 73) AS "hwPhonesActive"
                            FROM appliance WHERE appliance.type = \'phone\' AND appliance."isHW" IS TRUE
                            GROUP BY appliance.__location_id
                          )
                
                        SELECT
                          office.__id,
                          office."lotusId",
                          office.title AS "officeTitle",
                          address."regioinId",
                          address."regionTitle",
                          address."cityId",
                          address."cityTitle",
                          "lotusData".employees,
                          "hwPhonesActive"."hwPhonesActive"
                        FROM company.offices AS office
                        LEFT JOIN address ON office.__address_id = address.__id
                        LEFT JOIN "lotusData" ON "lotusData".lotus_id = office."lotusId"
                        LEFT JOIN "hwPhonesActive" ON office.__id = "hwPhonesActive".__location_id
                      )
                
                    SELECT
                      "hdsPhone".__appliance_id AS "applianceId",
                      (\'Prefix \' || "hdsPhone".prefix) AS prefix,
                      appliance."platformId",
                      appliance."platformTitle",
                      office."__id" AS "officeId",
                      office."lotusId",
                      office."officeTitle",
                      office."regioinId",
                      office."regionTitle",
                      office."cityId",
                      office."cityTitle",
                      office.employees,
                      office."hwPhonesActive"
                    FROM "hdsPhones" AS "hdsPhone"
                    LEFT JOIN appliance ON "hdsPhone".__appliance_id = appliance.__id
                    LEFT JOIN office ON appliance.__location_id = office.__id
                  )
                
                SELECT * FROM "phones"
            ';

        // For main DB
        foreach ($sql as $key => $query) {
            if (true === $this->db->execute($query)) {
                echo 'Main DB: ' . $key . ' - OK' . PHP_EOL;
            }
        }
    }
    
}
