<?php
/**
 * Post Types
 *
 * @package     Cooked
 * @subpackage  Post Types
 * @since       1.0.0
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cooked_Post_Types Class
 *
 * This class handles the post type creation.
 *
 * @since 1.0.0
 */
class Cooked_Post_Types {

	function __construct() {

		register_activation_hook( COOKED_PLUGIN_FILE, array( &$this, 'activation' ) );
		register_deactivation_hook( COOKED_PLUGIN_FILE, array( &$this, 'deactivation' ) );

		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'after_setup_theme', array( &$this, 'image_sizes' ) );
		add_action( 'template_redirect', array( &$this, 'redirects' ) );
		add_action( 'wp_head', array( &$this, 'cooked_meta_tags' ), 5 );
		add_action( 'manage_cp_recipe_posts_custom_column', array( &$this, 'custom_columns_data' ), 10, 2 );

		add_filter( 'enter_title_here', array( &$this, 'change_new_recipe_title' ) );
		add_filter( 'query_vars', array( &$this, 'add_query_vars_filter' ) );
		add_filter( 'manage_cp_recipe_posts_columns', array( &$this, 'custom_columns' ) );
		add_filter( 'nav_menu_css_class', array( &$this, 'cooked_nav_classes' ), 10, 2 );

	}

	function custom_columns( $columns ) {
	  	$new_columns = array();
		foreach( $columns as $key => $val ):
			$new_columns[$key] = $val;
			if ( $key == 'cb' ):
				$new_columns['featured_image'] = '<i class="cooked-icon cooked-icon-image"></i>';
			endif;
		endforeach;
		return $new_columns;
	}

	function custom_columns_data( $column, $post_id ) {
	    if ( $column == 'featured_image' ):
	        echo '<span class="cooked-admin-recipes-list-image">';
	    		echo the_post_thumbnail( 'thumbnail' );
	    	echo '</span>';
	    endif;
	}

	public static function cooked_nav_classes( $classes, $item ) {
		global $_cooked_settings;
		$blog_page_id = get_option( 'page_for_posts', false );
		$browse_page_id = $_cooked_settings['browse_page'];
	    if ( ( is_post_type_archive( 'cp_recipe' ) || is_singular( 'cp_recipe' ) )
	         && $item->object_id == $blog_page_id ){
	         $classes = array_diff( $classes, array( 'current_page_parent' ) );
	    }
	    if ( ( is_post_type_archive( 'cp_recipe' ) || is_singular( 'cp_recipe' ) )
	         && $item->object_id == $browse_page_id && is_array($classes) && !in_array( 'current_page_parent', $classes ) ){
	         $classes[] = 'current_page_parent';
	    }
	    return $classes;
	}

	public static function cooked_meta_tags(){

		global $_cooked_settings,$post;

		if ( isset($_cooked_settings['advanced']) && !empty($_cooked_settings['advanced']) && in_array( 'disable_meta_tags', $_cooked_settings['advanced'] ) )
			return false;

		if ( isset( $post->post_type ) && $post->post_type == 'cp_recipe' ):

			ob_start();

			$recipe = get_post( $post->ID );
			$recipe_settings = Cooked_Recipes::get( $post->ID, true );
			$image_url = false;

			if ( has_post_thumbnail($recipe) ) :
	   			$image_url = get_the_post_thumbnail_url( $recipe,'cooked-large' );
			endif; ?>

			<meta property="og:type" content="website">
			<meta property="og:title" content="<?php echo esc_attr( $post->post_title ); ?>">
			<meta property="og:description" content="<?php echo esc_attr( $recipe_settings['excerpt'] ); ?>">
			<meta property="og:image" content="<?php echo esc_attr( $image_url ); ?>">
			<meta property="og:locale" content="<?php echo esc_attr( get_locale() ); ?>">
			<meta property="og:url" content="<?php echo get_permalink( $post->ID ); ?>"><?php

			echo ob_get_clean();

		endif;

	}

	public static function add_query_vars_filter( $vars ){
		$vars[] = "servings";
		return $vars;
	}

	public function redirects(){

		$_cooked_settings = Cooked_Settings::get();
		$parent_page = ( isset($_cooked_settings['browse_page']) && $_cooked_settings['browse_page'] ? $_cooked_settings['browse_page'] : false );
		$front_page = get_option( 'page_on_front' );

		if ( $parent_page ):
			if ( is_post_type_archive('cp_recipe') ):
				if ( wp_redirect( get_permalink( $parent_page ) ) ):
					exit;
				endif;
			elseif ( is_tax('cp_recipe_category') ):
				global $wp_query;
				if ( isset($wp_query->query['cp_recipe_category']) && taxonomy_exists('cp_recipe_category') && term_exists( $wp_query->query['cp_recipe_category'], 'cp_recipe_category' )
				     || isset($wp_query->query['taxonomy']) && $wp_query->query['taxonomy'] == 'cp_recipe_category' && taxonomy_exists('cp_recipe_category') && term_exists( $wp_query->query['term'], 'cp_recipe_category' ) ):
					if ( $parent_page != $front_page && get_option('permalink_structure') ):
						if ( wp_redirect( esc_url_raw( untrailingslashit( get_permalink( $parent_page ) ) . '/' . $_cooked_settings['recipe_category_permalink'] . '/' . ( isset( $wp_query->query['term'] ) ? $wp_query->query['term'] : $wp_query->query['cp_recipe_category'] ) ) ) ):
							exit;
						endif;
					elseif ( $parent_page == $front_page ):
						if ( wp_redirect( esc_url_raw( get_home_url() . '?cp_recipe_category=' . ( isset( $wp_query->query['term'] ) ? $wp_query->query['term'] : $wp_query->query['cp_recipe_category'] ) ) ) ):
							exit;
						endif;
					else:
						if ( wp_redirect( esc_url_raw( get_permalink( $parent_page ) . '&cp_recipe_category=' . ( isset( $wp_query->query['term'] ) ? $wp_query->query['term'] : $wp_query->query['cp_recipe_category'] ) ) ) ):
							exit;
						endif;
					endif;
				endif;
			endif;
		endif;

	}

	public function activation(){
		self::init();
		Cooked_Roles::add_roles();
		Cooked_Roles::add_caps();
		flush_rewrite_rules();
	}

	public function deactivation(){
		Cooked_Roles::remove_caps();
		Cooked_Roles::remove_roles();
		flush_rewrite_rules();
	}

	public static function init(){

		global $_cooked_settings,$wpdb;
		$_cooked_settings = Cooked_Settings::get();
		$_cooked_taxonomies = Cooked_Taxonomies::get();

		$parent_page_slug = ( isset($_cooked_settings['browse_page']) && $_cooked_settings['browse_page'] ? ltrim( untrailingslashit( str_replace( home_url(), '', get_permalink( $_cooked_settings['browse_page'] ) ) ), '/' ) : false );

		if(!empty($_GET['settings-updated'])) {

			$taxonomy_settings_update = apply_filters( 'cooked_taxonomy_settings_update', array(
					'recipe_permalink' => ( !$_cooked_settings['recipe_permalink'] ? 'recipes' : sanitize_title_with_dashes( $_cooked_settings['recipe_permalink'], null, 'save' ) ),
					'recipe_author_permalink' => ( !$_cooked_settings['recipe_author_permalink'] || 'author' == $_cooked_settings['recipe_author_permalink'] ? 'recipe-author' : sanitize_title_with_dashes( $_cooked_settings['recipe_author_permalink'], null, 'save' ) ),
					'recipe_category_permalink' => ( !$_cooked_settings['recipe_category_permalink'] ? 'recipe-category' : sanitize_title_with_dashes( $_cooked_settings['recipe_category_permalink'], null, 'save' ) )
				)
			);

			foreach( $taxonomy_settings_update as $setting_key => $setting_value ):
				$_cooked_settings[ $setting_key ] = $setting_value;
			endforeach;

			update_option( 'cooked_settings',$_cooked_settings );
			update_option( 'cooked_settings_saved',true );

			flush_rewrite_rules();

		}

		global $cooked_taxonomies_for_menu;

		if ( !empty($_cooked_taxonomies) ):
			foreach( $_cooked_taxonomies as $slug => $args ):
				register_taxonomy( $slug, array('cp_recipe'), $args );
				if ( $parent_page_slug ):
					add_rewrite_rule('^' . $parent_page_slug . '/' . $args['rewrite']['slug'] . '/([^/]*)/page/([^/]*)/?', 'index.php?page_id='.$_cooked_settings['browse_page'].'&paged=$matches[2]&'.$slug.'=$matches[1]', 'top');
					add_rewrite_rule('^' . $parent_page_slug . '/' . $args['rewrite']['slug'] . '/([^/]*)/?', 'index.php?page_id='.$_cooked_settings['browse_page'].'&'.$slug.'=$matches[1]', 'top');
				endif;
				$cooked_taxonomies_for_menu[] = array(
					'menu' => 'cooked_recipes_menu',
					'name' => $args['labels']['menu_name'],
					'capability' => 'manage_categories',
					'url' => 'edit-tags.php?taxonomy=' . $slug . '&post_type=cp_recipe'
				);
			endforeach;
		endif;

		$post_types = self::get();
		if ( !empty($post_types) ):
			foreach( $post_types as $slug => $args ):
				register_post_type( $slug, $args );
			endforeach;
		endif;

	}

	public static function image_sizes(){
		add_image_size( 'cooked-square', 700, 700, true );
		add_image_size( 'cooked-medium', 700, 525, true );
		add_image_size( 'cooked-large', 2000, 2000 );
	}

	public static function get() {

		global $_cooked_settings;
		$recipe_permalink = ( isset($_cooked_settings['recipe_permalink']) && $_cooked_settings['recipe_permalink'] ? $_cooked_settings['recipe_permalink'] : 'recipes' );
		$public_recipes = true;
		$has_archive_slug = sanitize_title_with_dashes( __('Recipe Archive','cooked') );
		$exclude_from_search = false;

		if ( !isset($_GET['print']) && isset( $_cooked_settings['advanced'] ) && in_array( 'disable_public_recipes', $_cooked_settings['advanced'] ) ):
			$public_recipes = false;
			$has_archive_slug = false;
			$exclude_from_search = true;
		endif;

		$post_types = apply_filters( 'cooked_post_types', array(
			'cp_recipe' => array(
				'labels' => array(
					'name'               => esc_html__( 'Recipes', 'cooked' ),
					'singular_name'      => esc_html__( 'Recipe', 'cooked' ),
					'menu_name'          => esc_html__( 'Recipes', 'cooked' ),
					'name_admin_bar'     => esc_html__( 'Recipe', 'cooked' ),
					'add_new'            => esc_html__( 'Add New', 'cooked' ),
					'add_new_item'       => esc_html__( 'Add New Recipe', 'cooked' ),
					'new_item'           => esc_html__( 'New Recipe', 'cooked' ),
					'edit_item'          => esc_html__( 'Edit Recipe', 'cooked' ),
					'view_item'          => esc_html__( 'View Recipe', 'cooked' ),
					'all_items'          => esc_html__( 'All Recipes', 'cooked' ),
					'search_items'       => esc_html__( 'Search Recipes', 'cooked' ),
					'not_found'          => esc_html__( 'No recipes found.', 'cooked' ),
					'not_found_in_trash' => esc_html__( 'No recipes found in trash.', 'cooked' )
				),
				'description' => esc_html__('Recipes','cooked'),
				'public' => $public_recipes,
				'show_ui' => true,
				'show_in_admin_bar' => true,
				'show_in_menu' => 'cooked_recipes_menu',
				'show_in_rest' => true,
				'rest_base' => 'cooked_recipe',
				'rest_controller_class' => 'WP_REST_Posts_Controller',
				'exclude_from_search' => $exclude_from_search,
				'has_archive' => $has_archive_slug,
				'menu_position' => 25,
				'supports' => array( 'title', 'editor', 'thumbnail', 'comments', 'author' ),
				'rewrite' => array(
					'with_front' => false,
					'slug' => $recipe_permalink
				)
			))
		);

		return $post_types;

	}

	public function change_new_recipe_title( $title ) {

		$screen = get_current_screen();
		if  ( 'cp_recipe' == $screen->post_type ) {
			$title = esc_html__('Recipe title ...','cooked');
		}

		return $title;

	}

}
