<?php get_header(); ?>
        <div id="main-container" class="container-fluid">
            <div id="main" class="container">
                <div class="row">
                    <div id="content" class="col-md-9">

                        <?php if ( is_home() && ! is_front_page() ) : ?>
                            <h1 class="page-title"><?php single_post_title(); ?></h1>
                        <?php endif; ?>

                        <?php if( have_posts() ): while( have_posts() ): the_post(); ?>

                            <?php echo get_template_part( 'content', get_post_type() ); ?>

                        <?php endwhile; endif; ?>  
                    </div>
                    <div class="col-md-3" id="sidebar">
                        <?php get_sidebar(); ?>
                    </div>
                </div>
            </div>
        </div>
<?php get_footer(); ?>