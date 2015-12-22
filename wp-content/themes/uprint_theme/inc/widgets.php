<?php

	class tim_recent_posts extends WP_Widget {
		public function __construct() {
			parent::__construct(
				'tim_recent_posts', // Base ID
				__('Tailored Marketing &raquo; Recent Posts', 'text_domain'), // Name
				array( 'description' => __( 'Shows the recent posts', 'text_domain' ), ) // Args
			);
		}
	
		public function widget( $args, $instance ) { ?>
			<?php echo $args['before_widget']; ?>
			<?php echo $args['before_title']; echo $instance['title']; echo $args['after_title']; ?>
            <div class="list-group">
			<?php 
				$arg = array( 'posts_per_page' => 5, 'post_type' => 'post', 'orderby' => 'date', 'order' => 'DESC' );
				$newposts = new WP_Query( $arg );
				while ( $newposts->have_posts() ) { $newposts->the_post(); 
			?>
			  <a class="list-group-item" href="<?php the_permalink();?>"><?php the_title(); ?></a>
			<?php
				}
				wp_reset_postdata();
			?>
			</div>
            <?php echo $args['after_widget']; ?>
        <?php
		}
	
		public function form( $instance ) {
			$instance = wp_parse_args( (array) $instance, array( 'title' => 'Recent posts', 'num' => 5 ) );
            $title = $instance['title'];
			$num   = $instance['num'];
?>
            <p><label for="<?php echo $this->get_field_id('phone'); ?>">Title: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label></p>
			<p><label for="<?php echo $this->get_field_id('num'); ?>">Number of posts: <input class="widefat" id="<?php echo $this->get_field_id('num'); ?>" name="<?php echo $this->get_field_name('num'); ?>" type="text" value="<?php echo esc_attr($num); ?>" /></label></p>
        <?php
		}
	
		public function update( $new_instance, $old_instance ) {
			$instance = $old_instance;
            $instance['title'] = $new_instance['title'];
			$instance['num']   = $new_instance['num'];
            return $instance;
		}
	}
	
	class tim_cats extends WP_Widget {
		public function __construct() {
			parent::__construct(
				'tim_cats', // Base ID
				__('Tailored Marketing &raquo; Categories', 'text_domain'), // Name
				array( 'description' => __( 'Shows the categories', 'text_domain' ), ) // Args
			);
		}
	
		public function widget( $args, $instance ) { ?>
			<?php echo $args['before_widget']; ?>
			<?php echo $args['before_title']; echo $instance['title']; echo $args['after_title']; ?>
            <div class="list-group">
			<?php 
				$categories = get_categories();
  				foreach($categories as $category) : 
			?>
			  <a class="list-group-item" href="/blog/category/<?php echo $category->slug; ?>/"><?php echo $category->cat_name ?></a>
			<?php
				endforeach; 
			?>
			</div>
            <?php echo $args['after_widget']; ?>
        <?php
		}
	
		public function form( $instance ) {
			$instance = wp_parse_args( (array) $instance, array( 'title' => 'Categories' ) );
            $title = $instance['title'];
?>
            <p><label for="<?php echo $this->get_field_id('phone'); ?>">Title: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label></p>
			
        <?php
		}
	
		public function update( $new_instance, $old_instance ) {
			$instance = $old_instance;
            $instance['title'] = $new_instance['title'];
            return $instance;
		}
	}