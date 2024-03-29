<?php

namespace App\Migrations;

use T4\Orm\Migration;

class m_1548671857_createUpdateHdsWeeklyStatsFunctions
    extends Migration
{

    public function up()
    {
        $sql['create_function__hds.create_hds_weekly_stats_by_prefixes'] = '
            CREATE OR REPLACE FUNCTION hds.create_hds_weekly_stats_by_prefixes() RETURNS VOID AS $$
            DECLARE
              view_name citext := \'view.hds_weekly_stats_by_prefixes\';
              prefix citext;
              prefix_columns citext := \'\';
            
            BEGIN
              FOR prefix IN
                SELECT DISTINCT t1.prefix FROM hds.hds_weekly_agents_dn_statistics t1 ORDER BY 1
              LOOP
                prefix_columns := prefix_columns || \', "Prefix \' || prefix || \'" CITEXT\';
              END LOOP;
            
              -- If DB is empty, then Return
              IF char_length(prefix_columns) < 1 THEN
                RETURN ;
              END IF;
            
              -- Drop old view if exists
              EXECUTE \'DROP VIEW IF EXISTS \' || view_name;
            
              -- Create new view
              EXECUTE \'
                CREATE VIEW \' || view_name || \' AS
                  SELECT * FROM crosstab(\'\'
                    SELECT
                      cast(to_char(min(date), \'\'\'\'YYYY-MM-DD\'\'\'\') AS citext) AS date,
                      prefix,
                      count(dn) AS amount_dn
                    FROM hds.hds_weekly_agents_dn_statistics
                    GROUP BY year, week, prefix
                    ORDER BY year, week, prefix
                \'\', \'\'
                  SELECT DISTINCT prefix FROM hds.hds_weekly_agents_dn_statistics ORDER BY 1
                \'\') AS ct(date citext \' || prefix_columns || \')
              \';
            
              RETURN;
            END;
            $$ LANGUAGE plpgsql
        ';

        $sql['create_function__hds.create_hds_weekly_stats_by_offices_on_prefix'] = '
            CREATE OR REPLACE FUNCTION hds.create_hds_weekly_stats_by_offices_on_prefix(prefix_val citext) RETURNS VOID AS $$
            DECLARE
              view_name citext := \'view.hds_weekly_stats_by_offices_on_prefix_\' || prefix_val;
              prefix_columns citext := \'\';
              r RECORD;
            
            BEGIN
              FOR r IN
                SELECT DISTINCT stats."lotusId",  offices.title
                FROM hds.hds_weekly_agents_dn_statistics AS stats
                LEFT JOIN company.offices ON stats."lotusId" = offices."lotusId"
                WHERE stats.prefix = prefix_val
                ORDER BY stats."lotusId"
              LOOP
                prefix_columns := prefix_columns || \', "\' || prefix_val || \'_\' || r.title || \'" CITEXT\';
              END LOOP;
            
              -- Drop old view if exists
              EXECUTE \'DROP VIEW IF EXISTS \' || view_name;
            
              -- Create new view
              EXECUTE \'
                CREATE VIEW \' || view_name || \' AS
                  SELECT * FROM crosstab(\'\'
                    SELECT
                      cast(to_char(min(date), \'\'\'\'YYYY-MM-DD\'\'\'\') AS citext) AS date,
                      "lotusId",
                      count(dn) AS amount_dn
                    FROM hds.hds_weekly_agents_dn_statistics
                    WHERE cast(prefix AS INTEGER) = \' || prefix_val || \'
                    GROUP BY year, week, "lotusId"
                    ORDER BY date, "lotusId"
                \'\', \'\'
                  SELECT DISTINCT "lotusId" FROM hds.hds_weekly_agents_dn_statistics WHERE cast(prefix AS INTEGER) = \' || prefix_val || \' ORDER BY 1
                \'\') AS ct(date citext \' || prefix_columns || \')
              \';
            
              RETURN;
            END;
            $$ LANGUAGE plpgsql
        ';

        $sql['create_function__hds.update_hds_weekly_stats'] = '
            CREATE OR REPLACE FUNCTION hds.update_hds_weekly_stats() RETURNS VOID AS $$
            DECLARE
              prefix citext;
            
            BEGIN
              -- Update view.hds_weekly_stats_by_prefixes
              PERFORM hds.create_hds_weekly_stats_by_prefixes();
            
              -- Update view.hds_weekly_stats_by_offices_on_prefix
              FOR prefix IN
                SELECT DISTINCT t1.prefix FROM hds.hds_weekly_agents_dn_statistics t1 ORDER BY 1
              LOOP
                PERFORM hds.create_hds_weekly_stats_by_offices_on_prefix(prefix);
              END LOOP;
            
              RETURN;
            END;
            $$ LANGUAGE plpgsql
        ';

        $sql['create_function__hds.drop_hds_weekly_stats_views'] = '
            CREATE OR REPLACE FUNCTION hds.drop_hds_weekly_stats_views() RETURNS VOID AS $$
            DECLARE
              prefix citext;
              view_by_prefixes_name citext := \'view.hds_weekly_stats_by_prefixes\';
              view_by_offices_on_prefix_name citext := \'view.hds_weekly_stats_by_offices_on_prefix_\';
            
            BEGIN
              -- Drop view.hds_weekly_stats_by_prefixes
              EXECUTE \'DROP VIEW IF EXISTS \' || view_by_prefixes_name;
            
              -- Drop views.hds_weekly_stats_by_offices_on_prefix
              FOR prefix IN
              SELECT DISTINCT t1.prefix FROM hds.hds_weekly_agents_dn_statistics t1 ORDER BY 1
              LOOP
                EXECUTE \'DROP VIEW IF EXISTS \' || view_by_offices_on_prefix_name || prefix;
              END LOOP;
            
              RETURN;
            END;
            $$ LANGUAGE plpgsql
        ';

        $sql['run_function__hds.update_hds_weekly_stats'] = 'SELECT hds.update_hds_weekly_stats()';

        // For main DB
        foreach ($sql as $key => $query) {
            if (true === $this->db->execute($query)) {
                echo 'Main DB: ' . $key . ' - OK' . PHP_EOL;
            }
        }
    }

    public function down()
    {

        $sql['run_function__hds.drop_hds_weekly_stats_views'] = 'SELECT hds.drop_hds_weekly_stats_views()';
        $sql['drop_function__hds.drop_hds_weekly_stats_views'] = 'DROP FUNCTION IF EXISTS hds.drop_hds_weekly_stats_views()';
        $sql['drop_function__hds.update_hds_weekly_stats'] = 'DROP FUNCTION IF EXISTS hds.update_hds_weekly_stats()';
        $sql['drop_function__hds.create_hds_weekly_stats_by_offices_on_prefix'] = 'DROP FUNCTION IF EXISTS hds.create_hds_weekly_stats_by_offices_on_prefix(citext)';
        $sql['drop_function__hds.create_hds_weekly_stats_by_prefixes'] = 'DROP FUNCTION IF EXISTS hds.create_hds_weekly_stats_by_prefixes()';

        // For main DB
        foreach ($sql as $key => $query) {
            if (true === $this->db->execute($query)) {
                echo 'Main DB: ' . $key . ' - OK' . PHP_EOL;
            }
        }
    }
    
}
