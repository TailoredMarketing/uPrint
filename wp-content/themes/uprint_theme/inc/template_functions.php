<?php
	
	add_filter( 'wp_pagenavi', 'ik_pagination', 10, 2 );
	
	function ik_pagination($html) {
		$out = '';
	  
		//wrap a's and span's in li's
		$out = str_replace("<div","",$html);
		$out = str_replace("class='wp-pagenavi'>","",$out);
		$out = str_replace("<a","<li><a",$out);
		$out = str_replace("</a>","</a></li>",$out);
		$out = str_replace("<span","<li><span",$out);  
		$out = str_replace("</span>","</span></li>",$out);
		$out = str_replace("</div>","",$out);
	  
		return '<ul class="pagination pagination-centered">'.$out.'</ul>';
	}
	
	function tailored_post_meta() {
        if( $category = get_the_category() ) {
            echo '<div class="post_meta">';
            if($category[0]){
                echo ' Posted in <a href="'.get_category_link($category[0]->term_id ).'">'.$category[0]->cat_name.'</a> on ';
            }
            the_time( get_option( 'date_format' ) );
            echo '</div>';
        }
    }
	
	function add_image_responsive_class($content) {
	   global $post;
	   $pattern ="/<img(.*?)class=\"(.*?)\"(.*?)>/i";
	   $replacement = '<img$1class="$2 img-responsive"$3>';
	   $content = preg_replace($pattern, $replacement, $content);
	   return $content;
	}
	add_filter('the_content', 'add_image_responsive_class');
	
	
	function custom_excerpt_length( $length ) {
		return 50;
	}
	add_filter( 'excerpt_length', 'custom_excerpt_length', 999 );
	
	function new_excerpt_more( $more ) {
		return '...';
	}
	add_filter('excerpt_more', 'new_excerpt_more');
	
	function the_quotes_posts() { ?>
		<?php 
			$args = array( 'posts_per_page' => 2, 'post_type' => 'quotes', 'orderby' => 'rand' );
			$myposts = new WP_Query( $args );
			$quotes = array();
			while ( $myposts->have_posts() ) { 
				$myposts->the_post(); 
				$quotes[] = array(
					'quote' => get_the_content(),
					'person' => get_the_title()
				);
			}
			wp_reset_postdata();
		?>
		<div class="container-fluid black-back" id="homequote">
			<div class="container text-center">
				<h2 class="animated fadeInDown"><?php echo $quotes[0]['quote']; ?><br><small><i><?php echo $quotes[0]['person']; ?></i></small></h2>
			</div>
		</div>
		<div class="container-fluid gold-back" id="homeblogs">
			<div class="container">
				<h2>Latest from the Blog</h2>
				<div class="row">
					<?php 
						$args = array( 'posts_per_page' => 4, 'post_type' => 'post' );
						$myposts = new WP_Query( $args );
						while ( $myposts->have_posts() ) : $myposts->the_post(); 
					?>
					<div class="col-md-6">
						<div class="thumbnail match">
						  <?php 
							if ( has_post_thumbnail() ) { // check if the post has a Post Thumbnail assigned to it.
								the_post_thumbnail( 'blog-home' );
							} 
						  ?>
						  <div class="caption">
							<h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
							<small>Posted 10/11/2015 by <a href="#">Kate</a>.</small>
						  </div>
						</div>
					</div>
					<?php
						endwhile; 
						wp_reset_postdata();
					?>
					<div class="col-md-24 text-right">
						<p><a href="/blog/" class="btn btn-primary">View More <i class="fa fa-sm fa-chevron-circle-right"></i></a></p>
					</div>
				</div>
			</div>
		</div>
		<div class="container-fluid black-back">
			<div class="container text-center">
				<h2 class="animated fadeInDown"><?php echo $quotes[1]['quote']; ?><br><small><i><?php echo $quotes[1]['person']; ?></i></small></h2>
			</div>
		</div>
		
	<?php
	}