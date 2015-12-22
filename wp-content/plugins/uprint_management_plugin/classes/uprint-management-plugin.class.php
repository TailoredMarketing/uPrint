<?php
class uPrintManagementPlugin {
    
    public static $_instance; 
    public $option;
	public $textdomain  = 'uprint_manage';
    public $locs = array();

    public static function getInstance() {
        if ( !(self::$_instance instanceof self) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    private function __construct() {
        
        register_activation_hook( __FILE__, array( 'uPrintManagementPlugin', 'plugin_activation' ) );

        //Init actions      
		add_action( 'init', array( $this, 'register_post_types' ) );
		//add_action( 'init', array( $this, 'register_taxonomies' ) ); 
        add_action( 'init', array( $this, 'register_shortcodes' ) );
        add_action( 'init', array( $this, 'add_theme_caps' ) );
        // Admin Init actions
        

        // Template redirect actions
        add_action( 'template_redirect', array( $this, 'check_login_status' ) );
        
        //Enqueue styles & scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) ); 
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) ); 
        
        // Admin post handlers
        // Applications
        add_action('admin_post_nopriv_frontend_application_submit', array($this, 'frontend_application_submit'));
        add_action('admin_post_frontend_application_submit', array($this, 'frontend_application_submit'));
        // Orders
        add_action('admin_post_nopriv_frontend_order_submit', array($this, 'frontend_order_submit'));
        add_action('admin_post_frontend_order_submit', array($this, 'frontend_order_submit'));
        
        // Admin ajax handlers
        add_action('wp_ajax_ajax_applcation_decision', array($this, 'ajax_applcation_decision'));

        // Front end ajax handlers 
        add_action('wp_ajax_ajax_order_modal', array($this, 'ajax_order_modal'));
        add_action('wp_ajax_ajax_location_lookup', array($this, 'ajax_location_lookup'));
        add_action('wp_ajax_nopriv_ajax_location_lookup', array($this, 'ajax_location_lookup'));

        // Applications meta box
        add_action( 'post_submitbox_misc_actions', array( $this, 'applications_publish_box' ) );
        add_action( 'add_meta_boxes', array( $this, 'applications_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_applications_meta' ), 10, 3 ) ;

        // Applications & Customers custom list columns
        add_filter( 'manage_applications_posts_columns', array( $this, 'applications_columns' ), 10 );
        add_action( 'manage_applications_posts_custom_column', array( $this,'applications_columns_content'), 10, 2 );
        add_filter( 'manage_customers_posts_columns', array( $this, 'applications_columns' ), 10 );
        add_action( 'manage_customers_posts_custom_column', array( $this,'applications_columns_content'), 10, 2 );

        // Locations custom list columns
        add_filter( 'manage_locations_posts_columns', array( $this, 'locations_columns' ), 10 );
        add_action( 'manage_locations_posts_custom_column', array( $this,'locations_columns_content'), 10, 2 );

        // Locations meta box
        add_action( 'add_meta_boxes', array( $this, 'locations_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_locations_meta' ), 10, 3 ) ;

        // Orders meta box
        add_action( 'add_meta_boxes', array( $this, 'orders_meta_box' ) );
        //add_action( 'save_post', array( $this, 'save_orders_meta' ), 10, 3 ) ;
		
        // Login failed action
        add_action( 'wp_login_failed', array( $this, 'pu_login_failed' ) );
        add_action( 'authenticate', array( $this, 'pu_blank_login' ) );

        add_filter( 'wp_dropdown_users', array( $this, 'author_override' ) );

        add_filter( 'pre_get_posts', array( $this, 'filter_applications_by_location' ) );
        add_filter( 'pre_get_posts', array( $this, 'filter_locations_by_user' ) );
        add_filter( 'pre_get_posts', array( $this, 'filter_orders_by_user' ) );
    }

    function ajax_location_lookup() {
        $output = array(
            'status' => 0
        );

        $postcode = explode( ' ', trim( $_POST['postcode'] ) );

        $region = $postcode[0];

        $args = array(
          'post_type'   => 'locations',
          'meta_query'  => array(
            array(
              'key'     => '_postcodes',
              'value'   => $region,
              'compare' => 'LIKE',
            )
          ),
          'posts_per_page' => 1
        );

        $q = new WP_Query($args);
        // check if a location is found
        $location = $q->posts[0]->ID;
        $loc_name = $q->posts[0]->post_title;
        if( $q->found_posts > 0 ) {
            $output = array(
                'status' => 1,
                'region' => $region,
                'postcode' => $_POST['postcode'],
                'location' => $location,
                'loc_name'=> $loc_name
            );
        } else {
            $args = array(
              'post_type'   => 'locations',
              'meta_query'  => array(
                array(
                  'key'     => '_default_loc',
                  'value'   => 1,
                  'compare' => '=',
                )
              ),
              'posts_per_page' => 1
            );

            $q = new WP_Query($args);
            // check if a location is found
            $location = $q->posts[0]->ID;
            $loc_name = $q->posts[0]->post_title;
            if( $q->found_posts > 0 ) {
                $output = array(
                    'status' => 2,
                    'postcode' => $_POST['postcode'],
                    'location' => $location,
                    'loc_name'=> $loc_name
                );
            }
        }

        echo json_encode( $output );
        die();
    }

    public function filter_applications_by_location( $query ){ 
        global $pagenow;
        $type = 'post';

        //wp_die( '<pre>'.print_r( $query, true ).'</pre>');
        if (isset($_GET['post_type'])) {
            $type = $_GET['post_type'];
        }
        if ( ( 'applications' == $type || 'customers' == $type ) && is_admin() && $pagenow=='edit.php' && !current_user_can( 'manage_options' ) ) {
            // re-add && !current_user_can( 'manage_options' )
            $userlocations = get_user_meta( get_current_user_id(), '_user_locations', true );
            //wp_die( '<pre>'.print_r( $userlocations, true ).'</pre>');
            $query->query_vars['meta_key']     = '_location';
            $query->query_vars['meta_value']   = $userlocations; // Get location of current user
            $query->query_vars['meta_compare'] = 'IN';
        }
    }

    public function filter_orders_by_user( $query ){ 
        global $pagenow;
        $type = 'post';

        //wp_die( '<pre>'.print_r( $query, true ).'</pre>');
        if (isset($_GET['post_type'])) {
            $type = $_GET['post_type'];
        }
        if (  'orders' == $type && is_admin() && $pagenow=='edit.php' ) {
            // re-add && !current_user_can( 'manage_options' )
            $userlocations = get_user_meta( get_current_user_id(), '_user_locations', true );
            //wp_die( '<pre>'.print_r( $userlocations, true ).'</pre>');
            $query->query_vars['meta_key']     = '_order_location';
            $query->query_vars['meta_value']   = $userlocations; // Get location of current user
            $query->query_vars['meta_compare'] = 'IN';
            //wp_die( '<pre>'.print_r( $query, true ).'</pre>');
        }
    }

    public function filter_locations_by_user( $query ){ 
        global $pagenow;
        $type = 'post';
        if (isset($_GET['post_type'])) {
            $type = $_GET['post_type'];
        }
        if ( 'locations' == $type && is_admin() && $pagenow=='edit.php' && !current_user_can( 'manage_options' ) ) {
            $query->query_vars['author'] = get_current_user_id();
        }
        
    }

    static function plugin_activation() {
        remove_role( 'location_admin' );
        $result = add_role(
            'location_admin',
            __( 'Location Admin' ),
            array(
                'read'         => true,  // true allows this capability
                'edit_posts'   => true,
                'edit_published_posts' => true,
                'delete_posts' => true, // Use false to explicitly deny
                'level_2'      => true
            )
        );
    }
    function author_override( $output ) {
        global $post, $user_ID;

        // return if this isn't the theme author override dropdown
        if (!preg_match('/post_author_override/', $output)) return $output;

        // return if we've already replaced the list (end recursion)
        if (preg_match ('/post_author_override_replaced/', $output)) return $output;

        // replacement call to wp_dropdown_users
          $output = wp_dropdown_users(array(
            'echo' => 0,
            'name' => 'post_author_override_replaced',
            'selected' => empty($post->ID) ? $user_ID : $post->post_author,
            'include_selected' => true
          ));

          // put the original name back
          $output = preg_replace('/post_author_override_replaced/', 'post_author_override', $output);

        return $output;
    }
    public function add_theme_caps() {
        $role = get_role( 'contributor' );
        $role->add_cap( 'add_orders' ); 

        $role = get_role( 'administrator' );
        $role->add_cap( 'add_orders' ); 

        $role = get_role( 'location_admin' );
        $role->add_cap( 'edit_others_posts' ); 

        //wp_die( '<pre>'.print_r($role, true).'</pre>'); 
    }

    public function applications_publish_box() {
        global $post;
        if( $post->post_type == 'applications') { ?>
            <div class="publish_custom">
                <h4>Application Decision</h4>
                <button type="submit" class="button button-primary application-button" data-post="<?php echo $post->ID; ?>" data-decision="accept">Accept</button>
                <button type="button" class="button-secondary delete application-button" data-post="<?php echo $post->ID; ?>" data-decision="reject">Reject</button> 
            </div>
 <?php  }
    }

    public function admin_enqueue_scripts() {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'admin-js', PLUGIN_URL.'/js/admin-js.js', array( 'jquery' ), '1.0.0' );

        wp_enqueue_style( 'admin-style', PLUGIN_URL.'/css/admin-style.css', false, '1.0.0' );
    }

    public function enqueue_scripts() {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'frontend-js', PLUGIN_URL.'/js/front-end.js', array( 'jquery' ), '1.0.0' );
        wp_localize_script( 'frontend-js', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );

        //wp_enqueue_style( 'admin-style', PLUGIN_URL.'/css/admin-style.css', false, '1.0.0' );
    }

    public function register_post_types() {
        
        $applications = array(
            'labels'              => array(
                'name'               => _x( 'Applications', 'post type general name', $this->textdomain ),
                'singular_name'      => _x( 'Application', 'post type singular name', $this->textdomain ),
                'menu_name'          => _x( 'Applications', 'admin menu', $this->textdomain ),
                'name_admin_bar'     => _x( 'Applications', 'add new on admin bar', $this->textdomain ),
                'add_new'            => _x( 'Add New Application', 'book', $this->textdomain ),
                'add_new_item'       => __( 'Add New Applications', $this->textdomain ),
                'new_item'           => __( 'New Application', $this->textdomain ),
                'edit_item'          => __( 'Edit Application', $this->textdomain ),
                'view_item'          => __( 'View Application', $this->textdomain ),
                'all_items'          => __( 'All Applications', $this->textdomain ),
                'search_items'       => __( 'Search Applications', $this->textdomain ),
                'parent_item_colon'  => __( 'Parent Application:', $this->textdomain ),
                'not_found'          => __( 'No applications found.', $this->textdomain ),
                'not_found_in_trash' => __( 'No applications found in Trash.', $this->textdomain )
            ),
            'public'             => false,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'exclude_from_search'=> true,
            'menu_position'      => 27,
            'menu_icon'          => 'dashicons-clipboard',
            'supports'           => array( 'title' ),
        );

        $orders = array(
            'labels'              => array(
                'name'               => _x( 'Orders', 'post type general name', $this->textdomain ),
                'singular_name'      => _x( 'Order', 'post type singular name', $this->textdomain ),
                'menu_name'          => _x( 'Orders', 'admin menu', $this->textdomain ),
                'name_admin_bar'     => _x( 'Orders', 'add new on admin bar', $this->textdomain ),
                'add_new'            => _x( 'Add New Order', 'book', $this->textdomain ),
                'add_new_item'       => __( 'Add New Orders', $this->textdomain ),
                'new_item'           => __( 'New Order', $this->textdomain ),
                'edit_item'          => __( 'View Order', $this->textdomain ),
                'view_item'          => __( 'View Order', $this->textdomain ),
                'all_items'          => __( 'All Orders', $this->textdomain ),
                'search_items'       => __( 'Search Orders', $this->textdomain ),
                'parent_item_colon'  => __( 'Parent Order:', $this->textdomain ),
                'not_found'          => __( 'No orders found.', $this->textdomain ),
                'not_found_in_trash' => __( 'No orders found in Trash.', $this->textdomain )
            ),
            'public'             => false,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'exclude_from_search'=> true,
            'menu_position'      => 29,
            'menu_icon'          => 'dashicons-cart',
            'supports'           => array( 'title' ),
        );

        $locations = array(
            'labels'              => array(
                'name'               => _x( 'Locations', 'post type general name', $this->textdomain ),
                'singular_name'      => _x( 'Location', 'post type singular name', $this->textdomain ),
                'menu_name'          => _x( 'Locations', 'admin menu', $this->textdomain ),
                'name_admin_bar'     => _x( 'Locations', 'add new on admin bar', $this->textdomain ),
                'add_new'            => _x( 'Add New Location', 'book', $this->textdomain ),
                'add_new_item'       => __( 'Add New Locations', $this->textdomain ),
                'new_item'           => __( 'New Location', $this->textdomain ),
                'edit_item'          => __( 'Edit Location', $this->textdomain ),
                'view_item'          => __( 'View Location', $this->textdomain ),
                'all_items'          => __( 'All Locations', $this->textdomain ),
                'search_items'       => __( 'Search Locations', $this->textdomain ),
                'parent_item_colon'  => __( 'Parent Location:', $this->textdomain ),
                'not_found'          => __( 'No locations found.', $this->textdomain ),
                'not_found_in_trash' => __( 'No locations found in Trash.', $this->textdomain )
            ),
            'public'             => false,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'exclude_from_search'=> true,
            'menu_position'      => 26,
            'menu_icon'          => 'dashicons-admin-home',
            'supports'           => array( 'title', 'author'),
        );

        $customers = array(
            'labels'              => array(
                'name'               => _x( 'Customers', 'post type general name', $this->textdomain ),
                'singular_name'      => _x( 'Customer', 'post type singular name', $this->textdomain ),
                'menu_name'          => _x( 'Customers', 'admin menu', $this->textdomain ),
                'name_admin_bar'     => _x( 'Customers', 'add new on admin bar', $this->textdomain ),
                'add_new'            => _x( 'Add New Customer', 'book', $this->textdomain ),
                'add_new_item'       => __( 'Add New Customers', $this->textdomain ),
                'new_item'           => __( 'New Customer', $this->textdomain ),
                'edit_item'          => __( 'Edit Customer', $this->textdomain ),
                'view_item'          => __( 'View Customer', $this->textdomain ),
                'all_items'          => __( 'All Customers', $this->textdomain ),
                'search_items'       => __( 'Search Customers', $this->textdomain ),
                'parent_item_colon'  => __( 'Parent Customer:', $this->textdomain ),
                'not_found'          => __( 'No customers found.', $this->textdomain ),
                'not_found_in_trash' => __( 'No customers found in Trash.', $this->textdomain )
            ),
            'public'             => false,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'exclude_from_search'=> true,
            'menu_position'      => 28,
            'menu_icon'          => 'dashicons-businessman',
            'supports'           => array( 'title' ),
        );

        register_post_type( 'applications', $applications );
        register_post_type( 'orders', $orders );
        register_post_type( 'locations', $locations );
        register_post_type( 'customers', $customers );
    }

    public function applications_meta_box() {
        add_meta_box(
            'applications_meta',
            __( 'Application Data', $this->textdomain ),
            array( $this, 'applications_meta_callback' ),
            'applications',
            'advanced',
            'core'
        );
        add_meta_box(
            'applications_meta',
            __( 'Application Data', $this->textdomain ),
            array( $this, 'applications_meta_callback' ),
            'customers',
            'advanced',
            'core'
        );
    }

    public function applications_meta_callback( $post ) {
        wp_nonce_field( 'applications_meta_data', 'applications_meta_nonce' );
        $data = get_post_meta( $post->ID, '_application_data', true );
?>
        <table class="form-table">
            <tr>
                <th>Name</th>
                <td><input type="text" class="regular-text" name="name" value="<?php echo ( isset( $data['name'] ) ? esc_attr( $data['name'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Position</th>
                <td><input type="text" class="regular-text" name="position" value="<?php echo ( isset( $data['position'] ) ? esc_attr( $data['position'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Company</th>
                <td><input type="text" class="regular-text" name="company" value="<?php echo ( isset( $data['company'] ) ? esc_attr( $data['company'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Type</th>
                <td><input type="text" class="regular-text" name="type" value="<?php echo ( isset( $data['type'] ) ? esc_attr( $data['type'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Address</th>
                <td><textarea style="width: 25em;" rows="6" name="address"><?php echo ( isset( $data['address'] ) ? esc_textarea( $data['address'] ) : '' ); ?></textarea></td>
            </tr>
            <tr>
                <th>Telephone</th>
                <td><input type="text" class="regular-text" name="tel" value="<?php echo ( isset( $data['tel'] ) ? esc_attr( $data['tel'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><input type="text" class="regular-text" name="email" value="<?php echo ( isset( $data['email'] ) ? esc_attr( $data['email'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>A4 Usage</th>
                <td><input type="text" class="regular-text" name="a4usage" value="<?php echo ( isset( $data['a4usage'] ) ? esc_attr( $data['a4usage'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>A3 Usage</th>
                <td><input type="text" class="regular-text" name="a3usage" value="<?php echo ( isset( $data['a3usage'] ) ? esc_attr( $data['a3usage'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Mode</th>
                <td><input type="text" class="regular-text" name="mode" value="<?php echo ( isset( $data['mode'] ) ? esc_attr( $data['mode'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Networking</th>
                <td><input type="text" class="regular-text" name="network" value="<?php echo ( isset( $data['network'] ) ? esc_attr( $data['network'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Duplex</th>
                <td><input type="text" class="regular-text" name="duplex" value="<?php echo ( isset( $data['duplex'] ) ? esc_attr( $data['duplex'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Future</th>
                <td><input type="text" class="regular-text" name="future" value="<?php echo ( isset( $data['future'] ) ? esc_attr( $data['future'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Number of Printers</th>
                <td><input type="text" class="regular-text" name="printers" value="<?php echo ( isset( $data['printers'] ) ? esc_attr( $data['printers'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Printer Make</th>
                <td><input type="text" class="regular-text" name="make" value="<?php echo ( isset( $data['make'] ) ? esc_attr( $data['make'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Printer Model</th>
                <td><input type="text" class="regular-text" name="model" value="<?php echo ( isset( $data['model'] ) ? esc_attr( $data['model'] ) : '' ); ?>"></td>
            </tr>
        </table>
<?php
    }

    public function save_applications_meta( $post_id ) {

        if ( ! isset( $_POST['applications_meta_nonce'] ) )
            return $post_id;

        $nonce = $_POST['applications_meta_nonce'];

        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $nonce, 'applications_meta_data' ) )
            return $post_id;

        // If this is an autosave, our form has not been submitted,
                //     so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
            return $post_id;

        // Check the user's permissions.
        if ( 'applications' == $_POST['post_type'] ) {

            if ( ! current_user_can( 'edit_page', $post_id ) )
                return $post_id;
    
        } else {

            if ( ! current_user_can( 'edit_post', $post_id ) )
                return $post_id;

        }

        // Sanitize the user input.
        $mydata = array(
            'name'      => ( isset( $_POST['name'] )        ? $_POST['name'] : '' ),
            'position'  => ( isset( $_POST['position'] )    ? $_POST['position'] : '' ),
            'company'   => ( isset( $_POST['company'] )     ? $_POST['company'] : '' ),
            'type'      => ( isset( $_POST['type'] )        ? $_POST['type'] : '' ),
            'address'   => ( isset( $_POST['address'] )     ? $_POST['address'] : '' ),
            'tel'       => ( isset( $_POST['tel'] )         ? $_POST['tel'] : '' ),
            'email'     => ( isset( $_POST['email'] )       ? $_POST['email'] : '' ),
            'a4usage'   => ( isset( $_POST['a4usage'] )     ? $_POST['a4usage'] : '' ),
            'a3usage'   => ( isset( $_POST['a3usage'] )     ? $_POST['a3usage'] : '' ),
            'mode'      => ( isset( $_POST['mode'] )        ? $_POST['mode'] : '' ),
            'network'   => ( isset( $_POST['network'] )     ? $_POST['network'] : '' ),
            'duplex'    => ( isset( $_POST['duplex'] )      ? $_POST['duplex'] : '' ),
            'future'    => ( isset( $_POST['future'] )      ? $_POST['future'] : '' ),
            'printers'  => ( isset( $_POST['printers'] )    ? $_POST['printers'] : '' ),
            'make'      => ( isset( $_POST['make'] )        ? $_POST['make'] : '' ),
            'model'     => ( isset( $_POST['model'] )       ? $_POST['model'] : '' ),
        );
                
        // Update the meta field.
        update_post_meta( $post_id, '_application_data', $mydata );

    }

    public function locations_meta_box() {
        add_meta_box(
            'locations_meta',
            __( 'Location Data', $this->textdomain ),
            array( $this, 'locations_meta_callback' ),
            'locations',
            'advanced',
            'core'
        );
    }

    public function locations_meta_callback( $post ) {
        wp_nonce_field( 'locations_meta_data', 'locations_meta_nonce' );
        $data = get_post_meta( $post->ID, '_location_data', true );
        $postcodes = get_post_meta( $post->ID, '_postcodes', true );
        $default = get_post_meta( $post->ID, '_default_loc', true );
?>
        <table class="form-table">
            <tr>
                <th>Default?</th>
                <td>
                    <input type="checkbox" name="default" value="1" <?php checked( $default, 1 ); ?> >
                </td>
            </tr>
            <tr>
                <th>Town</th>
                <td><input type="text" class="regular-text" name="town" value="<?php echo ( isset( $data['town'] ) ? esc_attr( $data['town'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Postcode</th>
                <td><input type="text" class="regular-text" name="postcode" value="<?php echo ( isset( $data['postcode'] ) ? esc_attr( $data['postcode'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Telephone</th>
                <td><input type="text" class="regular-text" name="tel" value="<?php echo ( isset( $data['tel'] ) ? esc_attr( $data['tel'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><input type="email" class="regular-text" name="email" value="<?php echo ( isset( $data['email'] ) ? esc_attr( $data['email'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Website</th>
                <td><input type="url" class="regular-text" name="website" value="<?php echo ( isset( $data['website'] ) ? esc_attr( $data['website'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Bank Name</th>
                <td><input type="text" class="regular-text" name="bankname" value="<?php echo ( isset( $data['bankname'] ) ? esc_attr( $data['bankname'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Bank Town</th>
                <td><input type="text" class="regular-text" name="banktown" value="<?php echo ( isset( $data['banktown'] ) ? esc_attr( $data['banktown'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Administrator Name</th>
                <td><input type="text" class="regular-text" name="adminname" value="<?php echo ( isset( $data['adminname'] ) ? esc_attr( $data['adminname'] ) : '' ); ?>"></td>
            </tr>
            <tr>
                <th>Postcode List</th>
                <td>
                    <textarea style="width: 25em;" rows="20" class="regular-text" name="postcodelist"><?php echo ( isset( $postcodes ) ? implode( "\r\n",  $postcodes ) : '' ); ?></textarea>
                    <p class="description">One per line</p>
                </td>
            </tr>
        </table>
<?php
    }

    public function save_locations_meta( $post_id ) {

        if ( ! isset( $_POST['locations_meta_nonce'] ) )
            return $post_id;

        $nonce = $_POST['locations_meta_nonce'];

        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $nonce, 'locations_meta_data' ) )
            return $post_id;

        // If this is an autosave, our form has not been submitted,
                //     so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
            return $post_id;

        // Check the user's permissions.
        if ( 'locations' == $_POST['post_type'] ) {

            if ( ! current_user_can( 'edit_page', $post_id ) )
                return $post_id;
    
        } else {

            if ( ! current_user_can( 'edit_post', $post_id ) )
                return $post_id;

        }

        // Sanitize the user input.
        $mydata = array(
            'town'          => ( isset( $_POST['town'] )        ? $_POST['town']        : '' ),
            'postcode'      => ( isset( $_POST['postcode'] )    ? $_POST['postcode']    : '' ),
            'tel'           => ( isset( $_POST['tel'] )         ? $_POST['tel']         : '' ),
            'email'         => ( isset( $_POST['email'] )       ? $_POST['email']       : '' ),
            'website'       => ( isset( $_POST['website'] )     ? $_POST['website']     : '' ),
            'bankname'      => ( isset( $_POST['bankname'] )    ? $_POST['bankname']    : '' ),
            'banktown'      => ( isset( $_POST['banktown'] )    ? $_POST['banktown']    : '' ),
            'adminname'     => ( isset( $_POST['adminname'] )   ? $_POST['adminname']   : '' ),
            //'postcodelist'  => ( isset( $_POST['postcodelist'] )? $_POST['postcodelist']: '' ),
        );
        if( isset( $_POST['postcodelist'] ) ) {
            $postcodes = explode( "\n", str_replace( "\r", "", $_POST['postcodelist'] ) );
        }   
        $default = ( isset( $_POST['default'] )     ? $_POST['default']     : 0 ); 
        // Update the meta field.
        
        $userlocations = get_user_meta( $_POST['post_author_override'], '_user_locations', true );

        if( !is_array( $userlocations ) ) $userlocations = array();
        if( !in_array( $post_id, $userlocations ) ) {
            $userlocations[] = $post_id;
        }

        update_user_meta( $_POST['post_author_override'], '_user_locations', $userlocations );

        update_post_meta( $post_id, '_location_data', $mydata );
        update_post_meta( $post_id, '_default_loc', $default );
        update_post_meta( $post_id, '_postcodes', $postcodes );
    }
    
    public function orders_meta_box() {
        add_meta_box(
            'orders_meta',
            __( 'Order Data', $this->textdomain ),
            array( $this, 'orders_meta_callback' ),
            'orders',
            'advanced',
            'core'
        );
    }

    public function orders_meta_callback( $post ) {
        wp_nonce_field( 'orders_meta_data', 'orders_meta_nonce' );
        $data     = get_post_meta( $post->ID, '_order_data', true );
        $customer = get_post_meta( $data['customer'], '_application_data', true );
    ?>
        <table class="order-contact-table ">
            <tr>
                <th>Order Date</th>
                <td><?php echo get_the_date( 'd/m/Y H:i:s', $post->ID ); ?></td>
            </tr>
            <tr>
                <th>Company</th>
                <td><?php echo ( isset( $customer['company'] ) ? $customer['company']: '' ); ?></td>
            </tr>
            <tr>
                <th>Order Contact</th>
                <td><?php echo ( isset( $data['contact'] ) ? $data['contact']: '' ); ?></td>
            </tr>
            <tr>
                <th>Telephone</th>
                <td><?php echo ( isset( $data['tel'] ) ? $data['tel']: '' ); ?></td>
            </tr>
            <tr>
                <th>Order Status</th>
                <td>
                    <select>
                        <option>Order Recieved</option>
                        <option>Processing</option>
                        <option>Order Complete</option>
                    </select>
                </td>
            </tr>
        </table>
        <h3>Order Details</h3>
        <table class="order-detail-table striped">
            <thead>
                <tr>
                    <th>Cartridge Ref</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Printer</th>
            </thead>
            <tbody>
                <?php 
                    if( isset( $data['items'] ) && is_array( $data['items'] ) ) {
                        for( $i=0; $i<count($data['items']); $i++ ) { 
                            $item = $data['items'][$i];
                            ?>
                            <tr>
                                <td><?php echo ( isset( $item['ref'] ) ? $item['ref']: '' ); ?></td>
                                <td><?php echo ( isset( $item['desc'] ) ? $item['desc']: '' ); ?></td>
                                <td><?php echo ( isset( $item['qty'] ) ? $item['qty']: '' ); ?></td>
                                <td><?php echo ( isset( $item['printer'] ) ? $item['printer']: '' ); ?></td>
                            </tr>
                        <?php
                        }
                    } else {
                        echo '<tr><td colspan="4">No items</td>/tr>';
                    }
                ?>
            </tbody>
        </table>
    <?php
    }

    public function register_shortcodes() {
        add_shortcode( 'application_form', array( $this, 'application_form_shortcode' ) );
        add_shortcode( 'login_page', array( $this, 'login_page_shortcode' ) );
        add_shortcode( 'account_page', array( $this, 'account_page_shortcode' ) );
        add_shortcode( 'order_page', array( $this, 'order_page_shortcode' ) );
    }

    public function application_form_shortcode( $atts ) {
       ob_start();
?>
        <form class="form-horizontal" action="<?php echo admin_url('admin-post.php'); ?>" method="POST">
            <input type="hidden" value="frontend_application_submit" name="action">
            <?php wp_nonce_field( 'frontend_application_submit', 'frontend_application_nonce' ); ?>
            <div class="form-group">
                <label class="col-sm-3">Name</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" placeholder="Name" name="name">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">Company Name</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" placeholder="Company Name" name="company">
                </div>
            </div>
            <hr>
            <div class="form-group">
                <label class="col-sm-3">Street Address</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" placeholder="Street Address" name="address1">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">Address 2</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" placeholder="Address 2" name="address2">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">Town</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" placeholder="Town" name="town">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">County</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" placeholder="County" name="county">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">Post Code</label>
                <div class="col-sm-9">
                    <input type="text" id="applyPostcode" class="form-control" placeholder="Post Code" name="postcode">
                    <input type="hidden" id="locationID" class="form-control" name="location">
                </div>
            </div>
            <hr>
            <div class="form-group">
                <label class="col-sm-3">Business Type</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" placeholder="Business Type" name="type">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">Position</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" placeholder="Position" name="position">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">Telephone</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" placeholder="Telephone" name="tel">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">Email</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" placeholder="Email" name="email">
                </div>
            </div>
            <hr>
            <div class="form-group">
                <label class="col-sm-3">Approx. Monthly A4 Usage</label>
                <div class="col-sm-9">
                    <select class="form-control" name="a4usage">
                        <option disabled selected>Please select...</option>
                        <option>None</option>
                        <option>Upto 500</option>
                        <option>500-1000</option>
                        <option>1000-3000</option>
                        <option>3000-5000</option>
                        <option>Over 5000</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">Approx. Monthly A3 Usage</label>
                <div class="col-sm-9">
                    <select class="form-control" name="a3usage">
                        <option disabled selected>Please select...</option>
                        <option>None</option>
                        <option>Upto 500</option>
                        <option>500-1000</option>
                        <option>1000-3000</option>
                        <option>3000-5000</option>
                        <option>Over 5000</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">Mode</label>
                <div class="col-sm-9">
                    <select class="form-control" name="mode">
                        <option disabled selected>Please select...</option>
                        <option>Colour</option>
                        <option>Black &amp; White</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">Networking</label>
                <div class="col-sm-9">
                    <select class="form-control" name="network">
                        <option disabled selected>Please select...</option>
                        <option>Yes</option>
                        <option>No</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">Duplex</label>
                <div class="col-sm-9">
                    <select class="form-control" name="duplex">
                        <option disabled selected>Please select...</option>
                        <option>Yes</option>
                        <option>No</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">Future Usage</label>
                <div class="col-sm-9">
                    <select class="form-control">
                        <option disabled selected>Please select...</option>
                        <option>Will increase</option>
                        <option>Will decrease</option>
                        <option>Stay the same</option>
                    </select>
                </div>
            </div>
            <hr>
            <div class="form-group">
                <label class="col-sm-3">Number of Printers</label>
                <div class="col-sm-9">
                    <input type="number" class="form-control" placeholder="Number of Printers" name="printers">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">Sample Printer Name</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" placeholder="Sample Printer Name" name="make">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">Sample Printer Model</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" placeholder="Sample Printer Model" name="model">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Submit Application</button>
        </form>
<?php
       $output = ob_get_clean();
       return $output; 
    }

    public function login_page_shortcode( $atts ) {
        $args   = array( 'echo' => false, 'redirect' => site_url( '/account' ) );
        $output = '';
        if(isset($_GET['login']) && $_GET['login'] == 'failed')
        {
            $output .= '
                    <div class="alert alert-danger" role="alert">  
                        <strong>ERROR</strong>: There was an error logging in. Please try again.<br>
                    </div>';
        }
        ob_start();
    ?>
        <form class="form-horizontal" name="loginform" id="loginform" action="<?php echo get_option('home'); ?>/wp-login.php" method="post">
            <div class="form-group">
                <label class="col-md-2">Username</label>
                <div class="col-md-4">
                    <input type="text" name="log" id="user_login" class="form-control" value="" size="20" tabindex="10" />
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-2">Password</label>
                <div class="col-md-4">
                    <input type="password" name="pwd" id="user_pass" class="form-control" value="" size="20" tabindex="20" />
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-2">Remember me?</label>
                <div class="col-md-4">
                    <input name="rememberme" type="checkbox" id="rememberme" value="forever" class="checkbox" tabindex="90" />
                </div>
            </div>
            
                <input type="submit" name="wp-submit" id="wp-submit" class="btn btn-primary" value="Log In" tabindex="100" />
                <input type="hidden" name="redirect_to" value="<?php echo site_url( '/account/' ); ?>" />
                <input type="hidden" name="testcookie" value="1" />
            
        </form>
    <?php
        $form = ob_get_clean();
        return $output.$form;
    }

    public function account_page_shortcode( $atts ) {
        ob_start();
        $user_ID = get_current_user_id();
    ?>
        <div class="row">
            <div class="col-md-9">
                <h2>Your Orders</h2>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Order No.</th>
                            <th>Date</th>
                            <th class="text-center">Items</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                        <?php 
                            $orders = get_posts( array( 'post_type' => 'orders', 'posts_per_page' => -1, 'author' => $user_ID ) );
                            if( count( $orders ) > 0 ) {
                                foreach ($orders as $order ) {
                                    setup_postdata( $order );
                                    $meta = get_post_meta( $order->ID, '_order_data', true );
                                ?>
                                    <tr>
                                        <td><?php echo ( isset( $order->ID ) ? $order->ID : '' ); ?></td>
                                        <td><?php echo get_the_date( 'd/m/Y', $order->ID ); ?></td>
                                        <td class="text-center"><?php echo ( isset( $meta['items'] ) ? count( $meta['items'] ) : '0' ); ?></td>
                                        <td><?php echo ( isset( $meta['status'] ) ? $meta['status'] : '' ); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-link tooltip_show show_order_modal" data-target="#order_modal" data-order="<?php echo $order->ID; ?>" data-placement="top" title="View Order"><i class="fa fa-fw fa-search"></i></button> 
                                            <button type="button" class="btn btn-link tooltip_show" data-placement="top" title="Reorder"><i class="fa fa-fw fa-refresh"></i></button> 
                                            <button type="button" class="btn btn-link tooltip_show" data-placement="top" title="Delete Order"><i class="fa fa-fw fa-trash"></i></button>
                                        </td>   
                                    </tr>
                                <?php
                                }
                            } else {
                                echo '<tr><td colspan="5">No orders to show you yet. <a href="'.home_url( '/order/' ).'">Create a new order</a>.</td></tr>'; 
                            }
                        ?>
                </table>
                <a href="<?php echo home_url( '/order/' ); ?>" class="btn btn-primary">Create a new order</a>
                <div class="modal fade" id="order_modal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                <h4 class="modal-title" id="myModalLabel">Order Details</h4>
                            </div>
                            <div class="modal-body">
                                {{populate order data here}}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <h2>Your Details</h2>
                <?php 
                    $customerdata = get_posts( array( 'post_type' => 'customers', 'posts_per_page' => 1, 'author' => $user_ID ) );
                    $details      = get_post_meta( $customerdata[0]->ID, '_application_data', true );
                ?>
                <dl>
                    <dt>Company</dt>
                    <dd><?php echo ( isset( $details['company'] ) ? $details['company'] : '' ); ?></dd>
                    <dt>Name</dt>
                    <dd><?php echo ( isset( $details['name'] ) ? $details['name'] : '' ); ?></dd>
                    <dt>Position</dt>
                    <dd><?php echo ( isset( $details['position'] ) ? $details['position'] : '' ); ?></dd>
                    <dt>Business Type</dt>
                    <dd><?php echo ( isset( $details['type'] ) ? $details['type'] : '' ); ?></dd>
                    <dt>Email</dt>
                    <dd><?php echo ( isset( $details['email'] ) ? $details['email'] : '' ); ?></dd>
                    <dt>Telephone</dt>
                    <dd><?php echo ( isset( $details['tel'] ) ? $details['tel'] : '' ); ?></dd>
                    <dt>A4 Usage</dt>
                    <dd><?php echo ( isset( $details['a4usage'] ) ? $details['a4usage'] : '' ); ?></dd>
                    <dt>A3 Usage</dt>
                    <dd><?php echo ( isset( $details['a3usage'] ) ? $details['a3usage'] : '' ); ?></dd>
                    <dt>Mode</dt>
                    <dd><?php echo ( isset( $details['mode'] ) ? $details['mode'] : '' ); ?></dd>
                    <dt>Duplex</dt>
                    <dd><?php echo ( isset( $details['duplex'] ) ? $details['duplex'] : '' ); ?></dd>
                    <dt>Future Usage</dt>
                    <dd><?php echo ( isset( $details['future'] ) ? $details['future'] : '' ); ?></dd>
                    <dt>Printers</dt>
                    <dd><?php echo ( isset( $details['printers'] ) ? $details['printers'] : '' ); ?></dd>
                    <dt>Example Make</dt>
                    <dd><?php echo ( isset( $details['make'] ) ? $details['make'] : '' ); ?></dd>
                    <dt>Example Model</dt>
                    <dd><?php echo ( isset( $details['model'] ) ? $details['model'] : '' ); ?></dd>
                </dl>
            </div>
        </div>
    <?php
        wp_reset_postdata();
        $output = ob_get_clean();
        return $output;
    }

    public function order_page_shortcode( $atts ) {
        ob_start();
        $customerdata = get_posts( array( 'post_type' => 'customers', 'posts_per_page' => 1, 'author' => get_current_user_id() ) );
        $details      = get_post_meta( $customerdata[0]->ID, '_application_data', true );
        $location     = get_post_meta( $customerdata[0]->ID, '_location', true);
    ?>
        <form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="POST" class="form-horizontal">
            <input type="hidden" value="frontend_order_submit" name="action">
            <?php wp_nonce_field( 'frontend_order_submit', 'frontend_order_nonce' ); ?>
            <input type="hidden" name="company" value="<?php echo ( isset( $details['company'] ) ? $details['company'] : '' ); ?>">
            <input type="hidden" name="customer" value="<?php echo $customerdata[0]->ID; ?>">
            <input type="hidden" name="location" value="<?php echo $location; ?>">
            <div class="form-group">
                <label class="col-sm-3">Order Contact</label>
                <div class="col-sm-9">
                    <input required type="text" name="contact" class="form-control" placeholder="Order Contact" value="<?php echo ( isset( $details['name'] ) ? $details['name'] : '' ); ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3">Telephone</label>
                <div class="col-sm-9">
                    <input required type="text" name="tel" class="form-control" placeholder="Telephone" value="<?php echo ( isset( $details['tel'] ) ? $details['tel'] : '' ); ?>">
                </div>
            </div>
            <h3>Order Details</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Cartridge Ref.</th>
                        <th>Cartridge Descrption</th>
                        <th width="80">Qty.</th>
                        <th>Printer Model</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="tr_clone">
                        <td><input required type="text" name="ref[]" class="form-control" placeholder="Cartridge Ref. Number"></td>
                        <td><input required type="text" name="desc[]" class="form-control" placeholder="Cartridge Descrption"></td>
                        <td><input required type="number" name="qty[]" min="1" class="form-control" placeholder="0"></td>
                        <td><input required type="text" name="printer[]" class="form-control" placeholder="Printer Model"></td>
                        <td class="text-right">
                            <button type="button" class="delete-order-row btn btn-danger"><i class="fa fa-trash"></i></button>
                        </td>
                    </tr>   
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="text-right">
                            <button type="button" class="add-order-row btn btn-xs btn-default"><i class="fa fa-plus"></i> Add Row</button>                           
                        </td>
                    </tr>   
                </tfoot>
            </table>
            <p class="text-right">
                <a href="<?php echo home_url('/account/'); ?>" class="btn btn-default">Cancel</a> 
                <button type="submit" class="btn btn-primary">Submit Order</button>
            </p>
        </form>
    <?php
    }

    public function frontend_order_submit() {
        if ( ! isset( $_POST['frontend_order_nonce'] ) )
            return;

        $nonce = $_POST['frontend_order_nonce'];

        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $nonce, 'frontend_order_submit' ) )
            return;


        if ( ! current_user_can( 'add_orders' ) )
                return;
       
        $post = array (
            'post_type' => 'orders',
            'post_title' => $_POST['name'].' at '. $_POST['company'].' on '. date( 'd/m/Y H:i:s' ),
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
            );
        $post_id = wp_insert_post( $post );
        $post = array(
            'ID' => $post_id,
            'post_title' => 'Order Number '.$post_id.' ('.$_POST['company'].') on '. date( 'd/m/Y H:i:s' ),
            );
        $post_id = wp_update_post( $post );
        // Is there another way around this?
        $items = array();
        $i = 0;
        foreach( $_POST['ref'] as $item ) {
            $items[] = array(
                'ref'      => $_POST['ref'][$i],
                'desc'     => $_POST['desc'][$i],
                'qty'      => $_POST['qty'][$i],
                'printer'  => $_POST['printer'][$i],
                );
            $i++;
        }
        $meta = array(
            'customer' => $_POST['customer'],
            'contact'  => $_POST['contact'],
            'tel'      => $_POST['tel'],
            'items'    => $items,
            'status'   => 'Order Received'
        );

        update_post_meta( $post_id, '_order_location', $_POST['location'] );
        update_post_meta( $post_id, '_order_data', $meta );

        wp_safe_redirect( home_url( '/account/?order=sent' ) );
    }

    public function frontend_application_submit() {
        //die( '<pre>'.print_r( $_POST ).'</pre>' );
        $post = array (
            'post_type' => 'applications',
            'post_title' => $_POST['name'].' - '. $_POST['position'] .' at '. $_POST['company'],
            'post_status' => 'publish'
            );
        $post_id = wp_insert_post( $post );

        $mydata = array(
            'name'      => ( isset( $_POST['name'] )        ? $_POST['name'] : '' ),
            'position'  => ( isset( $_POST['position'] )    ? $_POST['position'] : '' ),
            'company'   => ( isset( $_POST['company'] )     ? $_POST['company'] : '' ),
            'type'      => ( isset( $_POST['type'] )        ? $_POST['type'] : '' ),
            'address1'  => ( isset( $_POST['address1'] )    ? $_POST['address1'] : '' ),
            'address2'  => ( isset( $_POST['address2'] )    ? $_POST['address2'] : '' ),
            'town'      => ( isset( $_POST['town'] )        ? $_POST['town'] : '' ),
            'county'    => ( isset( $_POST['county'] )      ? $_POST['county'] : '' ),
            'postcode'  => ( isset( $_POST['postcode'] )    ? $_POST['postcode'] : '' ),
            'tel'       => ( isset( $_POST['tel'] )         ? $_POST['tel'] : '' ),
            'email'     => ( isset( $_POST['email'] )       ? $_POST['email'] : '' ),
            'a4usage'   => ( isset( $_POST['a4usage'] )     ? $_POST['a4usage'] : '' ),
            'a3usage'   => ( isset( $_POST['a3usage'] )     ? $_POST['a3usage'] : '' ),
            'mode'      => ( isset( $_POST['mode'] )        ? $_POST['mode'] : '' ),
            'network'   => ( isset( $_POST['network'] )     ? $_POST['network'] : '' ),
            'duplex'    => ( isset( $_POST['duplex'] )      ? $_POST['duplex'] : '' ),
            'future'    => ( isset( $_POST['future'] )      ? $_POST['future'] : '' ),
            'printers'  => ( isset( $_POST['printers'] )    ? $_POST['printers'] : '' ),
            'make'      => ( isset( $_POST['make'] )        ? $_POST['make'] : '' ),
            'model'     => ( isset( $_POST['model'] )       ? $_POST['model'] : '' ),
        );
           
        // Update the meta field.
        update_post_meta( $post_id, '_application_data', $mydata );
        
        if( !isset( $_POST['location'] ) ) {
            if (preg_match("(([A-Z]{1,2}[0-9]{1,2})($|[ 0-9]{1,2}))", trim($_POST['postcode']), $match)) {
               $region=$match[1];
            }
            
            $args = array(
              'post_type'   => 'locations',
              'meta_query'  => array(
                array(
                  'key'     => '_postcodes',
                  'value'   => $region,
                  'compare' => 'LIKE',
                )
              ),
              'posts_per_page' => 1
            );

            $q = new WP_Query($args);
            if( $q-> found_posts > 0 ) {
               $location = $q->posts[0]->ID; 
           } else {
               $location = '';
           }
            
            // if a location is not found find the default
        } else {
            $location = $_POST['location'];
        }
        update_post_meta( $post_id, '_location', $location );
        
        wp_safe_redirect( $_POST['_wp_http_referer'] );
    }

    public function ajax_applcation_decision() {
        $output = array (
            'status'   => 0,
            'redirect' => null
        );
        if( $_POST['decision'] == 'accept' ) {
            $post = array(
                'ID'        => $_POST['post'],
                'post_type' => 'customers'
            );
            if( wp_update_post( $post ) != 0 ) {
                $data = get_post_meta( $_POST['post'], '_application_data', true );
                $user_id = username_exists( $data['email'] );
                if ( !$user_id and email_exists( $data['email'] ) == false ) {
                    $random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
                    $userdata = array(
                        'user_login'  =>  $data['email'],
                        'user_pass'   =>  $random_password,
                        'user_email'  =>  $data['email'],
                    );
                    $user_id = wp_insert_user( $userdata ) ;
                    $user = new WP_User( $user_id );
                    $user->set_role( 'contributor' );

                } else {
                    $random_password = __('User already exists.  Password inherited.');
                }
                $post = array(
                    'ID'        => $_POST['post'],
                    'post_author' => $user_id
                );
                wp_update_post( $post );
                
                $to = $data['email'];
                $subject = 'Your uPrint Logins';
                $body    = "<body>
                            <p>Please log in using the following details at <a href=\"http://uprint.tailoreddev.co.uk/login\">".esc_url( home_url( '/login' ) )."</a></p>

                            <p>Username: $data[email]</p>
                            <p>Password: $random_password</p>
                            ";
                $headers = array('Content-Type: text/html; charset=UTF-8');
                wp_mail( $to, $subject, $body, $headers );
                
                $blogusers = get_users( array( 'role' => 'administrator', 'fields' => array( 'user_email' ) ) );
                foreach ( $blogusers as $user ) {
                    $to = $user->user_email;
                    wp_mail( $to, $subject, $body, $headers );
                }
            }

            $output['status'] = 1;
        } elseif( $_POST['decision'] == 'reject' ) {
            if( wp_trash_post( $_POST['post'] ) ) {
                $output['status'] = 2;
                $output['redirect'] = admin_url( '/edit.php?post_type=applications ');
            } else {
                $output['status'] = 3;
            }
            
        }
        echo json_encode( $output );
        wp_die();
    }

    public function applications_columns ($defaults) {
        $defaults['company']  = 'Company';
        $defaults['a4usage']  = 'A4 Usage';
        $defaults['a3usage']  = 'A3 Usage';
        $defaults['printers'] = 'Printers';
        return $defaults;
    }

    public function applications_columns_content ($column_name, $post_ID) {
        $data = get_post_meta( $post_ID, '_application_data', true );
        //print_var($meta);
        $company  = isset( $data['company'] ) ? $data['company'] : '';
        $a4usage  = isset( $data['a4usage'] ) ? $data['a4usage'] : '';
        $a3usage  = isset( $data['a3usage'] ) ? $data['a3usage'] : '';
        $printers = isset( $data['printers'] ) ? $data['printers'] : '';

        if ($column_name == 'company') {
            echo $company;
        }

        if ($column_name == 'a4usage') {
            echo $a4usage;
        }

        if ($column_name == 'a3usage') {
            echo $a3usage;
        }

        if ($column_name == 'printers') {
            echo $printers;
        }

    }

    public function locations_columns ($defaults) {
        unset($defaults['date']);
        unset($defaults['author']);
        $defaults['default_loc']    = 'Default?';
        $defaults['town']           = 'Town';
        $defaults['postcode']       = 'Postcode';
        $defaults['telephone']      = 'Telephone';
        $defaults['email']          = 'Email Address';
        $defaults['postcode_areas'] = 'Postcodes Covered';
        return $defaults;
    }

    public function locations_columns_content ($column_name, $post_ID) {
        $data           = get_post_meta( $post_ID, '_location_data', true );
        $postcodes      = get_post_meta( $post_ID, '_postcodes', true );
        $default        = get_post_meta( $post_ID, '_default_loc', true );

        $default_loc    = ( isset( $default ) && $default == 1 ? '<span class="dashicons dashicons-yes"></span>' : '' );
        $town           = isset( $data['town'] ) ? $data['town'] : '';
        $postcode       = isset( $data['postcode'] ) ? $data['postcode'] : '';
        $telephone      = isset( $data['tel'] ) ? $data['tel'] : '';
        $email          = isset( $data['email'] ) ? '<a title="Email '.$data['email'].'" href="mailto:'.$data['email'].'">'.$data['email'].'</a>' : '';
        $postcode_areas = isset( $postcodes ) ? count( $postcodes ) : '0';
        

        if ($column_name == 'default_loc') {
            echo $default_loc;
        }

        if ($column_name == 'town') {
            echo $town;
        }

        if ($column_name == 'postcode') {
            echo $postcode;
        }

        if ($column_name == 'telephone') {
            echo $telephone;
        }

        if ($column_name == 'email') {
            echo $email;
        }

        if ($column_name == 'postcode_areas') {
            echo $postcode_areas;
        }

    }

    public function check_login_status() {
        if( is_page('account') || is_page( 'order' ) ) {
            if( !is_user_logged_in() || !current_user_can( 'add_orders' ) ) {
                wp_safe_redirect( home_url( '/login/' ) );
            }
        };
    }

    public function pu_login_failed( $user ) {
        // check what page the login attempt is coming from
        $referrer = $_SERVER['HTTP_REFERER'];

        // check that were not on the default login page
        if ( !empty($referrer) && !strstr($referrer,'wp-login') && !strstr($referrer,'wp-admin') && $user!=null ) {
            // make sure we don't already have a failed login attempt
            if ( !strstr($referrer, '?login=failed' )) {
                // Redirect to the login page and append a querystring of login failed
                wp_redirect( $referrer . '?login=failed');
            } else {
                wp_redirect( $referrer );
            }

            exit;
        }
    }

    public function pu_blank_login( $user ){
        $referrer = $_SERVER['HTTP_REFERER'];

        $error = false;

        if($_POST['log'] == '' || $_POST['pwd'] == '')
        {
            $error = true;
        }

        if ( !empty($referrer) && !strstr($referrer,'wp-login') && !strstr($referrer,'wp-admin') && $error ) {

            // make sure we don't already have a failed login attempt
            if ( !strstr($referrer, '?login=failed') ) {
                // Redirect to the login page and append a querystring of login failed
                wp_redirect( $referrer . '?login=failed' );
            } else {
                wp_redirect( $referrer );
            }

        exit;

        }
    }

    public function ajax_order_modal() {
        $data     = get_post_meta( $_POST['order'], '_order_data', true );
        $customer = get_post_meta( $data['customer'], '_application_data', true );  
    ?>
        <h5>Order Details</h5>
        <table class="table table-striped">
            <tr>
                <th>Order Date</th>
                <td><?php echo get_the_date( 'd/m/Y H:i:s', $_POST['order'] ); ?></td>
            </tr>
            <tr>
                <th>Company</th>
                <td><?php echo ( isset( $customer['company'] ) ? $customer['company']: '' ); ?></td>
            </tr>
            <tr>
                <th>Order Contact</th>
                <td><?php echo ( isset( $data['contact'] ) ? $data['contact']: '' ); ?></td>
            </tr>
            <tr>
                <th>Telephone</th>
                <td><?php echo ( isset( $data['tel'] ) ? $data['tel']: '' ); ?></td>
            </tr>
            <tr>
                <th>Order Status</th>
                <td><?php echo ( isset( $data['status'] ) ? $data['status']: '' ); ?></td>
            </tr>
        </table>
        <h5>Items Ordered</h5>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Cartridge Ref</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Printer</th>
            </thead>
            <tbody>
                <?php 
                    if( isset( $data['items'] ) && is_array( $data['items'] ) ) {
                        for( $i=0; $i<count($data['items']); $i++ ) { 
                            $item = $data['items'][$i];
                            ?>
                            <tr>
                                <td><?php echo ( isset( $item['ref'] ) ? $item['ref']: '' ); ?></td>
                                <td><?php echo ( isset( $item['desc'] ) ? $item['desc']: '' ); ?></td>
                                <td><?php echo ( isset( $item['qty'] ) ? $item['qty']: '' ); ?></td>
                                <td><?php echo ( isset( $item['printer'] ) ? $item['printer']: '' ); ?></td>
                            </tr>
                        <?php
                        }
                    } else {
                        echo '<tr><td colspan="4">No items</td>/tr>';
                    }
                ?>
            </tbody>
        </table>
    <?php
        wp_die();
    }

}