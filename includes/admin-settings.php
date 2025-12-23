<?php
/**
 * Admin settings page for The Subscriber Purge plugin.
 *
 * @package TheSubscriberPurge
 */

declare(strict_types=1);

namespace TheSubscriberPurge\Admin;

// Initialize admin settings.
add_action( 'admin_menu', __NAMESPACE__ . '\add_menu' );
add_action( 'admin_init', __NAMESPACE__ . '\register_settings' );

/**
 * Add admin menu.
 */
function add_menu(): void {
	add_options_page(
		__( 'Subscriber Purge', 'the-subscriber-purge' ),
		__( 'Subscriber Purge', 'the-subscriber-purge' ),
		'manage_options',
		'tsp-settings',
		__NAMESPACE__ . '\render_page'
	);
}

/**
 * Register settings.
 */
function register_settings(): void {
	// Register the unified settings option.
	register_setting(
		'tsp_settings_group',
		'tsp_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_settings',
			'default'           => array(
				'days_inactive' => 30,
				'send_emails'   => true,
				'notify_admin'  => true,
			),
		)
	);

	add_settings_section(
		'tsp_settings_section',
		__( 'Subscriber Purge Settings', 'the-subscriber-purge' ),
		null,
		'tsp_settings_page'
	);

	add_settings_field(
		'tsp_days_inactive',
		__( 'Days Inactive Before Purge', 'the-subscriber-purge' ),
		__NAMESPACE__ . '\render_days_field',
		'tsp_settings_page',
		'tsp_settings_section'
	);

	add_settings_field(
		'tsp_send_emails',
		__( 'Send Email Notifications', 'the-subscriber-purge' ),
		__NAMESPACE__ . '\render_email_field',
		'tsp_settings_page',
		'tsp_settings_section'
	);

	add_settings_field(
		'tsp_notify_admin',
		__( 'Notify Admin on Purge', 'the-subscriber-purge' ),
		__NAMESPACE__ . '\render_notify_admin_field',
		'tsp_settings_page',
		'tsp_settings_section'
	);
}

/**
 * Render the settings page.
 */
function render_page(): void {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Subscriber Purge Settings', 'the-subscriber-purge' ); ?></h1>
		<p><?php esc_html_e( 'Configure automatic deletion of inactive subscriber accounts.', 'the-subscriber-purge' ); ?></p>

		<form method="post" action="options.php">
			<?php
			settings_fields( 'tsp_settings_group' );
			do_settings_sections( 'tsp_settings_page' );
			submit_button();
			?>
		</form>

		<div style="margin-top: 40px; padding: 15px; background: #f0f0f0; border-left: 4px solid #0073aa;">
			<h3><?php esc_html_e( 'How it works', 'the-subscriber-purge' ); ?></h3>
			<ul style="margin: 10px 0; padding-left: 20px;">
				<li><?php esc_html_e( 'The plugin runs every 15 minutes automatically.', 'the-subscriber-purge' ); ?></li>
				<li><?php esc_html_e( 'It checks for subscriber accounts that haven\'t made any comments.', 'the-subscriber-purge' ); ?></li>
				<li><?php esc_html_e( 'Accounts inactive for the specified number of days are deleted oldest-first, one user per run.', 'the-subscriber-purge' ); ?></li>
				<li><?php esc_html_e( 'Optionally, an email notification is sent before deletion (only one per run).', 'the-subscriber-purge' ); ?></li>
			</ul>
		</div>

		<?php render_upcoming_purges(); ?>
	</div>
	<?php
}

/**
 * Render days field.
 */
function render_days_field(): void {
	$value = (int) \TheSubscriberPurge\Settings\get( 'days_inactive', 30 );
	?>
	<input
		type="number"
		id="tsp_days_inactive"
		name="tsp_settings[days_inactive]"
		value="<?php echo esc_attr( (string) $value ); ?>"
		min="1"
		max="365"
		style="width: 100px;"
	/>
	<p class="description">
		<?php esc_html_e( 'Number of days a subscriber account can be inactive (with no comments) before it is purged. Default: 30 days.', 'the-subscriber-purge' ); ?>
	</p>
	<?php
}

/**
 * Render email notification field.
 */
function render_email_field(): void {
	$value = (bool) \TheSubscriberPurge\Settings\get( 'send_emails', true );
	?>
	<input
		type="checkbox"
		id="tsp_send_emails"
		name="tsp_settings[send_emails]"
		value="1"
		<?php checked( $value, true ); ?>
	/>
	<label for="tsp_send_emails">
		<?php esc_html_e( 'Send email notification to users before their account is deleted', 'the-subscriber-purge' ); ?>
	</label>
	<p class="description">
		<?php esc_html_e( 'If enabled, users will receive an email explaining why their account was deleted.', 'the-subscriber-purge' ); ?>
	</p>
	<?php
}

/**
 * Render admin notification field.
 */
function render_notify_admin_field(): void {
	$value = (bool) \TheSubscriberPurge\Settings\get( 'notify_admin', true );
	?>
	<input
		type="checkbox"
		id="tsp_notify_admin"
		name="tsp_settings[notify_admin]"
		value="1"
		<?php checked( $value, true ); ?>
	/>
	<label for="tsp_notify_admin">
		<?php esc_html_e( 'Send email notification to site admin when a user is purged', 'the-subscriber-purge' ); ?>
	</label>
	<p class="description">
		<?php esc_html_e( 'If enabled, the site admin will receive detailed information about each purged account.', 'the-subscriber-purge' ); ?>
	</p>
	<?php
}

/**
 * Sanitize all settings.
 *
 * @param mixed $input The input values.
 * @return array<string, mixed> Sanitized settings.
 */
function sanitize_settings( mixed $input ): array {
	$input = is_array( $input ) ? $input : array();

	return array(
		'days_inactive' => max( 1, min( 365, intval( $input['days_inactive'] ?? 30 ) ) ),
		'send_emails'   => ! empty( $input['send_emails'] ),
		'notify_admin'  => ! empty( $input['notify_admin'] ),
	);
}

/**
 * Calculate days until purge for a user.
 *
 * @param int $user_id The user ID.
 * @return int Days until purge.
 */
function get_days_until_purge( int $user_id ): int {
	$user              = get_user_by( 'id', $user_id );
	$days_inactive     = (int) \TheSubscriberPurge\Settings\get( 'days_inactive', 30 );
	$registration_time = strtotime( $user->user_registered );
	$purge_time        = $registration_time + ( $days_inactive * DAY_IN_SECONDS );
	$days_until_purge  = (int) ceil( ( $purge_time - time() ) / DAY_IN_SECONDS );

	return max( 0, $days_until_purge );
}

/**
 * Render table of users scheduled for purge.
 */
function render_upcoming_purges(): void {
	$days_inactive = (int) \TheSubscriberPurge\Settings\get( 'days_inactive', 30 );
	$upcoming      = \TheSubscriberPurge\Purge\get_inactive_subscribers( $days_inactive );

	if ( empty( $upcoming ) ) {
		?>
		<div style="margin-top: 40px; padding: 15px; background: #e7f5fe; border-left: 4px solid #0073aa;">
			<h3><?php esc_html_e( 'Users Scheduled for Purge', 'the-subscriber-purge' ); ?></h3>
			<p><?php esc_html_e( 'No subscriber accounts are currently scheduled for purge.', 'the-subscriber-purge' ); ?></p>
		</div>
		<?php
		return;
	}
	?>
	<div style="margin-top: 40px; padding: 20px; background: #fff8e5; border: 1px solid #ffb81c; border-radius: 4px;">
		<h3><?php esc_html_e( 'Users Scheduled for Purge', 'the-subscriber-purge' ); ?></h3>
		<p><?php esc_html_e( 'These subscriber accounts have no comments and will be deleted when the cron runs (every 15 minutes, one user per run starting with the oldest).', 'the-subscriber-purge' ); ?></p>
		
		<table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
			<thead>
				<tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
					<th style="padding: 10px; text-align: left;"><?php esc_html_e( 'Username', 'the-subscriber-purge' ); ?></th>
					<th style="padding: 10px; text-align: left;"><?php esc_html_e( 'Email', 'the-subscriber-purge' ); ?></th>
					<th style="padding: 10px; text-align: left;"><?php esc_html_e( 'Registered', 'the-subscriber-purge' ); ?></th>
					<th style="padding: 10px; text-align: center;"><?php esc_html_e( 'Days Until Purge', 'the-subscriber-purge' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $upcoming as $user ) {
					$days_until     = get_days_until_purge( (int) $user->ID );
					$registered_ts  = strtotime( $user->user_registered );
					$registered_str = gmdate( 'Y-m-d H:i', false !== $registered_ts ? $registered_ts : 0 );
					?>
					<tr style="border-bottom: 1px solid #eee;">
						<td style="padding: 10px;"><?php echo esc_html( $user->user_login ); ?></td>
						<td style="padding: 10px;"><?php echo esc_html( $user->user_email ); ?></td>
						<td style="padding: 10px; "><?php echo esc_html( $registered_str ); ?></td>
						<td style="padding: 10px; text-align: center;">
							<span style="padding: 4px 8px; background: <?php echo $days_until <= 2 ? '#ffcccc' : '#ffffcc'; ?>; border-radius: 3px;">
								<?php echo esc_html( (string) $days_until ); ?>
							</span>
						</td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
	</div>
	<?php
}
