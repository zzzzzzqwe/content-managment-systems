<?php
/**
 * Plugin Name: USM Notes
 * Description: Adds a "Notes" section with priorities and due dates.
 * Version: 1.0.0
 * Author: Gachayev Dmitrii
 * Text Domain: usm-notes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------------------------------------------ */
/*  Enqueue front-end styles                                          */
/* ------------------------------------------------------------------ */
function usm_enqueue_styles() {
    wp_enqueue_style(
        'usm-notes-style',
        plugin_dir_url( __FILE__ ) . 'css/usm-notes.css',
        array(),
        '1.0.0'
    );
}
add_action( 'wp_enqueue_scripts', 'usm_enqueue_styles' );

/* ------------------------------------------------------------------ */
/*  1. Register Custom Post Type — Notes                              */
/* ------------------------------------------------------------------ */
function usm_register_notes_cpt() {
    $labels = array(
        'name'               => 'Notes',
        'singular_name'      => 'Note',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Note',
        'edit_item'          => 'Edit Note',
        'new_item'           => 'New Note',
        'view_item'          => 'View Note',
        'search_items'       => 'Search Notes',
        'not_found'          => 'No notes found',
        'not_found_in_trash' => 'No notes found in Trash',
        'all_items'          => 'All Notes',
        'menu_name'          => 'Notes',
    );

    $args = array(
        'labels'        => $labels,
        'public'        => true,
        'has_archive'   => true,
        'show_in_rest'  => true,
        'supports'      => array( 'title', 'editor', 'author', 'thumbnail' ),
        'menu_icon'     => 'dashicons-edit-page',
        'rewrite'       => array( 'slug' => 'notes' ),
    );

    register_post_type( 'usm_note', $args );
}
add_action( 'init', 'usm_register_notes_cpt' );

/* ------------------------------------------------------------------ */
/*  2. Register Taxonomy — Priority                                   */
/* ------------------------------------------------------------------ */
function usm_register_priority_taxonomy() {
    $labels = array(
        'name'              => 'Priorities',
        'singular_name'     => 'Priority',
        'search_items'      => 'Search Priorities',
        'all_items'         => 'All Priorities',
        'parent_item'       => 'Parent Priority',
        'parent_item_colon' => 'Parent Priority:',
        'edit_item'         => 'Edit Priority',
        'update_item'       => 'Update Priority',
        'add_new_item'      => 'Add New Priority',
        'new_item_name'     => 'New Priority Name',
        'menu_name'         => 'Priorities',
    );

    $args = array(
        'labels'       => $labels,
        'hierarchical' => true,
        'public'       => true,
        'show_in_rest' => true,
        'rewrite'      => array( 'slug' => 'priority' ),
    );

    register_taxonomy( 'usm_priority', 'usm_note', $args );
}
add_action( 'init', 'usm_register_priority_taxonomy' );

/* ------------------------------------------------------------------ */
/*  3. Meta Box — Due Date                                            */
/* ------------------------------------------------------------------ */
function usm_add_due_date_meta_box() {
    add_meta_box(
        'usm_due_date',
        'Due Date',
        'usm_due_date_meta_box_html',
        'usm_note',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes', 'usm_add_due_date_meta_box' );

function usm_due_date_meta_box_html( $post ) {
    $value = get_post_meta( $post->ID, '_usm_due_date', true );
    wp_nonce_field( 'usm_save_due_date', 'usm_due_date_nonce' );
    ?>
    <label for="usm_due_date_field">Reminder date:</label><br>
    <input type="date" id="usm_due_date_field" name="usm_due_date" value="<?php echo esc_attr( $value ); ?>" required style="width:100%;margin-top:6px;">
    <?php
}

function usm_save_due_date( $post_id ) {
    // Nonce check
    if ( ! isset( $_POST['usm_due_date_nonce'] ) ||
         ! wp_verify_nonce( $_POST['usm_due_date_nonce'], 'usm_save_due_date' ) ) {
        return;
    }

    // Autosave check
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Permissions check
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $date = isset( $_POST['usm_due_date'] ) ? sanitize_text_field( $_POST['usm_due_date'] ) : '';

    // Validate: date is required
    if ( empty( $date ) ) {
        set_transient( 'usm_due_date_error_' . $post_id, 'Due date is required.', 30 );
        return;
    }

    // Validate: date must not be in the past
    if ( $date < current_time( 'Y-m-d' ) ) {
        set_transient( 'usm_due_date_error_' . $post_id, 'Due date cannot be in the past.', 30 );
        return;
    }

    update_post_meta( $post_id, '_usm_due_date', $date );
    delete_transient( 'usm_due_date_error_' . $post_id );
}
add_action( 'save_post_usm_note', 'usm_save_due_date' );

/* Display validation errors */
function usm_admin_notices() {
    $screen = get_current_screen();
    if ( ! $screen || 'usm_note' !== $screen->post_type ) {
        return;
    }

    global $post;
    if ( ! $post ) {
        return;
    }

    $error = get_transient( 'usm_due_date_error_' . $post->ID );
    if ( $error ) {
        echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
        delete_transient( 'usm_due_date_error_' . $post->ID );
    }
}
add_action( 'admin_notices', 'usm_admin_notices' );

/* ------------------------------------------------------------------ */
/*  4. Due Date column in admin list                                  */
/* ------------------------------------------------------------------ */
function usm_add_due_date_column( $columns ) {
    $columns['usm_due_date'] = 'Due Date';
    return $columns;
}
add_filter( 'manage_usm_note_posts_columns', 'usm_add_due_date_column' );

function usm_due_date_column_content( $column, $post_id ) {
    if ( 'usm_due_date' === $column ) {
        $date = get_post_meta( $post_id, '_usm_due_date', true );
        echo $date ? esc_html( $date ) : '&mdash;';
    }
}
add_action( 'manage_usm_note_posts_custom_column', 'usm_due_date_column_content', 10, 2 );

function usm_due_date_column_sortable( $columns ) {
    $columns['usm_due_date'] = 'usm_due_date';
    return $columns;
}
add_filter( 'manage_edit-usm_note_sortable_columns', 'usm_due_date_column_sortable' );

/* ------------------------------------------------------------------ */
/*  5. Shortcode [usm_notes]                                          */
/* ------------------------------------------------------------------ */
function usm_notes_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'priority'    => '',
        'before_date' => '',
    ), $atts, 'usm_notes' );

    $query_args = array(
        'post_type'      => 'usm_note',
        'posts_per_page' => -1,
        'orderby'        => 'meta_value',
        'meta_key'       => '_usm_due_date',
        'order'          => 'ASC',
    );

    // Filter by priority taxonomy
    if ( ! empty( $atts['priority'] ) ) {
        $query_args['tax_query'] = array(
            array(
                'taxonomy' => 'usm_priority',
                'field'    => 'slug',
                'terms'    => sanitize_text_field( $atts['priority'] ),
            ),
        );
    }

    // Filter by due date
    if ( ! empty( $atts['before_date'] ) ) {
        $query_args['meta_query'] = array(
            array(
                'key'     => '_usm_due_date',
                'value'   => sanitize_text_field( $atts['before_date'] ),
                'compare' => '<=',
                'type'    => 'DATE',
            ),
        );
    }

    $query = new WP_Query( $query_args );

    if ( ! $query->have_posts() ) {
        return '<p class="usm-notes-empty">Нет заметок с заданными параметрами</p>';
    }

    $output = '<div class="usm-notes-list">';

    while ( $query->have_posts() ) {
        $query->the_post();

        $due_date   = get_post_meta( get_the_ID(), '_usm_due_date', true );
        $priorities = get_the_terms( get_the_ID(), 'usm_priority' );
        $priority   = ( $priorities && ! is_wp_error( $priorities ) )
                      ? esc_html( $priorities[0]->name )
                      : '';

        $output .= '<div class="usm-note-card">';
        $output .= '<h3 class="usm-note-title">' . esc_html( get_the_title() ) . '</h3>';

        if ( $priority || $due_date ) {
            $output .= '<div class="usm-note-meta">';
            if ( $priority ) {
                $slug    = ( $priorities && ! is_wp_error( $priorities ) ) ? $priorities[0]->slug : '';
                $output .= '<span class="usm-note-priority usm-priority-' . esc_attr( $slug ) . '">' . $priority . '</span>';
            }
            if ( $due_date ) {
                $output .= '<span class="usm-note-date">Due: ' . esc_html( $due_date ) . '</span>';
            }
            $output .= '</div>';
        }

        $output .= '<div class="usm-note-excerpt">' . wp_kses_post( get_the_excerpt() ) . '</div>';
        $output .= '</div>';
    }

    $output .= '</div>';
    wp_reset_postdata();

    return $output;
}
add_shortcode( 'usm_notes', 'usm_notes_shortcode' );
