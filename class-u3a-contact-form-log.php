<?php
class U3aContactFormLog
{
    /**
     * The table_name
     *
     * @var string 
     */
    public static $table_name;

    /**
     * Set up the actions and filters used by this class.
     *
     * @param $plugin_file the value of __FILE__ from the main plugin file 
     */
    public static function initialise($plugin_file)
    {
        // set the table name
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'u3a_cf_log';
        // Add the Contact Form Log to the dashboard
        add_action('admin_menu', array(self::class, 'log_page'));
        // Hook: function to process the form in render_log()
        add_action('admin_post_u3a_cf_log', array(self::class, 'save_display_params'));
        
        // create the database table if it has not yet been created.
        $table_exists = get_option('u3a_cf_log_table', false);
        if (!$table_exists) {
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
        $query = "SELECT COUNT(*) as total FROM %i WHERE time_sent >= %d ";
        if ('blocked' == $which) {
            $query .= " AND blocked != 'n' ";
        }
        $row = $wpdb->get_row($wpdb->prepare($query, self::$table_name, $time_limit));
        $count = (null !== $row) ? $row->total : 0;
        return $count;
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
    public static function get_list($days, $which='all', $limit=25, $offset=0)
    {
        global $wpdb;
        $time_limit = time() - $days*86400;
        $params = [self::$table_name, $time_limit, $limit, $offset];
        $querywhere = "SELECT * FROM %i WHERE time_sent >= %d ";
        $orderby = " ORDER BY time_sent DESC LIMIT %d OFFSET %d";
        if ('all' != $which) {
            if ('blocked' == $which) {
                $querywhere .= " AND blocked != 'n' ";
            } else {
                $querywhere .= " AND to_email = %s";
                array_splice($params, 2, 0,$which); // add an extra param after $time_limit
            }
        }
        $results = $wpdb->get_results($wpdb->prepare($querywhere . $orderby, $params));
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
     * Add admin page for showing log.
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
     * Outputs either the log summary and form to enter request for details, or list of details.
     *
     * Arguments passed as query parameters
     *      mode: 'list' or assumed to be 'summary' in list mode further parameters
     *      filter: 'all', 'blocked' or an addressee email (required)
     *      per-page: number of records to display per page
     *      page_num: which page of records to display 
     */
    public static function render_log()
    {
        print '<div class="wrap">';
        print '<h2>Contact form log</h2>';
        $mode = isset($_GET['mode']) ? sanitize_text_field($_GET['mode']) : 'summary';
        if ('list' == $mode) {
            $filter = isset($_GET['filter']) ? sanitize_text_field(urldecode($_GET['filter'])) : '';
            // see select options in form in 'summary' mode for valid values
            $filter_plain_values = ['all', 'blocked'];
            if (!in_array($filter, $filter_plain_values) && !filter_var($filter, FILTER_VALIDATE_EMAIL)) {
                print 'In list mode. <br>Missing or invalid filter parameter';
                print '</div>'; //end class 'wrap'
                return;
            }
            $per_page = isset($_GET['per_page']) ? (int)($_GET['per_page']) : 25;
            // see select options in form in 'summary' mode for valid values
            $per_page_values = [25, 50, 100];
            if (!in_array($per_page, $per_page_values)) {
                print 'In list mode. <br>Invalid per_page parameter';
                print '</div>'; //end class 'wrap'
                return;
            }
            // (int) gives zero if not numeric input, so $page_num will default to 0 for non numeric input value.
            $page_num = isset($_GET['page_num']) ? (int)($_GET['page_num']) : 0;
            $page_num = ($page_num > 0) ? $page_num : 1; // change default to 1
            print self::display_list($filter, $per_page, $page_num);
            print '</div>'; //end class 'wrap'
            return;
        }

        // else treat as 'summary' mode

        // get summary data
        $days = 30;
        $num_msgs = self::get_count($days, 'all');
        $num_blocked = self::get_count($days, 'blocked');

        // print summary data
        print <<<END
        <p> There were $num_msgs messages sent via u3a-contact-form in the last $days days.
        <br>
        This includes $num_blocked messages blocked as spam.</p>
        END;

        $nonce_code =  wp_nonce_field('u3a_cf_log', 'u3a_cf_nonce', true, false);
        $submit_button = get_submit_button('Show selected messages','primary large','submit', true);
        print <<<END
        <form method="POST" action="admin-post.php">
        <input type="hidden" name="action" value="u3a_cf_log">
        $nonce_code
        <p>You can filter which message you want to see.</p>
        <table><tr><td>
        <label for="filter">Filter messages: </label>
            <select name="filter" id="filter" onchange="u3a_filter_change()" required>
              <option value="all">All</option>
              <option value="blocked">Blocked only</option>
              <option value="email">Specific recipient</option>
            </select>
        </td>
        <td>
        <label for="per_page">Messages per page: </label>
            <select name="per_page" id="per_page" required>
              <option value=25 selected="selected">25</option>
              <option value=50>50</option>
              <option value=100>100</option>
            </select>
        </td></tr>
        <tr id="email_choice" style="display:none;">
        <td>
            Which recipient email address do you want to filter on?<br>
            <label for="to_email">Recipient email address: </label>
            <input type="email" name="to_email" id="to_email" placeholder="Enter the addressee email"/>
            </td>
        </tr>
        </table>
        </table>
        $submit_button
        </form>
        <script>
            function u3a_filter_change() {
                var email_choice = document.getElementById("email_choice");
                var to_email = document.getElementById("to_email");
                if ("email" == document.getElementById("filter").value) {
                    email_choice.style.display = "block";
                    to_email.required = true;
                } else {
                    email_choice.style.display = "none";
                    to_email.required = false;

                }
            }
        </script>
        END;
        return;
    }

    /**
     * Saves the display params as query string and then redirects.
     *
     * Called when form with action 'u3a_cf_log' is submitted.
     */
    public static function save_display_params()
    {
        // check nonce
        if (check_admin_referer('u3a_cf_log', 'u3a_cf_nonce') == false) wp_die('Invalid form submission');

        $filter = empty($_POST['filter']) ? 'all' : sanitize_text_field($_POST['filter']);;
        if ('email' == $filter) {
            $to_email = empty($_POST['to_email']) ? 'no_email' : sanitize_email($_POST['to_email']);
            $filter = $to_email;
        }
        $per_page = empty($_POST['per_page']) ? '25' : sanitize_text_field($_POST['per_page']);;

        // redirect back to log page (list mode)
        $filter = urlencode($filter);
        $params = "&mode=list&per_page=$per_page&filter=$filter";
        wp_safe_redirect(admin_url('admin.php?page=u3a-contact-form-log' . $params));
        exit();
    }

    /**
     * Display a list of selected records from the message log.
     *
     * @param str   $filter: 'all', 'blocked' or an addressee email
     * @param int   $per-page: number of records to display per page
     * @param int   $page_num: which page of records to display
     *
     * @return str  $HTML 
     */
    public static function display_list($filter, $per_page=25, $page_num=1) {
        $HTML = "<p> Showing results with filter = $filter.<br>Page: $page_num</p>";
        $days = 100;  // assumes that database record more than three months old have been deleted
        $offset = $per_page * ($page_num - 1);
        $email_list = self::get_list($days, $filter, $per_page, $offset);
        // format time_sent attributes like '2024-02-14 18:30:23'
        foreach ($email_list as $row) {
            $row->time_sent = date('Y-m-d H:i:s', $row->time_sent);
        }
        $count = (!empty($email_list)) ? count($email_list) : 0;
        $start = $offset + 1;
        $end = $offset + $count;
        if ($count) {
            $HTML .= "<p> Records $start to $end</p>" . self::array_of_objects_to_HTML_table($email_list);
        } else {
            $HTML .= "No matching records";
        }
        if ($count == $per_page) { // there may be more records
            $next_page = $page_num + 1;
            $params = "&mode=list&per_page=$per_page&filter=$filter&page_num=$next_page";
            $url = admin_url('admin.php?page=u3a-contact-form-log' . $params);
            $HTML .= "<br><p><a href='$url'>Next Page</a>";
        } else {
            $url = admin_url('admin.php?page=u3a-contact-form-log&mode=summary');
            $HTML .= "<br><p><a href='$url'>Back to summary page</a>";
        }
        return $HTML;
    }

/**
 * Makes an html table from an array of objects.
 *
 * @param array $data each element must be an object with printable values.
 * @return  the required HTML <table> or '' if no data
 */

public static function array_of_objects_to_HTML_table($data) {
    if (empty($data)) {
        return '';
    }
    $HTML = '<table class= "u3acf_table">' . "\n";
    // head
    $HTML .= '  <thead>' . "\n";
    $HTML .= '    <tr>' . "\n";;
    $headings = array_keys(get_object_vars($data[0]));
    $headings = str_replace('_', ' ', $headings);
    foreach ($headings as $heading) {
        $HTML .= "<th>$heading</th>";
    }
    $HTML .= '    </tr>' . "\n";
    $HTML .= '  </thead>' . "\n";
    // body
    $HTML .= '  <tbody>' . "\n";
    foreach ($data as $row) {
        $HTML .= '    <tr>' . "\n";
        $values = get_object_vars($row);
        $values = str_replace('@', '@<br>', $values);
        foreach ($values as $value) {
            $HTML .= "<td>$value</td>";
        }
        $HTML .= '    </tr>' . "\n";
   }
    $HTML .= '  </tbody>' . "\n";
    $HTML .= '</table>' . "\n";
    return $HTML;
}

}

