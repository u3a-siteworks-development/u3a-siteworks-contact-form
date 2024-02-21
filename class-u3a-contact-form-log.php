<?php
class U3aContactFormLog
{
    global $wpdb;

    /**
     * The table_name
     *
     * @var string 
     */
    public static $table_name = $wpdb->prefix . 'u3a_cf_log';

    /**
     * Set up the actions and filters used by this class.
     *
     * @param $plugin_file the value of __FILE__ from the main plugin file 
     */
    public static function initialise($plugin_file)
    {
        // Add the Contact Form Log to the dashboard
        add_action('admin_menu', array(self::class, 'log_page'));
        // Hook: function to process the form in render_log()
        add_action('admin_post_u3a_cf_log', array(self::class, 'save_display_params'));
        
        // create the database table if it has not yet been created.
        $table_exists = get_option('u3a_cf_log_table', false);
        if (!table_exists) {
            self::create_table();
            update_option('u3a_cf_log_table', '1');
        }
    }

    /**
     * Creates the db table of u3a_cf_log.
     *
     * Note: field blocked = 'n' if not blocked,
     *       other single char values can describe reason for block.
     *       field copy_to_user ='y'/'n'
     */

    public static function create_table()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = self::$table_name;
        $sql = "CREATE TABLE $table_name (
            id INT(11) NOT NULL AUTO_INCREMENT,
            to_name VARCHAR(100) NOT NULL,
            to_email VARCHAR(100) NOT NULL,
            reply_name VARCHAR(100) NOT NULL,
            reply_email VARCHAR(100) NOT NULL,
            subject VARCHAR(100) NOT NULL,
            blocked CHAR(1) NOT NULL,
            copy_to_user CHAR(1) NOT NULL,
            time_sent BIGINT(13),
            PRIMARY KEY  (id),
            KEY (to_email)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Deletes the db table u3a_cf
     */
    public static function delete_table()
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", self::$table_name));
    }

    /**
     * Adds a record to the log.
     *
     * @return int the id of the record
     */
    public static function log_message($to_name, $to_email, $reply_name, $reply_email, $subject, $blocked='n', $copy_to_user='n')
    {
        global $wpdb;
        $wpdb->insert( 
            self::$table_name, 
            array( 
                'to_name' => $to_name,
                'to_email' => $to_email,
                'reply_name' => $reply_name,
                'reply_email' => $reply_email,
                'subject' => substr($subject,0,100),
                'blocked' => $blocked,
                'copy_to_user' => $copy_to_user,
                'time_sent' => time(), 
            ), 
            array( 
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
            )
        );
        return $wpdb->insert_id;
    }

    /**
     * Counts number of messages.
     *
     * @param  int $days number of days to go back
     * @param  str $which 'all' for all records, 'blocked' for blocked records only.
     * @return int number of matching records
     */
    public static function get_count($days, $which='all')
    {
        global $wpdb;
        $time_limit = time() - $days*86400;
        $query = "SELECT COUNT(*) FROM %i WHERE sent_time >= %d ";
        if ('blocked' == $which) {
            $query .= " AND blocked != 'n' ";
        }
        $row = $wpdb->get_row($wpdb->prepare($query, self::$table_name, $time_limit));
        var_dump $row;
        return 999; // not really
    }

    /**
     * Gets a list of messages.
     *
     * @param  int $days    number of days to go back
     * @param  str $which   'all' for all records,
     *                      or 'blocked' for blocked records only,
     *                      or the to_email address of the selected messages
     * @param  int $limit   max number of records to return
     * @param  int $offset  number of initial records to ignore  
     * @return array        a numbered array of matching records
     */
    public static function get_list($days, $which='all', $limit=50, $offset=0)
    {
        global $wpdb;
        $params = [$limit, $offset];
        $time_limit = time() - $days*86400;
        $params[] = $time_limit;
        $query = "SELECT * FROM %i ORDER BY time_sent DESC LIMIT %s OFFSET %s WHERE sent_time >= %d ";
        if ('all' != $which) {
            if ('blocked' == $which) {
                $query .= " AND blocked != 'n' ";
            } else {
                $query .= " AND to_email = %s ";
                $params[] = $which; // append an extra param for prepare function
            }
        }
        $results = $wpdb->get_results($wpdb->prepare($query, self::$table_name, $params));
        var_dump $results[0];
        return $results;
    }

    /**
     * Delete all messages that were sent more than $days ago.
     */
    public static function clear_old_messages($days=90)
    {
        global $wpdb;
        $stale = time() - $days*86400;
        return $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE sent_time < %d", self::$table_name, $stale));
    }

    /**
     * Add page for showing log.
     *
     */
    public static function log_page()
    {
        add_menu_page(
            'u3a Contact Form Log',
            'u3a Contact Form Log',
            'manage_options',
            'u3a-contact-form-log',
            array(self::class, 'render_log'),
            'dashicons-email',
            30
        );
    }

    /**
     * Output the log summary and details.
     *
     */
    public static function render_log()
    {
        print '<h2>Contact form log</h2>';

        $mode = isset($_GET['mode']) ? sanitize_text_field($_GET['mode']) : 'summary';
        if ('list' == $mode) {
            $params = get_transient('u3a_cf_log');
            if (false === $params) {
                //oh dear, better try again somehow.
            } else {
                list($filter, $per_page) = explode(",", $params);
                print display_list($filter, $per_page);
            }
            exit(); //is this right or just return?
        }

        // else treat as 'summary' mode

        // get summary data
        $days = 30;
        $num_msgs = get_count($days, 'all');
        $num_blocked = get_count($days, 'blocked');

        // print summary data
        print <<<END
        <p> There were $num_msgs messages sent via u3a-contact-form in the last $days days.
        <br>
        This includes $num_blocked messages which were blocked as spam.</p>
        END;

        $nonce_code =  wp_nonce_field('u3a_cf_log', 'u3a_nonce', true, false);
        $submit_button = get_submit_button('Save Settings');
        print <<<END
        <form method="POST" action="admin-post.php">
        <input type="hidden" name="action" value="u3a_cf_log">
        $nonce_code
        $u3aMQDetect
        <label for="filter">Filter messages: </label>
        <select name="filter" id="filter" required>
          <option value="all">All</option>
          <option value="blocked">Blocked only/option>
          <option value="email">Specific recipient</option>
        </select>
        <div id="email-choice">
            <label for="to_email">Recipient email address: </label>
            <input type="email" name="to_email" id="to_email"/>
        </div>
        <label for="per_page">Messages per page: </label>
        <select name="per_page" id="per_page" required>
          <option value=25 selected>25</option>
          <option value=50>50/option>
          <option value=100>100</option>
        </select>
        $submit_button
        </form>
XXX        TBD need some javascript here
        END;
        exit(); //is this right or just return?
    }

    /**
     * Saves the display params in a transient.
     *
     */
    public static function save_display_params()
    {
        // check nonce
        if (check_admin_referer('u3a_cf_log', 'u3a_nonce') == false) wp_die('Invalid form submission');
        // check for WP magic quotes  DONT NEED THIS HERE
        $u3aMQDetect = $_POST['u3aMQDetect'];
        $needStripSlashes = (strlen($u3aMQDetect) > 5) ? true : false; // backslash added to apostrophe in test string?

        $filter = empty($_POST['filter']) ? 'all' : sanitize_text_field($_POST['filter']);;
        if ('email' == $filter) {
            $to_email = empty($_POST['to_email']) ? 'no_email' : sanitize_email($_POST['to_email']);
            $filter = $to_email;
        }
        $per_page = empty($_POST['per_page']) ? '25' : sanitize_text_field($_POST['per_page']);;
        set_transient('u3a_cf_log', $filter . "," . $per_page, 10*60);

        // redirect back to log page (list mode)
        wp_safe_redirect(admin_url('admin.php?page=u3a-contact-form-log&mode=list'));
        exit();
    }

    /**
     * Display a log list.
     *
     */
    public static function display_list($filter, $per_page) {
        $HTML = <<<END
        <p> list TBD here</p>
        END;
        return $HTML;

    }

}
