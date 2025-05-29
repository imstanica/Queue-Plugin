<?php
// File: admin/knowledgebase.php
// Creează layout 60/40 pentru Categories + Articles

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.', 'queues' ) );
}

global $wpdb;
$p           = $wpdb->prefix;
$cat_table   = "{$p}queues_kb_categories";
$art_table   = "{$p}queues_kb_articles";

// Preluăm datele pentru afișare
$cats = $wpdb->get_results( "SELECT * FROM {$cat_table} ORDER BY name ASC" );
$arts = $wpdb->get_results( "
    SELECT a.id, a.title, a.kb_category_id, c.name AS cat_name
    FROM {$art_table} a
    LEFT JOIN {$cat_table} c ON a.kb_category_id = c.id
    ORDER BY a.id DESC
" );

// Detectăm edit-mode din query string
$edit_cat = isset( $_GET['edit_kb_cat'] )
    ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$cat_table} WHERE id=%d", intval( $_GET['edit_kb_cat'] ) ) )
    : null;

$edit_art = isset( $_GET['edit_kb_art'] )
    ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$art_table} WHERE id=%d", intval( $_GET['edit_kb_art'] ) ) )
    : null;
?>
<style>
  .kb-column-left  { float:left;  width:60%;  box-sizing:border-box; padding-right:20px; }
  .kb-column-right { float:right; width:35%; box-sizing:border-box; }
  .clear { clear:both; }
  .kb-column h2 { margin-top:0; }
</style>

<div class="wrap">
  <h1><?php esc_html_e( 'Knowledgebase', 'queues' ); ?></h1>

  <div class="kb-column-left">
    <h2>
      <?php echo $edit_art
        ? esc_html__( 'Edit Article', 'queues' )
        : esc_html__( 'Add New Article', 'queues' );
      ?>
    </h2>
    <form method="post">
      <?php wp_nonce_field( 'kb_art_nonce' ); ?>
      <input type="hidden" name="kb_art_action" value="<?php echo $edit_art ? 'edit' : 'add'; ?>">
      <?php if ( $edit_art ) : ?>
        <input type="hidden" name="kb_art_id" value="<?php echo esc_attr( $edit_art->id ); ?>">
      <?php endif; ?>

      <table class="form-table">
        <tr>
          <th><label for="kb_art_cat"><?php esc_html_e( 'Category', 'queues' ); ?></label></th>
          <td>
            <select name="kb_art_cat" id="kb_art_cat" required>
              <option value=""><?php esc_html_e( '— Select Category —', 'queues' ); ?></option>
              <?php foreach ( $cats as $c ) : ?>
                <option value="<?php echo esc_attr( $c->id ); ?>"
                  <?php selected( $edit_art->kb_category_id ?? '', $c->id ); ?>>
                  <?php echo esc_html( $c->name ); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <tr>
          <th><label for="kb_art_title"><?php esc_html_e( 'Title', 'queues' ); ?></label></th>
          <td>
            <input name="kb_art_title" id="kb_art_title" type="text" class="regular-text"
                   value="<?php echo esc_attr( $edit_art->title ?? '' ); ?>" required>
          </td>
        </tr>
        <tr>
          <th><label for="kb_art_content"><?php esc_html_e( 'Content', 'queues' ); ?></label></th>
          <td>
            <?php
            wp_editor(
              $edit_art->content ?? '',
              'kb_art_content',
              [ 'textarea_rows' => 8 ]
            );
            ?>
          </td>
        </tr>
      </table>
      <?php submit_button(
        $edit_art ? __( 'Update Article', 'queues' ) : __( 'Add Article', 'queues' ),
        'primary',
        'submit'
      ); ?>
      <?php if ( $edit_art ) : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=queues_knowledgebase' ) ); ?>" class="button">
          <?php esc_html_e( 'Cancel', 'queues' ); ?>
        </a>
      <?php endif; ?>
    </form>

    <h2><?php esc_html_e( 'Existing Articles', 'queues' ); ?></h2>
    <table class="widefat fixed striped">
      <thead>
        <tr>
          <th><?php esc_html_e( 'ID',       'queues' ); ?></th>
          <th><?php esc_html_e( 'Category','queues' ); ?></th>
          <th><?php esc_html_e( 'Title',    'queues' ); ?></th>
          <th><?php esc_html_e( 'Actions',  'queues' ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if ( $arts ) : foreach ( $arts as $a ) : ?>
          <tr>
            <td><?php echo esc_html( $a->id ); ?></td>
            <td><?php echo esc_html( $a->cat_name ); ?></td>
            <td><?php echo esc_html( $a->title ); ?></td>
            <td>
              <a href="<?php echo esc_url( add_query_arg([
                'page'        => 'queues_knowledgebase',
                'edit_kb_art' => $a->id,
              ], admin_url( 'admin.php' ) ) ); ?>">
                <?php esc_html_e( 'Edit', 'queues' ); ?>
              </a>
              |
              <button type="submit" formmethod="post" formaction=""
                      class="button-link delete"
                      onclick="if(!confirm('<?php esc_attr_e( 'Delete this article?', 'queues' ); ?>'))return false;">
                <?php esc_html_e( 'Delete', 'queues' ); ?>
              </button>
              <input type="hidden" name="kb_art_action" value="delete">
              <input type="hidden" name="kb_art_id"     value="<?php echo esc_attr( $a->id ); ?>">
              <?php wp_nonce_field( 'kb_art_nonce' ); ?>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="4"><?php esc_html_e( 'No articles found.', 'queues' ); ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="kb-column-right">
    <h2>
      <?php echo $edit_cat
        ? esc_html__( 'Edit Category', 'queues' )
        : esc_html__( 'Add New Category', 'queues' );
      ?>
    </h2>
    <form method="post">
      <?php wp_nonce_field( 'kb_cat_nonce' ); ?>
      <input type="hidden" name="kb_cat_action" value="<?php echo $edit_cat ? 'edit' : 'add'; ?>">
      <?php if ( $edit_cat ) : ?>
        <input type="hidden" name="kb_cat_id" value="<?php echo esc_attr( $edit_cat->id ); ?>">
      <?php endif; ?>

      <table class="form-table">
        <tr>
          <th><label for="kb_cat_name"><?php esc_html_e( 'Name', 'queues' ); ?></label></th>
          <td>
            <input name="kb_cat_name" id="kb_cat_name" type="text" class="regular-text"
                   value="<?php echo esc_attr( $edit_cat->name ?? '' ); ?>" required>
          </td>
        </tr>
      </table>
      <?php submit_button(
        $edit_cat ? __( 'Update Category', 'queues' ) : __( 'Add Category', 'queues' ),
        'primary',
        'submit'
      ); ?>
      <?php if ( $edit_cat ) : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=queues_knowledgebase' ) ); ?>" class="button">
          <?php esc_html_e( 'Cancel', 'queues' ); ?>
        </a>
      <?php endif; ?>
    </form>

    <h2><?php esc_html_e( 'Existing Categories', 'queues' ); ?></h2>
    <table class="widefat fixed striped">
      <thead>
        <tr>
          <th><?php esc_html_e( 'ID',   'queues' ); ?></th>
          <th><?php esc_html_e( 'Name', 'queues' ); ?></th>
          <th><?php esc_html_e( 'Actions', 'queues' ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if ( $cats ) : foreach ( $cats as $c ) : ?>
          <tr>
            <td><?php echo esc_html( $c->id ); ?></td>
            <td><?php echo esc_html( $c->name ); ?></td>
            <td>
              <a href="<?php echo esc_url( add_query_arg([
                'page'        => 'queues_knowledgebase',
                'edit_kb_cat' => $c->id,
              ], admin_url( 'admin.php' ) ) ); ?>">
                <?php esc_html_e( 'Edit', 'queues' ); ?>
              </a>
              |
              <button type="submit" formmethod="post" formaction=""
                      class="button-link delete"
                      onclick="if(!confirm('<?php esc_attr_e( 'Delete this category?', 'queues' ); ?>'))return false;">
                <?php esc_html_e( 'Delete', 'queues' ); ?>
              </button>
              <input type="hidden" name="kb_cat_action" value="delete">
              <input type="hidden" name="kb_cat_id"     value="<?php echo esc_attr( $c->id ); ?>">
              <?php wp_nonce_field( 'kb_cat_nonce' ); ?>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="3"><?php esc_html_e( 'No categories found.', 'queues' ); ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="clear"></div>
</div>
