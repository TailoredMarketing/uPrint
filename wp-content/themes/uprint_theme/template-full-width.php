<?php 
/*
Template Name: Full Width
*/
?>
<?php get_header(); ?>
        <div id="main-container" class="container-fluid">
            <div id="main" class="container">
                <div class="row">
                    <div id="content" class="col-md-12">
                        <?php if( have_posts() ): while( have_posts() ): the_post(); ?>

                            <?php echo get_template_part( 'content', get_post_type() ); ?>

                        <?php endwhile; endif; ?>  
                    </div>
                </div>
            </div>
        </div>
<?php get_footer(); ?>