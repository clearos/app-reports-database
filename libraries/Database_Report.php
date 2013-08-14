<?php

/**
 * Database report base class.
 *
 * @category   apps
 * @package    reports-database
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2012-2013 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/reports_database/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// N A M E S P A C E
///////////////////////////////////////////////////////////////////////////////

namespace clearos\apps\reports_database;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('reports_database');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Classes
//--------

use \clearos\apps\base\Configuration_File as Configuration_File;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Folder as Folder;
use \clearos\apps\base\Shell as Shell;
use \clearos\apps\reports\Report_Engine as Report_Engine;

clearos_load_library('base/Configuration_File');
clearos_load_library('base/File');
clearos_load_library('base/Folder');
clearos_load_library('base/Shell');
clearos_load_library('reports/Report_Engine');

// Exceptions
//-----------

use \clearos\apps\base\Engine_Exception as Engine_Exception;

clearos_load_library('base/Engine_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Database report base class.
 *
 * @category   apps
 * @package    reports-database
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2012-2013 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/reports_database/
 */

class Database_Report extends Report_Engine
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const DB_HOST = '127.0.0.1';
    const DB_PORT = '3308';
    const DB_USER = 'reports';
    const DB_NAME = 'reports';

    const DEFAULT_CACHE_TIME = 300; // seconds
    const DEFAULT_RECORDS = 400;

    const FILE_CONFIG_DB = '/var/clearos/system_database/reports';
    const PATH_CACHE = '/var/clearos/reports_database/cache';
    const COMMAND_INIT_TABLES = '/usr/sbin/initialize-report-tables';

    ///////////////////////////////////////////////////////////////////////////////
    // V A R I A B L E S
    ///////////////////////////////////////////////////////////////////////////////

    protected $db_handle = NULL;
    protected $timestamp_field = 'timestamp';

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Database report constructor.
     */

    public function __construct($options = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (isset($options['timestamp_field']))
            $this->timestamp_field = $options['timestamp_field'];
    }

    /**
     * Returns reports database password.
     *
     * @return string reports database password
     * @throws Engine_Exception
     */

    public function get_password()
    {
        clearos_profile(__METHOD__, __LINE__);

        $file = new Configuration_File(self::FILE_CONFIG_DB, 'explode', '=', 2);
        $db_config = $file->load();

        return $db_config['password'];
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Creates a temporary table.
     *
     * @param string $app     app name
     * @param string $sql     SQL
     * @param array  $options $options array
     *
     * @return void
     */

    protected function _create_temporary_table($app, $sql, $options = array())
    {
        clearos_profile(__METHOD__, __LINE__);

        // Add data range SQL
        //-------------------

        if (empty($options['range'])) {
            $sql_range = '';
        } else if ($options['range'] === Report_Engine::RANGE_TODAY) {
            $date = date('Y-m-d');
            $sql_range = " AND date(" . $this->timestamp_field . ") = '$date'";
        } else if ($options['range'] === Report_Engine::RANGE_YESTERDAY) {
            $date = date("Y-m-d", time() - 86400);
            $sql_range = " AND date(" . $this->timestamp_field . ") = '$date'";
        } else if ($options['range'] === Report_Engine::RANGE_LAST_7_DAYS) {
            $date = date("Y-m-d", time() - (7*86400));
            $sql_range = " AND date(" . $this->timestamp_field . ") >= '$date'";
        } else if ($options['range'] === Report_Engine::RANGE_LAST_30_DAYS) {
            $date = date("Y-m-d", time() - (30*86400));
            $sql_range = " AND date(" . $this->timestamp_field . ") >= '$date'";
        } else {
            $sql_range = '';
        }

        // Create table
        //--------------

        $this->_get_db_handle();

        $group_by = (!empty($sql['group_by'])) ? 'GROUP BY ' . $sql['group_by'] : '';
        $order_by = (!empty($sql['order_by'])) ? 'ORDER BY ' . $sql['order_by'] : '';

        $full_sql = 'CREATE TEMPORARY TABLE ' . $sql['table'] . ' ' .
            'SELECT ' . $sql['select'] . ' ' .
            'FROM ' . $sql['from'] . ' ' .
            'WHERE (' .  $sql['where'] . ') ' . $sql_range . ' ' .
            $group_by . ' ' .
            $order_by . ';';

        // clearos_log('reports_database', $full_sql);

        // Run query
        //----------

        try {
            $dbs = $this->db_handle->prepare($full_sql);
            $dbs->execute();
        } catch(\PDOException $e) {  
            throw new Engine_Exception($e->getMessage());
        }
    }

    /**
     * Creates a temporary table.
     *
     * @return void
     */

    protected function _get_db_handle()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! is_null($this->db_handle))
            return;

        // Get database configuration
        //---------------------------

        $file = new Configuration_File(self::FILE_CONFIG_DB, 'explode', '=', 2);
        $db_config = $file->load();

        // Get a connection
        //-----------------

        try {
            $this->db_handle = new \PDO(
                'mysql:host=' . self::DB_HOST . ';port=' . self::DB_PORT . ';dbname=' . self::DB_NAME,
                self::DB_USER,
                $db_config['password']
            );
        } catch(\PDOException $e) {  
            throw new Engine_Exception($e->getMessage());
        }
    }

    /**
     * Initializes database.
     *
     * @param string $app_name app name
     *
     * @return void
     */

    protected function _initialize_tables($app_name, $test_table)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Check initialization
        //---------------------

        try {
            $this->_get_db_handle();

            $dbs = $this->db_handle->prepare("SELECT * FROM $test_table LIMIT 1");
            $dbs->execute();
            $rows = array();

            while ($row = $dbs->fetch())
                $rows[] = $row;

            if (! empty($rows))
                return;
        } catch(\PDOException $e) {  
            clearos_log('reports_database', 'waiting to initialize report: ' . $app_name);
            return;
        }

        // Run init script
        //----------------

        $options['validate_exit_code'] = FALSE;

        $shell = new Shell();
        $retval = $shell->execute(self::COMMAND_INIT_TABLES, $app_name, TRUE, $options);
    }

    /**
     * Runs database insert.
     *
     * @param string $app app identifier
     * @param string $sql SQL information
     *
     * @return array table rows
     */

    protected function _run_insert($app, $sql)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Generate SQL
        //-------------

        $full_sql = 'INSERT INTO ' . $sql['insert'] . ' VALUES (' . $sql['values'] . ');';

        // Get database handle
        //--------------------

        $this->_get_db_handle();

        // Run query
        //----------

        try {
            $dbs = $this->db_handle->prepare($full_sql);
            $dbs->execute();
        } catch(\PDOException $e) {  
            throw new Engine_Exception($e->getMessage());
        }
    }

    /**
     * Runs database query.
     *
     * Options
     * - date_range
     * - records (default: 200)
     * - cache_time (default: 120 seconds)
     *
     * @param string $app     app identifier
     * @param string $sql     SQL information
     * @param array  $options options
     *
     * @return array table rows
     */

    protected function _run_query($app, $sql, $options = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Add data range SQL
        //-------------------

        if (empty($options['range'])) {
            $sql_range = '';
        } else if ($options['range'] === Report_Engine::RANGE_TODAY) {
            $date = date('Y-m-d');
            $sql_range = " AND date(" . $this->timestamp_field . ") = '$date'";
        } else if ($options['range'] === Report_Engine::RANGE_YESTERDAY) {
            $date = date("Y-m-d", time() - 86400);
            $sql_range = " AND date(" . $this->timestamp_field . ") = '$date'";
        } else if ($options['range'] === Report_Engine::RANGE_LAST_7_DAYS) {
            $date = date("Y-m-d", time() - (7*86400));
            $sql_range = " AND date(" . $this->timestamp_field . ") >= '$date'";
        } else if ($options['range'] === Report_Engine::RANGE_LAST_30_DAYS) {
            $date = date("Y-m-d", time() - (30*86400));
            $sql_range = " AND date(" . $this->timestamp_field . ") >= '$date'";
        } else {
            $sql_range = '';
        }

        // Add record limit SQL
        //---------------------

        $limit = (empty($records)) ? 'LIMIT ' . self::DEFAULT_RECORDS : " LIMIT $records";

        // Generate SQL statement
        // - SQL queries using a timeline use a simplified format
        // - Non-timeline queries use a mostly explicit SQL statement
        //-----------------------------------------------------------

        if (empty($sql['timeline_select'])) {
            $select = 'SELECT ' . $sql['select'] . ' FROM ' . $sql['from'];

            if (! empty($sql['where']))
                $where = 'WHERE (' .  $sql['where'] . ') ' . $sql_range;
            else if (! empty($sql['joins']))
                $where = ' ' . $sql['joins'] . ' ';

            $group_by = (!empty($sql['group_by'])) ? 'GROUP BY ' . $sql['group_by'] : '';
            $order_by = (!empty($sql['order_by'])) ? 'ORDER BY ' . $sql['order_by'] : '';
        } else {
            // For timeline queries, an app developer passes in the table columns
            // and table names... that's it.  The SQL is generated from that information.
            //
            // The sample SQL is shown using the following input parameters (Proxy Report)
            //
            // $sql['timeline_select'] = array('load_1min', 'load_5min', 'load_15min');
            // $sql['timeline_from'] = 'resource';

            $select_lines = '';

            // Grab all raw data if less than 24 hours.
            //
            // SELECT load_1min, load_5min, load_15min, timestamp 
            // FROM resource 
            // WHERE timestamp is NOT NULL AND date(timestamp) = '...' 
            // ORDER BY timestamp DESC
            //-------------------------------------------------------------------------

            if (isset($options['range']) && (($options['range'] === Report_Engine::RANGE_TODAY) || ($options['range'] === Report_Engine::RANGE_YESTERDAY))) {

                foreach ($sql['timeline_select'] as $selected_entry)
                    $select_lines .= " $selected_entry,";

                $select = 'SELECT ' . $select_lines . ' DATE_FORMAT(' . $this->timestamp_field . ', \'%Y-%m-%d %H:%i\') as ' . $this->timestamp_field . ' FROM ' . $sql['timeline_from'];

                if (! empty($sql['timeline_where']))
                    $where = 'WHERE (' .  $sql['timeline_where'] . ') ' . $sql_range;
                else
                    $where = 'WHERE ' . $this->timestamp_field . ' is NOT NULL ' . $sql_range;

                $group_by = '';
                $order_by = 'ORDER BY ' . $this->timestamp_field . ' DESC';

                // Grab the average over an hour for 7-day data.
                //
                // SELECT AVG(load_1min) as load_1min, AVG(load_5min) as load_5min, AVG(load_15min) as load_15min, MIN(timestamp) as timestamp
                // FROM resource
                // WHERE timestamp is NOT NULL
                // GROUP BY DATE(timestamp), HOUR(timestamp)
                // ORDER BY timestamp DESC 
                //-------------------------------------------------------------------------

            } else if (isset($options['range']) && ($options['range'] === Report_Engine::RANGE_LAST_7_DAYS)) {
                foreach ($sql['timeline_select'] as $selected_entry)
                    $select_lines .= " AVG($selected_entry) as $selected_entry,";

                $select = 'SELECT ' . $select_lines . ' DATE_FORMAT(MIN(' . $this->timestamp_field . '), \'%Y-%m-%d %H:%i\') as ' . $this->timestamp_field . ' FROM ' . $sql['timeline_from'];

                if (! empty($sql['timeline_where']))
                    $where = 'WHERE (' .  $sql['timeline_where'] . ') ' . $sql_range;
                else
                    $where = 'WHERE ' . $this->timestamp_field . ' is NOT NULL ' . $sql_range;

                $group_by = 'GROUP BY DATE(' . $this->timestamp_field . '), HOUR(' . $this->timestamp_field . ')';
                $order_by = 'ORDER BY ' . $this->timestamp_field . ' DESC';

                // Grab the average over a full day for 30 days or more.
                //
                // SELECT AVG(load_1min) as load_1min, AVG(load_5min) as load_5min, AVG(load_15min) as load_15min, MIN(timestamp) as timestamp
                // FROM resource
                // WHERE timestamp is NOT NULL
                // GROUP BY DATE(timestamp) 
                // ORDER BY timestamp DESC 
                //-------------------------------------------------------------------------

            } else {
                foreach ($sql['timeline_select'] as $selected_entry)
                    $select_lines .= " AVG($selected_entry) as $selected_entry,";

                $select = 'SELECT ' . $select_lines . ' DATE(MIN(' . $this->timestamp_field . ')) as ' . $this->timestamp_field . ' FROM ' . $sql['timeline_from'];
                if (! empty($sql['timeline_where']))
                    $where = 'WHERE (' .  $sql['timeline_where'] . ') ' . $sql_range;
                else
                    $where = 'WHERE ' . $this->timestamp_field . ' is NOT NULL ' . $sql_range;

                $group_by = 'GROUP BY DATE(' . $this->timestamp_field . ')';
                $order_by = 'ORDER BY ' . $this->timestamp_field .' DESC';
            }
        }

        $full_sql = $select . ' ' .  $where . ' ' .  $group_by . ' ' .  $order_by . ' ' .  $limit . ';';

        // clearos_log('reports_database', $full_sql);

        // Check cache
        //------------

        clearstatcache();

        $cache_id = isset($options['cache_id']) ? $options['cache_id'] : '';
        $cache_time = isset($options['cache_time']) ? $options['cache_time'] : self::DEFAULT_CACHE_TIME;
        $cache_pathname = self::PATH_CACHE . '/' . $app;
        $cache_folder = new Folder($cache_pathname);

        if (! $cache_folder->exists())
            $cache_folder->create('root', 'root', '0755');

        $cache_filename = $cache_pathname . '/' . md5($full_sql . $sql_range . $cache_id);
        $cache = new File($cache_filename);

        if ($cache->exists()) {
            $stat = stat($cache_filename);

            if ((time() - $stat['ctime']) <= $cache_time) {
                return unserialize($cache->get_contents());
            } else {
                $cache->delete();
            }
        }

        // Get database handle
        //--------------------

        $this->_get_db_handle();

        // Run query
        //----------

        try {
            $dbs = $this->db_handle->prepare($full_sql);
            $dbs->execute();
            $rows = array();

            while ($row = $dbs->fetch())
                $rows[] = $row;
        } catch(\PDOException $e) {  
            throw new Engine_Exception($e->getMessage());
        }

        // Handle cache
        //-------------

        if (! $cache->exists()) {
            $cache->create('root', 'root', '0600');
            $cache->add_lines(serialize($rows));
        }

        return $rows;
    }

    /**
     * Runs database update
     *
     * @param string $app app identifier
     * @param string $sql SQL information
     *
     * @return array table rows
     */

    protected function _run_update($app, $sql)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Generate SQL
        //-------------

        $full_sql = 'UPDATE ' . $sql['table'] . ' SET ' . $sql['set'] . ' WHERE ' . $sql['where'];

        // Get database handle
        //--------------------

        $this->_get_db_handle();

        // Run query
        //----------

        try {
            $dbs = $this->db_handle->prepare($full_sql);
            $dbs->execute();
        } catch(\PDOException $e) {  
            throw new Engine_Exception($e->getMessage());
        }
    }
}
