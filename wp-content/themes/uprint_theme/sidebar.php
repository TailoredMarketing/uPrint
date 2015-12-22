<?php
	if( is_page() && is_active_sidebar( 'main_sidebar' ) ) {
		dynamic_sidebar( 'main_sidebar' );
	}

	if( ( is_home() && !is_front_page() ) || is_single() || is_archive() ) {
		dynamic_sidebar( 'blog_sidebar' );
	}