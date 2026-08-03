<?php
/**
 * WhatsApp lead report markup.
 *
 * @package WordPressWhatsAppConversions
 *
 * @var array<string, string>    $filters Date filters.
 * @var array<string, int|float> $metrics Aggregate metrics.
 * @var array<int, array<string, string|int>> $leads Recent leads.
 * @var string $page_url Report URL relative to wp-admin.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'WhatsApp Leads', 'wordpress-whatsapp-conversions' ); ?></h1>

	<?php if ( '' !== $filters['error'] ) : ?>
		<div class="notice notice-error inline"><p><?php echo esc_html( $filters['error'] ); ?></p></div>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="wwc-leads">
		<label for="wwc-date-from"><?php echo esc_html__( 'Created from', 'wordpress-whatsapp-conversions' ); ?></label>
		<input id="wwc-date-from" type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
		<label for="wwc-date-to"><?php echo esc_html__( 'Created to', 'wordpress-whatsapp-conversions' ); ?></label>
		<input id="wwc-date-to" type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
		<?php submit_button( __( 'Filter', 'wordpress-whatsapp-conversions' ), 'secondary', 'submit', false ); ?>
		<a class="button" href="<?php echo esc_url( admin_url( $page_url ) ); ?>"><?php echo esc_html__( 'Reset', 'wordpress-whatsapp-conversions' ); ?></a>
	</form>

	<h2><?php echo esc_html__( 'Cohort metrics', 'wordpress-whatsapp-conversions' ); ?></h2>
	<p class="description"><?php echo esc_html__( 'The selected period applies to the date when the WhatsApp intent was created.', 'wordpress-whatsapp-conversions' ); ?></p>
	<table class="widefat striped">
		<tbody>
			<tr><th scope="row"><?php echo esc_html__( 'Intents', 'wordpress-whatsapp-conversions' ); ?></th><td><?php echo esc_html( number_format_i18n( $metrics['intents'] ) ); ?></td></tr>
			<tr><th scope="row"><?php echo esc_html__( 'Confirmed conversations', 'wordpress-whatsapp-conversions' ); ?></th><td><?php echo esc_html( number_format_i18n( $metrics['confirmed'] ) ); ?></td></tr>
			<tr><th scope="row"><?php echo esc_html__( 'Unique senders', 'wordpress-whatsapp-conversions' ); ?></th><td><?php echo esc_html( number_format_i18n( $metrics['unique_senders'] ) ); ?></td></tr>
			<tr><th scope="row"><?php echo esc_html__( 'Conversion rate', 'wordpress-whatsapp-conversions' ); ?></th><td><?php echo esc_html( number_format_i18n( $metrics['conversion_rate'], 2 ) ); ?>%</td></tr>
			<tr><th scope="row"><?php echo esc_html__( 'GA4 sent', 'wordpress-whatsapp-conversions' ); ?></th><td><?php echo esc_html( number_format_i18n( $metrics['ga4_sent'] ) ); ?></td></tr>
			<tr><th scope="row"><?php echo esc_html__( 'GA4 failed', 'wordpress-whatsapp-conversions' ); ?></th><td><?php echo esc_html( number_format_i18n( $metrics['ga4_failed'] ) ); ?></td></tr>
			<tr><th scope="row"><?php echo esc_html__( 'GA4 skipped', 'wordpress-whatsapp-conversions' ); ?></th><td><?php echo esc_html( number_format_i18n( $metrics['ga4_skipped'] ) ); ?></td></tr>
		</tbody>
	</table>

	<h2><?php echo esc_html__( 'Latest confirmed conversations', 'wordpress-whatsapp-conversions' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Confirmed at', 'wordpress-whatsapp-conversions' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Source', 'wordpress-whatsapp-conversions' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Source path', 'wordpress-whatsapp-conversions' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Ad ID', 'wordpress-whatsapp-conversions' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'GA4 status', 'wordpress-whatsapp-conversions' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $leads ) ) : ?>
				<tr><td colspan="5"><?php echo esc_html__( 'No confirmed conversations found.', 'wordpress-whatsapp-conversions' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $leads as $lead ) : ?>
					<tr>
						<td><?php echo esc_html( get_date_from_gmt( (string) $lead['converted_at'], 'Y-m-d H:i:s' ) ); ?></td>
						<td><?php echo esc_html( (string) $lead['source'] ); ?></td>
						<td><?php echo esc_html( (string) $lead['source_url'] ); ?></td>
						<td><?php echo esc_html( (int) $lead['has_ad_id'] === 1 ? __( 'Yes', 'wordpress-whatsapp-conversions' ) : __( 'No', 'wordpress-whatsapp-conversions' ) ); ?></td>
						<td><?php echo esc_html( (string) $lead['ga4_status'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
