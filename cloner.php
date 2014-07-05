<?php
/*
Plugin Name: Cloner
Plugin URI: 
Description: 
Author: WPMU DEV
Author URI: http://premium.wpmudev.org/
Version: 1.0
Network: true
Text Domain: blog_templates
Domain Path: lang
*/

/*  Copyright 2010-2011 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class WPMUDEV_Cloner {

	public $admin_menu_id;

	public function __construct() {
		$this->set_constants();
		$this->includes();

		add_filter( 'manage_sites_action_links', array( &$this, 'add_site_action_link' ), 10, 2 );
		add_action( 'network_admin_menu', array( &$this, 'add_admin_menu' ) );
	}


	private function set_constants() {
		define( 'WPMUDEV_CLONER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	}

	private function includes() {
		include_once( WPMUDEV_CLONER_PLUGIN_DIR . '/copier/copier.php' );
	}

	/**
	 * Add a new action link in the Network Sites Page that clones a site
	 */
	public function add_site_action_link( $links, $blog_id ) {
		$clone_url = add_query_arg( 'blog_id', $blog_id, network_admin_url( 'index.php?page=clone_site' ) );

		if ( ! is_main_site( $blog_id ) && $blog_id !== 1 )
			$links['clone'] = '<span class="clone"><a href="' . $clone_url . '">Clone</a></span>';

		return $links;
	}

	/**
	 * Add a hidden menu in admin that displays the form
	 * to clone a site
	 */
	public function add_admin_menu() {
		$this->admin_menu_id = add_submenu_page( null, __( 'Clone Site', 'wpmudev-cloner' ), __( 'Clone Site', 'wpmudev-cloner' ), 'manage_network', 'clone_site', array( &$this, 'render_admin_menu' ) );

		// Sanitize the form when the menu is loaded
		add_action( 'load-' . $this->admin_menu_id, array( $this, 'sanitize_clone_form' ) );
	}

	/**
	 * Render the admin menu
	 */
	public function render_admin_menu() {
		global $current_site;

		if ( isset( $_REQUEST['cloned'] ) ) {
			// Site has been cloned
			$messages = array(
				sprintf( __( 'Your new site has been cloned. <a href="%s">Go to dashboard</a>', 'wpmudev-cloner' ), get_admin_url( $_REQUEST['cloned'] ) )
			);
		}

		$blog_id = absint( $_REQUEST['blog_id'] );
		?>
			<div class="wrap">
				<h2><?php _e( 'Clone Site', 'wpmudev-cloner' ); ?></h2>
				
					<?php
					if ( ! empty( $messages ) ) {
						foreach ( $messages as $msg )
							echo '<div id="message" class="updated"><p>' . $msg . '</p></div>';
					} ?>
					<form method="post" action="<?php echo add_query_arg( 'action', 'clone', network_admin_url( 'index.php?page=clone_site' ) ); ?>">

						<?php wp_nonce_field( 'clone-site-' . $blog_id, '_wpnonce_clone-site' ) ?>
						<input type="hidden" name="blog_id" value="<?php echo $blog_id; ?>" />

						<table class="form-table">
							<tr class="form-field form-required">
								<th scope="row"><?php _e( 'Site Address' ) ?></th>
								<td>
									<?php if ( is_subdomain_install() ) { ?>
										<input name="blog" type="text" class="regular-text" title="<?php esc_attr_e( 'Domain' ) ?>"/><span class="no-break">.<?php echo preg_replace( '|^www\.|', '', $current_site->domain ); ?></span>
									<?php } else {
										echo $current_site->domain . $current_site->path ?><input name="blog" class="regular-text" type="text" title="<?php esc_attr_e( 'Domain' ) ?>"/>
									<?php }
									echo '<p>' . __( 'Only lowercase letters (a-z) and numbers are allowed.' ) . '</p>';
									?>
								</td>
							</tr>
						</table>
						<?php submit_button( __( 'Clone Site', 'wpmudev-cloner' ), 'primary', 'clone-site-submit' ); ?>
					</form>
			</div>
		<?php
	}

	/**
	 * Sanitize the clone form
	 */
	function sanitize_clone_form() {
		$blog_id = ! empty( $_REQUEST['blog_id'] ) ? absint( $_REQUEST['blog_id'] ) : 0;
		$blog_details = get_blog_details( $blog_id );

		// Does the source blog exists?
		if ( ! $blog_id || empty( $blog_details ) )
			wp_die( __( 'The blog that you are trying to copy does not exist', 'wpmudev-cloner' ) );

		if ( ! empty( $_REQUEST['clone-site-submit'] ) ) {
			// Submitting form
			check_admin_referer( 'clone-site-' . $blog_id, '_wpnonce_clone-site' );

			if ( empty( $_REQUEST['blog'] ) )
				wp_die( __( 'Can&#8217;t create an empty site.' ) );

			$blog = $_REQUEST['blog'];

			// Sanitize the domain/subfolder
			$domain = '';
			if ( preg_match( '|^([a-zA-Z0-9-])+$|', $blog ) )
				$domain = strtolower( $blog );

			if ( empty( $domain ) )
				wp_die( __( 'Missing or invalid site address.' ) );

			$dest_blog_details = get_blog_details( $domain );

			if ( ! isset( $_REQUEST['confirm'] ) && ! empty( $dest_blog_details ) ) {

				// Source and destination blogs are the same!
				if ( $dest_blog_details->blog_id == $blog_id )
					wp_die( 'You cannot copy a blog to itself', 'wpmudev-cloner' );
				
				$clone_link = add_query_arg( 
					array(
						'action' => 'clone',
						'blog' => $domain,
						'blog_id' => $blog_id,
						'confirm' => 'true',
						'clone-site-submit' => 'true'
					), 
					network_admin_url( 'index.php?page=clone_site' ) 
				);

				$clone_link = wp_nonce_url( $clone_link, 'clone-site-' . $blog_id, '_wpnonce_clone-site' );
				ob_start();
				?>
					<p><?php printf( __( 'You have chosen a URL that already exists. If you choose ‘Continue’, all existing site content and settings on %s will be completely overwritten with content and settings from %s. This change is permanent and can’t be undone, so please be careful. ', 'wpmudev-cloner' ), '<strong>' . get_site_url( $dest_blog_details->blog_id ) . '</strong>', '<strong>' . get_site_url( $blog_details->blog_id ) . '</strong>' ); ?></p>
					<a href="<?php echo $clone_link; ?>" class="button button-primary"><?php _e( 'Continue', 'wpmudev-cloner' ); ?></a>
				<?php
				$content = ob_get_clean();
				wp_die( $content );
			}
			
			$blog_details = get_blog_details( $domain );
		    if ( ! empty( $blog_details ) ) {
		    	// Do ot clone to the main site
		        if ( $blog_details->blog_id === 1 || is_main_site( $blog_details->blog_id ) )
		            wp_die( __( 'Sorry, main site cannot be overwritten' ) );

		        if ( $blog_details->blog_id == $blog_id )
		            wp_die( __( 'You cannot clone a blog to its own domain' ) );

		        // The blog must be overwritten because it already exists
		        $args['override'] = absint( $blog_details->blog_id );
		    }

			
			// New Blog Templates integration
			if ( class_exists( 'blog_templates' ) ) {
				$action_order = defined('NBT_APPLY_TEMPLATE_ACTION_ORDER') && NBT_APPLY_TEMPLATE_ACTION_ORDER ? NBT_APPLY_TEMPLATE_ACTION_ORDER : 9999;
				// Set to *very high* so this runs after every other action; also, accepts 6 params so we can get to meta
				remove_action( 'wpmu_new_blog', array( 'blog_templates', 'set_blog_defaults'), apply_filters('blog_templates-actions-action_order', $action_order), 6); 
			}

			$current_site = get_current_site();

			if ( is_subdomain_install() ) {
				$domain = $domain . '.' . $current_site->domain;
				$path = '';
			}
			else {
				$path = '/' . $domain;
				$domain = $current_site->domain;
			}

			// Set everything needed to clone the site
			$result = $this->pre_clone_actions( $blog_id, $domain, $path, $args );

			if ( is_integer( $result ) ) {
				$redirect_to = get_admin_url( $result );
				wp_redirect( $redirect_to );	
				exit;
			}
			
		}
	}

	/**
	 * Set everything needed to clone a site:
	 * 
	 * Create a new empty site if we are not overrriding an existsing blog
	 * Change the blog Name
	 */
	public function pre_clone_actions( $source_blog_id, $domain, $path, $args ) {
        global $wpdb;

        $defaults = array(
            'override' => false
        );
        $args = wp_parse_args( $args, $defaults );
        extract( $args );

        $blog_details = get_blog_details( $override );
        if ( empty( $blog_details ) )
            $override = false;

        $new_blog_id = $override;
        if ( ! $override ) {
        	// Overrriding, let's create an  empty blog
            $new_blog_id = create_empty_blog( $domain, $path, 'aaaaa' );            
        }

        if ( ! is_integer( $new_blog_id ) )
            return new WP_Error( 'create_empty_blog', strip_tags( $new_blog_id ) );

        $source_blog_details = get_blog_details( $source_blog_id );

        // Update the blog name
        update_blog_option( $new_blog_id, 'blogname', $source_blog_details->blogname );

        // And set copier arguments
        $result = copier_set_copier_args( $source_blog_id, $new_blog_id );

        return $new_blog_id;
    }

}

$nbt_cloner = new WPMUDEV_Cloner();