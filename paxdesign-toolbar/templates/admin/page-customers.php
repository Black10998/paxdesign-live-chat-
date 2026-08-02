<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$search      = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
$page_num    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
$per_page    = 25;
$offset      = ( $page_num - 1 ) * $per_page;
$customer_id = (int) ( $_GET['customer_id'] ?? 0 );
$customers   = PDX_Customers::list_customers( $search, $per_page, $offset );
$total       = PDX_Customers::count_customers( $search );
$pages       = (int) ceil( $total / $per_page );
$detail      = $customer_id ? PDX_Customers::customer_detail( $customer_id ) : [];
$all_modules = $this->modules->all();

include __DIR__ . '/partials/header.php';
?>
<div class="pdx-page-header">
  <h1>PAXDesign Customer Accounts</h1>
  <p>Manage customer profiles, verification, purchases, and PaxDesign module access — without granting WordPress administrator roles.</p>
</div>

<form method="get" class="pdx-card" style="margin-bottom:16px;padding:16px">
  <input type="hidden" name="page" value="<?php echo esc_attr( PDX_SLUG . '-customers' ); ?>" />
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by name or email…" class="pdx-input" style="min-width:240px;flex:1" />
    <button type="submit" class="pdx-btn-primary">Search</button>
    <?php if ( $search ) : ?>
      <a class="pdx-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=' . PDX_SLUG . '-customers' ) ); ?>">Clear</a>
    <?php endif; ?>
  </div>
</form>

<?php if ( ! empty( $detail ) ) : ?>
<div class="pdx-card" style="margin-bottom:16px">
  <div class="pdx-card__header"><h2 class="pdx-admin-name-with-badge"><?php echo PDX_Verified_Badge::name_with_badge( $detail['display_name'], (bool) $detail['verified'], [ 'size' => 18 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2></div>
  <div class="pdx-card__body">
    <div class="pdx-stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
      <div class="pdx-stat-card"><span class="pdx-stat-card__label">Email</span><span class="pdx-stat-card__value" style="font-size:13px"><?php echo esc_html( $detail['email'] ); ?></span></div>
      <div class="pdx-stat-card"><span class="pdx-stat-card__label">Email Status</span><span class="pdx-stat-card__value pdx-admin-verified-cell" style="font-size:13px"><?php
        if ( $detail['verified'] ) {
          echo PDX_Verified_Badge::render( true, [ 'size' => 14, 'context' => PDX_Verified_Badge::CONTEXT_EMAIL, 'inline' => true ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
          echo ' Verified';
        } else {
          echo 'Not Verified';
        }
      ?></span></div>
      <div class="pdx-stat-card"><span class="pdx-stat-card__label">Account</span><span class="pdx-stat-card__value" style="font-size:13px"><?php echo esc_html( ucfirst( $detail['account_status'] ) ); ?></span></div>
      <div class="pdx-stat-card"><span class="pdx-stat-card__label">Payment</span><span class="pdx-stat-card__value" style="font-size:13px"><?php echo esc_html( $detail['payment_summary']['label'] ?? 'Free' ); ?></span></div>
    </div>
    <p style="font-size:12px;color:#8b949e;margin:0 0 12px">Registered: <?php echo esc_html( $detail['registered'] ); ?> · Last login: <?php echo esc_html( $detail['last_login'] ?: '—' ); ?></p>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
      <?php foreach ( [ 'activate' => 'Activate', 'suspend' => 'Suspend', 'resend_verification' => 'Resend Verification' ] as $act => $label ) : ?>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'pdx_customer_action', 'pdx_nonce' ); ?>
        <input type="hidden" name="action" value="pdx_customer_action" />
        <input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $customer_id ); ?>" />
        <input type="hidden" name="customer_action" value="<?php echo esc_attr( $act ); ?>" />
        <button type="submit" class="pdx-btn-ghost"><?php echo esc_html( $label ); ?></button>
      </form>
      <?php endforeach; ?>
    </div>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:16px">
      <?php wp_nonce_field( 'pdx_customer_action', 'pdx_nonce' ); ?>
      <input type="hidden" name="action" value="pdx_customer_action" />
      <input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $customer_id ); ?>" />
      <input type="hidden" name="customer_action" value="save_notes" />
      <label class="pdx-label">Internal notes</label>
      <textarea name="admin_notes" class="pdx-input" rows="3" style="width:100%;margin:6px 0 8px"><?php echo esc_textarea( $detail['notes'] ?? '' ); ?></textarea>
      <button type="submit" class="pdx-btn-primary">Save notes</button>
    </form>

    <div class="pdx-card" style="margin-bottom:16px">
      <div class="pdx-card__header"><h3>Grant / Revoke Module Access</h3></div>
      <div class="pdx-card__body">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
          <?php wp_nonce_field( 'pdx_customer_action', 'pdx_nonce' ); ?>
          <input type="hidden" name="action" value="pdx_customer_action" />
          <input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $customer_id ); ?>" />
          <div>
            <label class="pdx-label">Module</label>
            <select name="module_id" class="pdx-select">
              <?php foreach ( $all_modules as $mid => $mod ) : ?>
                <option value="<?php echo esc_attr( $mid ); ?>"><?php echo esc_html( $mod['label'] ?? $mid ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="pdx-label">Days (0 = lifetime)</label>
            <input type="number" name="grant_days" value="0" min="0" class="pdx-input" style="width:90px" />
          </div>
          <button type="submit" name="customer_action" value="grant_module" class="pdx-btn-primary">Grant Access</button>
          <button type="submit" name="customer_action" value="revoke_module" class="pdx-btn-ghost">Revoke Access</button>
        </form>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:flex-end;margin-top:12px">
          <?php wp_nonce_field( 'pdx_customer_action', 'pdx_nonce' ); ?>
          <input type="hidden" name="action" value="pdx_customer_action" />
          <input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $customer_id ); ?>" />
          <input type="hidden" name="customer_action" value="extend_subscription" />
          <label class="pdx-label">Extend subscription (days)</label>
          <input type="number" name="extend_days" value="30" min="1" class="pdx-input" style="width:90px" />
          <button type="submit" class="pdx-btn-ghost">Extend Subscription</button>
        </form>
      </div>
    </div>

    <?php
    $vip_catalog = class_exists( 'PAXdesign_Customer_Avatar_Vip_Presets', false )
      ? PAXdesign_Customer_Avatar_Vip_Presets::catalog_for_user( 0 )
      : [];
    $vip_grants  = $detail['vip_avatar_grants'] ?? [];
    ?>
    <?php if ( ! empty( $vip_catalog ) ) : ?>
    <div class="pdx-card" style="margin-bottom:16px">
      <div class="pdx-card__header"><h3>Exclusive VIP Avatars</h3></div>
      <div class="pdx-card__body">
        <p style="font-size:12px;color:#8b949e;margin:0 0 12px">Assign premium animated SVG avatars to this customer. Only administrators can grant these exclusive avatars.</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:12px">
          <?php foreach ( $vip_catalog as $vip ) :
            $granted = in_array( $vip['id'], $vip_grants, true );
            $active  = ( $detail['avatar_preset'] ?? '' ) === $vip['id'];
          ?>
          <div style="text-align:center;padding:10px;border:1px solid #30363d;border-radius:12px;background:#0d1117">
            <img src="<?php echo esc_url( $vip['url'] ); ?>" alt="" width="64" height="64" style="border-radius:50%;display:block;margin:0 auto 8px;background:#161b22" />
            <div style="font-size:11px;color:#c9d1d9;margin-bottom:8px;line-height:1.3"><?php echo esc_html( $vip['label'] ); ?></div>
            <?php if ( $granted ) : ?>
              <span style="display:inline-block;font-size:10px;color:#3fb950;margin-bottom:8px"><?php echo $active ? 'Active' : 'Granted'; ?></span>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:4px">
              <?php wp_nonce_field( 'pdx_customer_action', 'pdx_nonce' ); ?>
              <input type="hidden" name="action" value="pdx_customer_action" />
              <input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $customer_id ); ?>" />
              <input type="hidden" name="vip_avatar_id" value="<?php echo esc_attr( $vip['id'] ); ?>" />
              <?php if ( $granted ) : ?>
                <button type="submit" name="customer_action" value="revoke_vip_avatar" class="pdx-btn-ghost" style="width:100%">Revoke</button>
              <?php else : ?>
                <button type="submit" name="customer_action" value="grant_vip_avatar" class="pdx-btn-primary" style="width:100%">Grant</button>
              <?php endif; ?>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ( ! empty( $detail['orders'] ) ) : ?>
    <div class="pdx-card">
      <div class="pdx-card__header"><h3>Orders & Invoices</h3></div>
      <div class="pdx-card__body" style="padding:0">
        <table class="pdx-table">
          <thead><tr><th>Order</th><th>Date</th><th>Product</th><th>Amount</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ( $detail['orders'] as $o ) : ?>
            <tr>
              <td><?php echo esc_html( $o['order_id'] ); ?></td>
              <td><?php echo esc_html( $o['paid_at'] ); ?></td>
              <td><?php echo esc_html( $o['product'] ); ?></td>
              <td><?php echo esc_html( $o['currency'] . ' ' . number_format( (float) $o['amount'], 2 ) ); ?></td>
              <td><?php echo esc_html( $o['payment_status'] ); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="pdx-card">
  <div class="pdx-card__header"><h2>Customers (<?php echo number_format( $total ); ?>)</h2></div>
  <div class="pdx-card__body" style="padding:0">
    <?php if ( empty( $customers ) ) : ?>
      <div style="padding:24px;text-align:center;color:#6e7681">No customers found.</div>
    <?php else : ?>
    <table class="pdx-table">
      <thead>
        <tr>
          <th>Name</th><th>Email</th><th>Verified</th><th>Account</th><th>Payment</th><th>Registered</th><th>Last Login</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $customers as $c ) :
          $row = PDX_Customers::customer_row( $c->ID );
        ?>
        <tr>
          <td><?php echo PDX_Verified_Badge::name_with_badge( $row['display_name'], (bool) $row['verified'], [ 'size' => 14 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
          <td style="font-size:11px"><?php echo esc_html( $row['email'] ); ?></td>
          <td><?php
            if ( $row['verified'] ) {
              echo '<span class="pdx-admin-verified-cell">';
              echo PDX_Verified_Badge::render( true, [ 'size' => 14, 'context' => PDX_Verified_Badge::CONTEXT_EMAIL, 'inline' => true ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
              echo ' Verified</span>';
            } else {
              echo 'Not Verified';
            }
          ?></td>
          <td><?php echo esc_html( ucfirst( $row['account_status'] ) ); ?></td>
          <td><?php echo esc_html( $row['payment_summary']['label'] ?? 'Free' ); ?></td>
          <td><?php echo esc_html( mysql2date( 'Y-m-d', $row['registered'] ) ); ?></td>
          <td><?php echo esc_html( $row['last_login'] ? mysql2date( 'Y-m-d H:i', $row['last_login'] ) : '—' ); ?></td>
          <td><a href="<?php echo esc_url( add_query_arg( [ 'page' => PDX_SLUG . '-customers', 'customer_id' => $c->ID, 's' => $search ?: null ], admin_url( 'admin.php' ) ) ); ?>">Manage</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php if ( $pages > 1 ) : ?>
<div style="margin-top:12px;display:flex;gap:8px">
  <?php for ( $p = 1; $p <= $pages; $p++ ) : ?>
    <a class="pdx-btn-ghost<?php echo $p === $page_num ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( [ 'page' => PDX_SLUG . '-customers', 'paged' => $p, 's' => $search ?: null ], admin_url( 'admin.php' ) ) ); ?>"><?php echo (int) $p; ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

</main></div></div>
