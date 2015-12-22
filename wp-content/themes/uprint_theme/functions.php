<?php
require_once('inc/wp_bootstrap_navwalker.php');
require_once('inc/template_functions.php');
require_once('inc/widgets.php');
class tailored_theme_class {
    
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'init', array( $this, 'register_image_sizes' ) );
        add_action( 'init', array( $this, 'register_sidebars' ) );
		add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'init', array( $this, 'register_menus' ) );
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );
		if ( ! isset( $content_width ) ) $content_width = 1070;
		
        add_theme_support( 'post-thumbnails' );
		add_theme_support( 'title-tag' );
    }
	
    public function enqueue_scripts(){
		
		wp_enqueue_style( 'bootstrap', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css', '1.0');
		wp_enqueue_style( 'fontawesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css', '1.0');
		wp_enqueue_style( 'google-fonts', '//fonts.googleapis.com/css?family=Roboto:400,500,700|Open+Sans:400,300,400italic,600', '1.0');
		wp_enqueue_style( 'theme-style', get_stylesheet_directory_uri() . '/css/main.css', array( 'bootstrap', 'fontawesome', 'google-fonts' ) );
		wp_enqueue_style( 'animate', get_stylesheet_directory_uri() . '/css/animate.css', array() );
		
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'bootstrap-js', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js', array( 'jquery'), '1.0', true );
		wp_enqueue_script( 'matchHeight-js', get_stylesheet_directory_uri() . '/js/jquery.matchHeight-min.js', array( 'jquery' ), '1.0', true );
		wp_enqueue_script( 'custom-js', get_stylesheet_directory_uri() . '/js/front-end.js', array( 'jquery', 'bootstrap-js' ), '1.0', true );
		
	}
    
    public function register_image_sizes() {
        add_image_size( 'home-blog', 600, 350, true ); 
        add_image_size( 'custom-medium', 451, 347, true ); 
        add_image_size( 'custom-small', 65, 65, true ); 
        add_image_size( 'blog-home', 300, 200, true ); 
		add_image_size( 'blog-home-lg', 455, 228, true ); 
    }
    	
    public function register_menus() {
        register_nav_menus( array(
            'primary' => __( 'Primary Menu', 'sosen' ),
        ) );
		
		register_nav_menus( array(
            'footer' => __( 'Footer Menu', 'sosen' ),
        ) );
				
    }
    
	public function register_widgets (){
		//register_widget( 'tim_recent_posts' );
		//register_widget( 'tim_cats' );
	}
	
    public function register_sidebars() {
		register_sidebar( array(
			'name' => __( 'Main Sidebar', 'seowned' ),
			'id' => 'main_sidebar',
			'before_widget' => '<div class="panel panel-default">aa',
			'after_widget' => "</div></div>",
			'before_title' => '<div class="panel-heading"><h3 class="panel-title">',
			'after_title' => '</h3></div><div class="panel-body">',
		) );
				
		register_sidebar( array(
			'name' => __( 'Blog Sidebar', 'seowned' ),
			'id' => 'blog_sidebar',
			'before_widget' => '<div class="panel panel-default">',
			'after_widget' => "</div>",
			'before_title' => '<div class="panel-heading"><h3 class="panel-title">',
			'after_title' => '</h3></div>',
		) );
        
	}	
	
	public function register_post_types() {
		
	}
}
$tailored_theme = new tailored_theme_class();