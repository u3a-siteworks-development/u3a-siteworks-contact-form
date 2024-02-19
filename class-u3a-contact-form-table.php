<?php
class U3aEmailContactsTable
{
    /**
     * Creates the db table of u3a_email_contacts
     */
    public static function create_table() {

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'u3a_email_contacts';

        $sql = "CREATE TABLE $table_name (
            id INT(11) NOT NULL AUTO_INCREMENT,
            addressee VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            source_url VARCHAR(255),
            blocked CHAR(1) DEFAULT NULL,
            nonce INT(11),
            created BIGINT(13),
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Deletes the db table of u3a_email_contacts
     */
    public static function delete_table() {

        global $wpdb;
        $table_name = $wpdb->prefix . 'u3a_email_contacts';
        $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_name));
    }

    /**
     * Add a contact instance
     *    Returns the id of the added record.
     *    the 'blocked' attribute is not set, and is for future use! 
     */
    public static function add_contact_instance( $addressee, $email, $source_url) {
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'u3a_email_contacts';
        $wpdb->insert( 
            $table_name, 
            array( 
                'addressee' => $addressee,
                'email' => $email,
                'source_url' => $source_url,
                'nonce' => wp_rand(),
                'created' => time(), 
            ), 
            array( 
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
            )
        );
        return $wpdb->insert_id;
    }

    /**
     * Get the contact instance with id = $id
     *  Returns null if not found.
     *  Returns an object if found.
     */
    public static function get_contact_instance( $id ) {
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'u3a_email_contacts';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $table_name, $id));
    }

    /**
     * Delete the contact instance with id = $id
     */
    public static function delete_contact_instance( $id ) {
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'u3a_email_contacts';
        return $wpdb->delete( $table_name, array( 'id' => $id ) );
    }

    /**
     * Delete all contact instances that were created more than $stale_delay_days ago,
     * as they are no longer of use.
     */
    public static function clear_old_contact_instances($stale_delay_days=1) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'u3a_email_contacts';
        $stale = time() - $stale_delay_days*86400;
        return $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE created < %d", $table_name, $stale));
    }
}
