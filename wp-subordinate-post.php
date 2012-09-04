<?php

if ( !defined('ABSPATH') )
  die('-1');

if( ! class_exists('WP_Subordinate_Post_Factory')) {
	class WP_Subordinate_Post_Factory {

		public $post_type = array();
		protected $show_parent_column = true;
		protected $show_type_parent = false;
		protected $hierarchical_delim = '&#8212;';
		protected $namespace = 'wp_subordinate_post';

		protected $rewrite_rules = array();

		function __construct(){
			// play nice with PHP_INT_MAX incase someone really wants to override - thanks Daniel @MZAWeb
			add_action( 'registered_post_type', array($this,'registered_post_type'), PHP_INT_MAX - 1000, 2 );
			add_action( 'admin_menu', array( $this, 'admin_menu' ));
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ));
			add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_custom_box' ), 10, 2 );
			// hook the get_permalink method to build custom session link
			add_filter( 'post_type_link', array( $this, 'post_type_link'), 10, 2);
			add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), PHP_INT_MAX - 1000 );

			add_action( 'save_post', array( $this, 'save_post' ) );

			// reset dropdown args for parent pages because it looses track of posts with remote parents
			add_filter( 'quick_edit_dropdown_pages_args', array( $this, 'dropdown_pages_args' ));
			add_filter( 'page_attributes_dropdown_pages_args', array( $this, 'dropdown_pages_args' ));

			// apply filter overrides to settings
			$this->show_parent_column = apply_filters("{$this->namespace}_show_parent_column", $this->show_parent_column );
			$this->show_type_parent = apply_filters("{$this->namespace}_show_type_parent", $this->show_type_parent );
		}

		public function maybe_flush_rewrite_rules() {
			$key = $this->namespace . "_rw_hash";

			$hash = md5( maybe_serialize( $this->rewrite_rules ) );

			if ( $hash !== get_option( $key ) ) {
				update_option( $key, $hash );
				flush_rewrite_rules();
			}
		}

		public function add_rewrite_rules( $parent, $child ) {
			
			$parent_object = get_post_type_object($parent);

			if ( is_array( $parent_object->rewrite ) && isset( $parent_object->rewrite["slug"] ) ) {
				$parent = $parent_object->rewrite["slug"];
			}

			add_rewrite_rule( '^' . $parent . '/([^/]*)/([^/]*)/?', 'index.php?' . $child . '=$matches[2]', 'top' );
			
			$this->rewrite_rules[$parent] = $child;
		}

		public function deactivate() {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();	
		}

		public function post_type_link( $permalink, $post ) {
			if ( get_option('permalink_structure') && isset( $post->post_parent ) && $post->post_parent > 0 && array_key_exists($post->post_type, $this->post_type) ) {
				$pre_built = $this->walk_parent_permalink( $post->post_parent, array( 'link' => '/' . $post->post_name, 'post_type' => $post->post_type ));
				return trailingslashit( $pre_built['link'] );
			}
			return $permalink;
		}

		function walk_parent_permalink( $post_id, $args = array() ){
			$defaults = array(
				'link' => '',
				'post_type' => null
				);
			$args = wp_parse_args( $args, $defaults );
			$parent = get_post( $post_id );
			if( $parent->post_parent > 0 && $parent->post_type == $args['post_type']) {
				return $this->walk_parent_permalink( $parent->post_parent, array( 'link' => '/' . $parent->post_name . $args['link'], 'post_type' => $parent->post_type ));
			} else {
				return array( 'link' => untrailingslashit( get_permalink( $parent->ID ) ) . $args['link'], 'post_type' => $parent->post_type );
			}
		}

		function dropdown_pages_args( $args ){
			$screen = get_current_screen();
			$query_args = array(
				'post_type' => $screen->post_type,
				'posts_per_page' => -1,
				'post_status' => 'any',
				'fields' => 'ids'
				);
			$force_include = new WP_Query( $query_args );
			$args['include'] = $force_include->posts;
			return $args;
		}

		public function registered_post_type( $post_type, $args ){
			global $wp_post_types;
			$set_association = array();
			// find associated parent
			if( array_key_exists('parent', $args) ) {
				$set_association[$post_type] = $args->parent;				
			}
			// find all associated children
			if( array_key_exists('children', $args) ) {
				$children = ( is_array($args->children) ) ? $args->children : array($args->children);
				foreach($children as $child){
					$set_association[ $child ] = $post_type;
				}
			}
			foreach($set_association as $ptype => $parent){
				$this->post_type[ $ptype ] = $parent;
				add_filter( 'manage_' . $ptype . '_posts_columns' , array($this,'manage__columns'));
				add_action( 'manage_' . $ptype . '_posts_custom_column' , array($this,'manage__custom_column'), 10, 2 );
				// add_filter( 'manage_' . $ptype . '_sortable_columns', array($this,'manage__sortable_columns'));

				$this->add_rewrite_rules( $parent, $ptype );
			}

		}

		function quick_edit_custom_box( $column_name, $post_type ) {
		    if( $column_name == 'parent' ) {
		    	// load js for setting up parent post type select
				add_action( 'admin_footer-edit.php', array( $this, 'quick_edit_custom_box_js'), 20);
?>
				<fieldset class="inline-edit-col-left"><div class="inline-edit-col">&nbsp;</div></fieldset>
			    <fieldset class="inline-edit-col-right inline-edit-<?php echo $post_type; ?>">
			      <div class="inline-edit-col inline-edit-<?php echo $column_name ?>">
			      	<?php $this->render_parent_list(); ?>
			      </div>
			    </fieldset>
<?php
			}
		}

		public function quick_edit_custom_box_js(){
	      	?><script type="text/javascript">
				function quick_edit_custom_box_js() {
				    var $ = jQuery;
				    var _edit = inlineEditPost.edit;
				    inlineEditPost.edit = function(id) {
				        var args = [].slice.call(arguments);
				        _edit.apply(this, args);

				        if (typeof(id) == 'object') {
				            id = this.getId(id);
				        }

			            var
			            editRow = $('#edit-' + id),
			            postRow = $('#post-'+id);
			            post_parent_id = $('.column-parent', postRow).find("input[name='post_parent_id']").val(),

			            // set the values in the quick-editor
			            $(':input[name="<?php echo "{$this->namespace}_id"; ?>"]', editRow).val(post_parent_id);
				    };
				}
				if (inlineEditPost) {
				    quick_edit_custom_box_js();
				} else {
				    jQuery(quick_edit_custom_box_js);
				}
			</script><?php
		}

		public function admin_menu(){
			foreach( $this->post_type as $ptype => $ptype_parent ) {
				$ptype_obj = get_post_type_object( $ptype );
				// prevent submenu items from showing if show_in_menu is false
				if( $ptype_obj->show_in_menu === false )
					continue;
				$all_items = apply_filters("{$this->namespace}_submenu_all_items", $this->submenu_label($ptype_obj->labels->all_items), $ptype_obj->labels->all_items );
				$add_new_item = apply_filters("{$this->namespace}_submenu_add_new_item", $this->submenu_label($ptype_obj->labels->add_new_item), $ptype_obj->labels->add_new_item );
				// need to add a posts page instead of submenu if parent post type is 'post'
				if( $ptype_parent == 'post' ) {
					add_posts_page( $ptype_obj->labels->name, $ptype_obj->labels->name, $ptype_obj->cap->edit_posts, "edit.php?post_type=$ptype");
					add_posts_page( $ptype_obj->labels->all_items, $all_items, $ptype_obj->cap->edit_posts, "edit.php?post_type=$ptype");
					add_posts_page( $ptype_obj->labels->add_new_item, $add_new_item, $ptype_obj->cap->edit_posts, "post-new.php?post_type=$ptype");
				} else {
					add_submenu_page( "edit.php?post_type=$ptype_parent", $ptype_obj->labels->name, $ptype_obj->labels->name, $ptype_obj->cap->edit_posts, "edit.php?post_type=$ptype");
					add_submenu_page( "edit.php?post_type=$ptype_parent", $ptype_obj->labels->all_items, $all_items, $ptype_obj->cap->edit_posts, "edit.php?post_type=$ptype");
					add_submenu_page( "edit.php?post_type=$ptype_parent", $ptype_obj->labels->add_new_item, $add_new_item, $ptype_obj->cap->edit_posts, "post-new.php?post_type=$ptype");
				}
				remove_menu_page( "edit.php?post_type=$ptype" );
			}
		}

		public function manage__columns( $columns ){
			$col['parent'] = 1;
			$col['title'] = 2;
			$screen = get_current_screen();
			if( array_key_exists($screen->post_type, $this->post_type) ) {
				$ptype_obj = get_post_type_object( $screen->post_type );
				// bump parent column in after checkbox
				if( $this->show_parent_column ) {
					$parent_ptype_obj = get_post_type_object( $this->post_type[ $screen->post_type ] );
					$columns = array_slice($columns, 0, $col['parent'], true) +
						array('parent' => apply_filters( "{$this->namespace}_parent_column_label", ( ! $this->show_type_parent ) ? sprintf( __('Parent %s'), $parent_ptype_obj->labels->singular_name) : __('Parent'))) +
						array_slice($columns, $col['parent'], NULL, true);
				} else {
					$col['title'] = 1;
				}
				if( $ptype_obj->hierarchical ) {
					// if hierarchical we want to unset the original title column because of title formatting
					unset($columns['title']);
					// bump title column in after parent
					$columns = array_slice($columns, 0, $col['title'], true) +
						array('title_subordinate_hierarchical' => __('Title')) +
						array_slice($columns, $col['title'], NULL, true);
				}
			}
			return $columns;
		}

		// unused
		public function manage__sortable_columns( $columns ){
			return $columns;
		}

		public function manage__custom_column( $column_name, $post_id ) {
			global $post;
			
			$ptype_obj = get_post_type_object( $post->post_type );

			if( ! empty($post->post_parent) )
				$parent_ptype_obj = get_post_type_object( get_post_type( $post->post_parent ) );

			switch($column_name) {
				case 'parent':
					// setup post_parent ID for parent post type select option
					echo '<input type="hidden" name="post_parent_id" value="' . $post->post_parent . '" />';
					if( ! empty($post->post_parent) ) {
						if( $ptype_obj->name != $parent_ptype_obj->name || $this->show_type_parent )
							echo apply_filters("{$this->namespace}_{$ptype_obj->name}_column_parent", '<a href="' . get_edit_post_link( $post->post_parent ) . '">' . get_the_title( $post->post_parent ) . '</a>');
							echo ($this->show_type_parent) ? ' (' . $parent_ptype_obj->labels->singular_name . ')' : '';
					}
					break;
				// hat tip /wp-admin/includes/class-wp-posts-list-table.php 'single_row'
				case 'title_subordinate_hierarchical':
					$level = 0;
					$pad = '';
					$can_edit_post = current_user_can( $ptype_obj->cap->edit_post, $post_id );
					$title = _draft_or_post_title();

					if ( 0 == $level && (int) $post->post_parent > 0 ) {
						//sent level 0 by accident, by default, or because we don't know the actual level
						$find_main_page = (int) $post->post_parent;
						while ( $find_main_page > 0 ) {
							$parent = get_post( $find_main_page );

							if ( is_null( $parent ) )
								break;

							if( ! in_array( $parent->post_type, $this->post_type ) )
								$level++;

							$find_main_page = (int) $parent->post_parent;
						}
					}

					$pad .= str_repeat( $this->hierarchical_delim . ' ', $level );

					ob_start();
?>
					<strong><?php if ( $can_edit_post && $post->post_status != 'trash' ) { ?>
						<a class="row-title" href="<?php echo get_edit_post_link( $post_id ); ?>" title="<?php echo esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $title ) ); ?>"><?php echo $pad; echo $title; ?></a>
						<?php 

						} else { 
							echo $pad; 
							echo $title; 
						}; 

						_post_states( $post ); 

						?>
					</strong>
<?php
					$title_html = ob_get_clean();

					echo apply_filters("{$this->namespace}_{$ptype_obj->name}_column_title", $title_html);

					$actions = array();
					if ( $can_edit_post && 'trash' != $post->post_status ) {
						$actions['edit'] = '<a href="' . get_edit_post_link( $post_id ) . '" title="' . esc_attr( __( 'Edit this item' ) ) . '">' . __( 'Edit' ) . '</a>';
						$actions['inline hide-if-no-js'] = '<a href="#" class="editinline" title="' . esc_attr( __( 'Edit this item inline' ) ) . '">' . __( 'Quick&nbsp;Edit' ) . '</a>';
					}
					if ( current_user_can( $ptype_obj->cap->delete_post, $post_id ) ) {
						if ( 'trash' == $post->post_status )
							$actions['untrash'] = "<a title='" . esc_attr( __( 'Restore this item from the Trash' ) ) . "' href='" . wp_nonce_url( admin_url( sprintf( $ptype_obj->_edit_link . '&action=untrash', $post_id ) ), 'untrash-post_' . $post_id ) . "'>" . __( 'Restore' ) . "</a>";
						elseif ( EMPTY_TRASH_DAYS )
							$actions['trash'] = "<a class='submitdelete' title='" . esc_attr( __( 'Move this item to the Trash' ) ) . "' href='" . get_delete_post_link( $post_id ) . "'>" . __( 'Trash' ) . "</a>";
						if ( 'trash' == $post->post_status || !EMPTY_TRASH_DAYS )
							$actions['delete'] = "<a class='submitdelete' title='" . esc_attr( __( 'Delete this item permanently' ) ) . "' href='" . get_delete_post_link( $post_id, '', true ) . "'>" . __( 'Delete Permanently' ) . "</a>";
					}
					if ( $ptype_obj->public ) {
						if ( in_array( $post->post_status, array( 'pending', 'draft', 'future' ) ) ) {
							if ( $can_edit_post )
								$actions['view'] = '<a href="' . esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_id ) ) ) . '" title="' . esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;' ), $title ) ) . '" rel="permalink">' . __( 'Preview' ) . '</a>';
						} elseif ( 'trash' != $post->post_status ) {
							$actions['view'] = '<a href="' . get_permalink( $post_id ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $title ) ) . '" rel="permalink">' . __( 'View' ) . '</a>';
						}
					}

					$actions = apply_filters( is_post_type_hierarchical( $post->post_type ) ? 'page_row_actions' : 'post_row_actions', $actions, $post );
					// we get the WP_List_Table namespace for actions (allow for overriding later)
					echo WP_List_Table::row_actions( $actions );

					get_inline_data( $post );

					break;
			}
		}

		public function submenu_label( $text ){
			return '&#8212; ' . $text;
		}

		public function add_meta_boxes(){
			global $post;
			if( array_key_exists( $post->post_type , $this->post_type ) ) {
				$parent_ptype_obj = get_post_type_object( $this->post_type[ $post->post_type ] );
				add_meta_box(
					"{$this->namespace}_{$post->post_type}",
					sprintf( __('Parent %s'), $parent_ptype_obj->labels->singular_name), 
					array( $this, 'render_parent_list' ), 
					$post->post_type, 
					'side', 
					'core'
					);
			}
		}

		public function render_parent_list(){
			global $post;
			$ptype = $this->post_type[ $post->post_type ];
			$selected = $post->post_parent;

			wp_nonce_field( plugin_basename( __FILE__ ), "{$this->namespace}_nonce" );

			// hat tip /wp-admin/includes/meta-boxes.php 'page_attributes_meta_box'
			$ptype_obj = get_post_type_object( $ptype );
			$dropdown_args = array(
				'post_type'        => $ptype,
				'selected'         => $selected,
				'name'             => "{$this->namespace}_id",
				'show_option_none' => __('(no parent)'),
				'sort_column'      => 'menu_order, post_title',
				'echo'             => 0,
			);

			$dropdown_args = apply_filters( "{$this->namespace}__dropdown_args", $dropdown_args, $post );
			$parents = wp_dropdown_pages( $dropdown_args );

			if ( ! empty($parents) ) {
?>
<label for="parent_id"><?php printf( __('Parent %s'), $ptype_obj->labels->singular_name ); ?></label>
<?php 
				echo $parents;
			} // end empty parents check
		}

		public function save_post( $post_id ){

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
				return;

			if ( !isset($_POST["{$this->namespace}_nonce"]) || !wp_verify_nonce( $_POST["{$this->namespace}_nonce"], plugin_basename( __FILE__ ) ) )
				return;

			// Check permissions
			if ( array_key_exists( $_POST['post_type'] , $this->post_type ) ) {
				if ( !current_user_can( 'edit_page', $post_id ) )
				    return;
			} else {
				if ( !current_user_can( 'edit_post', $post_id ) )
				    return;
			}

			$parent_id = ( isset($_POST['parent_id'])) ? $_POST['parent_id'] : null;
			$parent_id = ( isset($_POST["{$this->namespace}_id"]) && empty($parent_id) ) ? $_POST["{$this->namespace}_id"] : $parent_id;

			// unhook this function so it doesn't loop infinitely
			remove_action( 'save_post', array( $this, 'save_post' ) );
			// update the post, which calls save_post again
			wp_update_post(array('ID' => $post_id, 'post_parent' => $parent_id ));
			// re-hook this function
			add_action( 'save_post', array( $this, 'save_post' ) );

		}
	}
}
new WP_Subordinate_Post_Factory();