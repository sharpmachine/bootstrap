<header id="header">
	<div class="navbar navbar-fixed-top">
		<div class="navbar-inner">
			<div class="container">
				<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
	    			<span class="icon-bar"></span>
	    			<span class="icon-bar"></span>
	    			<span class="icon-bar"></span>
				</a>
				<a class="brand" href="<?php bloginfo('url') ?>"><?php bloginfo('name'); ?></a>
				<nav class="nav-collapse">
	    			<ul class="nav">
						<?php wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'menu_class' => 'nav', 'items_wrap' => '%3$s', 'walker' => new Bootstrap_Menu_Walker ) ); ?>
	    			</ul>
				</nav><!--/.nav-collapse -->
			</div>
		</div>
	</div>
</header>